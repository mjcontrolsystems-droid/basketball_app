<?php if ($accion === 'generar'): ?>
    <?php
    // Vista del generador automático de calendario. Se calcula el resumen (cuántas
    // jornadas y encuentros saldrían) para avisarlo ANTES de crear nada.
    $vueltasSugeridas = torneo_vueltas($torneo);
    $resumenUna = fixture_resumen(count($equipos), 1);
    $resumenDoble = fixture_resumen(count($equipos), 2);
    $regularesActuales = array_values(array_filter($partidos, fn($p) => ($p['fase'] ?? 'grupos') === 'grupos'));
    ?>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="<?= url('admin/partidos.php') ?>" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-arrow-left"></i></a>
        <div>
            <h3 class="mb-0">Generar calendario</h3>
            <div class="small text-muted">Todos contra todos, con las jornadas ya armadas y fechadas.</div>
        </div>
    </div>

    <?php if (count($equipos) < 2): ?>
    <div class="card-suave p-4 text-center text-muted">
        <i class="bi bi-people display-6 d-block mb-2 opacity-50"></i>
        Necesitas al menos 2 equipos cargados para generar el calendario.
        <div class="mt-3"><a href="<?= url('admin/equipos.php?accion=nuevo') ?>" class="btn btn-sm btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Agregar equipo</a></div>
    </div>
    <?php else: ?>

    <form method="post" class="card-suave p-4" style="max-width:760px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="generar_fixture">

        <div class="alert alert-info rounded-4 border-0 small mb-4">
            <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>Con los <?= count($equipos) ?> equipos cargados</div>
            <ul class="mb-0 ps-3">
                <li>Una vuelta: <strong><?= $resumenUna['jornadas'] ?></strong> jornadas y <strong><?= $resumenUna['partidos'] ?></strong> encuentros.</li>
                <li>Ida y vuelta: <strong><?= $resumenDoble['jornadas'] ?></strong> jornadas y <strong><?= $resumenDoble['partidos'] ?></strong> encuentros.</li>
                <?php if ($resumenUna['descansa']): ?>
                <li>Como son impares, cada jornada un equipo descansa (no se le programa rival).</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Vueltas</label>
                <select name="vueltas" class="form-select">
                    <option value="1" <?= $vueltasSugeridas === 1 ? 'selected' : '' ?>>Una vuelta — <?= $resumenUna['partidos'] ?> encuentros</option>
                    <option value="2" <?= $vueltasSugeridas === 2 ? 'selected' : '' ?>>Ida y vuelta — <?= $resumenDoble['partidos'] ?> encuentros</option>
                </select>
                <div class="form-text">Viene de la configuración de la copa o liga; puedes cambiarlo solo para esta generación.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Días entre jornadas</label>
                <input type="number" min="0" max="60" name="dias_entre_jornadas" class="form-control" value="7">
                <div class="form-text">7 = una jornada por semana. Usa 0 si todas se juegan el mismo día.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Fecha de la primera jornada</label>
                <input type="date" name="fecha_inicio" class="form-control" value="<?= e($torneo['fecha_inicio'] ?: date('Y-m-d')) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Hora por defecto</label>
                <input type="time" name="hora" class="form-control" value="19:00" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Cancha / sede por defecto</label>
                <input type="text" name="cancha" class="form-control" value="<?= e($torneo['sede_principal'] ?? '') ?>">
            </div>
        </div>

        <p class="small text-muted mt-3 mb-0">
            <i class="bi bi-lightbulb me-1"></i>Fecha, hora y cancha se aplican a todos los encuentros de arranque; después puedes ajustarlos uno por uno desde la lista.
        </p>

        <?php if (!empty($regularesActuales)): ?>
        <div class="alert alert-warning rounded-4 border-0 small mt-3 mb-0">
            <div class="form-check mb-0">
                <input class="form-check-input" type="checkbox" name="reemplazar" id="checkReemplazar" value="1">
                <label class="form-check-label" for="checkReemplazar">
                    <span class="fw-semibold">Ya hay <?= count($regularesActuales) ?> encuentros de temporada regular.</span>
                    Bórralos y rehaz el calendario desde cero.
                </label>
            </div>
            <div class="mt-2">Si alguno ya está jugado o tiene eventos cargados en su ficha, la generación se detiene y no se borra nada: primero tienes que resolverlo a mano.</div>
        </div>
        <?php endif; ?>

        <div class="d-flex gap-2 mt-4">
            <?php // Sin data-confirm a propósito: esta pantalla YA es el paso deliberado
                  // (dice cuántos encuentros va a crear) y el handler de confirmación
                  // resetea el formulario al cancelar, lo que aquí borraría lo que el
                  // organizador acaba de escribir. ?>
            <button type="submit" class="btn btn-degradado rounded-pill px-4"><i class="bi bi-calendar-plus me-1"></i>Generar calendario</button>
            <a href="<?= url('admin/partidos.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
        </div>
    </form>

    <?php endif; ?>

<?php elseif ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="<?= url('admin/partidos.php') ?>" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-arrow-left"></i></a>
        <h3 class="mb-0"><?= $accion === 'editar' ? 'Editar encuentro' : 'Programar encuentro' ?></h3>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="alert alert-danger rounded-3">
        <ul class="mb-0 small">
            <?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="post" class="card-suave p-4" style="max-width:760px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= $partidoEditar['id'] ?? 0 ?>">

        <div class="row g-3">
            <?php if ($esLiga): ?>
            <?php // Liga: no hay fases que elegir, todo es temporada regular. Se manda el
                  // valor fijo para que el POST siga siendo válido sin mostrar un select
                  // de una sola opción. ?>
            <input type="hidden" name="fase" value="grupos">
            <?php else: ?>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Fase</label>
                <select name="fase" id="selectFase" class="form-select">
                    <?php foreach ($fasesValidas as $f): ?>
                    <option value="<?= e($f) ?>" <?= $faseSeleccionada === $f ? 'selected' : '' ?>><?= e(FASES_LABEL[$f]) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Las fases de eliminación directa no cuentan para la tabla de posiciones.</div>
            </div>
            <?php endif; ?>
            <?php // La jornada ya no se escribe a mano: se deduce de la fecha del encuentro
                  // (los del mismo fin de semana quedan juntos, una fecha nueva abre la
                  // siguiente). El campo queda bloqueado salvo que se pida corregirlo. ?>
            <div class="col-md-6" id="grupoJornada">
                <label class="form-label small fw-semibold">Jornada</label>
                <input type="number" min="1" max="<?= (int) $jornadaTope ?>" name="jornada"
                       id="campoJornada" class="form-control"
                       value="<?= e((string) ($partidoEditar['jornada'] ?? $jornadaSugerida)) ?>"
                       <?= $jornadaManualMarcada ? '' : 'readonly' ?>>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" value="1" name="jornada_manual"
                           id="jornadaManual" data-activa="#campoJornada"
                           <?= $jornadaManualMarcada ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="jornadaManual">Ajustar la jornada manualmente</label>
                </div>
                <div class="form-text">
                    Se asigna sola según la fecha: los encuentros del mismo fin de semana van
                    a la misma jornada y una fecha nueva abre la siguiente. Marca la casilla
                    solo para una reprogramación (máximo, la jornada <?= (int) $jornadaTope ?>).
                </div>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-semibold">Fecha</label>
                <input type="date" name="fecha" class="form-control" value="<?= e($partidoEditar['fecha'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Hora</label>
                <input type="time" name="hora" class="form-control" value="<?= e($partidoEditar['hora'] ?? '') ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Cancha / Sede</label>
                <input type="text" name="cancha" class="form-control" value="<?= e($partidoEditar['cancha'] ?? '') ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label small fw-semibold">Equipo local</label>
                <select name="equipo_local" class="form-select" required>
                    <option value="">Selecciona...</option>
                    <?php foreach ($equipos as $eq): ?>
                    <option value="<?= $eq['id'] ?>" <?= (int) ($partidoEditar['equipo_local'] ?? 0) === $eq['id'] ? 'selected' : '' ?>><?= e($eq['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Equipo visitante</label>
                <select name="equipo_visitante" class="form-select" required>
                    <option value="">Selecciona...</option>
                    <?php foreach ($equipos as $eq): ?>
                    <option value="<?= $eq['id'] ?>" <?= (int) ($partidoEditar['equipo_visitante'] ?? 0) === $eq['id'] ? 'selected' : '' ?>><?= e($eq['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label small fw-semibold">Estado</label>
                <select name="estado" class="form-select" id="selectEstado">
                    <option value="programado" <?= ($partidoEditar['estado'] ?? 'programado') === 'programado' ? 'selected' : '' ?>>Programado</option>
                    <option value="jugado" <?= ($partidoEditar['estado'] ?? '') === 'jugado' ? 'selected' : '' ?>>Jugado</option>
                </select>
            </div>
            <div class="col-md-8">
                <label class="form-label small fw-semibold">Marcador</label>
                <div class="form-control d-flex align-items-center justify-content-between bg-light">
                    <span class="fw-bold fs-5">
                        <?= ($partidoEditar['marcador_local'] ?? '') !== '' && ($partidoEditar['marcador_local'] ?? null) !== null ? (int) $partidoEditar['marcador_local'] : '–' ?>
                        <span class="text-muted mx-1">-</span>
                        <?= ($partidoEditar['marcador_visitante'] ?? '') !== '' && ($partidoEditar['marcador_visitante'] ?? null) !== null ? (int) $partidoEditar['marcador_visitante'] : '–' ?>
                    </span>
                    <?php if (($partidoEditar['id'] ?? 0) > 0): ?>
                    <a href="<?= url('admin/partido_eventos.php?partido_id=' . (int) $partidoEditar['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-clipboard-data me-1"></i>Registrar <?= e(mb_strtolower(etiqueta_anotaciones($torneo['deporte'] ?? null))) ?></a>
                    <?php endif; ?>
                </div>
                <div class="form-text">El marcador se calcula automáticamente con los <?= e(mb_strtolower(etiqueta_anotaciones($torneo['deporte'] ?? null))) ?> registrados en la ficha de Eventos.</div>
            </div>

            <div class="col-md-6">
                <label class="form-label small fw-semibold">Árbitro (opcional)</label>
                <input type="text" name="arbitro" class="form-control" value="<?= e($partidoEditar['arbitro'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Observaciones (opcional)</label>
                <textarea name="observaciones" class="form-control" rows="2"><?= e($partidoEditar['observaciones'] ?? '') ?></textarea>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn btn-degradado rounded-pill px-4">Guardar encuentro</button>
            <a href="<?= url('admin/partidos.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
        </div>
    </form>

<?php else: ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h3 class="mb-0">Encuentros (<?= count($partidos) ?>)</h3>
            <div class="small text-muted"><?= $esLiga ? 'Liga' : 'Campeonato' ?> · <?= e(torneo_vueltas_label($torneo)) ?></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= e(url_copa('calendario_imprimir.php')) ?>" target="_blank" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-printer me-1"></i>Imprimir calendario</a>
            <a href="<?= url('admin/partidos.php?accion=generar') ?>" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-calendar-plus me-1"></i>Generar calendario</a>
            <a href="<?= url('admin/partidos.php?accion=nuevo') ?>" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Programar encuentro</a>
        </div>
    </div>

    <?php if (empty($partidos) && count($equipos) >= 2): ?>
    <?php // Atajo para el arranque de temporada: es justo cuando el generador ahorra más
          // trabajo, y es cuando menos se le ocurre a alguien que existe. ?>
    <div class="card-suave p-4 text-center mb-4">
        <i class="bi bi-calendar-plus display-6 d-block mb-2" style="color:var(--color-primario);"></i>
        <div class="fw-semibold mb-1">Todavía no hay encuentros programados</div>
        <p class="text-muted small mb-3">Con tus <?= count($equipos) ?> equipos se puede armar el calendario completo de una sola vez, todos contra todos, en vez de programarlos uno por uno.</p>
        <div><a href="<?= url('admin/partidos.php?accion=generar') ?>" class="btn btn-degradado rounded-pill px-4"><i class="bi bi-magic me-1"></i>Generar calendario automático</a></div>
    </div>
    <?php endif; ?>

    <?php if (!$esLiga): ?>
    <ul class="nav nav-pills mb-4" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#panelGrupos" type="button">Fase de Grupos</button></li>
        <?php foreach ($fasesTorneo as $f): ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#panel-<?= $f ?>" type="button"><?= e(FASES_LABEL[$f]) ?> <?= count($playoffsPorFase[$f]) > 0 ? '<span class="badge rounded-pill text-bg-secondary ms-1">' . count($playoffsPorFase[$f]) . '</span>' : '' ?></button></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="panelGrupos">
            <?php foreach ($jornadas as $numJornada => $lista): ?>
            <h6 class="text-muted text-uppercase small fw-bold mb-2 mt-4">Jornada <?= $numJornada ?></h6>
            <div class="row row-cols-1 row-cols-lg-2 g-3 mb-2">
                <?php foreach ($lista as $p): ?>
                    <?= admin_tarjeta_partido($p, $equiposPorId) ?>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <?php if (empty($jornadas)): ?>
                <p class="text-muted">Aún no hay encuentros <?= $esLiga ? 'programados' : 'de fase de grupos programados' ?>.</p>
            <?php endif; ?>
        </div>

        <?php foreach ($fasesTorneo as $f): ?>
        <div class="tab-pane fade" id="panel-<?= $f ?>">
            <?php if (empty($playoffsPorFase[$f])): ?>
                <div class="card-suave p-4 text-center text-muted">
                    <i class="bi bi-trophy fs-3 d-block mb-2 opacity-50"></i>
                    Aún no has programado encuentros de <?= e(FASES_LABEL[$f]) ?>.
                    <div class="mt-2">
                        <a href="<?= url('admin/partidos.php?accion=nuevo&fase=' . $f) ?>" class="btn btn-sm btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Programar <?= e(FASES_LABEL[$f]) ?></a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-lg-2 g-3">
                    <?php foreach ($playoffsPorFase[$f] as $p): ?>
                        <?= admin_tarjeta_partido($p, $equiposPorId) ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>
