<?php
declare(strict_types=1);

/**
 * Hoja de solvencia: qué jugadores NO pueden jugar por tener multas pendientes.
 *
 * Es la pieza que hace útil todo el control de sanciones en una liga real: el organizador
 * la abre en el celular al llegar a la cancha o la imprime y se la da al árbitro, y decide
 * quién entra SIN depender de haber capturado la alineación en la app. Funciona aunque los
 * datos del partido se registren días después.
 *
 * Por jornada (?jornada=N) o de toda la copa. Es pública a propósito: así el capitán de
 * cada equipo puede consultarla antes del partido y llegar pagando.
 */

$torneo = copa_actual();

$equipos = equipos_listar($torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[$eq['id']] = $eq;
}
$jugadores = jugadores_listar($torneo['id']);
$jugadoresPorId = jugadores_por_id($jugadores);

$partidos = partidos_listar($torneo['id']);
$jornadas = partidos_por_jornada($partidos);

// Deuda vigente de cada jugador (solo sanciones pendientes).
$deudaPorJugador = sanciones_deuda_por_jugador($torneo['id']);

// Jugadores en deuda agrupados por equipo, ya listos para pintar.
$morososPorEquipo = [];
foreach ($deudaPorJugador as $jugadorId => $info) {
    $jug = $jugadoresPorId[$jugadorId] ?? null;
    if ($jug === null) {
        continue;
    }
    $morososPorEquipo[(int) $jug['equipo_id']][] = [
        'jugador' => $jug,
        'total' => $info['total'],
        'cantidad' => $info['cantidad'],
    ];
}

// Jornada a mostrar: la elegida, o la próxima con partidos programados.
$jornadaElegida = isset($_GET['jornada']) ? (int) $_GET['jornada'] : 0;
if ($jornadaElegida === 0 || !isset($jornadas[$jornadaElegida])) {
    $jornadaElegida = null;
    foreach ($jornadas as $num => $lista) {
        if (count(array_filter($lista, fn($p) => ($p['estado'] ?? '') === 'programado')) > 0) {
            $jornadaElegida = $num;
            break;
        }
    }
    if ($jornadaElegida === null && !empty($jornadas)) {
        $jornadaElegida = max(array_keys($jornadas));
    }
}

// Encuentros de esa jornada, cada uno con los morosos de ambos equipos: es lo que se
// revisa antes del pitazo.
$encuentros = [];
foreach ($jornadas[$jornadaElegida] ?? [] as $p) {
    $local = (int) $p['equipo_local'];
    $visitante = (int) $p['equipo_visitante'];
    $encuentros[] = [
        'partido' => $p,
        'local' => $equiposPorId[$local] ?? null,
        'visitante' => $equiposPorId[$visitante] ?? null,
        'morosos_local' => $morososPorEquipo[$local] ?? [],
        'morosos_visitante' => $morososPorEquipo[$visitante] ?? [],
    ];
}

$totalMorosos = count($deudaPorJugador);
$cobraMultas = torneo_cobra_multas($torneo);
$bloquea = torneo_bloquea_morosos($torneo);

$titulo_pagina = 'Solvencia de jugadores — ' . $torneo['nombre'];
$pagina_activa = 'calendario';

vista_publica('publico/solvencia', compact(
    'bloquea',
    'cobraMultas',
    'encuentros',
    'equiposPorId',
    'jornadaElegida',
    'jornadas',
    'morososPorEquipo',
    'pagina_activa',
    'titulo_pagina',
    'torneo',
    'totalMorosos'
));
