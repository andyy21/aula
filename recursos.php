<?php
$titulo = 'Recursos';
$activo = 'recursos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/paquetes.php';

$materias = materias_con_paquetes((int)$usuario['id']);
?>
            <h1>Recursos y Apuntes</h1>

            <?php if (!$materias): ?>
                <p>No estás inscrito/a en ninguna materia, así que no hay recursos que mostrar.</p>
            <?php endif; ?>

            <?php foreach ($materias as $m): ?>
                <?php render_materia_paquetes($m); ?>
            <?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
