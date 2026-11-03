<?php
// Inscribirse o darse de baja de una materia (solo POST, con token CSRF).
require_once __DIR__ . '/includes/auth.php';
$usuario = requiere_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir('materias.php');
}
if (!csrf_valido()) {
    $_SESSION['flash'] = ['error', 'La sesión expiró. Inténtalo de nuevo.'];
    redirigir('materias.php');
}

$accion    = $_POST['accion'] ?? '';
$materiaId = filter_input(INPUT_POST, 'materia_id', FILTER_VALIDATE_INT);

$st = db()->prepare('SELECT nombre FROM materias WHERE id = ?');
$st->execute([$materiaId ?: 0]);
$materia = $st->fetch();

if (!$materia) {
    $_SESSION['flash'] = ['error', 'Esa materia no existe.'];
    redirigir('materias.php');
}

if ($accion === 'inscribir') {
    $st = db()->prepare('INSERT IGNORE INTO inscripciones (usuario_id, materia_id) VALUES (?, ?)');
    $st->execute([$usuario['id'], $materiaId]);
    $_SESSION['flash'] = ['ok', 'Te inscribiste en ' . $materia['nombre'] . '.'];
} elseif ($accion === 'baja') {
    $st = db()->prepare('DELETE FROM inscripciones WHERE usuario_id = ? AND materia_id = ?');
    $st->execute([$usuario['id'], $materiaId]);
    $_SESSION['flash'] = ['ok', 'Te diste de baja de ' . $materia['nombre'] . '.'];
}

redirigir('materias.php');
