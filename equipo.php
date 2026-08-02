<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/tabla.php';
require_once __DIR__ . '/includes/torneo_actual.php';

$equipos = db_leer('equipos', $torneo['id']);
$partidos = db_leer('partidos', $torneo['id']);

$id = (int) ($_GET['id'] ?? 0);
$equipo = db_buscar_por_id($equipos, $id);
if (!$equipo) {
    http_response_code(404);
    $titulo_pagina = 'Equipo no encontrado';
    require __DIR__ . '/includes/layout_top.php';
    echo '<div class="container seccion text-center"><h1>Equipo no encontrado</h1><a href="' . url_copa('equipos.php') . '" class="btn btn-degradado rounded-pill mt-3">Volver a equipos</a></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[$eq['id']] = $eq;
}

$tabla = calcular_tabla($equipos, $partidos, $torneo);
$filaEquipo = null;
foreach ($tabla as $fila) {
    if ($fila['equipo']['id'] === $id) {
        $filaEquipo = $fila;
        break;
    }
}

$partidosEquipo = array_values(array_filter($partidos, fn($p) => (int) $p['equipo_local'] === $id || (int) $p['equipo_visitante'] === $id));
usort($partidosEquipo, fn($a, $b) => strcmp($a['fecha'] . $a['hora'], $b['fecha'] . $b['hora']));

$jugadoresTodos = db_leer('jugadores', $torneo['id']);
$jugadoresEquipo = array_values(array_filter($jugadoresTodos, fn($j) => (int) $j['equipo_id'] === $id && !empty($j['activo'])));
usort($jugadoresEquipo, fn($a, $b) => $a['dorsal'] <=> $b['dorsal']);

$titulo_pagina = $equipo['nombre'] . ' — ' . $torneo['nombre'];
$pagina_activa = 'equipos';
require __DIR__ . '/includes/layout_top.php';
?>

<header class="hero-copa" style="padding-bottom:3rem;">
    <div class="container">
        <div class="equipo-hero" style="background:linear-gradient(135deg, <?= e($equipo['color_primario']) ?>, <?= e($equipo['color_secundario']) ?>);">
            <div class="row align-items-center gy-4">
                <div class="col-auto"><?= logo_equipo($equipo, 110) ?></div>
                <div class="col">
                    <p class="small text-uppercase fw-bold mb-1" style="letter-spacing:.1em;opacity:.85;"><?= e($equipo['ciudad']) ?></p>
                    <h1 class="mb-2"><?= e($equipo['nombre']) ?></h1>
                    <p class="mb-0" style="max-width:560px;opacity:.9;"><?= e($equipo['descripcion']) ?></p>
                </div>
                <?php if ($filaEquipo): ?>
                <div class="col-auto text-center">
                    <div class="hero-stat"><div class="valor">#<?= $filaEquipo['posicion'] ?></div><div class="etiqueta">Posición actual</div></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<section class="seccion pt-4">
    <div class="container">
        <div class="row g-4 mb-5">
            <div class="col-6 col-md-3">
                <div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-person-badge-fill"></i></span><div class="fs-3 fw-bold"><?= e($equipo['entrenador']) ?></div><div class="small text-muted"><?= e(forma_genero($torneo['genero'] ?? null, 'Entrenador', 'Entrenadora')) ?></div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-geo-alt-fill"></i></span><div class="fs-3 fw-bold"><?= e($equipo['sede']) ?></div><div class="small text-muted">Sede local</div></div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-calendar-heart-fill"></i></span><div class="fs-3 fw-bold"><?= e($equipo['fundacion']) ?></div><div class="small text-muted">Fundación</div></div>
            </div>
            <div class="col-6 col-md-3">
                <?php if ($torneo['permite_empates']): ?>
                <div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-trophy-fill"></i></span><div class="fs-3 fw-bold"><?= $filaEquipo['pg'] ?? 0 ?>-<?= $filaEquipo['pe'] ?? 0 ?>-<?= $filaEquipo['pp'] ?? 0 ?></div><div class="small text-muted">Récord (G-E-P)</div></div>
                <?php else: ?>
                <div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-trophy-fill"></i></span><div class="fs-3 fw-bold"><?= $filaEquipo['pg'] ?? 0 ?>-<?= $filaEquipo['pp'] ?? 0 ?></div><div class="small text-muted">Récord (G-P)</div></div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($filaEquipo): ?>
        <div class="row g-4 mb-5">
            <div class="col-6 col-md-2"><div class="stat-tile text-center"><span class="stat-icono"><?= icono_balon_img($torneo['deporte'] ?? null, 18) ?></span><div class="fs-4 fw-bold"><?= $filaEquipo['pj'] ?></div><div class="small text-muted">Jugados</div></div></div>
            <div class="col-6 col-md-2"><div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-graph-up-arrow"></i></span><div class="fs-4 fw-bold"><?= $filaEquipo['pf'] ?></div><div class="small text-muted">Puntos a favor</div></div></div>
            <div class="col-6 col-md-2"><div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-graph-down-arrow"></i></span><div class="fs-4 fw-bold"><?= $filaEquipo['pc'] ?></div><div class="small text-muted">Puntos en contra</div></div></div>
            <div class="col-6 col-md-2"><div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-plus-slash-minus"></i></span><div class="fs-4 fw-bold <?= $filaEquipo['dif'] >= 0 ? 'text-success' : 'text-danger' ?>"><?= $filaEquipo['dif'] >= 0 ? '+' : '' ?><?= $filaEquipo['dif'] ?></div><div class="small text-muted">Diferencia</div></div></div>
            <div class="col-6 col-md-2"><div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-percent"></i></span><div class="fs-4 fw-bold"><?= $filaEquipo['porcentaje'] ?>%</div><div class="small text-muted">% Victorias</div></div></div>
            <div class="col-6 col-md-2"><div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-star-fill"></i></span><div class="fs-4 fw-bold"><?= $filaEquipo['pts'] ?></div><div class="small text-muted">Puntos tabla</div></div></div>
        </div>
        <?php endif; ?>

        <h4 class="mb-3"><?= e(forma_genero($torneo['genero'] ?? null, 'Jugadores', 'Jugadoras')) ?></h4>
        <?php if (empty($jugadoresEquipo)): ?>
            <p class="text-muted small mb-5">Todavía no hay <?= e(mb_strtolower(forma_genero($torneo['genero'] ?? null, 'jugadores', 'jugadoras'))) ?> <?= e(forma_genero($torneo['genero'] ?? null, 'registrados', 'registradas')) ?> para este equipo.</p>
        <?php else: ?>
        <div class="row row-cols-2 row-cols-md-4 g-3 mb-5">
            <?php foreach ($jugadoresEquipo as $j): ?>
            <div class="col">
                <div class="stat-tile text-center">
                    <div class="fs-4 fw-bold">#<?= e($j['dorsal']) ?></div>
                    <div class="small text-muted"><?= e($j['nombre']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <h4 class="mb-3">Partidos de <?= e($equipo['nombre']) ?></h4>
        <div class="row row-cols-1 row-cols-lg-2 g-3">
            <?php foreach ($partidosEquipo as $p): $local = $equiposPorId[$p['equipo_local']]; $visit = $equiposPorId[$p['equipo_visitante']]; $jugado = $p['estado'] === 'jugado'; $clicable = $jugado; ?>
            <div class="col">
                <div class="partido-card h-100 <?= $clicable ? 'fila-clicable' : '' ?>" <?= $clicable ? 'data-href="' . e(url_copa('partido.php?id=' . $p['id'])) . '"' : '' ?>>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="badge-jornada">Jornada <?= $p['jornada'] ?></span>
                        <span class="small text-muted"><?= formatear_fecha_larga($p['fecha']) ?></span>
                    </div>
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="equipo-col"><?= logo_equipo($local, 48) ?><span class="nombre"><?= e($local['nombre']) ?></span></div>
                        <div class="marcador text-center"><?= $jugado ? $p['marcador_local'] . ' - ' . $p['marcador_visitante'] : '<span class="text-muted fs-6">VS</span>' ?></div>
                        <div class="equipo-col"><?= logo_equipo($visit, 48) ?><span class="nombre"><?= e($visit['nombre']) ?></span></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
