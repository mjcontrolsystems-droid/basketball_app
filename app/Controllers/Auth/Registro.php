<?php
declare(strict_types=1);

// El alta pública (usuario/contraseña) está cerrada: esta página ya no crea cuentas
// directamente. Solo explica que el acceso es por invitación y ofrece el botón de
// Google, que a su vez valida la lista blanca en google_callback.php.
if (auth_check()) {
    header('Location: ' . url('admin/index.php'));
    exit;
}

$flash = obtener_flash();
$titulo_pagina = 'Acceso por invitación';

vista('auth/registro', compact(
    'flash',
    'titulo_pagina'
));
