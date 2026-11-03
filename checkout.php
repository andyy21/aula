<?php
// Pago: dibuja el botón de PayPal (JavaScript SDK, usa solo el Client ID)
// y, con ?ok=FOLIO, muestra la pantalla de pago exitoso.
require_once __DIR__ . '/includes/tienda.php';
require_once __DIR__ . '/includes/paypal.php';
$usuario = requiere_login();

$pdo     = db();
$compra  = null;   // venta pagada (pantalla de éxito)
$detalle = [];
$carrito = null;   // carrito por pagar

if (isset($_GET['ok']) && $_GET['ok'] !== '') {
    $st = $pdo->prepare("SELECT id, folio, total, pagado_en FROM ventas WHERE folio = ? AND usuario_id = ? AND estado = 'pagada'");
    $st->execute([(string)$_GET['ok'], $usuario['id']]);
    $compra = $st->fetch();
    if (!$compra) {
        redirigir('recursos.php');
    }
    $st = $pdo->prepare(
        'SELECT p.titulo, d.precio_unitario
         FROM detalle_venta d JOIN productos p ON p.id = d.producto_id
         WHERE d.venta_id = ?'
    );
    $st->execute([$compra['id']]);
    $detalle = $st->fetchAll();
} else {
    $carrito = carrito_detalle((int)$usuario['id']);
    if ($carrito['avisos'] || !$carrito['items']) {
        $_SESSION['flash'] = $carrito['avisos']
            ? ['error', implode(' ', $carrito['avisos'])]
            : ['error', 'Tu carrito está vacío.'];
        redirigir('carrito.php');
    }
}

$titulo = 'Pago';
$activo = 'carrito';
require __DIR__ . '/includes/header.php';

$clientId = paypal_client_id();
$moneda   = paypal_moneda();
$flagsJs  = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
?>
<?php if ($compra): ?>
            <h1>¡Pago exitoso!</h1>

            <section class="card pago-ok">
                <h3><i data-lucide="check-circle"></i> Gracias por tu compra</h3>
                <p class="recurso-meta">Folio <strong><?= e($compra['folio']) ?></strong> · <?= e(date('d/m/Y H:i', strtotime($compra['pagado_en']))) ?></p>

                <?php foreach ($detalle as $d): ?>
                    <div class="carrito-item">
                        <span class="recurso-nombre"><?= e($d['titulo']) ?></span>
                        <span class="carrito-precio"><?= e(dinero((float)$d['precio_unitario'])) ?></span>
                    </div>
                <?php endforeach; ?>

                <div class="carrito-total">
                    <span>Total pagado</span>
                    <span><?= e(dinero((float)$compra['total'])) ?></span>
                </div>

                <div class="carrito-acciones">
                    <a href="<?= e(url('recibo.php?folio=' . rawurlencode($compra['folio']) . '&descargar=1')) ?>" class="btn-pdf"><i data-lucide="download"></i> Descargar recibo</a>
                    <a href="<?= url('recursos.php') ?>" class="btn-pdf"><i data-lucide="folder"></i> Ir a mis recursos</a>
                </div>
            </section>

<?php else: ?>
            <h1>Pago</h1>

            <div class="checkout-grid">
                <section class="card">
                    <h3><i data-lucide="shopping-cart"></i> Resumen de tu compra</h3>
                    <?php foreach ($carrito['items'] as $it): ?>
                        <div class="carrito-item">
                            <div>
                                <p class="recurso-nombre"><?= e($it['titulo']) ?></p>
                                <p class="recurso-meta"><?= e($it['materia']) ?></p>
                            </div>
                            <span class="carrito-precio"><?= e(dinero_centavos($it['centavos'])) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="carrito-total">
                        <span>Total</span>
                        <span><?= e(dinero_centavos($carrito['total'])) ?></span>
                    </div>
                    <p style="margin-top: 1rem;"><a href="<?= url('carrito.php') ?>">← Volver al carrito</a></p>
                </section>

                <section class="card">
                    <h3><i data-lucide="credit-card"></i> Pagar con PayPal</h3>
                    <p class="recurso-meta">Modo de pruebas (Sandbox): no se cobra dinero real.</p>

                    <?php if ($clientId === ''): ?>
                        <div class="alerta alerta-error">Falta el Client ID de PayPal en config.local.php.</div>
                    <?php else: ?>
                        <div id="paypal-boton"></div>
                        <div id="pago-mensaje" class="alerta alerta-error" hidden></div>
                    <?php endif; ?>
                </section>
            </div>

    <?php if ($clientId !== ''): ?>
            <script src="https://www.paypal.com/sdk/js?client-id=<?= rawurlencode($clientId) ?>&amp;currency=<?= rawurlencode($moneda) ?>&amp;intent=capture"></script>
            <script>
                (function () {
                    var URL_CREAR    = <?= json_encode(url('api/crear-orden.php'), $flagsJs) ?>;
                    var URL_CAPTURAR = <?= json_encode(url('api/capturar-orden.php'), $flagsJs) ?>;
                    var URL_EXITO    = <?= json_encode(url('checkout.php?ok='), $flagsJs) ?>;
                    var CSRF         = <?= json_encode(csrf_token(), $flagsJs) ?>;
                    var msg = document.getElementById('pago-mensaje');

                    function mostrar(texto) { msg.textContent = texto; msg.hidden = false; }
                    function ocultar() { msg.hidden = true; }

                    function post(url, datos) {
                        return fetch(url, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams(datos)
                        }).then(function (r) {
                            return r.json().catch(function () {
                                return { error: 'Respuesta inesperada del servidor.' };
                            }).then(function (d) { return { ok: r.ok, d: d }; });
                        });
                    }

                    if (typeof paypal === 'undefined') {
                        mostrar('No se pudo cargar PayPal. Revisa tu conexión a internet y que el Client ID sea correcto.');
                        return;
                    }

                    paypal.Buttons({
                        style: { layout: 'vertical', color: 'gold', shape: 'rect', label: 'pay' },

                        createOrder: function () {
                            ocultar();
                            return post(URL_CREAR, { csrf: CSRF }).then(function (x) {
                                if (!x.ok || !x.d.id) {
                                    throw new Error((x.d.error || 'No se pudo crear la orden.') + (x.d.detalle ? ' [' + x.d.detalle + ']' : ''));
                                }
                                return x.d.id;
                            });
                        },

                        onApprove: function (data, actions) {
                            return post(URL_CAPTURAR, { csrf: CSRF, order_id: data.orderID }).then(function (x) {
                                if (x.d && x.d.reintentar) { return actions.restart(); }
                                if (!x.ok || !x.d.ok) {
                                    throw new Error((x.d.error || 'No se pudo confirmar el pago.') + (x.d.detalle ? ' [' + x.d.detalle + ']' : ''));
                                }
                                window.location.href = URL_EXITO + encodeURIComponent(x.d.folio);
                            });
                        },

                        onCancel: function () {
                            mostrar('Cancelaste el pago. Tu carrito sigue igual: puedes intentarlo de nuevo.');
                        },

                        onError: function (err) {
                            mostrar(err && err.message ? err.message : 'Ocurrió un error con PayPal. Inténtalo de nuevo.');
                        }
                    }).render('#paypal-boton');
                })();
            </script>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
