<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/usuarios.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/correo.php';

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
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo_pagina) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= asset_url('assets/css/style.css') ?>" rel="stylesheet">
    <link rel="icon" href="<?= url('assets/img/logo.png') ?>" type="image/png">
</head>
<body>
<div class="auth-wrap">
    <div class="auth-card">
        <div class="text-center mb-4">
            <div class="mx-auto mb-3" style="width:64px;"><?= icono_multideporte(64) ?></div>
            <h3 class="mb-1">Recuperar contraseña</h3>
            <p class="text-muted small mb-0">Te enviaremos un enlace para crear una contraseña nueva.</p>
        </div>

        <?php if ($enviado): ?>
        <div class="alert alert-success rounded-3 small">
            <i class="bi bi-envelope-check me-1"></i>Si ese correo tiene una cuenta con contraseña, en unos minutos recibirá un enlace para restablecerla. Revisa también la carpeta de spam.
        </div>
        <div class="text-center">
            <a href="<?= url('login.php') ?>" class="btn btn-degradado rounded-pill px-4">Volver al inicio de sesión</a>
        </div>
        <?php else: ?>

        <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <div class="mb-4">
                <label class="form-label small fw-semibold">Correo de tu cuenta</label>
                <input type="email" name="email" class="form-control form-control-lg" value="<?= e($_POST['email'] ?? '') ?>" autofocus required>
            </div>
            <button type="submit" class="btn btn-degradado btn-lg w-100 rounded-pill">Enviarme el enlace</button>
        </form>
        <p class="small text-muted mt-3 mb-0 text-center">¿Entras con Google? No necesitas contraseña: usa "Continuar con Google" en el <a href="<?= url('login.php') ?>">inicio de sesión</a>.</p>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="<?= url('login.php') ?>" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
