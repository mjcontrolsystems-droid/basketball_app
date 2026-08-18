<?php
declare(strict_types=1);

// La copa que resolvió el front controller a partir del slug de la URL.
$torneo = copa_actual();

$equipos = equipos_listar($torneo['id']);
$partidos = partidos_listar($torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[$eq['id']] = $eq;
}

$jornadas = partidos_por_jornada($partidos);
// En una liga no hay eliminación directa: el calendario es solo la temporada regular.
$esLiga = torneo_es_liga($torneo);
$fasesTorneo = torneo_fases_playoff($torneo);
$playoffsPorFase = partidos_playoffs_por_fase($partidos, $fasesTorneo);
$fasesValidas = array_merge(['grupos'], $fasesTorneo);

$faseSeleccionada = $_GET['fase'] ?? 'grupos';
if (!in_array($faseSeleccionada, $fasesValidas, true)) {
    $faseSeleccionada = 'grupos';
}

$jornadaSeleccionada = isset($_GET['jornada']) ? (int) $_GET['jornada'] : 0;
if ($jornadaSeleccionada === 0 || !isset($jornadas[$jornadaSeleccionada])) {
    // Por defecto muestra la primera jornada con partidos pendientes, si no, la última
    $jornadaSeleccionada = null;
    foreach ($jornadas as $num => $lista) {
        $tienePendientes = count(array_filter($lista, fn($p) => $p['estado'] === 'programado'));
        if ($tienePendientes > 0) {
            $jornadaSeleccionada = $num;
            break;
        }
    }
    if ($jornadaSeleccionada === null && !empty($jornadas)) {
        $jornadaSeleccionada = max(array_keys($jornadas));
    }
}

$titulo_pagina = 'Calendario — ' . $torneo['nombre'];
$pagina_activa = 'calendario';

vista_publica('publico/calendario', compact(
    'equiposPorId',
    'esLiga',
    'faseSeleccionada',
    'fasesValidas',
    'jornadaSeleccionada',
    'jornadas',
    'num',
    'pagina_activa',
    'playoffsPorFase',
    'titulo_pagina',
    'torneo'
));
