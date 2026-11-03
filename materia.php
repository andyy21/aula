<?php
$titulo = 'Materia';
$activo = 'materias';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/paquetes.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$lista = $id ? materias_con_paquetes((int)$usuario['id'], $id) : [];
?>
            <?php if (!$lista): ?>
                <h1>Materia no encontrada</h1>
                <p>Esa materia no existe o no estás inscrito/a en ella.</p>
                <p><a href="<?= url('materias.php') ?>">← Volver a Mis Materias</a></p>
            <?php else: ?>
                <?php $m = $lista[0]; ?>
                <h1><?= e($m['nombre']) ?></h1>
                <p class="recurso-meta"><?= e($m['profesor']) ?></p>
                <?php render_materia_paquetes($m); ?>

                <div class="barra-accion">
                    <a href="<?= url('materias.php') ?>">← Volver a Mis Materias</a>
                    <form method="post" action="<?= url('inscripcion.php') ?>"
                          onsubmit="return confirm('¿Darte de baja de esta materia? Dejarás de ver sus recursos (tus compras se conservan).');">
                        <?= csrf_campo() ?>
                        <input type="hidden" name="accion" value="baja">
                        <input type="hidden" name="materia_id" value="<?= (int)$m['id'] ?>">
                        <button type="submit" class="btn-peligro">Darme de baja</button>
                    </form>
                </div>
            <?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
