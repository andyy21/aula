<?php
// Llamadas a la API REST de PayPal (Orders v2) con cURL, sin Composer.
// Estas funciones SOLO se ejecutan en el servidor: el Secret nunca llega al navegador.
require_once __DIR__ . '/db.php';

function paypal_cfg(string $clave, $defecto = null)
{
    $c = config('paypal', []);
    return $c[$clave] ?? $defecto;
}

function paypal_moneda(): string
{
    return strtoupper((string)paypal_cfg('moneda', 'MXN'));
}

// El Client ID es público (lo usa el botón de PayPal en el navegador).
function paypal_client_id(): string
{
    return (string)paypal_cfg('client_id', '');
}

function paypal_api_base(): string
{
    return rtrim((string)paypal_cfg('api_base', 'https://api-m.sandbox.paypal.com'), '/');
}

// Petición HTTP genérica con cURL. Devuelve ['status' => int, 'data' => array, 'raw' => string].
function paypal_http(string $metodo, string $url, array $cabeceras, ?string $cuerpo, ?string $usuarioClave = null): array
{
    $ch = curl_init($url);
    $opciones = [
        CURLOPT_CUSTOMREQUEST  => $metodo,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $cabeceras,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
    ];
    if ($cuerpo !== null) {
        $opciones[CURLOPT_POSTFIELDS] = $cuerpo;
    }
    if ($usuarioClave !== null) {
        $opciones[CURLOPT_USERPWD] = $usuarioClave;
    }
    // Opcional: ruta al cacert.pem si XAMPP no trae certificados (ver instrucciones)
    $cacert = paypal_cfg('cacert');
    if ($cacert) {
        $opciones[CURLOPT_CAINFO] = $cacert;
    }
    curl_setopt_array($ch, $opciones);

    $respuesta = curl_exec($ch);
    if ($respuesta === false) {
        throw new RuntimeException('cURL: ' . curl_error($ch));
    }
    $codigo = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $datos  = json_decode((string)$respuesta, true);

    return ['status' => $codigo, 'data' => is_array($datos) ? $datos : [], 'raw' => (string)$respuesta];
}

// Token de acceso (OAuth2 con Client ID + Secret).
function paypal_token(): string
{
    static $token = null;
    if ($token !== null) {
        return $token;
    }
    $id     = paypal_client_id();
    $secret = (string)paypal_cfg('secret', '');
    if ($id === '' || $secret === '') {
        throw new RuntimeException('Faltan client_id o secret de PayPal en config.local.php');
    }

    $r = paypal_http(
        'POST',
        paypal_api_base() . '/v1/oauth2/token',
        ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
        'grant_type=client_credentials',
        $id . ':' . $secret
    );
    if ($r['status'] !== 200 || empty($r['data']['access_token'])) {
        throw new RuntimeException('PayPal rechazó las credenciales (HTTP ' . $r['status'] . '): ' . substr($r['raw'], 0, 300));
    }
    $token = $r['data']['access_token'];
    return $token;
}

// Llamada autenticada a la API. $cuerpo = array (se envía como JSON; [] se envía como {}).
function paypal_api(string $metodo, string $ruta, ?array $cuerpo = null, array $extra = []): array
{
    $cabeceras = array_merge(
        ['Authorization: Bearer ' . paypal_token(), 'Content-Type: application/json', 'Accept: application/json'],
        $extra
    );
    $json = $cuerpo === null ? null : json_encode($cuerpo ?: new stdClass(), JSON_UNESCAPED_UNICODE);
    return paypal_http($metodo, paypal_api_base() . $ruta, $cabeceras, $json);
}

// Texto corto con el error que devolvió PayPal (para el log).
function paypal_error_texto(array $r): string
{
    $d    = $r['data'];
    $txt  = 'HTTP ' . $r['status'];
    $txt .= isset($d['name']) ? ' ' . $d['name'] : '';
    $txt .= isset($d['message']) ? ': ' . $d['message'] : '';
    if (!empty($d['details'][0]['issue'])) {
        $txt .= ' [' . $d['details'][0]['issue'] . ']';
    }
    return $txt;
}
