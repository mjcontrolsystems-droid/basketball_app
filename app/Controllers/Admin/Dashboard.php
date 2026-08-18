<?php
declare(strict_types=1);

auth_requerir();
$torneo = admin_requerir_torneo_activo();

$seccion_activa = 'dashboard';
$titulo_pagina = 'Dashboard';

$equipos = equipos_listar($torneo['id']);
$partidos = partidos_listar($torneo['id']);
$patrocinadores = patrocinadores_listar($torneo['id']);
$tabla = calcular_tabla($equipos, $partidos, $torneo);
$lider = $tabla[0] ?? null;

$jugados = array_filter($partidos, fn($p) => $p['estado'] === 'jugado');
$programados = array_filter($partidos, fn($p) => $p['estado'] === 'programado');
$proximo = proximos_partidos($partidos, 1)[0] ?? null;
$equiposPorId = [];
foreach ($equipos as $eq) { $equiposPorId[$eq['id']] = $eq; }

vista_admin('admin/dashboard', compact(
    'equipos',
    'equiposPorId',
    'jugados',
    'patrocinadores',
    'programados',
    'proximo',
    'seccion_activa',
    'tabla',
    'titulo_pagina',
    'torneo'
));
