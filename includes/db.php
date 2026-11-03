<?php
// Carga de configuración y conexión PDO.
$archivoConfig = __DIR__ . '/../config.local.php';
if (!file_exists($archivoConfig)) {
    http_response_code(500);
    exit('Falta config.local.php. Copia config.example.php como config.local.php y edítalo.');
}
$CONFIG = require $archivoConfig;
date_default_timezone_set($CONFIG['zona_horaria'] ?? 'America/Monterrey');

function config(string $clave, $defecto = null)
{
    global $CONFIG;
    return $CONFIG[$clave] ?? $defecto;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config('db');
        $dsn = "mysql:host={$c['host']};dbname={$c['name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
?>