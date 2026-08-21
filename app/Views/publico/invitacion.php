<?php
/**
 * Página para aceptar una invitación a colaborar.
 *
 * Va sin el menú de la copa a propósito: quien llega no es un visitante buscando la tabla
 * de posiciones, es alguien que tiene que decidir una sola cosa. Todo lo demás distrae.
 */
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo_pagina) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= asset_url('assets/css/style.css') ?>" rel="stylesheet">
    <link rel="icon" href="<?= url('assets/img/logo.png') ?>" type="image/png">
</head>
<body style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:1.5rem;background:#f6f4fb;">

<div class="card-suave p-4 p-md-5 text-center" style="max-width:520px;width:100%;">

<?php if ($estado === 'invalida'): ?>

    <div style="font-size:2.5rem;">🔗</div>
    <h4 class="mt-3 mb-2">Esta invitación ya no sirve</h4>
    <p class="text-muted mb-4">
        El enlace venció, ya se usó, o la invitación fue retirada. Si crees que es un error,
        pídele a quien organiza la copa que te la mande otra vez.
    </p>
    <a href="<?= url('/') ?>" class="btn btn-outline-secondary rounded-pill px-4">Ir al inicio</a>

<?php elseif ($estado === 'otro_correo'): ?>

    <div style="font-size:2.5rem;">⚠️</div>
    <h4 class="mt-3 mb-2">Entraste con otra cuenta</h4>
    <p class="text-muted mb-1">La invitación es para:</p>
    <p class="fw-semibold mb-3"><?= e($emailInvitado) ?></p>
    <p class="text-muted mb-4">
        Pero tu sesión está abierta con <strong><?= e($emailSesion) ?></strong>.
        Cierra sesión y vuelve a entrar con el correo al que llegó la invitación.
    </p>
    <a href="<?= url('logout.php') ?>" class="btn btn-degradado rounded-pill px-4">Cerrar sesión</a>

<?php else: ?>

    <?php // Estado normal: la invitación es válida y falta entrar con Google. ?>
    <?php if (!empty($copa['logo'])): ?>
        <img src="<?= e(url_imagen($copa['logo'])) ?>" alt="" width="72" height="72" class="rounded-circle mb-3" style="object-fit:cover;">
    <?php else: ?>
        <div style="font-size:2.5rem;">🏆</div>
    <?php endif; ?>

    <p class="text-muted mb-1 mt-3">Te invitaron a ayudar en</p>
    <h4 class="mb-3"><?= e($copa['nombre']) ?></h4>

    <div class="alert alert-light border text-start mb-4">
        <div class="fw-semibold mb-1">
            <i class="bi bi-person-badge me-1"></i>Tu rol: <?= e(colaborador_nivel_nombre($nivel)) ?>
        </div>
        <div class="small text-muted"><?= e(COLABORADOR_NIVELES[$nivel]['resumen'] ?? '') ?></div>
    </div>

    <p class="text-muted small mb-4">
        Entra con tu cuenta de Google usando <strong><?= e($emailInvitado) ?></strong>.
        No necesitas crear ninguna contraseña.
    </p>

    <a href="<?= url('google_iniciar.php') ?>" class="btn btn-degradado rounded-pill px-4 w-100">
        <i class="bi bi-google me-2"></i>Entrar con Google y aceptar
    </a>

    <p class="text-muted mt-4 mb-0" style="font-size:.8rem;">
        Si no esperabas esta invitación, puedes cerrar esta página: no se crea ningún acceso hasta que aceptes.
    </p>

<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
