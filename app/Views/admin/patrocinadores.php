<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="<?= url('admin/patrocinadores.php') ?>" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-arrow-left"></i></a>
        <h3 class="mb-0"><?= $itemEditar ? 'Editar patrocinador' : 'Nuevo patrocinador' ?></h3>
    </div>

    <form method="post" enctype="multipart/form-data" class="card-suave p-4" style="max-width:680px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= $itemEditar['id'] ?? 0 ?>">

        <div class="row g-3">
            <div class="col-md-8">
                <label class="form-label small fw-semibold">Nombre</label>
                <input type="text" name="nombre" class="form-control" value="<?= e($itemEditar['nombre'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Orden</label>
                <input type="number" name="orden" class="form-control" value="<?= e((string) ($itemEditar['orden'] ?? 1)) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Nivel de patrocinio</label>
                <select name="nivel" class="form-select">
                    <option value="oficial" <?= ($itemEditar['nivel'] ?? '') === 'oficial' ? 'selected' : '' ?>>Oficial</option>
                    <option value="oro" <?= ($itemEditar['nivel'] ?? 'oro') === 'oro' ? 'selected' : '' ?>>Oro</option>
                    <option value="plata" <?= ($itemEditar['nivel'] ?? '') === 'plata' ? 'selected' : '' ?>>Plata</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Sitio web (opcional)</label>
                <input type="url" name="url" class="form-control" value="<?= e($itemEditar['url'] ?? '') ?>" placeholder="https://">
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Logo (opcional)</label>
                <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp" data-vista-previa="previewLogoPatrocinador">
                <div class="form-text">Si no subes uno, se mostrará el nombre como insignia de texto.</div>
                <div class="vista-previa-subida mt-2" id="previewLogoPatrocinador">
                    <?php if (!empty($itemEditar['logo'])): ?>
                    <figure class="vista-previa-item mb-0">
                        <img src="<?= e(url_imagen((string) $itemEditar['logo'])) ?>" alt="Logo actual">
                        <?= boton_quitar_archivo('quitar_logo', 'el logo', 'Se mostrará el nombre como insignia de texto. Se aplica al guardar.') ?>
                        <figcaption>Logo actual</figcaption>
                    </figure>
                    <?= enlace_deshacer_quitar() ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-degradado rounded-pill px-4">Guardar patrocinador</button>
            <a href="<?= url('admin/patrocinadores.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
        </div>
    </form>

<?php else: ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Patrocinadores (<?= count($patrocinadores) ?>)</h3>
        <a href="<?= url('admin/patrocinadores.php?accion=nuevo') ?>" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Nuevo patrocinador</a>
    </div>

    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
        <?php foreach ($patrocinadores as $p): ?>
        <div class="col">
            <div class="card-suave p-3 d-flex flex-row align-items-center gap-3">
                <div style="width:64px;height:64px;flex-shrink:0;" class="d-flex align-items-center justify-content-center border rounded-3">
                    <?= badge_patrocinador($p) ?>
                </div>
                <div class="flex-grow-1">
                    <div class="fw-semibold"><?= e($p['nombre']) ?></div>
                    <span class="tier-pill <?= e($p['nivel']) ?>"><?= e(nivel_patrocinio_label($p['nivel'])) ?></span>
                </div>
                <div class="d-flex flex-column gap-1">
                    <a href="<?= url('admin/patrocinadores.php?accion=editar&id=' . $p['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <form method="post" data-confirm="¿Eliminar a <?= e($p['nombre']) ?>?">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>
