<?php
declare(strict_types=1);

if (auth_check()) {
    header('Location: ' . url('admin/index.php'));
    exit;
}

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$cuenta = $token !== '' ? password_reset_validar($token) : null;
$error = '';
$listo = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cuenta !== null) {
    $nueva = (string) ($_POST['password_nueva'] ?? '');
    $confirmar = (string) ($_POST['password_confirmar'] ?? '');

    if (mb_strlen($nueva) < 8) {
        $error = 'La nueva contraseña debe tener al menos 8 caracteres.';
    } elseif ($nueva !== $confirmar) {
        $error = 'La confirmación no coincide con la nueva contraseña.';
    } elseif (password_reset_consumir($token, $nueva)) {
        $listo = true;
    } else {
        $cuenta = null; // el token venció entre cargar la página y enviar el formulario
    }
}

$titulo_pagina = 'Crear contraseña nueva';

vista('auth/restablecer_password', compact(
    'cuenta',
    'error',
    'listo',
    'titulo_pagina',
    'token'
));
