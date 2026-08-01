<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/usuarios.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/upload.php';

auth_requerir();

$organizador = usuarios_obtener_por_id((int) $_SESSION['usuario_id']);
$errores = [];
// Las cuentas que entraron con "Continuar con Google" no tienen password_hash propio,
// así que no tiene sentido (ni es seguro) dejarles cambiar una contraseña que no usan.
$esCuentaGoogle = !empty($organizador['google_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (($_POST['accion'] ?? '') === 'datos') {
        try {
            $fotoSubida = manejar_subida_imagen('foto', 'organizador');
        } catch (RuntimeException $e) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', $e->getMessage());
        }
        $organizador['nombre'] = trim((string) $_POST['nombre']);
        $organizador['cargo'] = trim((string) $_POST['cargo']);
        $organizador['email'] = trim((string) $_POST['email']);
        $organizador['telefono'] = trim((string) $_POST['telefono']);
        $organizador['bio'] = trim((string) $_POST['bio']);
        if ($fotoSubida) {
            eliminar_imagen($organizador['foto'] ?? null);
            $organizador['foto'] = $fotoSubida;
        }

        if ($organizador['nombre'] === '' || $organizador['email'] === '') {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'Nombre y correo son obligatorios.');
        }

        $otroConEseCorreo = usuarios_obtener_por_email($organizador['email']);
        if ($otroConEseCorreo && $otroConEseCorreo['id'] !== $organizador['id']) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'Ese correo ya está en uso por otra cuenta.');
        }

        // El rol de super-admin se define por correo (SUPERADMIN_EMAILS), así que dejar
        // que cualquiera escriba aquí uno de esos correos equivaldría a dejarle otorgarse
        // el rol (y el control de la lista blanca y los cupos) a sí mismo. Solo se permite
        // si la cuenta YA es super-admin (está corrigiendo mayúsculas o similar).
        $emailAnterior = usuarios_obtener_por_id((int) $organizador['id'])['email'] ?? '';
        $nuevoEsSuperadmin = in_array(mb_strtolower($organizador['email']), SUPERADMIN_EMAILS, true);
        $yaEraSuperadmin = in_array(mb_strtolower($emailAnterior), SUPERADMIN_EMAILS, true);
        if ($nuevoEsSuperadmin && !$yaEraSuperadmin) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'Ese correo está reservado y no puede asignarse a esta cuenta.');
        }

        usuarios_guardar($organizador);
        redirigir_con_mensaje(url('admin/perfil.php'), 'success', 'Perfil actualizado correctamente.');
    }

    if (($_POST['accion'] ?? '') === 'password') {
        if ($esCuentaGoogle) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'Tu cuenta entra con Google y no usa contraseña.');
        }
        $actual = (string) $_POST['password_actual'];
        $nueva = (string) $_POST['password_nueva'];
        $confirmar = (string) $_POST['password_confirmar'];

        if (!password_verify($actual, $organizador['password_hash'])) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'La contraseña actual no es correcta.');
        } elseif (mb_strlen($nueva) < 8) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'La nueva contraseña debe tener al menos 8 caracteres.');
        } elseif ($nueva !== $confirmar) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'La confirmación no coincide con la nueva contraseña.');
        } else {
            $organizador['password_hash'] = password_hash($nueva, PASSWORD_DEFAULT);
            usuarios_guardar($organizador);
            redirigir_con_mensaje(url('admin/perfil.php'), 'success', 'Contraseña actualizada correctamente.');
        }
    }
}

$seccion_activa = 'perfil';
$titulo_pagina = 'Mi Perfil';
require __DIR__ . '/includes/admin_layout_top.php';
?>

<h3 class="mb-4">Mi Perfil</h3>

<div class="row g-4">
    <div class="col-lg-7">
        <form method="post" enctype="multipart/form-data" class="card-suave p-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="datos">
            <div class="d-flex align-items-center gap-3 mb-4">
                <?php if (!empty($organizador['foto'])): ?>
                    <img src="<?= e(url_imagen($organizador['foto'])) ?>" class="rounded-circle" width="90" height="90" style="object-fit:cover;">
                <?php else: ?>
                    <div class="avatar-organizador"><?= e(iniciales_de($organizador['nombre'])) ?></div>
                <?php endif; ?>
                <div>
                    <label class="form-label small fw-semibold mb-1">Foto de perfil</label>
                    <input type="file" name="foto" class="form-control form-control-sm" accept=".png,.jpg,.jpeg,.webp">
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Nombre completo</label>
                    <input type="text" name="nombre" class="form-control" value="<?= e($organizador['nombre']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Cargo</label>
                    <input type="text" name="cargo" class="form-control" value="<?= e($organizador['cargo']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Correo electrónico</label>
                    <input type="email" name="email" class="form-control" value="<?= e($organizador['email']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" value="<?= e($organizador['telefono'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label small fw-semibold">Biografía</label>
                    <textarea name="bio" class="form-control" rows="3"><?= e($organizador['bio'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-degradado rounded-pill px-4 mt-4">Guardar cambios</button>
        </form>
    </div>

    <?php if (!$esCuentaGoogle): ?>
    <div class="col-lg-5">
        <form method="post" class="card-suave p-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="password">
            <h5 class="mb-3"><i class="bi bi-shield-lock me-2"></i>Cambiar contraseña</h5>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Contraseña actual</label>
                <input type="password" name="password_actual" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Nueva contraseña</label>
                <input type="password" name="password_nueva" class="form-control" minlength="8" required>
            </div>
            <div class="mb-3">
                <label class="form-label small fw-semibold">Confirmar nueva contraseña</label>
                <input type="password" name="password_confirmar" class="form-control" minlength="8" required>
            </div>
            <button type="submit" class="btn btn-outline-secondary rounded-pill px-4">Actualizar contraseña</button>
        </form>
    </div>
    <?php else: ?>
    <div class="col-lg-5">
        <div class="card-suave p-4 text-muted small">
            <i class="bi bi-google me-2"></i>Iniciaste sesión con Google, así que tu cuenta no usa contraseña.
        </div>
    </div>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/admin_layout_bottom.php'; ?>
