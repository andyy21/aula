<?php
// Carrito y ventas (Fase 4).
// El carrito vive en $_SESSION['carrito'] = [producto_id => cantidad].
// NUNCA se guardan precios en la sesión: siempre se leen de la base de datos.
require_once __DIR__ . '/auth.php';

// ---------- Carrito en sesión ----------

function carrito_ids(): array
{
    $ids = [];
    foreach (array_keys($_SESSION['carrito'] ?? []) as $id) {
        $ids[] = (int)$id;
    }
    return array_values(array_unique($ids));
}

function carrito_cantidad(): int
{
    return count(carrito_ids());
}

function carrito_agregar(int $productoId): void
{
    $_SESSION['carrito'][$productoId] = 1; // son paquetes digitales: 1 por paquete
}

function carrito_quitar(int $productoId): void
{
    unset($_SESSION['carrito'][$productoId]);
}

function carrito_vaciar(): void
{
    $_SESSION['carrito'] = [];
}

// ---------- Dinero en centavos (evita errores de decimales) ----------

function a_centavos($precio): int
{
    return (int)round(((float)$precio) * 100);
}

// 4900 -> "49.00" (formato que pide PayPal)
function centavos_a_texto(int $centavos): string
{
    return number_format($centavos / 100, 2, '.', '');
}

// 4900 -> "$49.00 MXN" (para mostrar)
function dinero_centavos(int $centavos): string
{
    return dinero($centavos / 100);
}

// ---------- Reglas de compra ----------

// Devuelve el motivo por el que NO se puede comprar un paquete, o null si sí se puede.
function motivo_no_comprable(int $usuarioId, int $productoId): ?string
{
    $st = db()->prepare(
        "SELECT p.activo, p.stock,
                EXISTS(SELECT 1 FROM inscripciones i
                       WHERE i.materia_id = p.materia_id AND i.usuario_id = ?) AS inscrito
         FROM productos p
         WHERE p.id = ?"
    );
    $st->execute([$usuarioId, $productoId]);
    $p = $st->fetch();

    if (!$p || !(int)$p['activo']) {
        return 'Ese paquete no está disponible.';
    }
    if (!(int)$p['inscrito']) {
        return 'Debes estar inscrito/a en la materia para comprar este paquete.';
    }
    if (tiene_acceso($usuarioId, $productoId)) {
        return 'Ya compraste este paquete.';
    }
    if ($p['stock'] !== null && (int)$p['stock'] <= 0) {
        return 'Este paquete está agotado.';
    }
    return null;
}

// Lee el carrito desde la BD (precios reales). Quita lo que ya no se puede comprar
// y lo avisa. Devuelve ['items' => [...], 'total' => centavos, 'avisos' => [...]].
function carrito_detalle(int $usuarioId): array
{
    $ids = carrito_ids();
    if (!$ids) {
        return ['items' => [], 'total' => 0, 'avisos' => []];
    }

    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $st = db()->prepare(
        "SELECT p.id, p.titulo, p.precio, m.nombre AS materia
         FROM productos p
         JOIN materias m ON m.id = p.materia_id
         WHERE p.id IN ($marcas)"
    );
    $st->execute($ids);
    $info = [];
    foreach ($st->fetchAll() as $fila) {
        $info[(int)$fila['id']] = $fila;
    }

    $items  = [];
    $avisos = [];
    $total  = 0;

    foreach ($ids as $id) {
        $motivo = motivo_no_comprable($usuarioId, $id);
        if ($motivo !== null) {
            $nombre   = isset($info[$id]) ? $info[$id]['titulo'] : 'un paquete';
            $avisos[] = 'Quitamos «' . $nombre . '» del carrito. ' . $motivo;
            carrito_quitar($id);
            continue;
        }
        $centavos = a_centavos($info[$id]['precio']);
        $items[]  = [
            'id'       => $id,
            'titulo'   => $info[$id]['titulo'],
            'materia'  => $info[$id]['materia'],
            'centavos' => $centavos,
        ];
        $total += $centavos;
    }

    return ['items' => $items, 'total' => $total, 'avisos' => $avisos];
}

// Folio único legible. Ej: AULA-20261102-A1B2C3
function generar_folio(): string
{
    return 'AULA-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

// ---------- Respuestas JSON para api/ ----------

function json_salida(int $codigo, array $datos): void
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

// ¿Estamos en la compu del desarrollador? Solo ahí se muestran detalles técnicos de errores.
function es_entorno_local(): bool
{
    $host = $_SERVER['SERVER_NAME'] ?? '';
    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}
