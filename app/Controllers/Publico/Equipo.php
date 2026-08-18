<?php
declare(strict_types=1);


// La copa que resolvió el front controller a partir del slug de la URL.
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

$tabla = calcular_tabla($equipos, $partidos, $torneo);
$filaEquipo = null;
foreach ($tabla as $fila) {
    if ($fila['equipo']['id'] === $id) {
        $filaEquipo = $fila;
        break;
    }
}

$partidosEquipo = array_values(array_filter($partidos, fn($p) => (int) $p['equipo_local'] === $id || (int) $p['equipo_visitante'] === $id));
usort($partidosEquipo, fn($a, $b) => strcmp($a['fecha'] . $a['hora'], $b['fecha'] . $b['hora']));

$jugadoresTodos = jugadores_listar($torneo['id']);
$jugadoresEquipo = array_values(array_filter($jugadoresTodos, fn($j) => (int) $j['equipo_id'] === $id && !empty($j['activo'])));
usort($jugadoresEquipo, fn($a, $b) => $a['dorsal'] <=> $b['dorsal']);

$titulo_pagina = $equipo['nombre'] . ' — ' . $torneo['nombre'];
$pagina_activa = 'equipos';

vista_publica('publico/equipo', compact(
    'equipo',
    'equiposPorId',
    'filaEquipo',
    'jugadoresEquipo',
    'pagina_activa',
    'partidosEquipo',
    'titulo_pagina',
    'torneo'
));
