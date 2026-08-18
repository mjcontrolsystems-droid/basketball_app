<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="<?= url('admin/torneos.php') ?>" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-arrow-left"></i></a>
        <h3 class="mb-0"><?= $accion === 'editar' ? 'Editar copa o liga' : 'Nueva copa o liga' ?></h3>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="alert alert-danger rounded-3">
        <ul class="mb-0 small">
            <?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="card-suave p-4" style="max-width:860px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= $torneoEditar['id'] ?? 0 ?>">

        <h6 class="text-uppercase small fw-bold text-muted mb-3">Datos básicos</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-7">
                <label class="form-label small fw-semibold">Nombre de la copa o liga</label>
                <input type="text" name="nombre" id="campoNombre" class="form-control" value="<?= e($torneoEditar['nombre'] ?? '') ?>" required placeholder="Ej. Papifútbol Masculino 2026">
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold">URL de la copa o liga</label>
                <div class="input-group">
                    <span class="input-group-text small">/</span>
                    <input type="text" name="slug" id="campoSlug" class="form-control" value="<?= e($torneoEditar['slug'] ?? '') ?>" placeholder="se genera automático" data-predeterminado="<?= !empty($torneoEditar['es_predeterminado']) ? '1' : '0' ?>" data-origen="<?= e(SITE_ORIGIN . BASE_URL) ?>">
                </div>
                <div class="form-text">Solo letras, números y guiones. Tu copa o liga quedará en: <strong id="previewUrlCopa"><?= !empty($torneoEditar['es_predeterminado']) ? e(SITE_ORIGIN . BASE_URL . '/') : e(SITE_ORIGIN . BASE_URL . '/' . ($torneoEditar['slug'] ?? '') . '/') ?></strong></div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Deporte</label>
                <select name="deporte" id="selectDeporte" class="form-select">
                    <option value="basketball" <?= $deportePorDefecto === 'basketball' ? 'selected' : '' ?>>Basketball</option>
                    <option value="futbol" <?= $deportePorDefecto === 'futbol' ? 'selected' : '' ?>>Fútbol</option>
                </select>
                <div class="form-text">Define los valores iniciales de empates y puntos abajo, y el catálogo de eventos (goles/puntos, tarjetas/faltas) de cada partido.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Género</label>
                <select name="genero" class="form-select">
                    <option value="mixto" <?= $generoPorDefecto === 'mixto' ? 'selected' : '' ?>>Mixto / no aplica</option>
                    <option value="femenino" <?= $generoPorDefecto === 'femenino' ? 'selected' : '' ?>>Femenino</option>
                    <option value="masculino" <?= $generoPorDefecto === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                </select>
                <div class="form-text">Ajusta "entrenador/a", "jugador/a", etc. en todo el sitio.</div>
            </div>

            <?php
            // Modalidad + duración de periodo: definen cuántos minutos dura cada tiempo/
            // cuarto (cronómetro y transmisión en vivo). El JS de app.js sugiere la
            // duración reglamentaria al elegir la modalidad; el organizador puede
            // sobreescribirla (muchas ligas amateur juegan tiempos más cortos).
            $modalidadActual = (string) ($torneoEditar['modalidad'] ?? '');
            $duracionActual = (int) ($torneoEditar['duracion_periodo_min'] ?? 0);
            ?>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Modalidad</label>
                <select name="modalidad" id="selectModalidad" class="form-select">
                    <?php foreach (MODALIDADES_POR_DEPORTE as $dep => $catalogo): foreach ($catalogo as $clave => $m): ?>
                    <option value="<?= e($clave) ?>" data-deporte="<?= e($dep) ?>" data-duracion="<?= (int) $m['duracion_min'] ?>" <?= $modalidadActual === $clave ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                    <?php endforeach; endforeach; ?>
                </select>
                <div class="form-text">Fútbol: 11, 7 o sala/5. Basketball: FIBA o estilo NBA. Define los tiempos reglamentarios.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Minutos por tiempo/cuarto</label>
                <input type="number" name="duracion_periodo_min" id="campoDuracionPeriodo" class="form-control" min="1" max="90" value="<?= $duracionActual > 0 ? $duracionActual : '' ?>" placeholder="Reglamentario de la modalidad">
                <div class="form-text">Déjalo vacío para usar el reglamentario. Cámbialo si tu liga juega tiempos distintos (ej. cuartos de 15 min).</div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Subtítulo</label>
                <input type="text" name="subtitulo" class="form-control" placeholder="Ej. Liga Municipal de Verano" value="<?= e($torneoEditar['subtitulo'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Temporada</label>
                <input type="text" name="temporada" class="form-control" placeholder="Ej. 2026" value="<?= e($torneoEditar['temporada'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="2" placeholder="Ej. Ocho equipos, una vuelta de temporada regular y el sueño de levantar la copa."><?= e($torneoEditar['descripcion'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Frase del hero (portada)</label>
                <input type="text" name="hero_frase" class="form-control" placeholder="Ej. Pasión, esfuerzo y comunidad en cada jornada" value="<?= e($torneoEditar['hero_frase'] ?? '') ?>">
            </div>
        </div>

        <h6 class="text-uppercase small fw-bold text-muted mb-3">Formato de la competencia</h6>
        <div class="row g-3 mb-4">
            <?php // Liga vs Campeonato: define si la competencia se decide SOLO por puntos
                  // (liga) o si además tiene cuadro de eliminación directa (campeonato).
                  // El JS de app.js muestra/oculta las fases según lo elegido. ?>
            <div class="col-12">
                <label class="form-label small fw-semibold d-block">¿Liga o campeonato?</label>
                <div class="row g-2" id="grupoFormatoTorneo">
                    <div class="col-md-6">
                        <input type="radio" class="btn-check" name="modo" value="<?= e(FORMATO_LIGA) ?>" id="modoLiga" <?= $modoPorDefecto === FORMATO_LIGA ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary w-100 text-start p-3 rounded-4" for="modoLiga">
                            <span class="d-block fw-semibold"><i class="bi bi-list-ol me-1"></i>Liga</span>
                            <span class="d-block small text-muted">Solo control de puntos: todos contra todos y gana quien termine arriba en la tabla. Sin cuartos, semifinal ni final.</span>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <input type="radio" class="btn-check" name="modo" value="<?= e(FORMATO_CAMPEONATO) ?>" id="modoCampeonato" <?= $modoPorDefecto === FORMATO_CAMPEONATO ? 'checked' : '' ?>>
                        <label class="btn btn-outline-secondary w-100 text-start p-3 rounded-4" for="modoCampeonato">
                            <span class="d-block fw-semibold"><i class="bi bi-trophy me-1"></i>Campeonato</span>
                            <span class="d-block small text-muted">Fase de grupos con tabla de puntos + eliminación directa (cuartos, semifinal, final) para definir al campeón.</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Número de equipos</label>
                <input type="number" min="2" name="num_equipos" class="form-control" value="<?= e((string) ($torneoEditar['num_equipos'] ?? 8)) ?>">
                <div class="form-text">Informativo, se muestra en el sitio.</div>
            </div>
            <?php // Cuántas veces se enfrentan todos entre sí en la temporada regular. Es
                  // lo que usa el generador automático de calendario (Encuentros →
                  // "Generar calendario") para saber cuántas jornadas armar. ?>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Vueltas de la temporada</label>
                <select name="vueltas" class="form-select">
                    <option value="1" <?= $vueltasPorDefecto === 1 ? 'selected' : '' ?>>Una vuelta (todos contra todos)</option>
                    <option value="2" <?= $vueltasPorDefecto === 2 ? 'selected' : '' ?>>Ida y vuelta (doble)</option>
                </select>
                <div class="form-text">Ida y vuelta: cada par juega dos veces, uno de local en cada partido.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">¿Permite empates?</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="checkEmpates" name="permite_empates" <?= !empty($torneoEditar['permite_empates']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="checkEmpates">Sí, esta copa o liga admite empates</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Puntos por resultado</label>
                <div class="row g-2">
                    <div class="col-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Victoria</span>
                            <input type="number" min="0" name="puntos_victoria" id="campoPtsVictoria" class="form-control" value="<?= e((string) ($torneoEditar['puntos_victoria'] ?? 2)) ?>">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Empate</span>
                            <input type="number" min="0" name="puntos_empate" id="campoPtsEmpate" class="form-control" value="<?= e((string) ($torneoEditar['puntos_empate'] ?? 0)) ?>">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Derrota</span>
                            <input type="number" min="0" name="puntos_derrota" id="campoPtsDerrota" class="form-control" value="<?= e((string) ($torneoEditar['puntos_derrota'] ?? 1)) ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12" id="grupoFasesPlayoff">
                <label class="form-label small fw-semibold d-block">Fases de eliminación directa</label>
                <?php $fasesGuardadas = $torneoEditar['fases_playoff'] ?? ['cuartos', 'semifinal', 'final']; ?>
                <?php foreach (FASES_PLAYOFF_CATALOGO as $f): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="fases_playoff[]" value="<?= e($f) ?>" id="fase-<?= e($f) ?>" <?= in_array($f, $fasesGuardadas, true) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="fase-<?= e($f) ?>"><?= e(FASES_LABEL[$f]) ?></label>
                </div>
                <?php endforeach; ?>
                <div class="form-text">Solo aplica al formato campeonato. En una liga el título se define en la tabla de puntos.</div>
            </div>
        </div>

        <h6 class="text-uppercase small fw-bold text-muted mb-3">Fechas, sede y estilo</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Fecha de inicio</label>
                <input type="date" name="fecha_inicio" class="form-control" value="<?= e($torneoEditar['fecha_inicio'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Fecha de fin</label>
                <input type="date" name="fecha_fin" class="form-control" value="<?= e($torneoEditar['fecha_fin'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Formato</label>
                <input type="text" name="formato" class="form-control" placeholder="Ej. Fase de grupos + eliminación directa" value="<?= e($torneoEditar['formato'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Sede principal</label>
                <input type="text" name="sede_principal" class="form-control" placeholder="Ej. Polideportivo Municipal, Ciudad Capital" value="<?= e($torneoEditar['sede_principal'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Instagram (opcional)</label>
                <input type="url" name="instagram" class="form-control" placeholder="https://instagram.com/tu_copa" value="<?= e($torneoEditar['instagram'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Color primario</label>
                <input type="color" name="color_primario" class="form-control form-control-color w-100" value="<?= e($torneoEditar['color_primario'] ?? '#7b2ff7') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Color secundario</label>
                <input type="color" name="color_secundario" class="form-control form-control-color w-100" value="<?= e($torneoEditar['color_secundario'] ?? '#ff6b35') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Color acento</label>
                <input type="color" name="color_acento" class="form-control form-control-color w-100" value="<?= e($torneoEditar['color_acento'] ?? '#ffc93c') ?>">
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Logo de la copa o liga (opcional)</label>
                <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp" data-vista-previa="previewLogoTorneo">
                <div class="form-text">Se usará en el navbar, la portada y al compartir el enlace, en lugar del balón del deporte. PNG con fondo transparente se ve mejor.</div>
                <div class="vista-previa-subida mt-2" id="previewLogoTorneo" data-vacio="Aún no has subido un logo: se mostrará el balón del deporte.">
                    <?php if (!empty($torneoEditar['logo'])): ?>
                    <figure class="vista-previa-item mb-0">
                        <img src="<?= e(url_imagen((string) $torneoEditar['logo'])) ?>" alt="Logo actual">
                        <figcaption>Logo actual</figcaption>
                    </figure>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-degradado rounded-pill px-4">Guardar</button>
            <a href="<?= url('admin/torneos.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
        </div>
    </form>

<?php else: ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">Mis Copas y Ligas (<?= count($torneos) ?>)</h3>
            <?php if ($limiteTorneos !== null): ?>
            <p class="text-muted small mb-0 mt-1">
                <i class="bi bi-ticket-perforated me-1"></i>Usas <?= $torneosCreados ?> de <?= $limiteTorneos ?> <?= $limiteTorneos === 1 ? 'copa o liga autorizada' : 'copas o ligas autorizadas' ?>.
            </p>
            <?php endif; ?>
        </div>
        <?php if ($puedeCrearTorneo): ?>
        <a href="<?= url('admin/torneos.php?accion=nuevo') ?>" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Nueva copa o liga</a>
        <?php else: ?>
        <button type="button" class="btn btn-degradado rounded-pill px-3 disabled" disabled title="<?= e(mensaje_limite_torneos($limiteTorneos)) ?>"><i class="bi bi-lock me-1"></i>Nueva copa o liga</button>
        <?php endif; ?>
    </div>

    <?php if (!$puedeCrearTorneo): ?>
    <div class="alert alert-warning rounded-4 border-0 shadow-sm d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <div class="fw-semibold">Sin cupo disponible</div>
            <div class="small"><?= e(mensaje_limite_torneos($limiteTorneos)) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
        <?php foreach ($torneos as $t): ?>
        <div class="col">
            <div class="card-suave p-3 h-100 d-flex flex-column <?= ($_SESSION['torneo_activo_id'] ?? null) === $t['id'] ? 'border border-2' : '' ?>" style="<?= ($_SESSION['torneo_activo_id'] ?? null) === $t['id'] ? 'border-color:var(--color-primario) !important;' : '' ?>">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge rounded-pill text-bg-light border small"><?= $t['deporte'] === 'futbol' ? '⚽ Fútbol' : '🏀 Basketball' ?></span>
                    <span class="badge rounded-pill text-bg-light border small"><?= torneo_es_liga($t) ? '<i class="bi bi-list-ol me-1"></i>Liga' : '<i class="bi bi-trophy me-1"></i>Campeonato' ?></span>
                    <?php if (torneo_vueltas($t) === 2): ?><span class="badge rounded-pill text-bg-light border small"><i class="bi bi-arrow-left-right me-1"></i>Ida y vuelta</span><?php endif; ?>
                    <?php if (($t['genero'] ?? 'mixto') !== 'mixto'): ?><span class="badge rounded-pill text-bg-light border small"><?= $t['genero'] === 'femenino' ? 'Femenino' : 'Masculino' ?></span><?php endif; ?>
                    <?php if (!$t['activo']): ?><span class="badge rounded-pill text-bg-secondary small">Inactiva</span><?php endif; ?>
                    <?php if ($t['es_predeterminado']): ?><span class="badge rounded-pill text-bg-warning small">Predeterminada</span><?php endif; ?>
                </div>
                <div class="fw-semibold mb-1"><?= e($t['nombre']) ?></div>
                <?php // min-width:0 en el <code> es lo que permite que la URL larga se
                      // trunque con "..." dentro de la tarjeta: sin él, un flex item no
                      // encoge por debajo de su contenido y la URL empujaba el ancho de
                      // toda la página en el teléfono (se veía cortada y con scroll lateral). ?>
                <?php // Los estilos de truncado van INLINE a propósito: son la garantía de que
                      // la URL larga jamás ensanche la tarjeta en el teléfono, aunque el
                      // navegador tenga una hoja de estilos vieja en caché. ?>
                <div class="d-flex align-items-center gap-1 mb-1" style="min-width:0;max-width:100%;">
                    <code class="small flex-grow-1" style="display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e(url_copa_de($t)) ?></code>
                    <button type="button" class="btn btn-sm btn-link p-0 ms-1 flex-shrink-0 btn-copiar-url" data-url="<?= e(url_copa_de($t)) ?>" title="Copiar enlace"><i class="bi bi-clipboard"></i></button>
                </div>
                <div class="d-flex align-items-center gap-1 mb-3">
                    <span class="small text-muted">Código:</span>
                    <code class="small fw-bold"><?= e($t['codigo']) ?></code>
                    <button type="button" class="btn btn-sm btn-link p-0 ms-1 btn-copiar-url" data-url="<?= e($t['codigo']) ?>" title="Copiar código"><i class="bi bi-clipboard"></i></button>
                    <form method="post" data-confirm="¿Generar un código nuevo para \"<?= e($t['nombre']) ?>\"? El código anterior dejará de funcionar." class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="regenerar_codigo">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-link p-0 ms-1 text-muted" title="Generar código nuevo"><i class="bi bi-arrow-repeat"></i></button>
                    </form>
                </div>
                <div class="d-flex gap-2 mt-auto flex-wrap">
                    <a href="<?= url('admin/torneos.php?accion=entrar&id=' . $t['id']) ?>" class="btn btn-sm btn-degradado rounded-pill flex-grow-1">Entrar</a>
                    <a href="<?= e(url_copa_de($t)) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="bi bi-box-arrow-up-right"></i></a>
                    <a href="<?= url('admin/torneos.php?accion=editar&id=' . $t['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <?php if (!$t['es_predeterminado']): ?>
                    <form method="post" data-confirm="¿Eliminar \"<?= e($t['nombre']) ?>\"? Se borrarán todos sus equipos, partidos y patrocinadores.">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>
