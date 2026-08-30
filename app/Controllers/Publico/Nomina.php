<?php
declare(strict_types=1);

/**
 * Nómina imprimible del equipo para entregar al árbitro.
 *
 * Es el papel de toda liga real: el capitán marca con lapicero quiénes van de titulares,
 * firma, y se lo entrega a la mesa antes del pitazo. La app imprime la lista con las
 * casillas VACÍAS a propósito — la decisión de quién juega se toma en la cancha viendo
 * quién llegó, no en la pantalla.
 *
 * Es pública como la hoja de solvencia: así cada capitán la imprime solo, sin pedírsela
 * al organizador. De paso, la hoja le avisa al árbitro quién está suspendido o debe
 * multa, que es justo lo que la mesa necesita verificar.
 */

$torneo = copa_actual();
$equipos = equipos_listar($torneo['id']);

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
$jugadoresTodos = jugadores_listar($torneo['id']);
$jugadoresPorId = jugadores_por_id($jugadoresTodos);

// Solo los activos: un jugador dado de baja no debe ni aparecer en el papel.
$plantilla = array_values(array_filter(
    $jugadoresTodos,
    fn($j) => (int) $j['equipo_id'] === $id && !empty($j['activo'])
));
usort($plantilla, fn($a, $b) => (int) $a['dorsal'] <=> (int) $b['dorsal']);

// --- El partido de la hoja: el pedido por URL, o el próximo del equipo ---
$partidos = partidos_listar($torneo['id']);
$partidoIdPedido = (int) ($_GET['partido'] ?? 0);
$partidoHoja = null;
$proximosDelEquipo = [];
foreach ($partidos as $p) {
    $esDelEquipo = (int) $p['equipo_local'] === $id || (int) $p['equipo_visitante'] === $id;
    if (!$esDelEquipo) {
        continue;
    }
    if ((int) $p['id'] === $partidoIdPedido) {
        $partidoHoja = $p;
    }
    if (($p['estado'] ?? '') !== 'jugado') {
        $proximosDelEquipo[] = $p;
    }
}
usort($proximosDelEquipo, fn($a, $b) => strcmp((string) $a['fecha'] . $a['hora'], (string) $b['fecha'] . $b['hora']));
if ($partidoHoja === null) {
    $partidoHoja = $proximosDelEquipo[0] ?? null;
}

// --- Avisos que el árbitro debe ver junto a cada nombre ---
$suspendidos = [];
if ($partidoHoja !== null && torneo_aplica_suspensiones($torneo)) {
    foreach (disciplina_suspendidos_para_partido($torneo['id'], $partidoHoja, $torneo, $partidos, $jugadoresPorId) as $jid => $info) {
        $suspendidos[$jid] = $info;
    }
}
$deudores = [];
if (torneo_cobra_multas($torneo) && torneo_bloquea_morosos($torneo)) {
    $deudores = sanciones_deuda_por_jugador($torneo['id']);
}

$rival = null;
$condicion = '';
if ($partidoHoja !== null) {
    $esLocal = (int) $partidoHoja['equipo_local'] === $id;
    $rival = $equiposPorId[$esLocal ? (int) $partidoHoja['equipo_visitante'] : (int) $partidoHoja['equipo_local']] ?? null;
    $condicion = $esLocal ? 'Local' : 'Visitante';
}

$jugadoresEnCancha = torneo_jugadores_en_cancha($torneo);

$titulo_pagina = 'Nómina de ' . $equipo['nombre'] . ' — ' . $torneo['nombre'];
$pagina_activa = 'equipos';

vista_publica('publico/nomina', compact(
    'condicion',
    'deporte',
    'deudores',
    'equipo',
    'jugadoresEnCancha',
    'pagina_activa',
    'partidoHoja',
    'plantilla',
    'proximosDelEquipo',
    'rival',
    'suspendidos',
    'titulo_pagina',
    'torneo'
));
