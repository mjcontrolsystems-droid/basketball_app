<?php
declare(strict_types=1);

// Imagen cuadrada (1080x1080) del podio de cierre, para WhatsApp o Instagram. Igual que
// la del resultado de un partido, se dibuja con <canvas> en el navegador y no en el
// servidor: así usa la tipografía del sitio sin depender de fuentes instaladas.

// La copa que resolvió el front controller a partir del slug de la URL.
$torneo = copa_actual();
$equipos = equipos_listar($torneo['id']);
$partidos = partidos_listar($torneo['id']);
$eventos = eventos_de_torneo($torneo['id']);

// Solo se puede compartir un podio ya publicado: si el organizador todavía no lo sacó,
// esta página tampoco lo adelanta.
$podio = torneo_podio_publicado($torneo)
    ? podio_calcular($torneo, $equipos, $partidos, $eventos)
    : null;

if ($podio === null) {
    vista_404_copa('Todavía no hay podio publicado', url_copa('index.php'), 'Volver al inicio');
}

$titulo_pagina = 'Podio — ' . $torneo['nombre'];
$pagina_activa = 'inicio';

vista_publica('publico/podio_imagen', compact(
    'pagina_activa',
    'podio',
    'titulo_pagina',
    'torneo'
));
