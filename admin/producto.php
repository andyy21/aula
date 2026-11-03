<?php
// Panel de administración · Alta y edición de un producto (paquete) y sus recursos.
//   producto.php            -> nuevo producto
//   producto.php?id=5       -> editar el producto 5 + agregar/quitar sus recursos (PDF o enlace)
require_once __DIR__ . '/../includes/admin.php';
$usuario = requiere_admin();

$pdo = db();

const MAX_PDF_BYTES = 10 * 1024 * 1024; // 10 MB por PDF

// Carpeta donde viven los PDF (fuera de acceso directo; se sirven con descargar.php).
function carpeta_recursos(): ?string
{
    $ruta = realpath(__DIR__ . '/../storage/recursos');
    return $ruta !== false ? $ruta : null;
}

// Nombre de archivo seguro y único a partir del título. Ej: "calculo-unidad-1-a1b2c3d4.pdf"
function nombre_archivo_seguro(string $titulo): string
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $titulo);
    $slug  = strtolower(trim(preg_replace('/[^A-Za-z0-9]+/', '-', $ascii !== false ? $ascii : ''), '-'));
    $slug  = substr($slug, 0, 40);
    if ($slug === '') {
        $slug = 'recurso';
    }
    return $slug . '-' . bin2hex(random_bytes(4)) . '.pdf';
}

// Borra un PDF de storage/recursos (solo dentro de esa carpeta).
function borrar_archivo_recurso(string $nombre): void
{
    $carpeta = carpeta_recursos();
    if (!$carpeta) {
        return;
    }
    $ruta = realpath($carpeta . DIRECTORY_SEPARATOR . basename($nombre));
    if ($ruta && strpos($ruta, $carpeta . DIRECTORY_SEPARATOR) === 0 && is_file($ruta)) {
        @unlink($ruta);
    }
}

// ---- Producto (si viene id) ----
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$producto = null;
if ($id) {
    $st = $pdo->prepare('SELECT * FROM productos WHERE id = ?');
    $st->execute([$id]);
    $producto = $st->fetch();
    if (!$producto) {
        http_response_code(404);
        exit('Producto no encontrado.');
    }
}
$destino = 'admin/producto.php' . ($id ? '?id=' . $id : '');

$materias    = $pdo->query('SELECT id, nombre FROM materias ORDER BY id')->fetchAll();
$materiasIds = array_map('intval', array_column($materias, 'id'));

// Valores del formulario (los de la BD, o los que escribió el admin si hubo errores)
$f = [
    'materia_id'  => $producto ? (int)$producto['materia_id'] : 0,
    'titulo'      => $producto ? (string)$producto['titulo'] : '',
    'descripcion' => $producto ? (string)$producto['descripcion'] : '',
    'precio'      => $producto ? number_format((float)$producto['precio'], 2, '.', '') : '',
    'stock'       => ($producto && $producto['stock'] !== null) ? (string)$producto['stock'] : '',
    'activo'      => $producto ? (int)$producto['activo'] : 1,
];
$errores = [];

// ---- Acciones (POST + CSRF) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Si el PDF pesa más que post_max_size, PHP vacía $_POST y $_FILES
    if (!$_POST && !$_FILES && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash_poner('error', 'El archivo es demasiado grande para la configuración del servidor.');
        redirigir($destino);
    }
    if (!csrf_valido()) {
        flash_poner('error', 'La sesión expiró. Inténtalo de nuevo.');
        redirigir($destino);
    }

    $accion = (string)($_POST['accion'] ?? '');

    // ----- Guardar datos del producto -----
    if ($accion === 'guardar') {
        $f['materia_id']  = (int)($_POST['materia_id'] ?? 0);
        $f['titulo']      = trim((string)($_POST['titulo'] ?? ''));
        $f['descripcion'] = trim((string)($_POST['descripcion'] ?? ''));
        $f['precio']      = trim((string)($_POST['precio'] ?? ''));
        $f['stock']       = trim((string)($_POST['stock'] ?? ''));
        $f['activo']      = isset($_POST['activo']) ? 1 : 0;

        if (!in_array($f['materia_id'], $materiasIds, true)) {
            $errores[] = 'Elige una materia válida.';
        }
        $largo = mb_strlen($f['titulo']);
        if ($largo < 3 || $largo > 150) {
            $errores[] = 'El título debe tener entre 3 y 150 caracteres.';
        }
        if (mb_strlen($f['descripcion']) > 1000) {
            $errores[] = 'La descripción no puede pasar de 1000 caracteres.';
        }
        $precioTxt = str_replace(',', '.', $f['precio']);
        if (!preg_match('/^\d{1,6}(\.\d{1,2})?$/', $precioTxt) || (float)$precioTxt <= 0) {
            $errores[] = 'El precio debe ser mayor que 0 (por ejemplo 49 o 49.50).';
        }
        if ($f['stock'] !== '' && !preg_match('/^\d{1,6}$/', $f['stock'])) {
            $errores[] = 'El stock debe ser un número entero (o déjalo vacío para ilimitado).';
        }

        if (!$errores) {
            $precioBD = number_format((float)$precioTxt, 2, '.', '');
            $stockBD  = $f['stock'] === '' ? null : (int)$f['stock'];
            $descBD   = $f['descripcion'] === '' ? null : $f['descripcion'];

            if ($producto) {
                $st = $pdo->prepare(
                    'UPDATE productos
                     SET materia_id = ?, titulo = ?, descripcion = ?, precio = ?, stock = ?, activo = ?
                     WHERE id = ?'
                );
                $st->execute([$f['materia_id'], $f['titulo'], $descBD, $precioBD, $stockBD, $f['activo'], $id]);
                flash_poner('ok', 'Cambios guardados.');
                redirigir($destino);
            }

            $st = $pdo->prepare(
                'INSERT INTO productos (materia_id, titulo, descripcion, precio, stock, activo)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $st->execute([$f['materia_id'], $f['titulo'], $descBD, $precioBD, $stockBD, $f['activo']]);
            flash_poner('ok', 'Producto creado. Ahora agrégale sus recursos (PDF o enlaces).');
            redirigir('admin/producto.php?id=' . (int)$pdo->lastInsertId());
        }
    }

    // ----- Acciones sobre recursos (solo si el producto ya existe) -----
    elseif (in_array($accion, ['recurso_pdf', 'recurso_enlace', 'recurso_borrar'], true)) {
        if (!$producto) {
            flash_poner('error', 'Primero guarda el producto.');
            redirigir($destino);
        }

        $error = null;
        $tituloR = trim((string)($_POST['titulo_recurso'] ?? ''));

        if ($accion === 'recurso_pdf') {
            $archivo = $_FILES['archivo'] ?? null;
            if ($tituloR === '' || mb_strlen($tituloR) > 150) {
                $error = 'Escribe un título para el recurso (máximo 150 caracteres).';
            } elseif (!$archivo || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
                $error = 'Elige un archivo PDF.';
            } elseif (in_array($archivo['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
                      || $archivo['size'] > MAX_PDF_BYTES) {
                $error = 'El PDF es demasiado grande (máximo 10 MB).';
            } elseif ($archivo['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
                $error = 'No se pudo subir el archivo. Inténtalo de nuevo.';
            } elseif (strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION)) !== 'pdf') {
                $error = 'Solo se aceptan archivos .pdf.';
            } else {
                // Un PDF de verdad empieza con "%PDF-"
                $fh  = fopen($archivo['tmp_name'], 'rb');
                $cab = $fh ? fread($fh, 5) : '';
                if ($fh) {
                    fclose($fh);
                }
                if ($cab !== '%PDF-') {
                    $error = 'El archivo no parece ser un PDF válido.';
                }
            }

            if ($error === null) {
                $carpeta = carpeta_recursos();
                if (!$carpeta) {
                    $error = 'No existe la carpeta storage/recursos en el proyecto.';
                } else {
                    $nombre = nombre_archivo_seguro($tituloR);
                    $ruta   = $carpeta . DIRECTORY_SEPARATOR . $nombre;
                    if (!move_uploaded_file($archivo['tmp_name'], $ruta)) {
                        $error = 'No se pudo guardar el archivo. Revisa los permisos de storage/recursos.';
                    } else {
                        try {
                            $st = $pdo->prepare('SELECT COALESCE(MAX(orden), 0) + 1 FROM recursos WHERE producto_id = ?');
                            $st->execute([$id]);
                            $orden = (int)$st->fetchColumn();
                            $st = $pdo->prepare(
                                "INSERT INTO recursos (producto_id, titulo, tipo, ruta_o_url, orden)
                                 VALUES (?, ?, 'pdf', ?, ?)"
                            );
                            $st->execute([$id, $tituloR, $nombre, $orden]);
                        } catch (PDOException $ex) {
                            @unlink($ruta);
                            throw $ex;
                        }
                    }
                }
            }
            $mensajeOk = 'PDF subido y agregado al paquete.';
        }

        elseif ($accion === 'recurso_enlace') {
            $urlR    = trim((string)($_POST['url'] ?? ''));
            $esquema = strtolower((string)parse_url($urlR, PHP_URL_SCHEME));
            if ($tituloR === '' || mb_strlen($tituloR) > 150) {
                $error = 'Escribe un título para el recurso (máximo 150 caracteres).';
            } elseif (mb_strlen($urlR) > 255 || !filter_var($urlR, FILTER_VALIDATE_URL)
                      || !in_array($esquema, ['http', 'https'], true)) {
                $error = 'Escribe un enlace válido que empiece con http:// o https://';
            } else {
                $st = $pdo->prepare('SELECT COALESCE(MAX(orden), 0) + 1 FROM recursos WHERE producto_id = ?');
                $st->execute([$id]);
                $orden = (int)$st->fetchColumn();
                $st = $pdo->prepare(
                    "INSERT INTO recursos (producto_id, titulo, tipo, ruta_o_url, orden)
                     VALUES (?, ?, 'enlace', ?, ?)"
                );
                $st->execute([$id, $tituloR, $urlR, $orden]);
            }
            $mensajeOk = 'Enlace agregado al paquete.';
        }

        else { // recurso_borrar
            $rid = (int)($_POST['recurso_id'] ?? 0);
            $st = $pdo->prepare('SELECT id, tipo, ruta_o_url FROM recursos WHERE id = ? AND producto_id = ?');
            $st->execute([$rid, $id]);
            $r = $st->fetch();
            if (!$r) {
                $error = 'Ese recurso no existe en este paquete.';
            } else {
                $st = $pdo->prepare('DELETE FROM recursos WHERE id = ?');
                $st->execute([$r['id']]);
                // El archivo solo se borra si ningún otro recurso lo usa
                if ($r['tipo'] === 'pdf') {
                    $st = $pdo->prepare("SELECT COUNT(*) FROM recursos WHERE tipo = 'pdf' AND ruta_o_url = ?");
                    $st->execute([$r['ruta_o_url']]);
                    if ((int)$st->fetchColumn() === 0) {
                        borrar_archivo_recurso($r['ruta_o_url']);
                    }
                }
            }
            $mensajeOk = 'Recurso quitado del paquete.';
        }

        flash_poner($error === null ? 'ok' : 'error', $error ?? $mensajeOk);
        redirigir($destino);
    }
}

// ---- Recursos del producto (para mostrarlos) ----
$recursos = [];
if ($producto) {
    $st = $pdo->prepare('SELECT * FROM recursos WHERE producto_id = ? ORDER BY orden, id');
    $st->execute([$id]);
    $recursos = $st->fetchAll();
    $carpeta = carpeta_recursos();
    foreach ($recursos as &$r) {
        $r['disponible'] = true;
        if ($r['tipo'] === 'pdf') {
            $r['disponible'] = $carpeta && is_file($carpeta . DIRECTORY_SEPARATOR . basename($r['ruta_o_url']));
        }
    }
    unset($r);
}

$titulo = $producto ? 'Editar producto' : 'Nuevo producto';
$activo = 'admin';
require __DIR__ . '/../includes/header.php';
?>
            <h1>Panel de administración</h1>
            <?php admin_menu('productos'); ?>
            <?php flash_mostrar(); ?>

            <p style="margin-bottom: 0.5rem;"><a href="<?= e(url('admin/productos.php')) ?>">&larr; Volver a productos</a></p>
            <h2 class="subtitulo" style="margin-top: 0.5rem;"><?= $producto ? 'Editar paquete' : 'Nuevo paquete' ?></h2>

            <?php if ($errores): ?>
                <div class="alerta alerta-error">
                    <?php foreach ($errores as $err): ?>
                        <div><?= e($err) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="<?= e(url($destino)) ?>" class="card form-admin">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="guardar">

                <div class="form-grid">
                    <label class="campo">
                        <span>Materia</span>
                        <select name="materia_id" required>
                            <option value="">— Elige una materia —</option>
                            <?php foreach ($materias as $m): ?>
                                <option value="<?= (int)$m['id'] ?>"<?= (int)$m['id'] === $f['materia_id'] ? ' selected' : '' ?>><?= e($m['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label class="campo">
                        <span>Título del paquete</span>
                        <input type="text" name="titulo" maxlength="150" value="<?= e($f['titulo']) ?>" required>
                    </label>
                    <label class="campo">
                        <span>Precio (<?= e((string)(config('paypal')['moneda'] ?? 'MXN')) ?>)</span>
                        <input type="text" name="precio" inputmode="decimal" placeholder="49.00" value="<?= e($f['precio']) ?>" required>
                    </label>
                    <label class="campo">
                        <span>Stock (vacío = ilimitado)</span>
                        <input type="text" name="stock" inputmode="numeric" placeholder="Ilimitado" value="<?= e($f['stock']) ?>">
                    </label>
                </div>

                <label class="campo">
                    <span>Descripción (opcional)</span>
                    <textarea name="descripcion" rows="3" maxlength="1000"><?= e($f['descripcion']) ?></textarea>
                </label>

                <label class="check" style="margin-bottom: 1rem;">
                    <input type="checkbox" name="activo" value="1"<?= $f['activo'] ? ' checked' : '' ?>>
                    Activo (se muestra y se puede comprar)
                </label>

                <?php if ($producto): ?>
                    <p class="recurso-meta" style="margin-bottom: 1rem;">
                        Ventas acumuladas: <strong><?= (int)$producto['ventas'] ?></strong> (se actualiza solo con cada pago).
                        Cambiar el precio no afecta las compras ya hechas.
                    </p>
                <?php endif; ?>

                <button type="submit" class="btn-comprar btn-boton"><i data-lucide="save"></i> <?= $producto ? 'Guardar cambios' : 'Crear producto' ?></button>
            </form>

            <h2 class="subtitulo">Recursos del paquete</h2>
            <?php if (!$producto): ?>
                <section class="card">
                    <p class="recurso-meta">Guarda el producto primero; después podrás subir PDF o agregar enlaces.</p>
                </section>
            <?php else: ?>
                <section class="card">
                    <?php if (!$recursos): ?>
                        <p class="recurso-meta">Este paquete todavía no tiene recursos.</p>
                    <?php endif; ?>

                    <?php foreach ($recursos as $r): ?>
                        <div class="recurso-item">
                            <div>
                                <p class="recurso-nombre"><?= e($r['titulo']) ?></p>
                                <p class="recurso-meta">
                                    <?= $r['tipo'] === 'pdf' ? 'PDF' : 'Enlace' ?> · <?= e($r['ruta_o_url']) ?>
                                    <?php if (!$r['disponible']): ?>
                                        · <span class="texto-error">falta el archivo en storage/recursos</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="acciones-fila">
                                <a href="<?= e(url('descargar.php?id=' . (int)$r['id'])) ?>" class="btn-pdf" target="_blank" rel="noopener">
                                    <i data-lucide="eye"></i> Ver
                                </a>
                                <form method="post" action="<?= e(url($destino)) ?>"
                                      onsubmit="return confirm('¿Quitar este recurso del paquete?');">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="accion" value="recurso_borrar">
                                    <input type="hidden" name="recurso_id" value="<?= (int)$r['id'] ?>">
                                    <button type="submit" class="btn-peligro">Quitar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </section>

                <div class="form-dos">
                    <form method="post" action="<?= e(url($destino)) ?>" enctype="multipart/form-data" class="card form-admin">
                        <h3><i data-lucide="upload"></i> Subir PDF</h3>
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="recurso_pdf">
                        <label class="campo">
                            <span>Título del recurso</span>
                            <input type="text" name="titulo_recurso" maxlength="150" required>
                        </label>
                        <label class="campo">
                            <span>Archivo PDF (máx. 10 MB)</span>
                            <input type="file" name="archivo" accept="application/pdf,.pdf" required>
                        </label>
                        <button type="submit" class="btn-pdf btn-boton"><i data-lucide="upload"></i> Subir PDF</button>
                    </form>

                    <form method="post" action="<?= e(url($destino)) ?>" class="card form-admin">
                        <h3><i data-lucide="link"></i> Agregar enlace</h3>
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="recurso_enlace">
                        <label class="campo">
                            <span>Título del recurso</span>
                            <input type="text" name="titulo_recurso" maxlength="150" required>
                        </label>
                        <label class="campo">
                            <span>Enlace (http:// o https://)</span>
                            <input type="text" name="url" maxlength="255" placeholder="https://" required>
                        </label>
                        <button type="submit" class="btn-pdf btn-boton"><i data-lucide="plus"></i> Agregar enlace</button>
                    </form>
                </div>
            <?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
