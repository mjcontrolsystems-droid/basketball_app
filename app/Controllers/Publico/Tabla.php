<?php
declare(strict_types=1);


// La copa que resolvió el front controller a partir del slug de la URL.
$torneo = copa_actual();
$equipos = equipos_listar($torneo['id']);
$partidos = partidos_listar($torneo['id']);
$deporte = $torneo['deporte'] ?? null;
$eventos = eventos_de_torneo($torneo['id']);
$tabla = calcular_tabla($equipos, $partidos, $torneo, $eventos);

// En una liga el título se decide en esta misma tabla: no hay "zona de playoffs" que
// resaltar ni cuadro final al que avanzar.
$esLiga = torneo_es_liga($torneo);

// En el formato de grupos no hay una tabla única: cada grupo tiene la suya y lo que
// importa es quién clasifica de cada uno.
$tieneGrupos = torneo_tiene_grupos($torneo);
$tablasGrupo = $tieneGrupos ? grupos_tablas($equipos, $partidos, $torneo, $eventos) : [];

$explicacionPuntos = $torneo['permite_empates']
    ? "PTS = {$torneo['puntos_victoria']} por victoria + {$torneo['puntos_empate']} por empate + {$torneo['puntos_derrota']} por derrota"
    : "PTS = {$torneo['puntos_victoria']} por victoria + {$torneo['puntos_derrota']} por derrota jugada";

$titulo_pagina = 'Tabla de Posiciones — ' . $torneo['nombre'];
$pagina_activa = 'tabla';

vista_publica('publico/tabla', compact(
    'deporte',
    'esLiga',
    'explicacionPuntos',
    'pagina_activa',
    'tabla',
    'tablasGrupo',
    'tieneGrupos',
    'titulo_pagina',
    'torneo'
));
