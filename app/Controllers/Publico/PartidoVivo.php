<?php
declare(strict_types=1);


// La copa que resolvió el front controller a partir del slug de la URL.
$torneo = copa_actual();
// Página pública pensada para proyectar en una pantalla/TV durante el partido: marcador
// grande, feed de eventos que se actualiza solo (via partido_vivo_datos.php) y confeti al
// anotar. No requiere sesión, solo el link (ver botón "Transmisión en vivo" en Encuentros).

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

if (!$partido || !$local || !$visit) {
    vista_404_copa('Partido no encontrado', url_copa('calendario.php'), 'Volver al calendario');
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

// El encuentro ya terminó: la página deja de anunciarse como "En vivo" y muestra el cartel
// de FINAL con el resultado. Si el organizador lo finaliza mientras alguien tiene la página
// abierta, el cartel aparece solo (ver partido_vivo.js), sin recargar.
$finalizado = partido_finalizado($partido);
$marcadorLocalActual = (int) ($partido['marcador_local'] ?? 0);
$marcadorVisitanteActual = (int) ($partido['marcador_visitante'] ?? 0);
$minutosExtra = partido_minutos_extra($partido);

// Alineaciones del encuentro (titulares y banca de cada equipo, con su posición).
$jugadoresTodos = jugadores_listar($torneo['id']);
$jugadoresPorId = jugadores_por_id($jugadoresTodos);
$alineacion = db_leer_alineacion($torneo['id'], $id);

// Enlace ABSOLUTO de ESTA transmisión, para el QR y el botón de compartir. Tiene que
// llevar https://dominio: una ruta relativa pegada en WhatsApp no es un enlace que se
// pueda abrir, y un QR con una ruta relativa no resuelve a nada.
$compartir_url = SITE_ORIGIN . url_copa('partido_vivo.php?id=' . $id);
$compartir_titulo = $local['nombre'] . ' vs ' . $visit['nombre'] . ' — En vivo';

vista('publico/partido_vivo', compact(
    'alineacion',
    'colorLocal',
    'colorVisit',
    'compartir_titulo',
    'compartir_url',
    'deporte',
    'finalizado',
    'jugadoresPorId',
    'local',
    'marcadorLocalActual',
    'marcadorVisitanteActual',
    'minutosExtra',
    'partido',
    'textoAmarilla',
    'textoCambio',
    'textoGol',
    'textoRoja',
    'torneo',
    'urlBalon',
    'urlDatos',
    'visit'
));
