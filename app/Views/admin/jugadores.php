<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= url('admin/equipos.php') ?>" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-arrow-left"></i></a>
    <div>
        <h3 class="mb-0"><?= e($etJugadores) ?></h3>
        <div class="small text-muted"><?= e($equipo['nombre']) ?></div>
    </div>
</div>

<?php // ---------- Importar desde Excel ----------
      // Cargar 16 equipos de 12 jugadores a mano son casi doscientas capturas, y el
      // organizador casi siempre ya tiene esa lista en un Excel del delegado.
      //
      // La vista previa es EDITABLE a propósito: la detección acierta casi siempre, pero
      // ningún Excel viene igual. Es más rápido corregir dos celdas aquí que descubrir el
      // error cuando el árbitro no encuentra al jugador en la ficha. ?>
<?php if ($accion === 'importar' && !empty($previaImport)): ?>
    <div class="card-suave p-4">
        <h5 class="mb-1"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Así quedaría la importación</h5>
        <p class="small text-muted">
            Archivo: <strong><?= e($previaImport['archivo']) ?></strong> ·
            <?= count($previaImport['jugadores']) ?> <?= e(mb_strtolower($etJugadores)) ?> a crear.
            Revisa y corrige lo que haga falta; nada se guarda hasta que confirmes.
        </p>

        <?php if (!empty($previaImport['motivos'])): ?>
        <div class="alert alert-info rounded-4 border-0 small">
            <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>Cómo se leyó el archivo</div>
            <ul class="mb-0 ps-3">
                <?php foreach ($previaImport['motivos'] as $motivo): ?>
                <li><?= e($motivo) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <?php // Corrección de columnas. Es la salida cuando la detección se equivoca: sin
              // esto habría que reescribir los doce nombres a mano, o volver a armar el
              // Excel. Se re-lee el mismo archivo, que quedó guardado en la sesión. ?>
        <form method="post" class="border rounded-4 p-3 mb-3">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="importar_remapear">
            <input type="hidden" name="equipo_id" value="<?= (int) $equipoId ?>">
            <div class="small fw-semibold mb-2"><i class="bi bi-sliders me-1"></i>¿Tomó mal alguna columna? Corrígela aquí</div>
            <div class="row g-2">
                <?php foreach ([
                    'nombre' => 'Nombre',
                    'apellido' => 'Apellido (si viene aparte)',
                    'dorsal' => 'Dorsal',
                    'posicion' => 'Posición',
                ] as $campo => $etiqueta): ?>
                <div class="col-6 col-lg-3">
                    <label class="form-label small text-muted mb-1" for="col_<?= e($campo) ?>"><?= e($etiqueta) ?></label>
                    <select name="col_<?= e($campo) ?>" id="col_<?= e($campo) ?>" class="form-select form-select-sm">
                        <option value="">— ninguna —</option>
                        <?php foreach ($previaImport['columnas'] as $indice => $columna): ?>
                        <option value="<?= (int) $indice ?>" <?= ($previaImport['mapa'][$campo] ?? null) === $indice ? 'selected' : '' ?>>
                            <?= e($columna['etiqueta']) ?><?= $columna['muestra'] !== '' ? ' (' . e($columna['muestra']) . ')' : '' ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endforeach; ?>
                <?php // Poder decir "no tiene encabezado" no es un detalle: en las listas
                      // escritas a mano la primera fila ya es un jugador, y tratarla como
                      // encabezado lo dejaría fuera de la importación sin avisar. ?>
                <div class="col-6 col-lg-3">
                    <label class="form-label small text-muted mb-1" for="fila_encabezado">Encabezado</label>
                    <select name="fila_encabezado" id="fila_encabezado" class="form-select form-select-sm">
                        <option value="-1" <?= (int) $previaImport['fila_encabezado'] === -1 ? 'selected' : '' ?>>No tiene encabezado</option>
                        <?php for ($f = 0; $f < 10; $f++): ?>
                        <option value="<?= $f ?>" <?= (int) $previaImport['fila_encabezado'] === $f ? 'selected' : '' ?>>Fila <?= $f + 1 ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <div class="col-12 col-lg-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3 w-100"><i class="bi bi-arrow-repeat me-1"></i>Volver a leer</button>
                </div>
            </div>
        </form>

        <?php if (empty($previaImport['jugadores'])): ?>
        <div class="alert alert-danger rounded-4 border-0 small">
            <i class="bi bi-exclamation-octagon-fill me-1"></i>
            Con estas columnas no sale ningún <?= e(mb_strtolower($etJugador)) ?> nuevo. Elige arriba cuál es la columna
            del nombre y vuelve a leer.
        </div>
        <?php endif; ?>

        <?php if (!empty($previaImport['omitidos'])): ?>
        <div class="alert alert-warning rounded-4 border-0 small">
            <div class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill me-1"></i>Filas que no entran</div>
            <ul class="mb-0 ps-3">
                <?php foreach ($previaImport['omitidos'] as $omitido): ?>
                <li><?= e($omitido) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>

        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="importar_confirmar">
            <input type="hidden" name="equipo_id" value="<?= (int) $equipoId ?>">

            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr class="small text-muted">
                            <th style="width:40px;"></th>
                            <th style="width:90px;">Dorsal</th>
                            <th>Nombre</th>
                            <th style="width:180px;">Posición</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($previaImport['jugadores'] as $i => $nuevo): ?>
                        <tr>
                            <td>
                                <input class="form-check-input" type="checkbox" name="imp_incluir[]" value="<?= (int) $i ?>" checked aria-label="Importar esta fila">
                            </td>
                            <td><input type="text" name="imp_dorsal[<?= (int) $i ?>]" class="form-control form-control-sm" maxlength="3" inputmode="numeric" value="<?= e($nuevo['dorsal']) ?>"></td>
                            <td><input type="text" name="imp_nombre[<?= (int) $i ?>]" class="form-control form-control-sm" value="<?= e($nuevo['nombre']) ?>"></td>
                            <td>
                                <select name="imp_posicion[<?= (int) $i ?>]" class="form-select form-select-sm">
                                    <option value="">Sin definir</option>
                                    <?php foreach (posiciones_catalogo($torneo['deporte'] ?? null) as $clave => $pos): ?>
                                    <option value="<?= e($clave) ?>" <?= $nuevo['posicion'] === $clave ? 'selected' : '' ?>><?= e($pos['label']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex gap-2 mt-4 flex-wrap">
                <button type="submit" class="btn btn-degradado rounded-pill px-4"><i class="bi bi-check2 me-1"></i>Importar los marcados</button>
                <a href="<?= e($urlLista) ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
            </div>
            <p class="small text-muted mt-2 mb-0">Desmarca la casilla de las filas que no quieras crear (totales, encabezados repetidos, cuerpo técnico).</p>
        </form>
    </div>

<?php elseif ($accion === 'nuevo' || $accion === 'editar'): ?>
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

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h5 class="mb-0">Plantilla (<?= count($jugadores) ?>)</h5>
        <div class="d-flex gap-2 flex-wrap">
            <?php // Importar desde el Excel que ya trae el delegado, en vez de capturar
                  // uno por uno. El archivo se lee y se muestra una vista previa editable:
                  // no se crea nada hasta confirmarla. ?>
            <form method="post" enctype="multipart/form-data" class="d-flex gap-2 align-items-center flex-wrap mb-0">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="importar_previa">
                <input type="hidden" name="equipo_id" value="<?= (int) $equipoId ?>">
                <input type="file" name="archivo" class="form-control form-control-sm" style="max-width:230px;" accept=".xlsx,.csv" required
                       aria-label="Archivo de Excel o CSV con la plantilla">
                <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-upload me-1"></i>Importar</button>
            </form>
            <a href="<?= $urlLista ?>&accion=nuevo" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i><?= e(forma_genero($torneo['genero'] ?? null, 'Nuevo', 'Nueva')) ?> <?= e(mb_strtolower($etJugador)) ?></a>
        </div>
    </div>
    <p class="small text-muted mt-n2 mb-4">
        <i class="bi bi-lightbulb me-1"></i>Puedes subir el Excel del delegado tal cual: la app busca sola la columna del
        nombre y la del dorsal, ignora el DPI y los teléfonos, y te muestra cómo quedaría antes de crear nada.
    </p>

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
