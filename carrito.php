<?php
// Carrito de compras. Las acciones (agregar / quitar / vaciar) llegan por POST con token CSRF
// y se procesan ANTES de imprimir cualquier HTML para poder redirigir.
require_once __DIR__ . '/includes/tienda.php';
$usuario = requiere_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valido()) {
        $_SESSION['flash'] = ['error', 'La sesión expiró. Inténtalo de nuevo.'];
        redirigir('carrito.php');
    }

    $accion     = $_POST['accion'] ?? '';
    $productoId = (int)(filter_input(INPUT_POST, 'producto_id', FILTER_VALIDATE_INT) ?: 0);

    if ($accion === 'agregar') {
        $motivo = motivo_no_comprable((int)$usuario['id'], $productoId);
        if ($motivo !== null) {
            $_SESSION['flash'] = ['error', $motivo];
        } else {
            carrito_agregar($productoId);
            $_SESSION['flash'] = ['ok', 'Paquete agregado al carrito.'];
        }
    } elseif ($accion === 'quitar') {
        carrito_quitar($productoId);
        $_SESSION['flash'] = ['ok', 'Paquete quitado del carrito.'];
    } elseif ($accion === 'vaciar') {
        carrito_vaciar();
        $_SESSION['flash'] = ['ok', 'Carrito vaciado.'];
    }
    redirigir('carrito.php');
}

$titulo = 'Carrito';
$activo = 'carrito';
require __DIR__ . '/includes/header.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$carrito = carrito_detalle((int)$usuario['id']);
?>
            <h1>Mi Carrito</h1>

            <?php if ($flash): ?>
                <div class="alerta <?= $flash[0] === 'ok' ? 'alerta-ok' : 'alerta-error' ?>"><?= e($flash[1]) ?></div>
            <?php endif; ?>
            <?php foreach ($carrito['avisos'] as $aviso): ?>
                <div class="alerta alerta-error"><?= e($aviso) ?></div>
            <?php endforeach; ?>

            <?php if (!$carrito['items']): ?>
                <section class="card">
                    <h3><i data-lucide="shopping-cart"></i> Tu carrito está vacío</h3>
                    <p>Agrega paquetes desde la sección de recursos.</p>
                    <p style="margin-top: 1rem;"><a href="<?= url('recursos.php') ?>" class="btn-pdf"><i data-lucide="folder"></i> Ver recursos</a></p>
                </section>
            <?php else: ?>
                <section class="card">
                    <h3><i data-lucide="shopping-cart"></i> Paquetes en tu carrito</h3>

                    <?php foreach ($carrito['items'] as $it): ?>
                        <div class="carrito-item">
                            <div>
                                <p class="recurso-nombre"><?= e($it['titulo']) ?></p>
                                <p class="recurso-meta"><?= e($it['materia']) ?></p>
                            </div>
                            <div class="carrito-der">
                                <span class="carrito-precio"><?= e(dinero_centavos($it['centavos'])) ?></span>
                                <form method="post" action="<?= url('carrito.php') ?>">
                                    <?= csrf_campo() ?>
                                    <input type="hidden" name="accion" value="quitar">
                                    <input type="hidden" name="producto_id" value="<?= (int)$it['id'] ?>">
                                    <button type="submit" class="btn-peligro">Quitar</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="carrito-total">
                        <span>Total</span>
                        <span><?= e(dinero_centavos($carrito['total'])) ?></span>
                    </div>

                    <div class="carrito-acciones">
                        <form method="post" action="<?= url('carrito.php') ?>">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="vaciar">
                            <button type="submit" class="btn-peligro">Vaciar carrito</button>
                        </form>
                        <a href="<?= url('checkout.php') ?>" class="btn-pdf"><i data-lucide="credit-card"></i> Continuar al pago</a>
                    </div>
                </section>

                <p style="margin-top: 1rem;"><a href="<?= url('recursos.php') ?>">← Seguir viendo recursos</a></p>
            <?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
