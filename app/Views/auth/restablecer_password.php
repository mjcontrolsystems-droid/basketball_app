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
            <h3 class="mb-1">Crear contraseña nueva</h3>
        </div>

        <?php if ($listo): ?>
        <div class="alert alert-success rounded-3 small">
            <i class="bi bi-check-circle me-1"></i>¡Listo! Tu contraseña fue actualizada.
        </div>
        <div class="text-center">
            <a href="<?= url('login.php') ?>" class="btn btn-degradado rounded-pill px-4">Iniciar sesión</a>
        </div>

        <?php elseif ($cuenta === null): ?>
        <div class="alert alert-danger rounded-3 small">
            <i class="bi bi-exclamation-triangle me-1"></i>Este enlace ya no es válido: venció (dura 1 hora) o ya fue usado. Pide uno nuevo.
        </div>
        <div class="text-center">
            <a href="<?= url('olvide_password.php') ?>" class="btn btn-degradado rounded-pill px-4">Pedir enlace nuevo</a>
        </div>

        <?php else: ?>
        <p class="text-muted small text-center mb-4">Cuenta: <strong><?= e($cuenta['email']) ?></strong></p>

        <?php if ($error): ?>
        <div class="alert alert-danger rounded-3 py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= e($error) ?></div>
        <?php endif; ?>

        <form method="post" novalidate>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="mb-3">
                <label class="form-label small fw-semibold">Contraseña nueva</label>
                <input type="password" name="password_nueva" class="form-control form-control-lg" minlength="8" autofocus required>
                <div class="form-text">Mínimo 8 caracteres.</div>
            </div>
            <div class="mb-4">
                <label class="form-label small fw-semibold">Confirmar contraseña</label>
                <input type="password" name="password_confirmar" class="form-control form-control-lg" minlength="8" required>
            </div>
            <button type="submit" class="btn btn-degradado btn-lg w-100 rounded-pill">Guardar contraseña</button>
        </form>
        <?php endif; ?>

        <div class="text-center mt-4">
            <a href="<?= url('login.php') ?>" class="small text-muted text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Volver al inicio de sesión</a>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
