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
            <h3 class="mb-1">Panel del Organizador</h3>
            <p class="text-muted small mb-0"><?= e($torneo['nombre']) ?> — <?= e($torneo['subtitulo']) ?></p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div>
        <?php endif; ?>
        <?php // Lo pinta SweetAlert2 desde app.js; el <noscript> es el respaldo. ?>
        <?php if ($flash): ?>
        <div id="datosFlash" class="d-none" data-tipo="<?= e($flash['tipo']) ?>" data-mensaje="<?= e($flash['mensaje']) ?>"></div>
        <noscript>
            <div class="alert alert-<?= $flash['tipo'] === 'error' ? 'danger' : $flash['tipo'] ?> rounded-3 py-2 small"><?= e($flash['mensaje']) ?></div>
        </noscript>
        <?php endif; ?>

        <form method="post" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Usuario</label>
                <input type="text" name="usuario" class="form-control form-control-lg" value="<?= e($_POST['usuario'] ?? '') ?>" autofocus required>
            </div>
            <div class="mb-2">
                <label class="form-label small fw-semibold">Contraseña</label>
                <input type="password" name="password" class="form-control form-control-lg" required>
            </div>
            <div class="text-end mb-4">
                <a href="<?= url('olvide_password.php') ?>" class="small text-muted text-decoration-none">¿Olvidaste tu contraseña?</a>
            </div>
            <button type="submit" class="btn btn-degradado btn-lg w-100 rounded-pill">Ingresar</button>
        </form>
        <?php if (GOOGLE_CLIENT_ID !== ''): ?>
        <div class="d-flex align-items-center gap-2 my-3">
            <hr class="flex-grow-1"><span class="small text-muted">o</span><hr class="flex-grow-1">
        </div>
        <a href="<?= url('google_iniciar.php') ?>" class="btn btn-outline-secondary btn-lg w-100 rounded-pill d-flex align-items-center justify-content-center gap-2">
            <i class="bi bi-google"></i>Continuar con Google
        </a>
        <?php endif; ?>
        <div class="text-center mt-4 d-flex flex-column gap-1">
            <span class="small text-muted">El acceso es por invitación del administrador.</span>
            <a href="<?= url('index.php') ?>" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Volver al sitio público</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
