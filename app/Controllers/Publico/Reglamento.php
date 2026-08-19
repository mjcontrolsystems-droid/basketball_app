<?php
declare(strict_types=1);

/**
 * Apartado público del reglamento del campeonato.
 *
 * Si la copa no tiene reglamento cargado se responde 404 en vez de mostrar una página
 * vacía: la sección solo existe cuando hay documento (el enlace del menú tampoco aparece).
 */

$torneo = copa_actual();

if (!torneo_tiene_reglamento($torneo)) {
    vista_404_copa(
        'Esta copa o liga todavía no publicó su reglamento',
        url_copa('index.php'),
        'Volver al inicio'
    );
}

$titulo_pagina = 'Reglamento — ' . $torneo['nombre'];
$pagina_activa = 'reglamento';

vista_publica('publico/reglamento', compact('pagina_activa', 'titulo_pagina', 'torneo'));
