<?php
// Uso: $titulo = 'Inicio'; $activo = 'inicio'; require __DIR__ . '/includes/header.php';
require_once __DIR__ . '/auth.php';
$usuario = requiere_login();
$titulo  = $titulo ?? 'Aula';
$activo  = $activo ?? '';
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?> · Aula</title>
    <link rel="stylesheet" href="<?= asset('styles.css') ?>">
    <script src="<?= asset('lucide.js') ?>"></script>
</head>

<body>
    <div class="layout">
        <?php require __DIR__ . '/sidebar.php'; ?>
        <main class="contenido">
            <div class="barrarriba">
                <div class="topbar-fecha">
                    <p><?= e(fecha_larga()) ?></p>
                    <p class="hora" id="reloj"><?= date('H:i') ?></p>
                </div>
                <div class="avatar-usuario" title="<?= e($usuario['nombre']) ?>"><?= e(iniciales($usuario['nombre'])) ?></div>
            </div>
