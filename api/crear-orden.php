<?php
// Paso 1 del pago: el botón de PayPal llama aquí.
// El servidor recalcula el total desde la BD, guarda la venta como 'pendiente'
// y crea la orden en PayPal. Devuelve {"id": "<order_id>"}.
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

$pdo     = db();
$carrito = carrito_detalle((int)$usuario['id']);

if ($carrito['avisos']) {
    json_salida(409, ['error' => 'Tu carrito cambió: ' . implode(' ', $carrito['avisos']) . ' Recarga la página.']);
}
if (!$carrito['items']) {
    json_salida(400, ['error' => 'Tu carrito está vacío.']);
}

// 1) Guardar la venta como pendiente + su detalle (precio histórico), en una transacción.
$folio = generar_folio();
try {
    $pdo->beginTransaction();
    $st = $pdo->prepare("INSERT INTO ventas (usuario_id, folio, total, estado) VALUES (?, ?, ?, 'pendiente')");
    $st->execute([$usuario['id'], $folio, centavos_a_texto($carrito['total'])]);
    $ventaId = (int)$pdo->lastInsertId();

    $stDet = $pdo->prepare('INSERT INTO detalle_venta (venta_id, producto_id, precio_unitario, cantidad) VALUES (?, ?, ?, 1)');
    foreach ($carrito['items'] as $it) {
        $stDet->execute([$ventaId, $it['id'], centavos_a_texto($it['centavos'])]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('[Aula] No se pudo guardar la venta: ' . $e->getMessage());
    json_salida(500, ['error' => 'No se pudo preparar tu compra. Inténtalo de nuevo.']);
}

// 2) Crear la orden en PayPal (desde el servidor).
$moneda   = paypal_moneda();
$totalTxt = centavos_a_texto($carrito['total']);
$articulos = [];
foreach ($carrito['items'] as $it) {
    $articulos[] = [
        'name'        => mb_substr($it['titulo'], 0, 127),
        'quantity'    => '1',
        'category'    => 'DIGITAL_GOODS',
        'unit_amount' => ['currency_code' => $moneda, 'value' => centavos_a_texto($it['centavos'])],
    ];
}
$orden = [
    'intent' => 'CAPTURE',
    'purchase_units' => [[
        'reference_id' => $folio,
        'custom_id'    => $folio,
        'description'  => 'Aula - paquetes de recursos',
        'amount'       => [
            'currency_code' => $moneda,
            'value'         => $totalTxt,
            'breakdown'     => ['item_total' => ['currency_code' => $moneda, 'value' => $totalTxt]],
        ],
        'items' => $articulos,
    ]],
    'application_context' => [
        'brand_name'          => 'Aula',
        'shipping_preference' => 'NO_SHIPPING',
        'user_action'         => 'PAY_NOW',
    ],
];

try {
    $r = paypal_api('POST', '/v2/checkout/orders', $orden, ['PayPal-Request-Id: ' . $folio]);
    if (!in_array($r['status'], [200, 201], true) || empty($r['data']['id'])) {
        throw new RuntimeException('Crear orden: ' . paypal_error_texto($r));
    }
    $orderId = $r['data']['id'];

    $st = $pdo->prepare('UPDATE ventas SET paypal_order_id = ? WHERE id = ?');
    $st->execute([$orderId, $ventaId]);
} catch (Throwable $e) {
    error_log('[Aula] PayPal crear orden (' . $folio . '): ' . $e->getMessage());
    $pdo->prepare("UPDATE ventas SET estado = 'fallida' WHERE id = ?")->execute([$ventaId]);
    $respuesta = ['error' => 'No se pudo iniciar el pago con PayPal. Inténtalo de nuevo en un momento.'];
    if (es_entorno_local()) {
        $respuesta['detalle'] = $e->getMessage(); // solo se ve en localhost
    }
    json_salida(502, $respuesta);
}

json_salida(200, ['id' => $orderId]);
