<?php
// Paquetes de recursos por materia: consulta y vista (usado por recursos.php y materia.php).
require_once __DIR__ . '/auth.php';

/**
 * Materias del usuario con sus paquetes activos, cada uno con sus recursos
 * y la marca 'acceso' (true si ya lo compró). Si $soloMateria viene, filtra por esa.
 */
function materias_con_paquetes(int $usuarioId, ?int $soloMateria = null): array
{
    $pdo = db();

    $sql = "SELECT m.id, m.nombre, m.profesor, m.icono
            FROM materias m
            JOIN inscripciones i ON i.materia_id = m.id
            WHERE i.usuario_id = ?";
    $params = [$usuarioId];
    if ($soloMateria !== null) {
        $sql .= ' AND m.id = ?';
        $params[] = $soloMateria;
    }
    $st = $pdo->prepare($sql . ' ORDER BY m.id');
    $st->execute($params);
    $materias = $st->fetchAll();

    // Productos comprados (venta pagada) por este usuario
    $st = $pdo->prepare(
        "SELECT DISTINCT d.producto_id
         FROM ventas v JOIN detalle_venta d ON d.venta_id = v.id
         WHERE v.usuario_id = ? AND v.estado = 'pagada'"
    );
    $st->execute([$usuarioId]);
    $comprados = array_flip($st->fetchAll(PDO::FETCH_COLUMN));

    // Activos, y también los desactivados que este usuario ya compró (para que no pierda su acceso)
    $stProd = $pdo->prepare('SELECT * FROM productos WHERE materia_id = ? ORDER BY id');
    $stRec  = $pdo->prepare('SELECT * FROM recursos WHERE producto_id = ? ORDER BY orden, id');

    foreach ($materias as &$m) {
        $stProd->execute([$m['id']]);
        $m['productos'] = array_values(array_filter(
            $stProd->fetchAll(),
            fn($p) => (int)$p['activo'] === 1 || isset($comprados[$p['id']])
        ));
        foreach ($m['productos'] as &$p) {
            $stRec->execute([$p['id']]);
            $p['recursos'] = $stRec->fetchAll();
            $p['acceso']   = isset($comprados[$p['id']]);
            $p['agotado']  = $p['stock'] !== null && (int)$p['stock'] <= 0;
        }
        unset($p);
    }
    unset($m);

    return $materias;
}

// Dibuja la tarjeta de una materia con sus paquetes.
function render_materia_paquetes(array $m): void
{
    ?>
    <section class="card recurso-materia">
        <h3><i data-lucide="<?= e($m['icono']) ?>"></i> <?= e($m['nombre']) ?></h3>

        <?php if (!$m['productos']): ?>
            <p class="recurso-meta">Todavía no hay paquetes disponibles para esta materia.</p>
        <?php endif; ?>

        <?php foreach ($m['productos'] as $p): ?>
            <div class="paquete<?= $p['acceso'] ? '' : ' bloqueado' ?>">
                <div class="paquete-cab">
                    <div>
                        <p class="recurso-nombre"><?= e($p['titulo']) ?></p>
                        <?php if ($p['descripcion']): ?>
                            <p class="recurso-meta"><?= e($p['descripcion']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($p['acceso']): ?>
                        <span class="insignia-ok"><i data-lucide="check-circle"></i> Comprado</span>
                    <?php elseif ($p['agotado']): ?>
                        <span class="insignia-agotado">Agotado</span>
                    <?php elseif (isset($_SESSION['carrito'][(int)$p['id']])): ?>
                        <a href="<?= url('carrito.php') ?>" class="btn-pdf">
                            <i data-lucide="shopping-cart"></i> En el carrito
                        </a>
                    <?php else: ?>
                        <form method="post" action="<?= url('carrito.php') ?>">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="accion" value="agregar">
                            <input type="hidden" name="producto_id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" class="btn-comprar btn-boton">
                                <i data-lucide="shopping-cart"></i> Comprar · <?= e(dinero((float)$p['precio'])) ?>
                            </button>
                        </form>
                    <?php endif; ?>
                </div>

                <?php foreach ($p['recursos'] as $r): ?>
                    <div class="recurso-item">
                        <div>
                            <p class="recurso-nombre"><?= e($r['titulo']) ?></p>
                            <p class="recurso-meta"><?= e($m['profesor']) ?> · <?= date('Y') ?></p>
                        </div>
                        <?php if ($p['acceso'] && $r['tipo'] === 'enlace'): ?>
                            <a href="<?= e($r['ruta_o_url']) ?>" class="btn-pdf" target="_blank" rel="noopener noreferrer">
                                <i data-lucide="external-link"></i> Abrir enlace
                            </a>
                        <?php elseif ($p['acceso']): ?>
                            <a href="<?= url('descargar.php?id=' . (int)$r['id']) ?>" class="btn-pdf">
                                <i data-lucide="file-text"></i> Ver PDF
                            </a>
                        <?php else: ?>
                            <span class="btn-pdf btn-bloqueado"><i data-lucide="lock"></i> Bloqueado</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </section>
    <?php
}
?>