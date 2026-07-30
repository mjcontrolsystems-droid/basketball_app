<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/liga.php';
require_once __DIR__ . '/includes/torneo_actual.php';

// Página pública pensada para proyectar en una pantalla/TV durante el partido: marcador
// grande, feed de eventos que se actualiza solo (via partido_vivo_datos.php) y confeti al
// anotar. No requiere sesión, solo el link (ver botón "Transmisión en vivo" en Encuentros).

$id = (int) ($_GET['id'] ?? 0);
$partidos = db_leer('partidos', $torneo['id']);
$partido = db_buscar_por_id($partidos, $id);

$equipos = db_leer('equipos', $torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[$eq['id']] = $eq;
}

$local = $partido ? ($equiposPorId[$partido['equipo_local']] ?? null) : null;
$visit = $partido ? ($equiposPorId[$partido['equipo_visitante']] ?? null) : null;

if (!$partido || !$local || !$visit) {
    http_response_code(404);
    $titulo_pagina = 'Partido no encontrado';
    require __DIR__ . '/includes/layout_top.php';
    echo '<div class="container seccion text-center"><h1>Partido no encontrado</h1><a href="' . url_copa('calendario.php') . '" class="btn btn-degradado rounded-pill mt-3">Volver al calendario</a></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$deporte = $torneo['deporte'] ?? null;
$basketball = es_basketball($deporte);
$urlDatos = e(url_copa('partido_vivo_datos.php?id=' . $id));
$balonImg = $basketball ? 'balon-basketball.png' : 'balon-futbol.png';
$urlBalon = e(url('assets/img/' . $balonImg));
// Texto del banner de reacción para cada tipo de evento (goles/puntos, tarjetas o faltas
// según el deporte, y cambios). Ver assets/js/partido_vivo.js para cómo se dispara cada uno.
$textoGol = $basketball ? '¡CANASTA!' : '¡GOL!';
$textoAmarilla = mb_strtoupper(etiqueta_falta_leve($deporte));
$textoRoja = mb_strtoupper(etiqueta_falta_grave($deporte));
$textoCambio = 'CAMBIO';
$colorLocal = color_hex_valido($local['color_primario'] ?? null, '#7b2ff7');
$colorVisit = color_hex_valido($visit['color_primario'] ?? null, '#ff6b35');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($local['nombre']) ?> vs <?= e($visit['nombre']) ?> — En vivo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= asset_url('assets/css/style.css') ?>" rel="stylesheet">
    <link rel="icon" href="<?= url('assets/img/logo.png') ?>" type="image/png">
    <?= torneo_variables_css($torneo) ?>
</head>
<body class="pagina-vivo">

<img src="<?= $urlBalon ?>" alt="" class="balon-fondo-vivo">

<div id="partidoVivo" class="pagina-vivo-contenido" data-url-datos="<?= $urlDatos ?>" data-url-balon="<?= $urlBalon ?>"
    data-texto-gol="<?= e($textoGol) ?>" data-texto-amarilla="<?= e($textoAmarilla) ?>"
    data-texto-roja="<?= e($textoRoja) ?>" data-texto-cambio="<?= e($textoCambio) ?>"
    data-cronometro-estado="<?= e($partido['cronometro_estado'] ?? 'detenido') ?>"
    data-cronometro-segundos="<?= (int) ($partido['cronometro_segundos'] ?? 0) ?>"
    data-cronometro-inicio="<?= e($partido['cronometro_inicio'] ?? '') ?>">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pagina-vivo-cabecera">
        <div class="d-flex align-items-center gap-2 badge-en-vivo">
            <span class="punto-en-vivo"></span>
            <span id="estadoPartido" class="fw-heading text-uppercase small"><?= $partido['estado'] === 'jugado' ? 'Finalizado' : 'En vivo' ?></span>
            <span class="opacity-50">·</span>
            <i class="bi bi-stopwatch"></i>
            <span id="cronometroVivo" class="cronometro-vivo font-monospace">00:00</span>
            <span id="periodoVivo" class="badge rounded-pill bg-light text-dark"><?= e(partido_periodo_etiqueta($deporte, (int) ($partido['cronometro_periodo'] ?? 1))) ?></span>
        </div>
        <span class="small opacity-75 fw-semibold"><i class="bi bi-trophy me-1"></i><?= e($torneo['nombre']) ?> · <?= e(formatear_fecha_larga($partido['fecha'])) ?></span>
        <button type="button" id="btnPantallaCompleta" class="btn btn-sm btn-outline-luz rounded-pill px-3">
            <i class="bi bi-arrows-fullscreen me-1"></i>Pantalla completa
        </button>
    </div>

    <div class="marcador-vivo">
        <div class="marcador-vivo-equipo" style="--color-equipo:<?= e($colorLocal) ?>;">
            <div class="marcador-vivo-logo"><?= logo_equipo($local, 100) ?></div>
            <span class="marcador-vivo-nombre"><?= e($local['nombre']) ?></span>
        </div>
        <div class="marcador-vivo-numeros">
            <span id="marcadorLocal" class="marcador-vivo-num"><?= (int) ($partido['marcador_local'] ?? 0) ?></span>
            <span class="marcador-vivo-guion">-</span>
            <span id="marcadorVisitante" class="marcador-vivo-num"><?= (int) ($partido['marcador_visitante'] ?? 0) ?></span>
        </div>
        <div class="marcador-vivo-equipo" style="--color-equipo:<?= e($colorVisit) ?>;">
            <div class="marcador-vivo-logo"><?= logo_equipo($visit, 100) ?></div>
            <span class="marcador-vivo-nombre"><?= e($visit['nombre']) ?></span>
        </div>
    </div>

    <div class="pagina-vivo-feed">
        <h6 class="text-uppercase small fw-bold opacity-75 mb-3"><i class="bi bi-broadcast me-1"></i><?= e(etiqueta_anotaciones($deporte)) ?>, tarjetas y cambios</h6>
        <ul id="feedEventos" class="feed-eventos list-unstyled mb-0">
            <li class="feed-evento-vacio text-center opacity-50 py-4">Esperando el inicio del partido...</li>
        </ul>
    </div>
</div>

<div id="bannerEvento" class="banner-evento">
    <span id="bannerEventoIcono" class="banner-evento-icono"></span>
    <span id="bannerEventoTexto" class="banner-evento-texto"></span>
</div>

<script src="<?= asset_url('assets/js/partido_vivo.js') ?>"></script>
</body>
</html>
