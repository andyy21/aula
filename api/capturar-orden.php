<?php
// Paso 2 del pago: el cliente ya aprobó en PayPal y el navegador llama aquí.
// El servidor captura el pago, verifica estado y monto, y en UNA transacción:
// marca la venta como pagada, suma ventas al producto, descuenta stock y vacía el carrito.
require_once __DIR__ . '/../includes/tienda.php';
require_once __DIR__ . '/../includes/paypal.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_salida(405, ['error' => 'Método no permitido.']);
}
$usuario = usuario_actual();
if (!$usuario) {
    json_salida(401, ['error' => 'Tu sesión expiró. Inicia sesión de nuevo.']);
}
if (!csrf_valido()) {
    json_salida(403, ['error' => 'Token inválido. Recarga la página e inténtalo de nuevo.']);
}

$orderId = trim((string)($_POST['order_id'] ?? ''));
if (!preg_match('/^[A-Za-z0-9_-]{5,64}$/', $orderId)) {
    json_salida(400, ['error' => 'Orden no válida.']);
}

$pdo = db();

// La venta debe existir y ser de este usuario.
$st = $pdo->prepare('SELECT id, folio, total, estado FROM ventas WHERE paypal_order_id = ? AND usuario_id = ?');
$st->execute([$orderId, $usuario['id']]);
$venta = $st->fetch();
if (!$venta) {
    json_salida(404, ['error' => 'No encontramos esa compra.']);
}

// Evita doble captura: si ya está pagada, solo confirmamos.
if ($venta['estado'] === 'pagada') {
    carrito_vaciar();
    json_salida(200, ['ok' => true, 'folio' => $venta['folio']]);
}
if ($venta['estado'] !== 'pendiente') {
    json_salida(409, ['error' => 'Esta compra ya no se puede cobrar. Vuelve a intentarlo desde tu carrito.']);
}

// 1) Capturar el pago en PayPal.
try {
    $ruta = '/v2/checkout/orders/' . rawurlencode($orderId);
    $r = paypal_api('POST', $ruta . '/capture', [], ['PayPal-Request-Id: captura-' . $venta['folio']]);

    // Si PayPal dice que ya estaba capturada, consultamos la orden para verificarla.
    if ($r['status'] === 422 && ($r['data']['details'][0]['issue'] ?? '') === 'ORDER_ALREADY_CAPTURED') {
        $r = paypal_api('GET', $ruta);
    }

    // El cliente puede intentar con otro método de pago.
    if ($r['status'] === 422 && ($r['data']['details'][0]['issue'] ?? '') === 'INSTRUMENT_DECLINED') {
        json_salida(422, ['error' => 'PayPal rechazó ese método de pago. Elige otro.', 'reintentar' => true]);
    }

    if (!in_array($r['status'], [200, 201], true)) {
        throw new RuntimeException('Capturar: ' . paypal_error_texto($r));
    }
} catch (Throwable $e) {
    error_log('[Aula] PayPal capturar (' . $venta['folio'] . '): ' . $e->getMessage());
    $respuesta = ['error' => 'No se pudo confirmar el pago con PayPal. Si te cobraron, contáctanos con el folio ' . $venta['folio'] . '.'];
    if (es_entorno_local()) {
        $respuesta['detalle'] = $e->getMessage();
    }
    json_salida(502, $respuesta);
}

// 2) Verificar estado y monto.
$orden   = $r['data'];
$captura = $orden['purchase_units'][0]['payments']['captures'][0] ?? null;
if (!$captura || empty($captura['id'])) {
    error_log('[Aula] Sin captura en la respuesta de PayPal (' . $venta['folio'] . ')');
    json_salida(502, ['error' => 'PayPal no devolvió la captura. Contáctanos con el folio ' . $venta['folio'] . '.']);
}

if (($orden['status'] ?? '') !== 'COMPLETED' || ($captura['status'] ?? '') !== 'COMPLETED') {
    json_salida(409, ['error' => 'PayPal aún no confirma el pago (estado: ' . ($captura['status'] ?? 'desconocido') . '). Folio ' . $venta['folio'] . '.']);
}

$monedaOk = ($captura['amount']['currency_code'] ?? '') === paypal_moneda();
$montoOk  = a_centavos($captura['amount']['value'] ?? 0) === a_centavos($venta['total']);
if (!$monedaOk || !$montoOk) {
    error_log('[Aula] MONTO NO COINCIDE en ' . $venta['folio'] . ' (captura ' . $captura['id'] . ')');
    $pdo->prepare("UPDATE ventas SET estado = 'fallida', paypal_capture_id = ? WHERE id = ?")
        ->execute([$captura['id'], $venta['id']]);
    json_salida(409, ['error' => 'El monto cobrado no coincide con tu compra. Contáctanos con el folio ' . $venta['folio'] . '.']);
}

// 3) Todo en una transacción: pagada + conteo de ventas + stock.
try {
    $pdo->beginTransaction();

    $st = $pdo->prepare(
        "UPDATE ventas SET estado = 'pagada', paypal_capture_id = ?, pagado_en = NOW()
         WHERE id = ? AND estado = 'pendiente'"
    );
    $st->execute([$captura['id'], $venta['id']]);

    // rowCount = 1 solo si ESTA petición fue la que la marcó (protege contra doble captura).
    if ($st->rowCount() === 1) {
        $st = $pdo->prepare('SELECT producto_id, cantidad FROM detalle_venta WHERE venta_id = ?');
        $st->execute([$venta['id']]);
        $detalle = $st->fetchAll();

        $stProd = $pdo->prepare(
            'UPDATE productos
             SET ventas = ventas + ?,
                 stock  = IF(stock IS NULL, NULL, GREATEST(stock - ?, 0))
             WHERE id = ?'
        );
        foreach ($detalle as $d) {
            $stProd->execute([$d['cantidad'], $d['cantidad'], $d['producto_id']]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Aula] Error al registrar el pago ' . $venta['folio'] . ' (captura ' . $captura['id'] . '): ' . $e->getMessage());
    json_salida(500, ['error' => 'Tu pago se cobró pero hubo un error al registrarlo. Contáctanos con el folio ' . $venta['folio'] . '.']);
}

carrito_vaciar();
json_salida(200, ['ok' => true, 'folio' => $venta['folio']]);
