<?php
declare(strict_types=1);

if (auth_check()) {
    header('Location: ' . url('admin/index.php'));
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ip = obtener_ip_cliente();

    if (auth_ip_bloqueada($ip)) {
        $error = 'Demasiados intentos. Espera un minuto antes de volver a intentar.';
    } else {
        auth_registrar_intento($ip);

        $usuario = trim((string) ($_POST['usuario'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($usuario === '' || $password === '') {
            $error = 'Ingresa tu usuario y contraseña.';
        } elseif (auth_intentar_login($usuario, $password)) {
            header('Location: ' . url('admin/index.php'));
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}

// El login es global (un solo admin para todas las copas); se usa la copa predeterminada
// solo para mostrar algo de marca en la pantalla, no porque el login "pertenezca" a ella.
$torneo = torneos_obtener_predeterminado() ?? ['nombre' => 'Panel Organizador', 'subtitulo' => ''];
$titulo_pagina = 'Acceso Organizador — ' . $torneo['nombre'];
$flash = obtener_flash();

vista('auth/login', compact(
    'error',
    'flash',
    'titulo_pagina',
    'torneo'
));
