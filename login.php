<?php
require_once __DIR__ . '/includes/auth.php';

// Si ya inició sesión, lo mandamos a su página.
if ($ya = usuario_actual()) {
    redirigir(pagina_destino($ya));
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $bloqueo  = (int)($_SESSION['login_bloqueo'] ?? 0);

    if (!csrf_valido()) {
        $error = 'La sesión expiró. Recarga la página e inténtalo de nuevo.';
    } elseif ($bloqueo > time()) {
        $error = 'Demasiados intentos. Espera ' . ($bloqueo - time()) . ' segundos.';
    } else {
        $st = db()->prepare('SELECT id, nombre, email, rol, password_hash FROM usuarios WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();

        if ($u && password_verify($password, $u['password_hash'])) {
            unset($_SESSION['login_intentos'], $_SESSION['login_bloqueo']);
            iniciar_sesion($u);
            redirigir(pagina_destino($u));
        }

        // Máximo 5 intentos fallidos y 1 minuto de espera
        $_SESSION['login_intentos'] = (int)($_SESSION['login_intentos'] ?? 0) + 1;
        if ($_SESSION['login_intentos'] >= 5) {
            $_SESSION['login_bloqueo']  = time() + 60;
            $_SESSION['login_intentos'] = 0;
        }
        $error = 'Correo o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión · Aula</title>
    <link rel="stylesheet" href="<?= asset('styles.css') ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="<?= asset('js/validacion.js') ?>"></script>
    <script src="<?= asset('js/password.js') ?>"></script>
</head>

<body class="auth-pagina">
    <main class="auth-card">
        <h1>Aula</h1>
        <p class="auth-sub">Inicia sesión para entrar a tu aula</p>

        <?php if ($error): ?>
            <div class="alerta alerta-error"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" action="<?= url('login.php') ?>" autocomplete="on">
            <?= csrf_campo() ?>
            <label class="campo">
                <span>Correo</span>
                <input type="email" name="email" value="<?= e($email) ?>" required autofocus>
            </label>
            <label class="campo">
                <span>Contraseña</span>
                <input type="password" name="password" data-regla="vacio" required>
            </label>
            <button type="submit" class="btn-principal">Entrar</button>
        </form>

        <p class="auth-pie">¿No tienes cuenta? <a href="<?= url('registro.php') ?>">Regístrate</a></p>
    </main>
</body>

</html>
