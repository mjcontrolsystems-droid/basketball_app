<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="<?= url('admin/equipos.php') ?>" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-arrow-left"></i></a>
        <h3 class="mb-0"><?= $equipoEditar ? 'Editar equipo' : 'Nuevo equipo' ?></h3>
    </div>

    <form method="post" enctype="multipart/form-data" class="card-suave p-4" style="max-width:760px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= $equipoEditar['id'] ?? 0 ?>">

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Nombre del equipo</label>
                <input type="text" name="nombre" class="form-control" value="<?= e($equipoEditar['nombre'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Ciudad</label>
                <input type="text" name="ciudad" class="form-control" value="<?= e($equipoEditar['ciudad'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Sede / Cancha local</label>
                <input type="text" name="sede" class="form-control" value="<?= e($equipoEditar['sede'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold"><?= e(forma_genero($torneo['genero'] ?? null, 'Entrenador', 'Entrenadora')) ?></label>
                <input type="text" name="entrenador" class="form-control" value="<?= e($equipoEditar['entrenador'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Año de fundación</label>
                <input type="text" name="fundacion" class="form-control" value="<?= e($equipoEditar['fundacion'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Color primario</label>
                <input type="color" name="color_primario" class="form-control form-control-color w-100" value="<?= e($equipoEditar['color_primario'] ?? '#7b2ff7') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Color secundario</label>
                <input type="color" name="color_secundario" class="form-control form-control-color w-100" value="<?= e($equipoEditar['color_secundario'] ?? '#ff6b35') ?>">
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="3"><?= e($equipoEditar['descripcion'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Escudo / Logo (opcional)</label>
                <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp" data-vista-previa="previewLogoEquipo">
                <div class="form-text">Si no subes uno, se generará un escudo automático con las iniciales y colores del equipo.</div>
                <div class="vista-previa-subida mt-2" id="previewLogoEquipo">
                    <?php if (!empty($equipoEditar)): ?>
                    <figure class="vista-previa-item mb-0">
                        <?= logo_equipo($equipoEditar, 72) ?>
                        <figcaption><?= !empty($equipoEditar['logo']) ? 'Escudo actual' : 'Escudo automático' ?></figcaption>
                    </figure>
                    <?php endif; ?>
                </div>
            </div>

            <?php // --- Plantilla inicial: solo al CREAR ---
                  // Un equipo sin jugadores no puede alinearse ni generar estadísticas,
                  // así que se piden aquí mismo. Al editar no aparece: la plantilla ya se
                  // administra en su propia pantalla. ?>
            <?php if ($accion === 'nuevo'): ?>
            <?php $filasPlantilla = $minimoPlantilla + 3; ?>
            <div class="col-12">
                <hr class="my-2">
                <label class="form-label small fw-semibold d-block mb-1"><i class="bi bi-people me-1"></i><?= e(forma_genero($torneo['genero'] ?? null, 'Jugadores', 'Jugadoras')) ?> del equipo</label>
                <p class="form-text mt-0 mb-2">
                    Esta modalidad juega con <strong><?= (int) $minimoPlantilla ?></strong> en cancha, así que necesitas registrar
                    al menos <strong><?= (int) $minimoPlantilla ?></strong>. Puedes agregar más después desde la pantalla de
                    <?= e(mb_strtolower(forma_genero($torneo['genero'] ?? null, 'jugadores', 'jugadoras'))) ?>. Las filas que dejes vacías se ignoran.
                </p>

                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="small text-muted">
                                <th style="width:90px;">Dorsal</th>
                                <th>Nombre completo</th>
                                <th style="width:190px;">Posición</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 0; $i < $filasPlantilla; $i++): ?>
                            <tr>
                                <td><input type="text" name="jug_dorsal[]" class="form-control form-control-sm" maxlength="3" inputmode="numeric" placeholder="<?= $i + 1 ?>"></td>
                                <td><input type="text" name="jug_nombre[]" class="form-control form-control-sm" placeholder="<?= $i < $minimoPlantilla ? 'Obligatorio' : 'Opcional' ?>"></td>
                                <td>
                                    <select name="jug_posicion[]" class="form-select form-select-sm">
                                        <option value="">Sin definir</option>
                                        <?php foreach (posiciones_catalogo($torneo['deporte'] ?? null) as $clave => $pos): ?>
                                        <option value="<?= e($clave) ?>"><?= e($pos['label']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-degradado rounded-pill px-4">Guardar equipo</button>
            <a href="<?= url('admin/equipos.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
        </div>
    </form>

<?php else: ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Equipos (<?= count($equipos) ?>)</h3>
        <a href="<?= url('admin/equipos.php?accion=nuevo') ?>" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Nuevo equipo</a>
    </div>

    <?php if (empty($equipos)): ?>
    <div class="card-suave p-4 text-center text-muted">
        <i class="bi bi-shield display-6 d-block mb-2"></i>
        Todavía no hay equipos registrados.
    </div>
    <?php else: ?>
    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
        <?php foreach ($equipos as $eq): ?>
        <div class="col">
            <div class="card-suave p-3">
                <div class="d-flex flex-row align-items-center gap-3 mb-2">
                    <?= logo_equipo($eq, 56) ?>
                    <div class="flex-grow-1" style="min-width:0;">
                        <div class="fw-semibold text-truncate"><?= e($eq['nombre']) ?></div>
                        <div class="small text-muted text-truncate"><?= e($eq['ciudad']) ?><?= $eq['entrenador'] !== '' ? ' · ' . e($eq['entrenador']) : '' ?></div>
                    </div>
                </div>
                <div class="d-flex gap-1 flex-wrap">
                    <?php // Botón con texto y no solo icono: la plantilla es el paso natural
                          // después de crear el equipo, no debe quedar escondida. ?>
                    <a href="<?= url('admin/jugadores.php?equipo_id=' . $eq['id']) ?>" class="btn btn-sm btn-outline-secondary flex-grow-1"><i class="bi bi-people me-1"></i><?= e(forma_genero($torneo['genero'] ?? null, 'Jugadores', 'Jugadoras')) ?></a>
                    <a href="<?= e(url_copa('equipo_reporte.php?id=' . $eq['id'])) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Reporte del equipo en PDF"><i class="bi bi-file-earmark-text"></i></a>
                    <a href="<?= url('admin/equipos.php?accion=editar&id=' . $eq['id']) ?>" class="btn btn-sm btn-outline-secondary" title="Editar equipo"><i class="bi bi-pencil"></i></a>
                    <form method="post" data-confirm="¿Eliminar a <?= e($eq['nombre']) ?>? Esta acción no se puede deshacer.">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $eq['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar equipo"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

<?php endif; ?>
