<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/liga.php';
require_once __DIR__ . '/includes/torneo_actual.php';

// Genera una imagen cuadrada (1080x1080) del resultado del partido, lista para subir a
// Instagram o mandar por WhatsApp. Se dibuja en el navegador con <canvas> (usando la
// misma tipografía del sitio) y se descarga como PNG con un botón — sin depender de
// fuentes instaladas en el servidor.

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

if (!$partido || !$local || !$visit || $partido['estado'] !== 'jugado') {
    http_response_code(404);
    $titulo_pagina = 'Resultado no disponible';
    require __DIR__ . '/includes/layout_top.php';
    echo '<div class="container seccion text-center"><h1>Resultado no disponible</h1><p class="text-muted">Este encuentro todavía no tiene un resultado final.</p><a href="' . url_copa('calendario.php') . '" class="btn btn-degradado rounded-pill mt-3">Volver al calendario</a></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$deporte = $torneo['deporte'] ?? null;
$titulo_pagina = 'Imagen del resultado — ' . $local['nombre'] . ' vs ' . $visit['nombre'];
$pagina_activa = 'calendario';
require __DIR__ . '/includes/layout_top.php';
?>

<header class="hero-copa" style="padding-bottom:2.5rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-image me-1"></i>Para compartir</p>
        <h1 class="text-white mb-2">Imagen del <span class="text-degradado">resultado</span></h1>
        <p style="color:rgba(255,255,255,.75);" class="mb-0">Descárgala y súbela a Instagram, o compártela por WhatsApp.</p>
    </div>
</header>

<section class="seccion pt-4">
    <div class="container text-center" style="max-width:640px;">
        <div id="datosResultado"
            data-torneo="<?= e($torneo['nombre']) ?>"
            data-temporada="<?= e($torneo['temporada']) ?>"
            data-color1="<?= e(color_hex_valido($torneo['color_primario'] ?? null, '#241a3a')) ?>"
            data-color2="<?= e(color_hex_valido($torneo['color_acento'] ?? null, '#7b2ff7')) ?>"
            data-local-nombre="<?= e($local['nombre']) ?>"
            data-local-color="<?= e(color_hex_valido($local['color_primario'] ?? null, '#7b2ff7')) ?>"
            data-local-logo="<?= !empty($local['logo']) ? e(url_imagen((string) $local['logo'])) : '' ?>"
            data-visit-nombre="<?= e($visit['nombre']) ?>"
            data-visit-color="<?= e(color_hex_valido($visit['color_primario'] ?? null, '#ff6b35')) ?>"
            data-visit-logo="<?= !empty($visit['logo']) ? e(url_imagen((string) $visit['logo'])) : '' ?>"
            data-marcador-local="<?= (int) $partido['marcador_local'] ?>"
            data-marcador-visit="<?= (int) $partido['marcador_visitante'] ?>"
            data-fecha="<?= e(formatear_fecha_larga($partido['fecha'])) ?>"
            data-cancha="<?= e($partido['cancha']) ?>">
        </div>

        <canvas id="canvasResultado" width="1080" height="1080" class="card-suave" style="width:100%;height:auto;max-width:480px;"></canvas>

        <div class="d-flex justify-content-center gap-2 mt-4 flex-wrap">
            <a id="btnDescargarImagen" download="resultado.png" class="btn btn-degradado rounded-pill px-4"><i class="bi bi-download me-1"></i>Descargar imagen</a>
            <a href="<?= url_copa('partido.php?id=' . $id) ?>" class="btn btn-outline-secondary rounded-pill px-4">Ver ficha del partido</a>
        </div>
        <p class="small text-muted mt-3">La imagen se genera en tu dispositivo con los colores de la copa y de los equipos.</p>
    </div>
</section>

<script src="<?= asset_url('assets/js/partido_imagen.js') ?>"></script>
<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
