<?php if ($accion === 'generar'): ?>
    <?php
    // Vista del generador automático de calendario. Se calcula el resumen (cuántas
    // jornadas y encuentros saldrían) para avisarlo ANTES de crear nada.
    $vueltasSugeridas = torneo_vueltas($torneo);
    $vueltaElegida = (int) ($datosPrevios['vueltas'] ?? $vueltasSugeridas) === 2 ? 2 : 1;
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
                    <option value="1" <?= $vueltaElegida === 1 ? 'selected' : '' ?>>Una vuelta — <?= $resumenUna['partidos'] ?> encuentros</option>
                    <option value="2" <?= $vueltaElegida === 2 ? 'selected' : '' ?>>Ida y vuelta — <?= $resumenDoble['partidos'] ?> encuentros</option>
                </select>
                <div class="form-text">Viene de la configuración de la copa o liga; puedes cambiarlo solo para esta generación.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Primer día de juego</label>
                <input type="date" name="fecha_inicio" class="form-control" value="<?= e($datosPrevios['fecha_inicio'] ?? ($torneo['fecha_inicio'] ?: date('Y-m-d'))) ?>" required>
                <div class="form-text">Tiene que caer en uno de los días que marques abajo.</div>
            </div>
        </div>

        <?php // --- Días de juego ---
              // El corazón del generador. En vez de "una jornada por fecha", el organizador
              // dice qué días juega y cuántos partidos caben: de ahí salen las jornadas.
              // Si el cupo del fin de semana es mayor que una ronda, los cupos de más se
              // llenan adelantando partidos de las últimas jornadas. ?>
        <hr class="my-4">
        <label class="form-label small fw-semibold d-block mb-1"><i class="bi bi-calendar-week me-1"></i>Días de juego</label>
        <p class="form-text mt-0 mb-3">
            Marca los días y cuántos partidos caben en cada uno. Con <?= count($equipos) ?> equipos, una ronda son
            <strong><?= max(1, intdiv(count($equipos), 2)) ?></strong> partidos: si en el fin de semana caben más,
            los cupos de sobra se llenan adelantando encuentros, y esos equipos juegan dos veces ese fin de semana.
        </p>

        <div class="table-responsive mb-3">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th style="width:130px;">Día</th>
                        <th style="width:110px;">Partidos</th>
                        <th style="width:130px;">Primera hora</th>
                        <th style="width:150px;">Cada cuánto</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (CALENDARIO_DIAS as $w => $nombreDia): ?>
                    <?php
                    $activo = isset($datosPrevios['dia_activo'])
                        ? in_array((string) $w, array_map('strval', (array) $datosPrevios['dia_activo']), true)
                        : in_array($w, [6, 0], true);
                    ?>
                    <tr>
                        <td>
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" name="dia_activo[]" value="<?= $w ?>" id="dia<?= $w ?>" <?= $activo ? 'checked' : '' ?>>
                                <label class="form-check-label" for="dia<?= $w ?>"><?= e($nombreDia) ?></label>
                            </div>
                        </td>
                        <td><input type="number" min="0" max="40" name="dia_partidos[<?= $w ?>]" class="form-control form-control-sm" value="<?= e((string) ($datosPrevios['dia_partidos'][$w] ?? ($w === 0 ? 5 : ($w === 6 ? 4 : 0)))) ?>"></td>
                        <td><input type="time" name="dia_hora[<?= $w ?>]" class="form-control form-control-sm" value="<?= e((string) ($datosPrevios['dia_hora'][$w] ?? '09:00')) ?>"></td>
                        <td><div class="input-group input-group-sm"><input type="number" min="0" max="480" step="15" name="dia_intervalo[<?= $w ?>]" class="form-control" value="<?= e((string) ($datosPrevios['dia_intervalo'][$w] ?? 90)) ?>"><span class="input-group-text">min</span></div></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Canchas</label>
                <input type="text" name="canchas" class="form-control" value="<?= e((string) ($datosPrevios['canchas'] ?? ($torneo['sede_principal'] ?? ''))) ?>" placeholder="Cancha 1, Cancha 2">
                <div class="form-text">Separadas por coma. Con dos canchas, dos partidos van a la misma hora y la siguiente tanda arranca después del intervalo.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Fechas que no se juegan</label>
                <?php // Calendario en vez de escribir fechas a mano: se hace clic sobre el
                      // día y listo. Solo deja marcar los días que realmente se juegan,
                      // porque excluir un martes en una liga de fin de semana no hace nada.
                      // El valor viaja en el campo oculto con el mismo formato de antes. ?>
                <div id="calendarioExcluidas"
                     class="calendario-excluir"
                     data-campo="campoFechasExcluidas"
                     data-dias="dia_activo[]"
                     data-inicio="fecha_inicio"></div>
                <input type="hidden" name="fechas_excluidas" id="campoFechasExcluidas" value="<?= e((string) ($datosPrevios['fechas_excluidas'] ?? '')) ?>">
                <div id="listaExcluidas" class="mt-2 d-flex flex-wrap gap-1"></div>
                <div class="form-text">Feriados o fines de semana sin confirmar. La jornada completa se corre a la semana siguiente, y las de atrás se corren con ella.</div>
            </div>
        </div>

        <?php if (!empty($regularesActuales)): ?>
        <?php
        // Última jornada y última fecha de lo que ya está programado, para sugerir por
        // dónde seguir sin que el organizador tenga que ir a buscarlo.
        $ultimaJornadaActual = 0;
        $ultimaFechaActual = '';
        foreach ($regularesActuales as $pExistente) {
            $ultimaJornadaActual = max($ultimaJornadaActual, (int) ($pExistente['jornada'] ?? 0));
            $ultimaFechaActual = max($ultimaFechaActual, (string) ($pExistente['fecha'] ?? ''));
        }
        ?>
        <div class="alert alert-warning rounded-4 border-0 small mt-3 mb-0">
            <div class="fw-semibold mb-2">
                <i class="bi bi-exclamation-triangle-fill me-1"></i>Ya hay <?= count($regularesActuales) ?> encuentros de temporada regular
                <?= $ultimaFechaActual !== '' ? '(hasta el ' . e(formatear_fecha_corta($ultimaFechaActual)) . ', jornada ' . (int) $ultimaJornadaActual . ')' : '' ?>.
            </div>

            <?php // Continuar es lo que hace falta cuando la primera jornada ya se publicó
                  // a los equipos: rehacer el calendario cambiaría partidos ya avisados. ?>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="que_hacer" id="checkContinuar" value="continuar" checked>
                <label class="form-check-label" for="checkContinuar">
                    <span class="fw-semibold">Conservarlos y generar solo lo que falta.</span>
                    <span class="d-block">No se toca ninguno de los que ya existen, no se repiten esos cruces, y las jornadas siguen desde la <?= (int) $ultimaJornadaActual + 1 ?>. Pon arriba como primer día de juego el del siguiente fin de semana.</span>
                </label>
            </div>

            <div class="form-check mb-0">
                <input class="form-check-input" type="radio" name="que_hacer" id="checkReemplazar" value="reemplazar">
                <label class="form-check-label" for="checkReemplazar">
                    <span class="fw-semibold text-danger">Borrarlos y rehacer el calendario desde cero.</span>
                    <span class="d-block">Solo si todavía no publicaste nada: los encuentros actuales se pierden.</span>
                </label>
            </div>
            <div class="mt-2 text-muted">Si alguno ya está jugado o tiene eventos cargados, rehacer se detiene y no se borra nada.</div>
        </div>
        <?php endif; ?>

        <?php // La semilla del sorteo viaja escondida: así el calendario que se crea es
              // EXACTAMENTE el que se acaba de ver en la vista previa, y no otro sorteo.
              // Al pedir una previa nueva se manda vacía y se sortea de nuevo. ?>
        <input type="hidden" name="semilla" value="<?= (int) ($previa['semilla'] ?? 0) ?>">

        <div class="d-flex gap-2 mt-4 flex-wrap">
            <?php // Sin data-confirm a propósito: esta pantalla YA es el paso deliberado
                  // (dice cuántos encuentros va a crear) y el handler de confirmación
                  // resetea el formulario al cancelar, lo que aquí borraría lo que el
                  // organizador acaba de escribir. ?>
            <button type="submit" name="solo_previa" value="1" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-eye me-1"></i>Ver vista previa</button>
            <?php if ($previa !== null): ?>
            <button type="submit" class="btn btn-degradado rounded-pill px-4"><i class="bi bi-calendar-plus me-1"></i>Crear estos <?= (int) $previa['total'] ?> encuentros</button>
            <?php endif; ?>
            <a href="<?= url('admin/partidos.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
        </div>
        <?php if ($previa === null): ?>
        <p class="small text-muted mt-2 mb-0"><i class="bi bi-lightbulb me-1"></i>Primero mira la vista previa: nada se crea hasta que la apruebes.</p>
        <?php endif; ?>
    </form>

    <?php // ---------- Vista previa ----------
          // Se muestra debajo del formulario, con la misma forma que tendría el calendario
          // impreso: una fila por jornada, con la cantidad de partidos de cada día. ?>
    <?php if ($previa !== null): ?>
    <div class="card-suave p-4 mt-4" style="max-width:760px;">
        <h5 class="mb-1">Así quedaría el calendario</h5>
        <p class="small text-muted">
            <?= count($previa['jornadas']) ?> jornadas · <?= (int) $previa['total'] ?> encuentros
            <?php if ((int) $previa['adelantados'] > 0): ?>
            · <?= (int) $previa['adelantados'] ?> adelantados
            <?php endif; ?>
        </p>

        <?php // Los nombres de día se sacan una sola vez de la primera jornada: son las
              // columnas de la tabla y también las que usan las filas de playoffs. ?>
        <?php $columnasDia = array_column($previa['jornadas'][0]['dias'] ?? [], 'nombre'); ?>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th>Jornada</th>
                        <?php foreach ($columnasDia as $nombreCol): ?>
                        <th class="text-center"><?= e($nombreCol) ?></th>
                        <?php endforeach; ?>
                        <th class="text-center">Total</th>
                        <th>Juegan dos veces</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previa['jornadas'] as $fila): ?>
                    <tr>
                        <td class="small text-nowrap">
                            <span class="fw-semibold"><?= (int) $fila['numero'] ?></span>
                            <span class="text-muted"> — <?= e(formatear_fecha_corta($fila['desde'])) ?><?= $fila['hasta'] !== $fila['desde'] ? ' / ' . e(formatear_fecha_corta($fila['hasta'])) : '' ?></span>
                        </td>
                        <?php foreach ($fila['dias'] as $d): ?>
                        <td class="text-center"><?= (int) $d['cantidad'] ?: '—' ?></td>
                        <?php endforeach; ?>
                        <td class="text-center fw-semibold"><?= (int) $fila['total'] ?></td>
                        <td class="small text-muted"><?= $fila['dobles'] ? e(implode(', ', $fila['dobles'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>

                    <?php foreach (($previa['playoffs'] ?? []) as $pf): ?>
                    <?php // Una fase puede ocupar más de un día (los cuartos se reparten
                          // entre sábado y domingo), así que se indexa por nombre de día. ?>
                    <?php $porDiaFase = array_column($pf['dias'], 'partidos', 'nombre'); ?>
                    <tr class="table-light">
                        <td class="small text-nowrap">
                            <span class="fw-semibold"><?= e($pf['label']) ?></span>
                            <span class="text-muted"> — <?= e(formatear_fecha_corta($pf['desde'])) ?><?= $pf['hasta'] !== $pf['desde'] ? ' / ' . e(formatear_fecha_corta($pf['hasta'])) : '' ?></span>
                        </td>
                        <?php foreach ($columnasDia as $nombreCol): ?>
                        <td class="text-center"><?= isset($porDiaFase[$nombreCol]) ? (int) $porDiaFase[$nombreCol] : '—' ?></td>
                        <?php endforeach; ?>
                        <td class="text-center fw-semibold"><?= (int) $pf['total'] ?></td>
                        <td class="small text-muted">Fecha reservada</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ((int) $previa['adelantados'] > 0): ?>
        <div class="alert alert-warning rounded-4 border-0 small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            Hay <?= (int) $previa['adelantados'] ?> partidos adelantados porque el cupo del fin de semana es mayor
            que una ronda. Esos equipos juegan dos veces el mismo fin de semana, nunca el mismo día, y el sorteo
            reparte el turno entre todos. Si no quieres que pase, baja los partidos por día.
        </div>
        <?php endif; ?>

        <?php if (!empty($previa['playoffs'])): ?>
        <p class="small text-muted mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>Las fechas de playoffs quedan reservadas pero no se crean encuentros:
            todavía no se sabe quién clasifica. Los programas cuando termine la temporada regular.
        </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>

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
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#panelGrupos" type="button">Temporada regular</button></li>
        <?php foreach ($fasesTorneo as $f): ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#panel-<?= $f ?>" type="button"><?= e(FASES_LABEL[$f]) ?> <?= count($playoffsPorFase[$f]) > 0 ? '<span class="badge rounded-pill text-bg-secondary ms-1">' . count($playoffsPorFase[$f]) . '</span>' : '' ?></button></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <?php // ---------- El cuadro final ----------
          // Un solo panel para los dos formatos con fase final. Siempre ofrece armar la
          // SIGUIENTE ronda que falte: la primera sale de la tabla (o de las tablas de
          // grupo) y las demás de los ganadores de la anterior. Nada se crea al cargar un
          // resultado — hay que darle al botón, para que se puedan revisar los cruces y
          // para que corregir un marcador no dispare partidos fantasma. ?>
    <?php if (!empty($pasoEliminacion['fase'])): ?>
    <div class="card-suave p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div style="min-width:0;">
                <h5 class="mb-1"><i class="bi bi-diagram-2 me-1"></i>Sigue: <?= e($pasoEliminacion['label']) ?></h5>
                <p class="small text-muted mb-0">
                    <?php if ($pasoEliminacion['origen'] === null): ?>
                        <?= !empty($tieneGrupos)
                            ? 'Se cruzan los clasificados de cada grupo: 1° de un grupo contra 2° de otro.'
                            : 'Se toman los mejores de la tabla y se siembran: 1° contra el último clasificado, 2° contra el penúltimo.' ?>
                    <?php elseif ($pasoEliminacion['fase'] === 'tercer_lugar'): ?>
                        Lo juegan los dos que perdieron la semifinal.
                    <?php else: ?>
                        Lo arman los ganadores de <?= e(mb_strtolower(FASES_LABEL[$pasoEliminacion['origen']] ?? '')) ?>.
                    <?php endif; ?>
                </p>
            </div>
            <form method="post" class="d-flex align-items-center gap-2 flex-wrap" data-confirm="Se van a crear los encuentros de <?= e($pasoEliminacion['label']) ?> con las posiciones de ahora mismo. ¿Continuamos?">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="armar_cruces">
                <?php if (!$pasoEliminacion['listo']): ?>
                <input type="hidden" name="aun_faltan" value="1">
                <?php endif; ?>
                <?php // Cuántos entran al cuadro. Solo se pregunta en la primera ronda de
                      // una liga con fase final: en grupos ya está en la configuración. ?>
                <?php if ($pasoEliminacion['origen'] === null && empty($tieneGrupos)): ?>
                <label class="small text-muted mb-0" for="cuantosClasifican">Clasifican</label>
                <select name="clasifican" id="cuantosClasifican" class="form-select form-select-sm" style="width:auto;">
                    <option value="2">Los 2 primeros</option>
                    <option value="4" selected>Los 4 primeros</option>
                    <option value="8">Los 8 primeros</option>
                    <option value="16">Los 16 primeros</option>
                </select>
                <?php endif; ?>
                <button type="submit" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-diagram-2 me-1"></i>Armar <?= e(mb_strtolower($pasoEliminacion['label'])) ?></button>
            </form>
        </div>

        <?php if (!empty($pasoEliminacion['motivo'])): ?>
        <div class="alert alert-warning rounded-4 border-0 small mt-3 mb-0">
            <i class="bi bi-exclamation-triangle-fill me-1"></i><?= e($pasoEliminacion['motivo']) ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php // ---------- Tablas de la fase de grupos ----------
          // Se muestran arriba de los encuentros porque son lo que el organizador consulta
          // todo el tiempo durante la fase de grupos: quién va clasificando. ?>
    <?php if (!empty($tieneGrupos) && !empty($tablasGrupo)): ?>
    <div class="card-suave p-4 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
            <div>
                <h5 class="mb-1"><i class="bi bi-diagram-3 me-1"></i>Grupos</h5>
                <p class="small text-muted mb-0">Las filas resaltadas son las que clasifican a la eliminación.</p>
            </div>
        </div>

        <div class="row g-3">
            <?php foreach ($tablasGrupo as $letra => $datosGrupo): ?>
            <div class="col-md-6 col-xl-3">
                <div class="border rounded-4 p-3 h-100">
                    <div class="fw-semibold small text-uppercase text-muted mb-2">Grupo <?= e($letra) ?></div>
                    <table class="table table-sm mb-0">
                        <thead>
                            <tr class="small text-muted">
                                <th style="width:24px;"></th>
                                <th>Equipo</th>
                                <th class="text-center">PJ</th>
                                <th class="text-center">Pts</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($datosGrupo['tabla'] as $i => $fila): ?>
                            <tr class="<?= $i < (int) $datosGrupo['clasifican'] ? 'table-success' : '' ?>">
                                <td class="small text-muted"><?= $i + 1 ?></td>
                                <td class="small text-truncate" style="max-width:120px;"><?= e($fila['equipo']['nombre']) ?></td>
                                <td class="text-center small"><?= (int) $fila['pj'] ?></td>
                                <td class="text-center small fw-semibold"><?= (int) $fila['pts'] ?></td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($datosGrupo['tabla'])): ?>
                            <tr><td colspan="4" class="small text-muted">Sin equipos en este grupo.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="tab-content">
        <div class="tab-pane fade show active" id="panelGrupos">
            <?php foreach ($jornadas as $numJornada => $lista): ?>
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2 mt-4">
                <h6 class="text-muted text-uppercase small fw-bold mb-0">Jornada <?= $numJornada ?></h6>
                <?php // Correr el calendario desde aquí. Nace de un caso real: un fin de
                      // semana que se cae obliga a empujar esta jornada Y todas las de
                      // atrás, porque si no se le encima a la siguiente. ?>
                <form method="post" class="d-flex align-items-center gap-1" data-confirm="Se correrá la jornada <?= (int) $numJornada ?> y todas las siguientes. Los encuentros ya jugados no se tocan. ¿Continuamos?">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="correr_calendario">
                    <input type="hidden" name="jornada" value="<?= (int) $numJornada ?>">
                    <select name="semanas" class="form-select form-select-sm" style="width:auto;" aria-label="Semanas a correr desde la jornada <?= (int) $numJornada ?>">
                        <option value="1">+1 semana</option>
                        <option value="2">+2 semanas</option>
                        <option value="-1">−1 semana</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-outline-secondary" title="Correr esta jornada y las siguientes"><i class="bi bi-calendar-range me-1"></i>Correr</button>
                </form>
                <?php // Borrar de aquí en adelante conservando lo anterior. Es lo que hace
                      // falta para rehacer solo la parte generada cuando las primeras
                      // jornadas ya se publicaron a los equipos. ?>
                <form method="post" class="mb-0" data-confirm="Se van a eliminar TODOS los encuentros desde la jornada <?= (int) $numJornada ?> en adelante. Las jornadas anteriores no se tocan. Si alguno ya está jugado o tiene eventos, no se borra nada. ¿Continuamos?">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="borrar_desde_jornada">
                    <input type="hidden" name="jornada" value="<?= (int) $numJornada ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Borrar esta jornada y todas las siguientes"><i class="bi bi-eraser me-1"></i>Borrar desde aquí</button>
                </form>
            </div>
            <div class="row row-cols-1 row-cols-lg-2 g-3 mb-2">
                <?php foreach ($lista as $p): ?>
                    <?= admin_tarjeta_partido($p, $equiposPorId) ?>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>

            <?php if (empty($jornadas)): ?>
                <p class="text-muted">Aún no hay encuentros <?= $esLiga ? 'programados' : 'de temporada regular programados' ?>.</p>
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
