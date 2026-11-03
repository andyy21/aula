<?php
// Plantilla de configuración.
// 1) Copia este archivo con el nombre  config.local.php
// 2) Llena tus datos reales en config.local.php
// config.local.php está en .gitignore: NUNCA se sube a GitHub (lleva el Secret de PayPal).
return [
    // Base de datos MySQL / MariaDB
    "db" => [
        "host" => "localhost",
        "name" => "aula",     // en hosting suele llevar prefijo, ej. "cuenta_aula"
        "user" => "root",     // en hosting: el usuario que creaste para la base
        "pass" => "",         // en XAMPP normalmente vacío
    ],

    "zona_horaria" => "America/Monterrey",

    // Credenciales de PayPal. Se obtienen en developer.paypal.com > Apps & Credentials.
    // Para pruebas usa SANDBOX. Para cobrar de verdad: credenciales Live y
    // "api_base" => "https://api-m.paypal.com" (y el sitio debe tener HTTPS).
    "paypal" => [
        "client_id" => "",
        "secret"    => "",
        "api_base"  => "https://api-m.sandbox.paypal.com",
        "moneda"    => "MXN",   // si tus cuentas sandbox no aceptan MXN, usa "USD"

        // Opcional: si en XAMPP sale un error de certificado SSL de cURL,
        // descarga cacert.pem y pon aquí su ruta completa. Ej: "C:/xampp/php/extras/ssl/cacert.pem"
        // "cacert" => "",
    ],
];
