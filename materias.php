<?php
$titulo = 'Materias';
$activo = 'materias';
require __DIR__ . '/includes/header.php';

$pdo = db();

$st = $pdo->prepare(
    "SELECT m.id, m.nombre, m.profesor, m.icono, m.imagen
     FROM materias m
     JOIN inscripciones i ON i.materia_id = m.id
     WHERE i.usuario_id = ?
     ORDER BY m.id"
);
$st->execute([$usuario['id']]);
$materias = $st->fetchAll();

// Materias en las que todavía no está inscrito
$st = $pdo->prepare(
    "SELECT id, nombre, profesor, icono
     FROM materias
     WHERE id NOT IN (SELECT materia_id FROM inscripciones WHERE usuario_id = ?)
     ORDER BY id"
);
$st->execute([$usuario['id']]);
$otras = $st->fetchAll();

// Mensaje de una acción anterior (inscripcion.php)
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
            <h1>Mis Materias</h1>

            <?php if ($flash): ?>
                <div class="alerta <?= $flash[0] === 'ok' ? 'alerta-ok' : 'alerta-error' ?>"><?= e($flash[1]) ?></div>
            <?php endif; ?>

            <?php if (!$materias): ?>
                <p>No estás inscrito/a en ninguna materia. Inscríbete en alguna de la lista de abajo.</p>
            <?php else: ?>
                <div class="materias-grid">
                    <?php foreach ($materias as $m): ?>
                        <a href="<?= url('materia.php?id=' . (int)$m['id']) ?>" class="materia-card">
                            <img src="<?= asset(rawurlencode($m['imagen'])) ?>" class="materia-img" alt="">
                            <div class="materia-icono"><i data-lucide="<?= e($m['icono']) ?>"></i></div>
                            <h3><?= e($m['nombre']) ?></h3>
                            <p><?= e($m['profesor']) ?></p>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($otras): ?>
                <h2 class="subtitulo">Materias disponibles</h2>
                <section class="card">
                    <?php foreach ($otras as $o): ?>
                        <div class="fila-inscribir">
                            <div>
                                <p class="recurso-nombre"><i data-lucide="<?= e($o['icono']) ?>"></i> <?= e($o['nombre']) ?></p>
                                <p class="recurso-meta"><?= e($o['profesor']) ?></p>
                            </div>
                            <form method="post" action="<?= url('inscripcion.php') ?>">
                                <?= csrf_campo() ?>
                                <input type="hidden" name="accion" value="inscribir">
                                <input type="hidden" name="materia_id" value="<?= (int)$o['id'] ?>">
                                <button type="submit" class="btn-pdf btn-boton">Inscribirme</button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </section>
            <?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
