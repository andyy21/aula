<?php
// Entrega un recurso SOLO si el usuario lo compró (o es admin).
// Los PDF viven en storage/recursos/, que no se puede abrir directo desde el navegador.
require_once __DIR__ . '/includes/auth.php';
$usuario = requiere_login();

function fallo(int $codigo, string $mensaje): void
{
    http_response_code($codigo);
    header('Content-Type: text/plain; charset=utf-8');
    exit($mensaje);
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    fallo(404, 'Recurso no encontrado.');
}

$st = db()->prepare('SELECT id, producto_id, titulo, tipo, ruta_o_url FROM recursos WHERE id = ?');
$st->execute([$id]);
$r = $st->fetch();
if (!$r) {
    fallo(404, 'Recurso no encontrado.');
}

// Candado real: sin compra pagada no hay descarga.
if ($usuario['rol'] !== 'admin' && !tiene_acceso((int)$usuario['id'], (int)$r['producto_id'])) {
    fallo(403, 'Necesitas comprar este paquete para ver este recurso.');
}

// Enlaces externos: solo http/https
if ($r['tipo'] === 'enlace') {
    $esquema = strtolower((string)parse_url($r['ruta_o_url'], PHP_URL_SCHEME));
    if (!in_array($esquema, ['http', 'https'], true)) {
        fallo(400, 'Enlace no válido.');
    }
    header('Location: ' . $r['ruta_o_url']);
    exit;
}

// PDF: se busca solo dentro de storage/recursos (sin rutas con ../)
$carpeta = realpath(__DIR__ . '/storage/recursos');
$ruta    = $carpeta ? realpath($carpeta . DIRECTORY_SEPARATOR . basename($r['ruta_o_url'])) : false;
if (!$ruta || strpos($ruta, $carpeta . DIRECTORY_SEPARATOR) !== 0 || !is_file($ruta)) {
    fallo(404, 'El archivo de este recurso todavía no está disponible.');
}

$nombre = preg_replace('/[^A-Za-z0-9_\-]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $r['titulo']) ?: 'recurso');
header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . trim($nombre, '_') . '.pdf"');
header('Content-Length: ' . filesize($ruta));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
readfile($ruta);
exit;
