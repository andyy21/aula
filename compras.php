<?php
// Mis compras: compras pagadas del usuario y descarga del recibo en PDF de cada una.
require_once __DIR__ . '/includes/auth.php';
$usuario = requiere_login();

$pdo = db();
$st = $pdo->prepare(
    "SELECT id, folio, total, pagado_en
     FROM ventas
     WHERE usuario_id = ? AND estado = 'pagada'
     ORDER BY pagado_en DESC, id DESC"
);
$st->execute([$usuario['id']]);
$ventas = $st->fetchAll();

// Paquetes de todas las compras en una sola consulta
$detalles = [];
if ($ventas) {
    $ids    = array_column($ventas, 'id');
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $st = $pdo->prepare(
        "SELECT d.venta_id, p.titulo, d.precio_unitario
         FROM detalle_venta d
         JOIN productos p ON p.id = d.producto_id
         WHERE d.venta_id IN ($marcas)
         ORDER BY d.venta_id, p.id"
    );
    $st->execute($ids);
    foreach ($st->fetchAll() as $fila) {
        $detalles[(int)$fila['venta_id']][] = $fila;
    }
}

$titulo = 'Mis compras';
$activo = 'compras';
require __DIR__ . '/includes/header.php';
?>
            <h1>Mis compras</h1>

            <?php if (!$ventas): ?>
                <section class="card">
                    <p>Todavía no tienes compras. Cuando compres un paquete, aquí podrás descargar tu recibo.</p>
                    <p style="margin-top: 1rem;"><a href="<?= url('recursos.php') ?>">Ir a recursos</a></p>
                </section>
            <?php endif; ?>

            <?php foreach ($ventas as $v): ?>
                <section class="card compra">
                    <div class="compra-cab">
                        <div>
                            <p class="recurso-nombre">Folio <?= e($v['folio']) ?></p>
                            <p class="recurso-meta"><?= $v['pagado_en'] ? e(date('d/m/Y H:i', strtotime($v['pagado_en']))) : '' ?></p>
                        </div>
                        <a href="<?= e(url('recibo.php?folio=' . rawurlencode($v['folio']) . '&descargar=1')) ?>" class="btn-pdf"><i data-lucide="download"></i> Descargar recibo</a>
                    </div>

                    <?php foreach (($detalles[(int)$v['id']] ?? []) as $d): ?>
                        <div class="carrito-item">
                            <span class="recurso-nombre"><?= e($d['titulo']) ?></span>
                            <span class="carrito-precio"><?= e(dinero((float)$d['precio_unitario'])) ?></span>
                        </div>
                    <?php endforeach; ?>

                    <div class="carrito-total">
                        <span>Total pagado</span>
                        <span><?= e(dinero((float)$v['total'])) ?></span>
                    </div>
                </section>
            <?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
