<?php
// Utilidades generales.

function e(?string $texto): string
{
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function base_url(): string
{
    // Se calcula sola a partir de la ubicación real del proyecto (no depende de config).
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $raiz = str_replace("\\", "/", (string)realpath(__DIR__ . "/.."));
    $doc  = str_replace("\\", "/", (string)realpath($_SERVER["DOCUMENT_ROOT"] ?? ""));
    if ($doc !== "" && stripos($raiz, $doc) === 0) {
        $rel = substr($raiz, strlen($doc));
    } else {
        $rel = dirname($_SERVER["SCRIPT_NAME"] ?? "/");
    }
    $rel  = trim(str_replace("\\", "/", $rel), "/");
    $base = $rel === "" ? "" : "/" . implode("/", array_map("rawurlencode", explode("/", $rel)));
    return $base;
}

function url(string $ruta = ""): string
{
    return base_url() . "/" . ltrim($ruta, "/");
}

function asset(string $ruta): string
{
    return url($ruta); // estructura plana: styles.css, lucide.js e imágenes viven en la raíz del proyecto
}

function dinero(float $monto): string
{
    $moneda = config('paypal')['moneda'] ?? 'MXN';
    return '$' . number_format($monto, 2) . ' ' . $moneda;
}

const DIAS_ES  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
const MESES_ES = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                  'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

// Ej: "Lunes, 02 de noviembre 2026"
function fecha_larga(?int $ts = null): string
{
    $ts = $ts ?? time();
    return DIAS_ES[(int)date('w', $ts)] . ', ' . date('d', $ts) . ' de '
         . MESES_ES[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

// "Ana Navarro" -> "AN"
function iniciales(string $nombre): string
{
    $partes = preg_split('/\s+/', trim($nombre));
    $ini = mb_substr($partes[0] ?? '', 0, 1);
    if (count($partes) > 1) {
        $ini .= mb_substr($partes[1], 0, 1);
    }
    return mb_strtoupper($ini);
}

function redirigir(string $ruta): void
{
    header('Location: ' . url($ruta));
    exit;
}
