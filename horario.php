<?php
$titulo = 'Horario';
$activo = 'horario';
require __DIR__ . '/includes/header.php';

// Clases de las materias en las que el usuario está inscrito
$st = db()->prepare(
    "SELECT h.dia, h.hora_inicio, h.hora_fin, m.nombre
     FROM horario h
     JOIN materias m ON m.id = h.materia_id
     JOIN inscripciones i ON i.materia_id = m.id
     WHERE i.usuario_id = ?
     ORDER BY h.hora_inicio, h.dia"
);
$st->execute([$usuario['id']]);

// $filas['08:00 - 09:00'][1] = 'Cálculo Vectorial'  (1 = lunes)
$filas = [];
foreach ($st->fetchAll() as $c) {
    $rango = substr($c['hora_inicio'], 0, 5) . ' - ' . substr($c['hora_fin'], 0, 5);
    $filas[$rango][(int)$c['dia']] = $c['nombre'];
}
$dias = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes'];
?>
            <h1>Horario de Clases</h1>

            <?php if (!$filas): ?>
                <p>Aún no tienes clases en tu horario.</p>
            <?php else: ?>
                <table class="horario-table">
                    <thead>
                        <tr>
                            <th>Hora</th>
                            <?php foreach ($dias as $nombreDia): ?>
                                <th><?= $nombreDia ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filas as $rango => $porDia): ?>
                            <tr>
                                <td><?= e($rango) ?></td>
                                <?php foreach ($dias as $num => $nombreDia): ?>
                                    <td><?= e($porDia[$num] ?? '') ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>