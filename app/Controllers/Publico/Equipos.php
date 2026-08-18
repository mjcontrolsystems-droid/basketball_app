<?php
declare(strict_types=1);


// La copa que resolvió el front controller a partir del slug de la URL.
$torneo = copa_actual();
$equipos = equipos_listar($torneo['id']);
$partidos = partidos_listar($torneo['id']);
$tabla = calcular_tabla($equipos, $partidos, $torneo);
$posicionPorEquipo = [];
foreach ($tabla as $fila) {
    $posicionPorEquipo[$fila['equipo']['id']] = $fila['posicion'];
}

$titulo_pagina = 'Equipos — ' . $torneo['nombre'];
$pagina_activa = 'equipos';

vista_publica('publico/equipos', compact(
    'equipos',
    'pagina_activa',
    'posicionPorEquipo',
    'titulo_pagina',
    'torneo'
));
