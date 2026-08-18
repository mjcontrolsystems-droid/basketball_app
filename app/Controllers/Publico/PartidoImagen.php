<?php
declare(strict_types=1);


// La copa que resolvió el front controller a partir del slug de la URL.
$torneo = copa_actual();
// Genera una imagen cuadrada (1080x1080) del resultado del partido, lista para subir a
// Instagram o mandar por WhatsApp. Se dibuja en el navegador con <canvas> (usando la
// misma tipografía del sitio) y se descarga como PNG con un botón — sin depender de
// fuentes instaladas en el servidor.

$id = (int) ($_GET['id'] ?? 0);
$partidos = partidos_listar($torneo['id']);
$partido = db_buscar_por_id($partidos, $id);

$equipos = equipos_listar($torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[$eq['id']] = $eq;
}

$local = $partido ? ($equiposPorId[$partido['equipo_local']] ?? null) : null;
$visit = $partido ? ($equiposPorId[$partido['equipo_visitante']] ?? null) : null;

if (!$partido || !$local || !$visit || $partido['estado'] !== 'jugado') {
    vista_404_copa('Resultado no disponible', url_copa('calendario.php'), 'Volver al calendario');
}

$deporte = $torneo['deporte'] ?? null;
$titulo_pagina = 'Imagen del resultado — ' . $local['nombre'] . ' vs ' . $visit['nombre'];
$pagina_activa = 'calendario';

vista_publica('publico/partido_imagen', compact(
    'id',
    'local',
    'pagina_activa',
    'partido',
    'titulo_pagina',
    'torneo',
    'visit'
));
