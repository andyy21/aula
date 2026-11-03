<?php
// Panel de administración · Productos: lista, activar/desactivar (baja lógica).
// El alta y la edición están en producto.php.
require_once __DIR__ . '/../includes/admin.php';
$usuario = requiere_admin();

$pdo = db();

// ---- Activar / desactivar (POST + CSRF). Nunca se borra un producto: se conserva el historial de ventas. ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        flash_poner('error', 'La sesión expiró. Inténtalo de nuevo.');
        redirigir('admin/productos.php');
    }
    $id    = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $nuevo = (($_POST['accion'] ?? '') === 'activar') ? 1 : 0;
    if ($id && in_array($_POST['accion'] ?? '', ['activar', 'desactivar'], true)) {
        $st = $pdo->prepare('UPDATE productos SET activo = ? WHERE id = ?');
        $st->execute([$nuevo, $id]);
        flash_poner('ok', $nuevo ? 'Producto activado.' : 'Producto desactivado (ya no se vende, pero quienes lo compraron lo conservan).');
    } else {
        flash_poner('error', 'Acción no válida.');
    }
    redirigir('admin/productos.php');
}

$productos = $pdo->query(
    "SELECT p.id, p.titulo, p.precio, p.stock, p.ventas, p.activo, m.nombre AS materia,
            (SELECT COUNT(*) FROM recursos r WHERE r.producto_id = p.id) AS n_recursos
     FROM productos p
     JOIN materias m ON m.id = p.materia_id
     ORDER BY p.activo DESC, m.id, p.id"
)->fetchAll();

$titulo = 'Productos';
$activo = 'admin';
require __DIR__ . '/../includes/header.php';
?>
            <h1>Panel de administración</h1>
            <?php admin_menu('productos'); ?>
            <?php flash_mostrar(); ?>

            <div class="barra-accion" style="margin: 0 0 1rem;">
                <h2 class="subtitulo" style="margin: 0;">Productos (<?= count($productos) ?>)</h2>
                <a href="<?= e(url('admin/producto.php')) ?>" class="btn-comprar"><i data-lucide="plus"></i> Nuevo producto</a>
            </div>

            <section class="card">
                <?php if (!$productos): ?>
                    <p>No hay productos. Crea el primero con el botón «Nuevo producto».</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                        <table class="tabla-admin">
                            <thead>
                                <tr>
                                    <th>Paquete</th>
                                    <th>Materia</th>
                                    <th>Precio</th>
                                    <th>Stock</th>
                                    <th>Ventas</th>
                                    <th>Recursos</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($productos as $p): ?>
                                    <tr<?= $p['activo'] ? '' : ' class="fila-inactiva"' ?>>
                                        <td><?= e($p['titulo']) ?></td>
                                        <td><?= e($p['materia']) ?></td>
                                        <td><?= e(dinero((float)$p['precio'])) ?></td>
                                        <td><?= $p['stock'] === null ? 'Ilimitado' : (int)$p['stock'] ?></td>
                                        <td><?= (int)$p['ventas'] ?></td>
                                        <td><?= (int)$p['n_recursos'] ?></td>
                                        <td>
                                            <?php if ($p['activo']): ?>
                                                <span class="etq etq-ok">Activo</span>
                                            <?php else: ?>
                                                <span class="etq etq-gris">Inactivo</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="acciones-fila">
                                                <a href="<?= e(url('admin/producto.php?id=' . (int)$p['id'])) ?>" class="btn-pdf"><i data-lucide="pencil"></i> Editar</a>
                                                <form method="post" action="<?= e(url('admin/productos.php')) ?>"
                                                      <?= $p['activo'] ? 'onsubmit="return confirm(\'¿Desactivar este producto? Ya no se podrá comprar.\');"' : '' ?>>
                                                    <?= csrf_campo() ?>
                                                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                                                    <?php if ($p['activo']): ?>
                                                        <input type="hidden" name="accion" value="desactivar">
                                                        <button type="submit" class="btn-peligro">Desactivar</button>
                                                    <?php else: ?>
                                                        <input type="hidden" name="accion" value="activar">
                                                        <button type="submit" class="btn-pdf btn-boton">Activar</button>
                                                    <?php endif; ?>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
            <p class="recurso-meta" style="margin-top: 1rem;">
                «Desactivar» es una baja lógica: el paquete deja de mostrarse y de venderse, pero se conserva el historial de ventas
                y quienes ya lo compraron siguen viéndolo.
            </p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
