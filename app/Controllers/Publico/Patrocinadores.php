<?php
declare(strict_types=1);


// La copa que resolvió el front controller a partir del slug de la URL.
$torneo = copa_actual();
$patrocinadores = patrocinadores_listar($torneo['id']);
usort($patrocinadores, fn($a, $b) => ($a['orden'] ?? 0) <=> ($b['orden'] ?? 0));

$patrocOficiales = array_values(array_filter($patrocinadores, fn($p) => $p['nivel'] === 'oficial'));
$patrocOro = array_values(array_filter($patrocinadores, fn($p) => $p['nivel'] === 'oro'));
$patrocPlata = array_values(array_filter($patrocinadores, fn($p) => $p['nivel'] === 'plata'));

$titulo_pagina = 'Patrocinadores — ' . $torneo['nombre'];
$pagina_activa = 'patrocinadores';

vista_publica('publico/patrocinadores', compact(
    'pagina_activa',
    'patrocOficiales',
    'patrocOro',
    'patrocPlata',
    'titulo_pagina',
    'torneo'
));
