<?php
// Panel de administración · Resumen: totales de ventas e ingresos.
require_once __DIR__ . '/../includes/admin.php';
$usuario = requiere_admin();

$pdo = db();

// Solo cuentan las ventas pagadas
$tot = $pdo->query(
    "SELECT COUNT(*) AS ventas, COALESCE(SUM(total), 0) AS ingresos
     FROM ventas WHERE estado = 'pagada'"
)->fetch();

$pendientes = (int)$pdo->query("SELECT COUNT(*) FROM ventas WHERE estado IN ('pendiente','fallida')")->fetchColumn();
$clientes   = (int)$pdo->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente'")->fetchColumn();
$prod       = $pdo->query("SELECT COUNT(*) AS total, COALESCE(SUM(activo), 0) AS activos FROM productos")->fetch();

$ultimas = $pdo->query(
    "SELECT v.folio, v.total, v.pagado_en, u.nombre
     FROM ventas v JOIN usuarios u ON u.id = v.usuario_id
     WHERE v.estado = 'pagada'
     ORDER BY v.pagado_en DESC, v.id DESC
     LIMIT 5"
)->fetchAll();

$masVendidos = $pdo->query(
    "SELECT p.titulo, m.nombre AS materia, p.ventas, p.precio
     FROM productos p JOIN materias m ON m.id = p.materia_id
     WHERE p.ventas > 0
     ORDER BY p.ventas DESC, p.id
     LIMIT 5"
)->fetchAll();

$titulo = 'Administración';
$activo = 'admin';
require __DIR__ . '/../includes/header.php';
?>
            <h1>Panel de administración</h1>
            <?php admin_menu('resumen'); ?>
            <?php flash_mostrar(); ?>

            <div class="admin-stats">
                <div class="card stat">
                    <p class="stat-num"><?= (int)$tot['ventas'] ?></p>
                    <p class="stat-txt">Ventas pagadas</p>
                </div>
                <div class="card stat">
                    <p class="stat-num"><?= e(dinero((float)$tot['ingresos'])) ?></p>
                    <p class="stat-txt">Ingresos</p>
                </div>
                <div class="card stat">
                    <p class="stat-num"><?= $clientes ?></p>
                    <p class="stat-txt">Clientes registrados</p>
                </div>
                <div class="card stat">
                    <p class="stat-num"><?= (int)$prod['activos'] ?> <small>de <?= (int)$prod['total'] ?></small></p>
                    <p class="stat-txt">Productos activos</p>
                </div>
            </div>

            <?php if ($pendientes > 0): ?>
                <p class="recurso-meta" style="margin-top: 1rem;">
                    Hay <?= $pendientes ?> venta(s) pendientes o fallidas (intentos de pago sin completar). No cuentan en los totales.
                </p>
            <?php endif; ?>

            <h2 class="subtitulo">Últimas ventas</h2>
            <section class="card">
                <?php if (!$ultimas): ?>
                    <p>Todavía no hay ventas pagadas.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                        <table class="tabla-admin">
                            <thead>
                                <tr><th>Folio</th><th>Cliente</th><th>Total</th><th>Fecha</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ultimas as $v): ?>
                                    <tr>
                                        <td><?= e($v['folio']) ?></td>
                                        <td><?= e($v['nombre']) ?></td>
                                        <td><?= e(dinero((float)$v['total'])) ?></td>
                                        <td><?= e(fecha_corta($v['pagado_en'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p style="margin-top: 1rem;"><a href="<?= e(url('admin/ventas.php')) ?>">Ver todas las ventas</a></p>
                <?php endif; ?>
            </section>

            <h2 class="subtitulo">Paquetes más vendidos</h2>
            <section class="card">
                <?php if (!$masVendidos): ?>
                    <p>Todavía no se vende ningún paquete.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                        <table class="tabla-admin">
                            <thead>
                                <tr><th>Paquete</th><th>Materia</th><th>Precio</th><th>Ventas</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($masVendidos as $p): ?>
                                    <tr>
                                        <td><?= e($p['titulo']) ?></td>
                                        <td><?= e($p['materia']) ?></td>
                                        <td><?= e(dinero((float)$p['precio'])) ?></td>
                                        <td><?= (int)$p['ventas'] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
