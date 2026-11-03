<?php
// Utilidades del panel de administración (menú, avisos, etiquetas).
require_once __DIR__ . '/auth.php';

// Aviso que se muestra una sola vez en la siguiente página.
function flash_poner(string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = [$tipo, $mensaje];
}

function flash_mostrar(): void
{
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    if ($f) {
        $clase = $f[0] === 'ok' ? 'alerta-ok' : 'alerta-error';
        echo '<div class="alerta ' . $clase . '">' . e($f[1]) . '</div>';
    }
}

// Pestañas de navegación del panel.
function admin_menu(string $activa): void
{
    $items = [
        'resumen'   => ['index.php',    'layout-dashboard', 'Resumen'],
        'productos' => ['productos.php', 'package',          'Productos'],
        'ventas'    => ['ventas.php',    'receipt',          'Ventas'],
        'usuarios'  => ['usuarios.php',  'users',            'Usuarios'],
    ];
    echo '<nav class="admin-tabs">';
    foreach ($items as $clave => [$archivo, $icono, $texto]) {
        $clase = $clave === $activa ? ' class="activo"' : '';
        echo '<a href="' . e(url('admin/' . $archivo)) . '"' . $clase . '><i data-lucide="' . $icono . '"></i>' . e($texto) . '</a>';
    }
    echo '</nav>';
}

// Etiqueta de color según el estado de una venta.
function etiqueta_estado(string $estado): string
{
    $clases = [
        'pagada'    => 'etq-ok',
        'pendiente' => 'etq-aviso',
        'fallida'   => 'etq-error',
        'cancelada' => 'etq-gris',
    ];
    return '<span class="etq ' . ($clases[$estado] ?? 'etq-gris') . '">' . e(ucfirst($estado)) . '</span>';
}

function fecha_corta(?string $fecha): string
{
    $ts = $fecha ? strtotime($fecha) : false;
    return $ts ? date('d/m/Y H:i', $ts) : '—';
}
