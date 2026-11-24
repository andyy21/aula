<?php
$titulo = 'Inicio';
$activo = 'inicio';
require __DIR__ . '/includes/header.php';

$pdo = db();

// Materias inscritas
$st = $pdo->prepare('SELECT COUNT(*) FROM inscripciones WHERE usuario_id = ?');
$st->execute([$usuario['id']]);
$totalMaterias = (int)$st->fetchColumn();

// Compañeros: otros clientes inscritos en alguna de mis materias
$st = $pdo->prepare(
    "SELECT DISTINCT u.id, u.nombre
     FROM usuarios u
     JOIN inscripciones i ON i.usuario_id = u.id
     WHERE u.rol = 'cliente' AND u.id <> ?
       AND i.materia_id IN (SELECT materia_id FROM inscripciones WHERE usuario_id = ?)
     ORDER BY u.nombre"
);
$st->execute([$usuario['id'], $usuario['id']]);
$companeros = $st->fetchAll();
$coloresAvatar = ['#6b8f5e', '#c9a24b', '#3d5c3a', '#8a6d9b'];

// Calendario del mes actual (semana empieza en domingo)
$hoy         = (int)date('j');
$mes         = (int)date('n');
$anio        = (int)date('Y');
$diasMes     = (int)date('t');
$primerDia   = (int)date('w', mktime(0, 0, 0, $mes, 1, $anio)); // 0 = domingo
$diasMesPrev = (int)date('t', mktime(0, 0, 0, $mes - 1, 1, $anio));
$celdas      = [];
for ($i = $primerDia - 1; $i >= 0; $i--) {
    $celdas[] = ['num' => $diasMesPrev - $i, 'clase' => 'otro'];
}
for ($d = 1; $d <= $diasMes; $d++) {
    $celdas[] = ['num' => $d, 'clase' => $d === $hoy ? 'hoy' : ''];
}
$resto = count($celdas) % 7;
if ($resto) {
    for ($d = 1; $d <= 7 - $resto; $d++) {
        $celdas[] = ['num' => $d, 'clase' => 'otro'];
    }
}
?>
            <h1>Bienvenido a tu Aula de Nuevo!</h1>

            <section class="carrusel" tabindex="0" aria-roledescription="carrusel" aria-label="Avisos destacados">
                <div class="carrusel-progreso" aria-hidden="true"><span></span></div>
                <div class="carrusel-pista">
                    <article class="carrusel-slide">
                        <img src="<?= asset('fondo1.png') ?>" alt="Escritorio con lámpara, calendario y libros abiertos">
                        <div class="carrusel-texto">
                            <span class="carrusel-etiqueta"><i data-lucide="sparkles"></i> Bienvenida</span>
                            <h3>Tu aula, siempre a la mano</h3>
                            <p>Revisa tus materias, tu horario y tus recursos en un solo lugar.</p>
                            <a class="carrusel-enlace" href="<?= url('materias.php') ?>">Ver mis materias</a>
                        </div>
                    </article>
                    <article class="carrusel-slide">
                        <img src="<?= asset('fondo2.png') ?>" alt="Escritorio de estudio con útiles y plantas">
                        <div class="carrusel-texto">
                            <span class="carrusel-etiqueta"><i data-lucide="file-text"></i> Recursos</span>
                            <h3>Paquetes de recursos</h3>
                            <p>Resúmenes, guías y ejercicios de tus materias, listos para descargar.</p>
                            <a class="carrusel-enlace" href="<?= url('recursos.php') ?>">Explorar recursos</a>
                        </div>
                    </article>
                    <article class="carrusel-slide">
                        <img src="<?= asset('fondo3.png') ?>" alt="Escritorio con libros apilados y un gato dormido">
                        <div class="carrusel-texto">
                            <span class="carrusel-etiqueta"><i data-lucide="calendar"></i> Horario</span>
                            <h3>Organiza tu horario</h3>
                            <p>Consulta tus clases de la semana y no te pierdas ninguna.</p>
                            <a class="carrusel-enlace" href="<?= url('horario.php') ?>">Ver horario</a>
                        </div>
                    </article>
                    <article class="carrusel-slide">
                        <img src="<?= asset('fondo4.png') ?>" alt="Escritorio con cuaderno, taza y libros">
                        <div class="carrusel-texto">
                            <span class="carrusel-etiqueta"><i data-lucide="shopping-cart"></i> Pago seguro</span>
                            <h3>Compra segura con PayPal</h3>
                            <p>Desbloquea paquetes completos y descarga tu recibo en PDF.</p>
                            <a class="carrusel-enlace" href="<?= url('carrito.php') ?>">Ir al carrito</a>
                        </div>
                    </article>
                    <article class="carrusel-slide">
                        <img src="<?= asset('fondo5.png') ?>" alt="Escritorio de estudio con material escolar">
                        <div class="carrusel-texto">
                            <span class="carrusel-etiqueta"><i data-lucide="receipt"></i> Historial</span>
                            <h3>Tus compras y recibos</h3>
                            <p>Todo lo que has comprado queda guardado en tu historial.</p>
                            <a class="carrusel-enlace" href="<?= url('compras.php') ?>">Mis compras</a>
                        </div>
                    </article>
                </div>
                <button type="button" class="carrusel-flecha carrusel-ant" aria-label="Aviso anterior"><i data-lucide="chevron-left"></i></button>
                <button type="button" class="carrusel-flecha carrusel-sig" aria-label="Aviso siguiente"><i data-lucide="chevron-right"></i></button>
                <button type="button" class="carrusel-pausa" aria-label="Pausar carrusel"><i data-lucide="pause" class="ic-pausa"></i><i data-lucide="play" class="ic-play"></i></button>
                <span class="carrusel-contador" aria-hidden="true"></span>
                <div class="carrusel-puntos"></div>
            </section>

            <div class="dashboard-grid">
                <!-- Las tarjetas Resumen, Próximas Clases, Tareas, Avisos y Progreso siguen con
                     datos de ejemplo. Se vuelven dinámicas en una fase opcional (tareas, notas, avisos). -->
                <section class="card resumen">
                    <h3><i data-lucide="bar-chart-3"></i> Resumen</h3>
                    <p>Promedio: 9.2</p>
                    <p>Creditos acumulados: 180/240</p>
                    <p>Materias cursadas: <?= $totalMaterias ?></p>
                    <p>Matricula: 2023-01</p>
                </section>

                <section class="card proxclases">
                    <h3><i data-lucide="calendar"></i> Próximas Clases</h3>
                    <p>Matemáticas - 04/10/2026 - 10:00 AM</p>
                    <p>Física - 04/10/2026 - 12:00 PM</p>
                </section>

                <section class="card tareas">
                    <h3><i data-lucide="list-checks"></i> Tareas Pendientes</h3>
                    <ul>
                        <li>Práctica 5 - Desarrollo de aplicación web</li>
                        <li>Ensayo - Historia de la literatura</li>
                    </ul>
                </section>

                <section class="card avisos">
                    <h3><i data-lucide="bell"></i> Avisos</h3>
                    <p>No hay avisos nuevos.</p>
                </section>

                <section class="card progreso">
                    <div class="anillo" style="--pct: 75%">
                        <span>75%<small>6/8 completas</small></span>
                    </div>

                    <div class="progreso-tareas">
                        <h3><i data-lucide="check-circle"></i> Tareas para mañana</h3>

                        <div class="tarea-item">
                            <div class="franja"></div>
                            <div class="info">
                                <span>Desarrollo web</span>
                                <strong>Práctica 5</strong>
                                <small>Vence 03/11 · 11:59 PM</small>
                            </div>
                        </div>

                        <div class="tarea-item">
                            <div class="franja" style="background-color: var(--dorado)"></div>
                            <div class="info">
                                <span>Historia</span>
                                <strong>Ensayo</strong>
                                <small>Vence 03/11 · 3:00 PM</small>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="fila-principal">
                <section class="card calendario">
                    <h3><i data-lucide="calendar-days"></i> <?= e(ucfirst(MESES_ES[$mes]) . ' ' . $anio) ?></h3>
                    <div class="calendario-grid">
                        <?php foreach (['D', 'L', 'M', 'M', 'J', 'V', 'S'] as $n): ?>
                            <div class="dia-nombre"><?= $n ?></div>
                        <?php endforeach; ?>
                        <?php foreach ($celdas as $c): ?>
                            <div class="dia-num<?= $c['clase'] ? ' ' . $c['clase'] : '' ?>"><?= $c['num'] ?></div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <div class="columna-derecha">
                    <div class="fila-mini">
                        <section class="card materias">
                            <h3><i data-lucide="book"></i> Mis Materias</h3>
                            <p><?= $totalMaterias ?> <?= $totalMaterias === 1 ? 'Materia inscrita' : 'Materias inscritas' ?></p>
                        </section>

                        <section class="card companeros">
                            <h3><i data-lucide="users"></i> Mis Compañeros</h3>
                            <div class="avatares-grupo">
                                <?php foreach (array_slice($companeros, 0, 4) as $i => $c): ?>
                                    <div class="avatar-mini" style="background-color:<?= $coloresAvatar[$i % 4] ?>" title="<?= e($c['nombre']) ?>"><?= e(iniciales($c['nombre'])) ?></div>
                                <?php endforeach; ?>
                            </div>
                            <p><?= count($companeros) ?> <?= count($companeros) === 1 ? 'Compañero' : 'Compañeros' ?> en el aula</p>
                        </section>
                    </div>

                    <div class="notas-grid" data-usuario="<?= (int)$usuario['id'] ?>">
                        <div class="nota">
                            <h4>Idea</h4>
                            <textarea data-nota="idea" placeholder="Ej. Revisar apuntes de la clase de hoy."></textarea>
                        </div>
                        <div class="nota">
                            <h4>Recordatorio</h4>
                            <textarea data-nota="recordatorio" placeholder="Ej. Entregar proyecto final el 10/10/2026."></textarea>
                        </div>
                        <div class="nota">
                            <h4>Nota</h4>
                            <textarea data-nota="nota" placeholder="Ej. Reunión con el profesor el 05/10/2026."></textarea>
                        </div>
                        <div class="nota">
                            <h4>Pendiente</h4>
                            <textarea data-nota="pendiente" placeholder="Ej. Revisar el informe de laboratorio."></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <script src="<?= asset('js/carrusel.js') ?>"></script>
            <script src="<?= asset('js/notas.js') ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>