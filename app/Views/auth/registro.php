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
    <div class="auth-card" style="max-width:460px;">
        <div class="text-center mb-4">
            <div class="mx-auto mb-3" style="width:64px;"><?= icono_multideporte(64) ?></div>
            <h3 class="mb-1">Acceso por invitación</h3>
            <p class="text-muted small mb-0">Esta plataforma no tiene registro abierto. Si el administrador ya autorizó tu correo, entra con tu cuenta de Google.</p>
        </div>

        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['tipo'] === 'error' ? 'danger' : $flash['tipo'] ?> rounded-3 py-2 small"><i class="bi bi-info-circle me-1"></i><?= e($flash['mensaje']) ?></div>
        <?php endif; ?>

        <?php if (GOOGLE_CLIENT_ID !== ''): ?>
        <a href="<?= url('google_iniciar.php') ?>" class="btn btn-outline-secondary btn-lg w-100 rounded-pill d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-google"></i>Continuar con Google
        </a>
        <?php else: ?>
        <p class="text-muted small text-center mb-0">El acceso todavía no está configurado. Contacta al administrador.</p>
        <?php endif; ?>

        <div class="text-center mt-4 d-flex flex-column gap-1">
            <a href="<?= url('login.php') ?>" class="small text-muted text-decoration-none">¿Ya tienes cuenta? Inicia sesión</a>
            <a href="<?= url('index.php') ?>" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Volver al sitio público</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
