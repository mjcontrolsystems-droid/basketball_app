<?php
declare(strict_types=1);


// La copa que resolvió el front controller a partir del slug de la URL.
$torneo = copa_actual();
$equipos = equipos_listar($torneo['id']);
$partidos = partidos_listar($torneo['id']);
$patrocinadores = patrocinadores_listar($torneo['id']);

$tabla = calcular_tabla($equipos, $partidos, $torneo);
$top5 = array_slice($tabla, 0, 5);
$proximos = proximos_partidos($partidos, 3);
$resultados = ultimos_resultados($partidos, 3);
$totalJugados = count(array_filter($partidos, fn($p) => $p['estado'] === 'jugado'));
$totalProgramados = count(array_filter($partidos, fn($p) => $p['estado'] === 'programado'));
$jornadaActual = empty($partidos) ? 0 : max(array_column($partidos, 'jornada'));

$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[$eq['id']] = $eq;
}

$deporte = $torneo['deporte'] ?? null;
$basketball = es_basketball($deporte);
// Liga = solo tabla de puntos: sin zona de playoffs marcada en la clasificación.
$esLiga = torneo_es_liga($torneo);
$jugadores = jugadores_listar($torneo['id']);
$eventos = eventos_de_torneo($torneo['id']);
$topGoleadores = array_slice(calcular_goleadores($eventos, $jugadores, $equiposPorId, $deporte), 0, 5);

$patrocOficiales = array_values(array_filter($patrocinadores, fn($p) => $p['nivel'] === 'oficial'));
$patrocOro = array_values(array_filter($patrocinadores, fn($p) => $p['nivel'] === 'oro'));
$patrocPlata = array_values(array_filter($patrocinadores, fn($p) => $p['nivel'] === 'plata'));

$titulo_pagina = $torneo['nombre'] . ' — ' . $torneo['subtitulo'];
$pagina_activa = 'inicio';

vista_publica('publico/inicio', compact(
    'basketball',
    'deporte',
    'equipos',
    'equiposPorId',
    'esLiga',
    'jornadaActual',
    'pagina_activa',
    'partidos',
    'patrocOficiales',
    'patrocOro',
    'patrocPlata',
    'proximos',
    'resultados',
    'titulo_pagina',
    'top5',
    'topGoleadores',
    'torneo',
    'totalJugados'
));
