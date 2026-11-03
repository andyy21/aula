<?php
$enlaces = [
    'inicio'   => ['index.php',    'home',      'Inicio'],
    'horario'  => ['horario.php',  'calendar',  'Horario'],
    'materias' => ['materias.php', 'book-open', 'Materias'],
    'recursos' => ['recursos.php', 'folder',    'Recursos'],
    'compras'  => ['compras.php',  'receipt',   'Mis compras'],
];
?>
<aside class="barralado">
    <div class="barralado_header">
        <h2>Aula</h2>
        <p>Bienvenido/a</p>
    </div>
    <nav class="barralado_nav">
        <?php foreach ($enlaces as $clave => [$archivo, $icono, $texto]): ?>
            <a href="<?= url($archivo) ?>" title="<?= e($texto) ?>"<?= $activo === $clave ? ' class="activo"' : '' ?>><i data-lucide="<?= $icono ?>"></i><?= e($texto) ?></a>
        <?php endforeach; ?>
        <?php $nCarrito = isset($_SESSION['carrito']) ? count($_SESSION['carrito']) : 0; ?>
        <a href="<?= url('carrito.php') ?>" title="Carrito"<?= $activo === 'carrito' ? ' class="activo"' : '' ?>>
            <i data-lucide="shopping-cart"></i>Carrito
            <?php if ($nCarrito > 0): ?><span class="carrito-badge"><?= (int)$nCarrito ?></span><?php endif; ?>
        </a>
        <?php if (($usuario['rol'] ?? '') === 'admin'): ?>
            <a href="<?= url('admin/index.php') ?>" title="Administración"<?= $activo === 'admin' ? ' class="activo"' : '' ?>><i data-lucide="shield"></i>Administración</a>
        <?php endif; ?>
        <a href="<?= url('logout.php') ?>" title="Cerrar sesión"><i data-lucide="log-out"></i>Cerrar sesión</a>
    </nav>
</aside>
