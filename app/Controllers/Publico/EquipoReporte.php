<?php
declare(strict_types=1);

/**
 * Reporte imprimible de un equipo: su situación completa en una hoja.
 *
 * Es el documento que el organizador entrega (o el propio equipo descarga) con todo lo
 * que le interesa: en qué puesto va, qué le falta por jugar, cómo le ha ido, quién anota,
 * quién está amonestado y quién no podrá jugar la próxima fecha.
 */

$torneo = copa_actual();
$equipos = equipos_listar($torneo['id']);
$partidos = partidos_listar($torneo['id']);

$id = (int) ($_GET['id'] ?? 0);
$equipo = db_buscar_por_id($equipos, $id);
if (!$equipo) {
    vista_404_copa('Equipo no encontrado', url_copa('equipos.php'), 'Volver a equipos');
}

$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[$eq['id']] = $eq;
}

$deporte = $torneo['deporte'] ?? null;
$eventos = eventos_de_torneo($torneo['id']);
$jugadoresTodos = jugadores_listar($torneo['id']);
$jugadoresPorId = jugadores_por_id($jugadoresTodos);

// --- Posición en la tabla ---
$tabla = calcular_tabla($equipos, $partidos, $torneo, $eventos);
$filaEquipo = null;
$totalEquipos = count($tabla);
foreach ($tabla as $fila) {
    if ((int) $fila['equipo']['id'] === $id) {
        $filaEquipo = $fila;
        break;
    }
}

// --- Partidos del equipo, separados en jugados y por jugar ---
$partidosEquipo = array_values(array_filter(
    $partidos,
    fn($p) => (int) $p['equipo_local'] === $id || (int) $p['equipo_visitante'] === $id
));
usort($partidosEquipo, fn($a, $b) => strcmp($a['fecha'] . $a['hora'], $b['fecha'] . $b['hora']));

$proximos = array_values(array_filter($partidosEquipo, fn($p) => ($p['estado'] ?? '') !== 'jugado'));
$jugados = array_reverse(array_values(array_filter($partidosEquipo, fn($p) => ($p['estado'] ?? '') === 'jugado')));

// --- Plantilla ---
$plantilla = array_values(array_filter($jugadoresTodos, fn($j) => (int) $j['equipo_id'] === $id));
usort($plantilla, fn($a, $b) => strnatcmp((string) $a['dorsal'], (string) $b['dorsal']));

// --- Estadísticas por jugador: anotaciones, amarillas y rojas ---
$statsJugador = [];
foreach ($plantilla as $j) {
    $statsJugador[(int) $j['id']] = ['anotaciones' => 0, 'amarillas' => 0, 'rojas' => 0];
}
$basketball = es_basketball($deporte);
foreach ($eventos as $ev) {
    $jid = (int) ($ev['jugador_id'] ?? 0);
    if (!isset($statsJugador[$jid])) {
        continue;
    }
    $tipo = (string) ($ev['tipo'] ?? '');
    if ($tipo === 'gol') {
        // En basketball cada canasta vale 1/2/3; en fútbol el autogol no es mérito propio.
        if ($basketball) {
            $statsJugador[$jid]['anotaciones'] += TIPOS_PUNTO_VALOR[$ev['tipo_gol'] ?? ''] ?? 1;
        } elseif (($ev['tipo_gol'] ?? '') !== 'autogol') {
            $statsJugador[$jid]['anotaciones']++;
        }
    } elseif ($tipo === 'amarilla') {
        $statsJugador[$jid]['amarillas']++;
    } elseif ($tipo === 'roja') {
        $statsJugador[$jid]['rojas']++;
    }
}

// Totales del equipo, para el resumen de arriba
$totalAmarillas = array_sum(array_column($statsJugador, 'amarillas'));
$totalRojas = array_sum(array_column($statsJugador, 'rojas'));
$totalAnotaciones = array_sum(array_column($statsJugador, 'anotaciones'));

// --- Quién no podrá jugar el próximo encuentro ---
$proximoPartido = $proximos[0] ?? null;
$suspendidosProximo = [];
if ($proximoPartido !== null) {
    foreach (disciplina_suspendidos_para_partido($torneo['id'], $proximoPartido, $torneo, $partidos, $jugadoresPorId) as $jid => $info) {
        if (isset($statsJugador[$jid])) {
            $suspendidosProximo[$jid] = $info;
        }
    }
}

// --- Multas pendientes del equipo ---
$deudaPorJugador = torneo_cobra_multas($torneo) ? sanciones_deuda_por_jugador($torneo['id']) : [];
$deudaEquipo = [];
foreach ($deudaPorJugador as $jid => $info) {
    if (isset($statsJugador[$jid])) {
        $deudaEquipo[$jid] = $info;
    }
}

$titulo_pagina = 'Reporte de ' . $equipo['nombre'] . ' — ' . $torneo['nombre'];
$pagina_activa = 'equipos';

vista_publica('publico/equipo_reporte', compact(
    'basketball',
    'deporte',
    'deudaEquipo',
    'equipo',
    'equiposPorId',
    'filaEquipo',
    'jugados',
    'jugadoresPorId',
    'pagina_activa',
    'plantilla',
    'proximoPartido',
    'proximos',
    'statsJugador',
    'suspendidosProximo',
    'titulo_pagina',
    'torneo',
    'totalAmarillas',
    'totalAnotaciones',
    'totalEquipos',
    'totalRojas'
));
