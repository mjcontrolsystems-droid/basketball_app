<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tabla.php';

auth_requerir();
$torneo = admin_requerir_torneo_activo();

$seccion_activa = 'dashboard';
$titulo_pagina = 'Dashboard';
require __DIR__ . '/includes/admin_layout_top.php';

$equipos = db_leer('equipos', $torneo['id']);
$partidos = db_leer('partidos', $torneo['id']);
$patrocinadores = db_leer('patrocinadores', $torneo['id']);
$tabla = calcular_tabla($equipos, $partidos, $torneo);
$lider = $tabla[0] ?? null;

$jugados = array_filter($partidos, fn($p) => $p['estado'] === 'jugado');
$programados = array_filter($partidos, fn($p) => $p['estado'] === 'programado');
$proximo = proximos_partidos($partidos, 1)[0] ?? null;
$equiposPorId = [];
foreach ($equipos as $eq) { $equiposPorId[$eq['id']] = $eq; }
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">Hola, <?= e(explode(' ', $organizador['nombre'])[0]) ?> 👋</h3>
        <p class="text-muted mb-0">Este es el resumen de <?= e($torneo['nombre']) ?> — Temporada <?= e($torneo['temporada']) ?>.</p>
    </div>
    <a href="<?= url('admin/partidos.php?accion=nuevo') ?>" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Programar encuentro</a>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg">
        <div class="stat-tile"><div class="text-muted small mb-1"><i class="bi bi-people me-1"></i>Equipos</div><div class="fs-3 fw-bold"><?= count($equipos) ?></div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="stat-tile"><div class="text-muted small mb-1"><i class="bi bi-check2-circle me-1"></i>Jugados</div><div class="fs-3 fw-bold"><?= count($jugados) ?></div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="stat-tile"><div class="text-muted small mb-1"><i class="bi bi-clock-history me-1"></i>Por jugar</div><div class="fs-3 fw-bold"><?= count($programados) ?></div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="stat-tile"><div class="text-muted small mb-1"><i class="bi bi-award me-1"></i>Patrocinadores</div><div class="fs-3 fw-bold"><?= count($patrocinadores) ?></div></div>
    </div>
    <div class="col-6 col-lg">
        <a href="<?= url('admin/comentarios.php') ?>" class="text-decoration-none text-dark">
            <div class="stat-tile"><div class="text-muted small mb-1"><i class="bi bi-chat-heart me-1"></i>Comentarios nuevos</div><div class="fs-3 fw-bold"><?= $comentariosNoLeidos ?></div></div>
        </a>
    </div>
</div>

<?php $visitas = visitas_resumen($torneo['id']); ?>
<div class="card-suave p-3 mb-4">
    <div class="d-flex flex-wrap align-items-center gap-3 gap-md-4">
        <span class="small fw-semibold text-muted text-uppercase"><i class="bi bi-eye me-1"></i>Visitas al sitio público</span>
        <span class="small">Hoy: <strong class="fs-6"><?= $visitas['hoy'] ?></strong></span>
        <span class="small">Últimos 7 días: <strong class="fs-6"><?= $visitas['semana'] ?></strong></span>
        <span class="small">Total: <strong class="fs-6"><?= $visitas['total'] ?></strong></span>
        <span class="small text-muted ms-auto d-none d-md-inline">Se cuenta una visita por persona por día.</span>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card-suave p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Tabla de posiciones</h5>
                <a href="<?= url('admin/partidos.php') ?>" class="small">Capturar resultados <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr class="small text-muted"><th>#</th><th>Equipo</th><th class="text-center">PJ</th><th class="text-center">PG-PP</th><th class="text-center">PTS</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($tabla, 0, 8) as $fila): ?>
                        <tr>
                            <td class="fw-bold"><?= $fila['posicion'] ?></td>
                            <td class="d-flex align-items-center gap-2"><?= logo_equipo($fila['equipo'], 28) ?><?= e($fila['equipo']['nombre']) ?></td>
                            <td class="text-center"><?= $fila['pj'] ?></td>
                            <td class="text-center"><?= $fila['pg'] ?>-<?= $fila['pp'] ?></td>
                            <td class="text-center fw-bold"><?= $fila['pts'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-suave p-4 mb-4">
            <h5 class="mb-3">Próximo encuentro</h5>
            <?php if ($proximo): $local = $equiposPorId[$proximo['equipo_local']]; $visit = $equiposPorId[$proximo['equipo_visitante']]; ?>
                <p class="small text-muted mb-3"><i class="bi bi-calendar3 me-1"></i><?= formatear_fecha_larga($proximo['fecha']) ?> · <?= e($proximo['hora']) ?> · <?= e($proximo['cancha']) ?></p>
                <div class="d-flex align-items-center justify-content-between">
                    <div class="equipo-col"><?= logo_equipo($local, 48) ?><span class="nombre"><?= e($local['nombre']) ?></span></div>
                    <span class="fw-bold text-muted">VS</span>
                    <div class="equipo-col"><?= logo_equipo($visit, 48) ?><span class="nombre"><?= e($visit['nombre']) ?></span></div>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No hay encuentros programados.</p>
            <?php endif; ?>
        </div>
        <div class="card-suave p-4">
            <h5 class="mb-3">Accesos rápidos</h5>
            <div class="d-grid gap-2">
                <a href="<?= url('admin/equipos.php?accion=nuevo') ?>" class="btn btn-outline-secondary rounded-pill text-start"><i class="bi bi-person-plus me-2"></i>Nuevo equipo</a>
                <a href="<?= url('admin/partidos.php?accion=nuevo') ?>" class="btn btn-outline-secondary rounded-pill text-start"><i class="bi bi-calendar-plus me-2"></i>Nuevo encuentro</a>
                <a href="<?= url('admin/patrocinadores.php?accion=nuevo') ?>" class="btn btn-outline-secondary rounded-pill text-start"><i class="bi bi-award me-2"></i>Nuevo patrocinador</a>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/admin_layout_bottom.php'; ?>
