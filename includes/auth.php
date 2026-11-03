<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/funciones.php';

if (session_status() === PHP_SESSION_NONE) {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $https]);
    session_start();
}

// Devuelve el usuario con sesión iniciada (o null).
function usuario_actual(): ?array
{
    static $usuario = false;
    if ($usuario === false) {
        $usuario = null;
        if (!empty($_SESSION['usuario_id'])) {
            $st = db()->prepare('SELECT id, nombre, email, rol FROM usuarios WHERE id = ?');
            $st->execute([$_SESSION['usuario_id']]);
            $usuario = $st->fetch() ?: null;
        }
    }
    return $usuario;
}

// Páginas del cliente: exige sesión iniciada.
function requiere_login(): array
{
    $u = usuario_actual();
    if (!$u) {
        redirigir('login.php');
    }
    return $u;
}

// Páginas de /admin: exige sesión y rol admin.
function requiere_admin(): array
{
    $u = requiere_login();
    if ($u['rol'] !== 'admin') {
        http_response_code(403);
        exit('Acceso denegado.');
    }
    return $u;
}

// Guarda al usuario en la sesión (regenera el ID para evitar fijación de sesión).
function iniciar_sesion(array $u): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = (int)$u['id'];
    $_SESSION['rol']        = $u['rol'];
    $_SESSION['nombre']     = $u['nombre'];
}

// A dónde manda cada rol después de entrar.
function pagina_destino(array $u): string
{
    return $u['rol'] === 'admin' ? 'admin/index.php' : 'index.php';
}

// ---- CSRF para formularios POST ----
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valido(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

// ¿El usuario compró (venta pagada) este producto?
function tiene_acceso(int $usuarioId, int $productoId): bool
{
    $st = db()->prepare(
        "SELECT 1 FROM ventas v
         JOIN detalle_venta d ON d.venta_id = v.id
         WHERE v.usuario_id = ? AND v.estado = 'pagada' AND d.producto_id = ?
         LIMIT 1"
    );
    $st->execute([$usuarioId, $productoId]);
    return (bool)$st->fetchColumn();
}
