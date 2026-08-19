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
                    <input type="file" name="foto" class="form-control form-control-sm" accept=".png,.jpg,.jpeg,.webp" data-vista-previa="previewFotoPerfil">
                    <div class="vista-previa-subida mt-2" id="previewFotoPerfil"></div>
                    <?php // Quitar la foto subida, no solo reemplazarla. ?>
                    <?php if (!empty($organizador['foto'])): ?>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" name="quitar_foto" value="1" id="chkQuitarFoto">
                        <label class="form-check-label small" for="chkQuitarFoto">
                            Quitar la foto
                            <span class="d-block text-muted">Se mostrará el avatar con tus iniciales.</span>
                        </label>
                    </div>
                    <?php endif; ?>
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
                <?php // Género de la persona (no el de la copa): define si el sitio público
                      // dice "El Organizador" o "La Organizadora". ?>
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">¿Cómo prefieres que te nombremos?</label>
                    <select name="genero" class="form-select">
                        <option value="" <?= ($organizador['genero'] ?? '') === '' ? 'selected' : '' ?>>Prefiero no indicarlo</option>
                        <option value="masculino" <?= ($organizador['genero'] ?? '') === 'masculino' ? 'selected' : '' ?>>Organizador (masculino)</option>
                        <option value="femenino" <?= ($organizador['genero'] ?? '') === 'femenino' ? 'selected' : '' ?>>Organizadora (femenino)</option>
                    </select>
                    <div class="form-text">Así aparecerás en la página pública "Organizador" de tus copas.</div>
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
