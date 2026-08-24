<?php
declare(strict_types=1);

/**
 * Perfil público de un jugador: sus números de la temporada en una sola página.
 *
 * Goles con el detalle de en qué partido fueron, tarjetas, si está suspendido para la
 * próxima fecha, y el estado de sus multas. Las multas se muestran porque en estas ligas
 * la solvencia ES pública (existe la hoja de solvencia imprimible por jornada): que cada
 * quien pueda ver lo suyo desde el teléfono evita la fila de "¿yo cuánto debo?" en la mesa.
 */

$torneo = copa_actual();
$equipos = equipos_listar($torneo['id']);
$partidos = partidos_listar($torneo['id']);
$jugadoresTodos = jugadores_listar($torneo['id']);

$id = (int) ($_GET['id'] ?? 0);
$jugador = db_buscar_por_id($jugadoresTodos, $id);
if (!$jugador) {
    vista_404_copa('Jugador no encontrado', url_copa('equipos.php'), 'Volver a equipos');
}

$equipo = db_buscar_por_id($equipos, (int) $jugador['equipo_id']);
$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[$eq['id']] = $eq;
}
$partidosPorId = [];
foreach ($partidos as $p) {
    $partidosPorId[$p['id']] = $p;
}

$deporte = $torneo['deporte'] ?? null;
$basketball = es_basketball($deporte);

// --- Sus eventos, partido por partido ---
$eventos = eventos_de_torneo($torneo['id']);
$anotaciones = [];   // detalle de cada gol/canasta, con su partido
$autogoles = 0;
$amarillas = [];
$rojas = [];
$totalPuntos = 0;

foreach ($eventos as $ev) {
    if ((int) ($ev['jugador_id'] ?? 0) !== $id) {
        continue;
    }
    $tipo = (string) ($ev['tipo'] ?? '');
    if ($tipo === 'gol') {
        if (!$basketball && ($ev['tipo_gol'] ?? '') === 'autogol') {
            $autogoles++;
            continue; // no es mérito propio: no entra a su lista de goles
        }
        $valor = $basketball ? (TIPOS_PUNTO_VALOR[$ev['tipo_gol'] ?? ''] ?? 1) : 1;
        $totalPuntos += $valor;
        $anotaciones[] = [
            'partido' => $partidosPorId[(int) ($ev['partido_id'] ?? 0)] ?? null,
            'minuto' => $ev['minuto'] ?? null,
            'tipo_gol' => (string) ($ev['tipo_gol'] ?? ''),
            'valor' => $valor,
        ];
    } elseif ($tipo === 'amarilla') {
        $amarillas[] = $ev;
    } elseif ($tipo === 'roja') {
        $rojas[] = $ev;
    }
}

// --- ¿Suspendido para el próximo partido de su equipo? ---
$jugadoresPorId = jugadores_por_id($jugadoresTodos);
$proximoDeSuEquipo = null;
foreach ($partidos as $p) {
    if (($p['estado'] ?? '') === 'jugado') {
        continue;
    }
    if ((int) $p['equipo_local'] === (int) $jugador['equipo_id'] || (int) $p['equipo_visitante'] === (int) $jugador['equipo_id']) {
        if ($proximoDeSuEquipo === null || strcmp((string) $p['fecha'], (string) $proximoDeSuEquipo['fecha']) < 0) {
            $proximoDeSuEquipo = $p;
        }
    }
}
$suspension = null;
if ($proximoDeSuEquipo !== null) {
    $suspendidos = disciplina_suspendidos_para_partido($torneo['id'], $proximoDeSuEquipo, $torneo, $partidos, $jugadoresPorId);
    $suspension = $suspendidos[$id] ?? null;
}

// --- Multas: qué debe y qué ya pagó ---
$multas = [];
$debe = 0.0;
$cobraMultas = torneo_cobra_multas($torneo);
if ($cobraMultas) {
    foreach (sanciones_listar($torneo['id']) as $s) {
        if ((int) ($s['jugador_id'] ?? 0) !== $id) {
            continue;
        }
        $multas[] = $s;
        if (($s['estado'] ?? '') === 'pendiente') {
            $debe += (float) $s['monto'];
        }
    }
}

$titulo_pagina = '#' . $jugador['dorsal'] . ' ' . $jugador['nombre'] . ' — ' . $torneo['nombre'];
$pagina_activa = 'equipos';

vista_publica('publico/jugador', compact(
    'amarillas',
    'anotaciones',
    'autogoles',
    'basketball',
    'cobraMultas',
    'debe',
    'deporte',
    'equipo',
    'equiposPorId',
    'jugador',
    'multas',
    'pagina_activa',
    'proximoDeSuEquipo',
    'rojas',
    'suspension',
    'titulo_pagina',
    'torneo',
    'totalPuntos'
));
