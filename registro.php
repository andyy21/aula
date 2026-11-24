<?php
require_once __DIR__ . '/includes/auth.php';

if ($ya = usuario_actual()) {
    redirigir(pagina_destino($ya));
}

$pdo      = db();
$materias = $pdo->query('SELECT id, nombre, profesor FROM materias ORDER BY id')->fetchAll();
$idsValidos = array_map('intval', array_column($materias, 'id'));

$errores  = [];
$nombre   = '';
$email    = '';
$elegidas = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre'] ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirma = (string)($_POST['confirma'] ?? '');
    // Solo se aceptan materias que existen
    $elegidas = array_values(array_intersect(array_map('intval', (array)($_POST['materias'] ?? [])), $idsValidos));

    if (!csrf_valido()) {
        $errores[] = 'La sesión expiró. Recarga la página e inténtalo de nuevo.';
    }
    if (mb_strlen($nombre) < 3 || mb_strlen($nombre) > 100) {
        $errores[] = 'Escribe tu nombre completo (de 3 a 100 caracteres).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 150) {
        $errores[] = 'El correo no es válido.';
    }
    if (strlen($password) < 8) {
        $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
    }
    if ($password !== $confirma) {
        $errores[] = 'Las contraseñas no coinciden.';
    }

    if (!$errores) {
        $st = $pdo->prepare('SELECT 1 FROM usuarios WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetchColumn()) {
            $errores[] = 'Ese correo ya está registrado.';
        }
    }

    if (!$errores) {
        try {
            $pdo->beginTransaction();
            // El rol siempre es "cliente": nunca se toma del formulario.
            $st = $pdo->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, 'cliente')");
            $st->execute([$nombre, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $nuevoId = (int)$pdo->lastInsertId();

            $ins = $pdo->prepare('INSERT INTO inscripciones (usuario_id, materia_id) VALUES (?, ?)');
            foreach ($elegidas as $materiaId) {
                $ins->execute([$nuevoId, $materiaId]);
            }
            $pdo->commit();

            iniciar_sesion(['id' => $nuevoId, 'rol' => 'cliente', 'nombre' => $nombre]);
            redirigir('index.php');
        } catch (PDOException $ex) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            // 23000 = correo duplicado (por si dos personas se registran a la vez)
            $errores[] = $ex->getCode() === '23000'
                ? 'Ese correo ya está registrado.'
                : 'No se pudo crear la cuenta. Inténtalo de nuevo.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta · Aula</title>
    <link rel="stylesheet" href="<?= asset('styles.css') ?>">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="<?= asset('js/validacion.js') ?>"></script>
    <script src="<?= asset('js/password.js') ?>"></script>
</head>

<body class="auth-pagina">
    <main class="auth-card">
        <h1>Aula</h1>
        <p class="auth-sub">Crea tu cuenta</p>

        <?php if ($errores): ?>
            <div class="alerta alerta-error">
                <?php foreach ($errores as $err): ?>
                    <div><?= e($err) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= url('registro.php') ?>">
            <?= csrf_campo() ?>
            <label class="campo">
                <span>Nombre completo</span>
                <input type="text" name="nombre" value="<?= e($nombre) ?>" maxlength="100" required autofocus>
            </label>
            <label class="campo">
                <span>Correo</span>
                <input type="email" name="email" value="<?= e($email) ?>" maxlength="150" required>
            </label>
            <label class="campo">
                <span>Contraseña (mínimo 8 caracteres)</span>
                <input type="password" name="password" minlength="8" required>
            </label>
            <label class="campo">
                <span>Confirmar contraseña</span>
                <input type="password" name="confirma" minlength="8" required>
            </label>

            <fieldset class="campo-materias">
                <legend>Materias en las que te inscribes</legend>
                <?php foreach ($materias as $m): ?>
                    <label class="check">
                        <input type="checkbox" name="materias[]" value="<?= (int)$m['id'] ?>"
                            <?= in_array((int)$m['id'], $elegidas, true) ? 'checked' : '' ?>>
                        <?= e($m['nombre']) ?> <small>· <?= e($m['profesor']) ?></small>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <button type="submit" class="btn-principal">Crear cuenta</button>
        </form>

        <p class="auth-pie">¿Ya tienes cuenta? <a href="<?= url('login.php') ?>">Inicia sesión</a></p>
    </main>
</body>

</html>
