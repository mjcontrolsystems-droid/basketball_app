<?php
declare(strict_types=1);
// Incluido desde index.php cuando se accede a la raíz del sitio sin ninguna copa
// (config.php, db.php y helpers.php ya están cargados por index.php).
//
// El registro público está cerrado (acceso por invitación, ver registro.php), así que
// esta página ya no es una landing para "crear tu cuenta": es una puerta de entrada para
// que un visitante con un código, QR o enlace llegue directo a la copa o liga que busca.

$torneo = null;

$titulo_pagina = 'Encuentra tu copa o liga';
$pagina_activa = 'inicio';

vista_publica('publico/portada', compact(
    'pagina_activa',
    'titulo_pagina'
));
