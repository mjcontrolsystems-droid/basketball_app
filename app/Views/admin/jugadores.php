<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= url('admin/equipos.php') ?>" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h3 class="mb-0"><?= e($etJugadores) ?></h3>
        <div class="small text-muted"><?= e($equipo['nombre']) ?></div>
    </div>
</div>

<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <form method="post" class="card-suave p-4" style="max-width:480px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="equipo_id" value="<?= $equipoId ?>">
        <input type="hidden" name="id" value="<?= $jugadorEditar['id'] ?? 0 ?>">

        <div class="row g-3">
            <div class="col-4">
                <label class="form-label small fw-semibold">Dorsal</label>
                <input type="text" name="dorsal" class="form-control" value="<?= e($jugadorEditar['dorsal'] ?? '') ?>" required maxlength="4">
            </div>
            <div class="col-8">
                <label class="form-label small fw-semibold">Nombre</label>
                <input type="text" name="nombre" class="form-control" value="<?= e($jugadorEditar['nombre'] ?? '') ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Posición</label>
                <select name="posicion" class="form-select">
                    <option value="">Sin definir</option>
                    <?php foreach (posiciones_catalogo($torneo['deporte'] ?? null) as $clave => $pos): ?>
                    <option value="<?= e($clave) ?>" <?= ($jugadorEditar['posicion'] ?? '') === $clave ? 'selected' : '' ?>><?= e($pos['label']) ?> (<?= e($pos['corta']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Se propone automáticamente al armar la alineación de cada encuentro; ahí puedes cambiarla partido por partido.</div>
            </div>
            <div class="col-12">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="checkActivo" name="activo" <?= ($jugadorEditar === null || !empty($jugadorEditar['activo'])) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="checkActivo"><?= e($etActivo) ?> (aparece disponible al cargar eventos de partido)</label>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-degradado rounded-pill px-4">Guardar <?= e(mb_strtolower($etJugador)) ?></button>
            <a href="<?= $urlLista ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
        </div>
    </form>

<?php else: ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h5 class="mb-0">Plantilla (<?= count($jugadores) ?>)</h5>
        <a href="<?= $urlLista ?>&accion=nuevo" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i><?= e(forma_genero($torneo['genero'] ?? null, 'Nuevo', 'Nueva')) ?> <?= e(mb_strtolower($etJugador)) ?></a>
    </div>

    <?php if (empty($jugadores)): ?>
        <p class="text-muted">Todavía no hay <?= e(mb_strtolower($etJugadores)) ?> <?= e(forma_genero($torneo['genero'] ?? null, 'cargados', 'cargadas')) ?> para este equipo.</p>
    <?php else: ?>
    <div class="table-responsive card-suave p-0">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th style="width:80px;">Dorsal</th>
                    <th>Nombre</th>
                    <th style="width:150px;">Posición</th>
                    <th style="width:100px;">Estado</th>
                    <th style="width:110px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php $ordenados = $jugadores; usort($ordenados, fn($a, $b) => $a['dorsal'] <=> $b['dorsal']); ?>
                <?php foreach ($ordenados as $j): ?>
                <tr>
                    <td class="fw-bold">#<?= e($j['dorsal']) ?></td>
                    <td><?= e($j['nombre']) ?></td>
                    <td class="small text-muted"><?= e(posicion_label($torneo['deporte'] ?? null, $j['posicion'] ?? null)) ?></td>
                    <td><?= $j['activo'] ? '<span class="badge rounded-pill text-bg-success-subtle text-success-emphasis small">' . e($etActivo) . '</span>' : '<span class="badge rounded-pill text-bg-secondary small">' . e($etInactivo) . '</span>' ?></td>
                    <td class="text-end">
                        <a href="<?= $urlLista ?>&accion=editar&id=<?= $j['id'] ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline" data-confirm="¿Eliminar a <?= e($j['nombre']) ?>?">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="equipo_id" value="<?= $equipoId ?>">
                            <input type="hidden" name="id" value="<?= $j['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

<?php endif; ?>
