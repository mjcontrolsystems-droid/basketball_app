<?php
declare(strict_types=1);

if (auth_check()) {
    header('Location: ' . url('admin/index.php'));
    exit;
}

$enviado = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Reutiliza el límite de intentos de registro (5 por 5 minutos por IP): esta página
    // también toca correos y no debe servir para bombardear buzones ni adivinar cuentas.
    $ip = obtener_ip_cliente();
    if (registro_ip_bloqueada($ip)) {
        $error = 'Demasiados intentos. Espera unos minutos antes de volver a intentar.';
    } elseif (!correo_configurado()) {
        $error = 'El envío de correos no está configurado. Contacta al administrador para restablecer tu contraseña.';
    } else {
        registro_registrar_intento($ip);
        $email = trim((string) ($_POST['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Ingresa un correo válido.';
        } else {
            // Si el correo existe y tiene contraseña, se envía el enlace; si no, no se
            // envía nada — pero la pantalla dice lo mismo en ambos casos, para no
            // revelar qué correos tienen cuenta (anti-enumeración).
            $token = password_reset_crear($email);
            if ($token !== null) {
                correo_recuperar_password($email, SITE_ORIGIN . url('restablecer_password.php?token=' . $token));
            }
            $enviado = true;
        }
    }
}

$titulo_pagina = 'Recuperar contraseña';

vista('auth/olvide_password', compact(
    'enviado',
    'error',
    'titulo_pagina'
));
