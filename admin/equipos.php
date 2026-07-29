<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/upload.php';

auth_requerir();
$torneo = admin_requerir_torneo_activo();

$equipos = db_leer('equipos', $torneo['id']);
$accion = $_GET['accion'] ?? 'lista';
$idEditar = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$equipoEditar = $idEditar ? db_buscar_por_id($equipos, $idEditar) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (($_POST['accion'] ?? '') === 'eliminar') {
        $id = (int) $_POST['id'];
        $equipoAEliminar = db_buscar_por_id($equipos, $id);
        $equipos = array_values(array_filter($equipos, fn($e) => $e['id'] !== $id));
        db_guardar('equipos', $equipos, $torneo['id']);

        // Elimina también los encuentros que involucraban a este equipo, para no dejar referencias huérfanas
        $partidos = db_leer('partidos', $torneo['id']);
        $partidosAEliminar = array_values(array_filter($partidos, fn($p) => (int) $p['equipo_local'] === $id || (int) $p['equipo_visitante'] === $id));
        $partidos = array_values(array_filter($partidos, fn($p) => (int) $p['equipo_local'] !== $id && (int) $p['equipo_visitante'] !== $id));
        db_guardar('partidos', $partidos, $torneo['id']);

        // Limpia también la plantilla del equipo y la ficha (goles/tarjetas/cambios) de los
        // partidos que jugaba, para no dejar referencias huérfanas.
        $jugadores = db_leer('jugadores', $torneo['id']);
        $jugadores = array_values(array_filter($jugadores, fn($j) => (int) $j['equipo_id'] !== $id));
        db_guardar('jugadores', $jugadores, $torneo['id']);

        foreach ($partidosAEliminar as $p) {
            db_guardar_eventos_partido($torneo['id'], (int) $p['id'], []);
        }

        if ($equipoAEliminar) {
            eliminar_imagen($equipoAEliminar['logo'] ?? null);
        }
        redirigir_con_mensaje(url('admin/equipos.php'), 'success', 'Equipo y sus encuentros asociados fueron eliminados.');
    }

    if (($_POST['accion'] ?? '') === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $datos = [
            'nombre' => trim((string) $_POST['nombre']),
            'ciudad' => trim((string) $_POST['ciudad']),
            'sede' => trim((string) $_POST['sede']),
            'entrenador' => trim((string) $_POST['entrenador']),
            'fundacion' => trim((string) $_POST['fundacion']),
            'color_primario' => (string) $_POST['color_primario'],
            'color_secundario' => (string) $_POST['color_secundario'],
            'descripcion' => trim((string) $_POST['descripcion']),
        ];

        if ($datos['nombre'] === '') {
            redirigir_con_mensaje(url('admin/equipos.php'), 'error', 'El nombre del equipo es obligatorio.');
        }

        try {
            $logoSubido = manejar_subida_imagen('logo', 'equipos');
        } catch (RuntimeException $e) {
            redirigir_con_mensaje(url('admin/equipos.php'), 'error', $e->getMessage());
        }

        if ($id > 0) {
            foreach ($equipos as &$e) {
                if ($e['id'] === $id) {
                    if ($logoSubido) {
                        eliminar_imagen($e['logo'] ?? null);
                        $datos['logo'] = $logoSubido;
                    } else {
                        $datos['logo'] = $e['logo'] ?? '';
                    }
                    $e = array_merge($e, $datos, ['id' => $id]);
                }
            }
            unset($e);
            $mensaje = 'Equipo actualizado correctamente.';
        } else {
            $datos['id'] = db_siguiente_id_global('equipos');
            $datos['logo'] = $logoSubido ?? '';
            $equipos[] = $datos;
            $mensaje = 'Equipo creado correctamente.';
        }

        db_guardar('equipos', $equipos, $torneo['id']);
        redirigir_con_mensaje(url('admin/equipos.php'), 'success', $mensaje);
    }
}

$seccion_activa = 'equipos';
$titulo_pagina = 'Equipos';
require __DIR__ . '/includes/admin_layout_top.php';
?>

<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="<?= url('admin/equipos.php') ?>" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-arrow-left"></i></a>
        <h3 class="mb-0"><?= $equipoEditar ? 'Editar equipo' : 'Nuevo equipo' ?></h3>
    </div>

    <form method="post" enctype="multipart/form-data" class="card-suave p-4" style="max-width:760px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= $equipoEditar['id'] ?? 0 ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Nombre del equipo</label>
                <input type="text" name="nombre" class="form-control" value="<?= e($equipoEditar['nombre'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Ciudad</label>
                <input type="text" name="ciudad" class="form-control" value="<?= e($equipoEditar['ciudad'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Sede / Cancha local</label>
                <input type="text" name="sede" class="form-control" value="<?= e($equipoEditar['sede'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold"><?= e(forma_genero($torneo['genero'] ?? null, 'Entrenador', 'Entrenadora')) ?></label>
                <input type="text" name="entrenador" class="form-control" value="<?= e($equipoEditar['entrenador'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Año de fundación</label>
                <input type="text" name="fundacion" class="form-control" value="<?= e($equipoEditar['fundacion'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Color primario</label>
                <input type="color" name="color_primario" class="form-control form-control-color w-100" value="<?= e($equipoEditar['color_primario'] ?? '#7b2ff7') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Color secundario</label>
                <input type="color" name="color_secundario" class="form-control form-control-color w-100" value="<?= e($equipoEditar['color_secundario'] ?? '#ff6b35') ?>">
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"><?= e($equipoEditar['descripcion'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Escudo / Logo (opcional)</label>
                <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp">
                <div class="form-text">Si no subes uno, se generará un escudo automático con las iniciales y colores del equipo.</div>
                <?php if (!empty($equipoEditar)): ?>
                <div class="mt-2"><?= logo_equipo($equipoEditar, 64) ?></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-degradado rounded-pill px-4">Guardar equipo</button>
            <a href="<?= url('admin/equipos.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
        </div>
    </form>

<?php else: ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Equipos (<?= count($equipos) ?>)</h3>
        <a href="<?= url('admin/equipos.php?accion=nuevo') ?>" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Nuevo equipo</a>
    </div>

    <?php if (empty($equipos)): ?>
    <div class="card-suave p-4 text-center text-muted">
        <i class="bi bi-shield display-6 d-block mb-2"></i>
        Todavía no hay equipos registrados.
    </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
        <?php foreach ($equipos as $eq): ?>
        <div class="col">
            <div class="card-suave p-3 d-flex flex-row align-items-center gap-3">
                <?= logo_equipo($eq, 56) ?>
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= e($eq['nombre']) ?></div>
                    <div class="small text-muted"><?= e($eq['ciudad']) ?> · <?= e($eq['entrenador']) ?></div>
                </div>
                <div class="d-flex flex-column gap-1">
                    <a href="<?= url('admin/jugadores.php?equipo_id=' . $eq['id']) ?>" class="btn btn-sm btn-outline-secondary" title="<?= e(forma_genero($torneo['genero'] ?? null, 'Jugadores', 'Jugadoras')) ?>"><i class="bi bi-people"></i></a>
                    <a href="<?= url('admin/equipos.php?accion=editar&id=' . $eq['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <form method="post" data-confirm="¿Eliminar a <?= e($eq['nombre']) ?>? Esta acción no se puede deshacer.">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $eq['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>

<?php require __DIR__ . '/includes/admin_layout_bottom.php'; ?>
