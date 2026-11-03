<?php
// Panel de administración · Usuarios: lista de solo lectura con búsqueda.
require_once __DIR__ . '/../includes/admin.php';
$usuario = requiere_admin();

$pdo = db();

$q = trim((string)($_GET['q'] ?? ''));
$q = mb_substr($q, 0, 100);

$sql = "SELECT u.id, u.nombre, u.email, u.rol, u.creado_en,
               (SELECT COUNT(*) FROM inscripciones i WHERE i.usuario_id = u.id) AS materias,
               (SELECT COUNT(*) FROM ventas v WHERE v.usuario_id = u.id AND v.estado = 'pagada') AS compras,
               (SELECT COALESCE(SUM(v.total), 0) FROM ventas v WHERE v.usuario_id = u.id AND v.estado = 'pagada') AS gastado
        FROM usuarios u";
$params = [];
if ($q !== '') {
    // Se escapan % y _ para que se busquen como texto normal
    $like   = '%' . addcslashes($q, '%_\\') . '%';
    $sql   .= ' WHERE u.nombre LIKE ? OR u.email LIKE ?';
    $params = [$like, $like];
}
$st = $pdo->prepare($sql . ' ORDER BY u.id');
$st->execute($params);
$usuarios = $st->fetchAll();

$totalUsuarios = (int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();

$titulo = 'Usuarios';
$activo = 'admin';
require __DIR__ . '/../includes/header.php';
?>
            <h1>Panel de administración</h1>
            <?php admin_menu('usuarios'); ?>
            <?php flash_mostrar(); ?>

            <div class="barra-accion" style="margin: 0 0 1rem;">
                <h2 class="subtitulo" style="margin: 0;">Usuarios (<?= $totalUsuarios ?>)</h2>
                <form method="get" action="<?= e(url('admin/usuarios.php')) ?>" class="buscador">
                    <input type="text" name="q" value="<?= e($q) ?>" placeholder="Buscar por nombre o correo" maxlength="100">
                    <button type="submit" class="btn-pdf btn-boton"><i data-lucide="search"></i> Buscar</button>
                    <?php if ($q !== ''): ?>
                        <a href="<?= e(url('admin/usuarios.php')) ?>">Limpiar</a>
                    <?php endif; ?>
                </form>
            </div>

            <section class="card">
                <?php if (!$usuarios): ?>
                    <p>No se encontraron usuarios<?= $q !== '' ? ' con esa búsqueda' : '' ?>.</p>
                <?php else: ?>
                    <div class="tabla-scroll">
                        <table class="tabla-admin">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Nombre</th>
                                    <th>Correo</th>
                                    <th>Rol</th>
                                    <th>Materias</th>
                                    <th>Compras</th>
                                    <th>Gastado</th>
                                    <th>Registro</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($usuarios as $u): ?>
                                    <tr>
                                        <td><?= (int)$u['id'] ?></td>
                                        <td><?= e($u['nombre']) ?></td>
                                        <td><?= e($u['email']) ?></td>
                                        <td>
                                            <?php if ($u['rol'] === 'admin'): ?>
                                                <span class="etq etq-aviso">Admin</span>
                                            <?php else: ?>
                                                <span class="etq etq-gris">Cliente</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= (int)$u['materias'] ?></td>
                                        <td><?= (int)$u['compras'] ?></td>
                                        <td><?= e(dinero((float)$u['gastado'])) ?></td>
                                        <td><?= e(fecha_corta($u['creado_en'])) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
