<?php
// Panel de administración · Ventas: cliente, paquetes, total, estado de PayPal y fecha.
require_once __DIR__ . '/../includes/admin.php';
$usuario = requiere_admin();

$pdo = db();

$estados = ['pagada', 'pendiente', 'fallida', 'cancelada'];
$estado  = (string)($_GET['estado'] ?? '');
if (!in_array($estado, $estados, true)) {
    $estado = ''; // todas
}

$porPagina = 25;
$pagina    = max(1, (int)(filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1));

// Conteo y suma por estado (para las pestañas de filtro y el resumen)
$resumen = [];
foreach ($pdo->query("SELECT estado, COUNT(*) AS n, COALESCE(SUM(total), 0) AS suma FROM ventas GROUP BY estado")->fetchAll() as $fila) {
    $resumen[$fila['estado']] = $fila;
}
$totalTodas = array_sum(array_map(fn($x) => (int)$x['n'], $resumen));
$ingresos   = (float)($resumen['pagada']['suma'] ?? 0);

$where  = $estado !== '' ? 'WHERE v.estado = ?' : '';
$params = $estado !== '' ? [$estado] : [];

$st = $pdo->prepare("SELECT COUNT(*) FROM ventas v $where");
$st->execute($params);
$totalFiltradas = (int)$st->fetchColumn();
$paginas = max(1, (int)ceil($totalFiltradas / $porPagina));
$pagina  = min($pagina, $paginas);
$offset  = ($pagina - 1) * $porPagina;

// LIMIT/OFFSET son enteros ya validados, por eso van directos en la consulta
$st = $pdo->prepare(
    "SELECT v.id, v.folio, v.total, v.estado, v.paypal_order_id, v.creado_en, v.pagado_en,
            u.nombre, u.email
     FROM ventas v
     JOIN usuarios u ON u.id = v.usuario_id
     $where
     ORDER BY v.id DESC
     LIMIT $porPagina OFFSET $offset"
);
$st->execute($params);
$ventas = $st->fetchAll();

// Paquetes de las ventas de esta página (una sola consulta)
$detalles = [];
if ($ventas) {
    $ids    = array_column($ventas, 'id');
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare(
        "SELECT d.venta_id, p.titulo
         FROM detalle_venta d
         JOIN productos p ON p.id = d.producto_id
         WHERE d.venta_id IN ($marcas)
         ORDER BY d.venta_id, p.id"
    );
    $st->execute($ids);
    foreach ($st->fetchAll() as $fila) {
        $detalles[(int)$fila['venta_id']][] = $fila['titulo'];
    }
}

// Enlace con filtro y página
function enlace_ventas(string $estado, int $pagina = 1): string
{
    $q = [];
    if ($estado !== '') {
        $q['estado'] = $estado;
    }
    if ($pagina > 1) {
        $q['pagina'] = $pagina;
    }
    return url('admin/ventas.php') . ($q ? '?' . http_build_query($q) : '');
}

$titulo = 'Ventas';
$activo = 'admin';
require __DIR__ . '/../includes/header.php';
?>
            <h1>Panel de administración</h1>
            <?php admin_menu('ventas'); ?>
            <?php flash_mostrar(); ?>

            <div class="admin-stats">
                <div class="card stat">
                    <p class="stat-num"><?= (int)($resumen['pagada']['n'] ?? 0) ?></p>
                    <p class="stat-txt">Ventas pagadas</p>
                </div>
                <div class="card stat">
                    <p class="stat-num"><?= e(dinero($ingresos)) ?></p>
                    <p class="stat-txt">Ingresos</p>
                </div>
            </div>

            <div class="filtros">
                <a href="<?= e(enlace_ventas('')) ?>"<?= $estado === '' ? ' class="activo"' : '' ?>>Todas (<?= $totalTodas ?>)</a>
                <?php foreach ($estados as $es): ?>
                    <a href="<?= e(enlace_ventas($es)) ?>"<?= $estado === $es ? ' class="activo"' : '' ?>><?= e(ucfirst($es)) ?> (<?= (int)($resumen[$es]['n'] ?? 0) ?>)</a>
                <?php endforeach; ?>
            </div>

            <section class="card">
                <?php if (!$ventas): ?>
                    <p>No hay ventas<?= $estado !== '' ? ' con ese estado' : '' ?>.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                        <table class="tabla-admin">
                            <thead>
                                <tr>
                                    <th>Folio</th>
                                    <th>Cliente</th>
                                    <th>Paquetes</th>
                                    <th>Total</th>
                                    <th>Estado</th>
                                    <th>ID de PayPal</th>
                                    <th>Fecha</th>
                                    <th>Recibo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ventas as $v): ?>
                                    <tr>
                                        <td><?= e($v['folio']) ?></td>
                                        <td>
                                            <?= e($v['nombre']) ?><br>
                                            <small class="recurso-meta"><?= e($v['email']) ?></small>
                                        </td>
                                        <td>
                                            <?php foreach (($detalles[(int)$v['id']] ?? []) as $t): ?>
                                                <div><?= e($t) ?></div>
                                            <?php endforeach; ?>
                                        </td>
                                        <td><?= e(dinero((float)$v['total'])) ?></td>
                                        <td><?= etiqueta_estado((string)$v['estado']) ?></td>
                                        <td><small><?= $v['paypal_order_id'] ? e($v['paypal_order_id']) : '—' ?></small></td>
                                        <td><?= e(fecha_corta($v['pagado_en'] ?: $v['creado_en'])) ?></td>
                                        <td>
                                            <?php if ($v['estado'] === 'pagada'): ?>
                                                <a href="<?= e(url('recibo.php?folio=' . rawurlencode($v['folio']))) ?>" class="btn-pdf" target="_blank" rel="noopener"><i data-lucide="file-text"></i> PDF</a>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($paginas > 1): ?>
                        <div class="paginacion">
                            <?php if ($pagina > 1): ?>
                                <a href="<?= e(enlace_ventas($estado, $pagina - 1)) ?>">&larr; Anterior</a>
                            <?php endif; ?>
                            <span>Página <?= $pagina ?> de <?= $paginas ?></span>
                            <?php if ($pagina < $paginas): ?>
                                <a href="<?= e(enlace_ventas($estado, $pagina + 1)) ?>">Siguiente &rarr;</a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
            <p class="recurso-meta" style="margin-top: 1rem;">
                Solo las ventas <strong>pagadas</strong> cuentan en los ingresos. Las pendientes y fallidas son intentos de pago que no se completaron.
            </p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
