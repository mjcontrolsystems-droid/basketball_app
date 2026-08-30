<div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
    <a href="<?= url('admin/partidos.php') ?>" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-arrow-left"></i></a>
    <div class="flex-grow-1">
        <h3 class="mb-0">Ficha del partido</h3>
        <div class="small text-muted"><?= $equipoLocal ? e($equipoLocal['nombre']) : '?' ?> vs <?= $equipoVisitante ? e($equipoVisitante['nombre']) : '?' ?> · <?= e(formatear_fecha_larga($partido['fecha'])) ?></div>
    </div>
    <?php // El enlace que se COPIA debe ser ABSOLUTO (https://dominio/...): pegado en
          // WhatsApp, una ruta relativa no es un link que se pueda abrir. ?>
    <a href="<?= e(url_copa('partido_vivo.php?id=' . $partidoId)) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-broadcast me-1"></i>Transmisión en vivo</a>
    <button type="button" class="btn btn-sm btn-outline-secondary btn-copiar-url" data-url="<?= e(SITE_ORIGIN . url_copa('partido_vivo.php?id=' . $partidoId)) ?>" title="Copiar enlace de transmisión en vivo"><i class="bi bi-link-45deg"></i></button>
    <a href="<?= e(url_copa('partido.php?id=' . $partidoId . '&imprimir=1')) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Descargar PDF</a>
    <a href="<?= url('admin/partidos.php') ?>" class="btn btn-sm btn-degradado rounded-pill px-3"><i class="bi bi-check2-circle me-1"></i>Guardar y volver a Encuentros</a>
</div>

<div class="card-suave p-3 mb-4 marcador-ficha">
    <div class="d-flex align-items-center justify-content-center gap-3 gap-md-4 flex-wrap">
        <div class="text-center" style="min-width:120px;">
            <?= logo_equipo($equipoLocal ?? ['nombre' => '?'], 44) ?>
            <div class="small fw-semibold mt-1"><?= $equipoLocal ? e($equipoLocal['nombre']) : '?' ?></div>
        </div>
        <div class="text-center px-2">
            <div class="display-6 fw-bold lh-1" data-marcador-vivo><?= (int) $marcadorLocalVivo ?> <span class="text-muted">-</span> <?= (int) $marcadorVisitanteVivo ?></div>
            <div class="small text-muted mt-1"><i class="bi bi-lightning-charge me-1"></i><?= e(etiqueta_anotaciones($deporte)) ?> en vivo</div>
        </div>
        <div class="text-center" style="min-width:120px;">
            <?= logo_equipo($equipoVisitante ?? ['nombre' => '?'], 44) ?>
            <div class="small fw-semibold mt-1"><?= $equipoVisitante ? e($equipoVisitante['nombre']) : '?' ?></div>
        </div>
    </div>
    <p class="text-center small text-muted mb-0 mt-2">El marcador se calcula automáticamente con los <?= e(mb_strtolower(etiqueta_anotaciones($deporte))) ?> que registres abajo.</p>

    <?php // Triunfo por default, aquí porque es AQUÍ donde se descubre que un equipo no
          // llegó. Fija el marcador reglamentario, no asigna goles a nadie y excluye el
          // encuentro de la portería menos vencida. Solo asistente/dueño lo ven. ?>
    <?php if (!empty($partido['por_default'])): ?>
    <p class="text-center mb-0 mt-2">
        <span class="badge rounded-pill text-bg-warning"><i class="bi bi-flag me-1"></i>Ganado por default (W.O.) — marcador reglamentario, sin goles individuales</span>
    </p>
    <?php elseif (!$resultadoBloqueado && puede('partidos_editar', $torneo)): ?>
    <?php [$ptsWo] = marcador_por_default($deporte); ?>
    <div class="d-flex justify-content-center gap-2 mt-2 flex-wrap">
        <form method="post" class="mb-0" data-confirm="¿El rival no se presentó? Se registrará <?= $ptsWo ?>-0 a favor de <?= e($equipoLocal['nombre'] ?? 'local') ?>, sin goles para nadie, y el encuentro quedará jugado.">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="marcar_default">
            <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
            <input type="hidden" name="lado" value="local">
            <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill px-3"><i class="bi bi-flag me-1"></i>W.O. a favor de <?= e($equipoLocal['nombre'] ?? 'Local') ?></button>
        </form>
        <form method="post" class="mb-0" data-confirm="¿El rival no se presentó? Se registrará <?= $ptsWo ?>-0 a favor de <?= e($equipoVisitante['nombre'] ?? 'visitante') ?>, sin goles para nadie, y el encuentro quedará jugado.">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="marcar_default">
            <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
            <input type="hidden" name="lado" value="visitante">
            <button type="submit" class="btn btn-sm btn-outline-warning rounded-pill px-3"><i class="bi bi-flag me-1"></i>W.O. a favor de <?= e($equipoVisitante['nombre'] ?? 'Visitante') ?></button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php if ($resultadoBloqueado): ?>
<div class="card-suave p-3 mb-4 border border-success-subtle d-flex flex-row align-items-center justify-content-between flex-wrap gap-2">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-lock-fill text-success fs-5"></i>
        <div>
            <div class="fw-semibold">Resultado en firme</div>
            <div class="small text-muted">El encuentro está finalizado: los eventos y el marcador ya no se pueden modificar. Si hubo un error, reábrelo para corregir.</div>
        </div>
    </div>
    <form method="post" class="mb-0" data-confirm="¿Reabrir este encuentro para corrección? El resultado saldrá de la tabla de posiciones hasta que lo marques como jugado otra vez, y la reapertura quedará registrada en la bitácora.">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="reabrir_correccion">
        <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3"><i class="bi bi-unlock me-1"></i>Reabrir para corrección</button>
    </form>
</div>
<?php endif; ?>

<?php
$cronometroEstado = $partido['cronometro_estado'] ?? 'detenido';
// El reloj arranca en los minutos que se configuraron para la copa (15, 20, 45...) y
// corre en cuenta REGRESIVA hacia 00:00, en los dos deportes — es como se ve un
// cronómetro en la cancha. El dato guardado (cronometro_segundos) sigue siendo tiempo
// transcurrido; esto solo cambia cómo se muestra.
$duracionPeriodoMin = torneo_duracion_periodo_min($torneo);
$minutosExtra = partido_minutos_extra($partido);
$cronometroSegundosMostrados = partido_cronometro_restante_segundos($partido, $torneo);
$cronometroAgotado = $cronometroSegundosMostrados === 0 && $cronometroEstado !== 'detenido';
$cronometroPeriodo = (int) ($partido['cronometro_periodo'] ?? 1);
$cronometroPeriodoMaximo = partido_periodo_maximo($deporte);
?>
<?php if (!$resultadoBloqueado): ?>
<div class="card-suave p-3 mb-4 d-flex flex-row align-items-center justify-content-between flex-wrap gap-3"
    id="cronometroPartido" data-estado="<?= e($cronometroEstado) ?>"
    data-segundos="<?= (int) ($partido['cronometro_segundos'] ?? 0) ?>"
    data-inicio="<?= e($partido['cronometro_inicio'] ?? '') ?>"
    data-duracion-segundos="<?= partido_duracion_periodo_segundos($partido, $torneo) ?>"
    data-minuto-base="<?= partido_minuto_base($partido, $torneo) ?>">
    <div class="d-flex align-items-center gap-3">
        <div>
            <div class="fs-2 fw-bold font-monospace<?= $cronometroAgotado ? ' cronometro-agotado' : '' ?>" id="cronometroTexto"><?= e(sprintf('%02d:%02d', intdiv($cronometroSegundosMostrados, 60), $cronometroSegundosMostrados % 60)) ?></div>
            <span class="badge rounded-pill text-bg-secondary"><?= e(partido_periodo_etiqueta($deporte, $cronometroPeriodo)) ?></span>
            <?php if ($minutosExtra > 0): ?>
            <span class="badge rounded-pill text-bg-warning">+<?= $minutosExtra ?> min extra</span>
            <?php endif; ?>
        </div>
        <div class="small text-muted">
            <?php if ($cronometroEstado === 'detenido'): ?>
                Cronómetro sin iniciar — arrancará en <?= $duracionPeriodoMin ?>:00 y bajará hasta 00:00, según la duración configurada en la copa.
            <?php elseif ($cronometroAgotado): ?>
                <i class="bi bi-exclamation-circle text-warning me-1"></i>Se agotó el tiempo de este periodo. Agrega minutos extra o pásalo al siguiente.
            <?php elseif ($cronometroEstado === 'corriendo'): ?>
                <i class="bi bi-record-circle text-danger me-1"></i>Corriendo — el minuto de cada evento se sugiere solo.
            <?php elseif ($cronometroEstado === 'pausado'): ?>
                <i class="bi bi-pause-circle me-1"></i>En pausa.
            <?php else: ?>
                Cronómetro finalizado.
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (in_array($cronometroEstado, ['corriendo', 'pausado'], true)): ?>
        <?php // Tiempo añadido dentro del encuentro: suma minutos al periodo en curso, y la
              // cuenta regresiva (aquí y en la transmisión en vivo) los toma al instante. ?>
        <form method="post" class="mb-0 d-flex gap-1 align-items-center">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="cronometro_agregar_extra">
            <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
            <select name="minutos" class="form-select form-select-sm" style="width:auto;">
                <?php foreach ([1, 2, 3, 5, 10] as $min): ?>
                <option value="<?= $min ?>">+<?= $min ?> min</option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-warning"><i class="bi bi-plus-circle me-1"></i>Tiempo extra</button>
        </form>
        <?php endif; ?>
        <?php if ($minutosExtra > 0): ?>
        <form method="post" class="mb-0" data-confirm="¿Quitar los <?= $minutosExtra ?> minutos extra de este periodo?">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="cronometro_quitar_extra">
            <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Quitar el tiempo extra agregado"><i class="bi bi-x-circle"></i></button>
        </form>
        <?php endif; ?>
        <?php if ($cronometroPeriodo < $cronometroPeriodoMaximo): ?>
        <form method="post" class="mb-0" data-confirm="¿Pasar a <?= e(partido_periodo_etiqueta($deporte, $cronometroPeriodo + 1)) ?>? El cronómetro vuelve a <?= $duracionPeriodoMin ?>:00 y se descarta el tiempo extra de este periodo.">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="cronometro_siguiente_periodo">
            <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-skip-forward-fill me-1"></i><?= e(partido_periodo_etiqueta($deporte, $cronometroPeriodo + 1)) ?></button>
        </form>
        <?php endif; ?>
        <?php if ($cronometroEstado === 'detenido'): ?>
        <form method="post" class="mb-0">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="cronometro_iniciar">
            <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
            <button type="submit" class="btn btn-sm btn-degradado rounded-pill px-3"><i class="bi bi-play-fill me-1"></i>Iniciar cronómetro</button>
        </form>
        <?php elseif (in_array($cronometroEstado, ['corriendo', 'pausado'], true)): ?>
        <form method="post" class="mb-0">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="cronometro_alternar_pausa">
            <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
            <button type="submit" class="btn btn-sm btn-outline-secondary">
                <?php if ($cronometroEstado === 'corriendo'): ?><i class="bi bi-pause-fill me-1"></i>Pausar<?php else: ?><i class="bi bi-play-fill me-1"></i>Reanudar<?php endif; ?>
            </button>
        </form>
        <?php // El data-confirm va en el FORM, no en el botón: el evento submit nace en el
              // formulario y no pasa por sus hijos, así que en el botón nunca se disparaba.
              //
              // Finalizar aquí cierra el encuentro, no solo para el reloj: marca el partido
              // como jugado con el marcador de los goles registrados. Por eso el texto avisa
              // qué va a pasar según el periodo en el que se esté. ?>
        <?php $esUltimoPeriodo = $cronometroPeriodo >= $cronometroPeriodoMaximo; ?>
        <form method="post" class="mb-0" data-confirm="<?= $esUltimoPeriodo
            ? 'Se dará el encuentro por finalizado y quedará marcado como jugado, con el marcador de los ' . e(mb_strtolower(etiqueta_anotaciones($deporte))) . ' registrados. ¿Continuamos?'
            : 'Vas en ' . e(mb_strtolower(partido_periodo_etiqueta($deporte, $cronometroPeriodo))) . ': se detendrá el cronómetro pero el encuentro NO se dará por jugado. ¿Continuamos?' ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="cronometro_finalizar">
            <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-stop-fill me-1"></i><?= $esUltimoPeriodo ? 'Finalizar encuentro' : 'Detener' ?></button>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php
// ---------------------------------------------------------------------------
// ALINEACIÓN DEL ENCUENTRO
// Es lo primero que se arma al iniciar un partido: de cada equipo, quién sale de titular
// (círculo VERDE), quién queda en la banca (círculo apagado) y en qué posición juega hoy.
// Cuántos titulares caben lo decide la modalidad de la copa (5, 7 u 11 jugadores en
// cancha, ver torneo_jugadores_en_cancha()). El contador vivo lo lleva assets/js/app.js;
// el tope también se valida en el servidor al guardar.
// ---------------------------------------------------------------------------
?>
<?php
// Se calcula ANTES de pintar el encabezado porque el botón de desplegar lo necesita.
$equiposConPlantilla = 0;
foreach ($equiposDelPartido as $eid) {
    if (!empty($jugadoresPorEquipo[$eid])) {
        $equiposConPlantilla++;
    }
}
?>
<div class="card-suave p-3 p-md-4 mb-4" id="tarjetaAlineacion">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div>
            <h6 class="text-uppercase small fw-bold text-muted mb-1"><i class="bi bi-diagram-3 me-1"></i>Alineación del encuentro</h6>
            <p class="small text-muted mb-0">
                Marca el círculo de quienes arrancan en cancha: <span class="punto-titular es-titular d-inline-block align-middle"></span> verde = titular,
                <span class="punto-titular d-inline-block align-middle"></span> apagado = banca.
                Esta modalidad juega con <strong><?= $jugadoresEnCancha ?></strong> por equipo.
            </p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (!$hayAlineacion): ?>
            <span class="badge rounded-pill text-bg-warning-subtle text-warning-emphasis"><i class="bi bi-exclamation-circle me-1"></i>Sin definir</span>
            <?php endif; ?>
            <?php // El listado completo (dos plantillas de ~20 cada una) vive plegado
                  // detrás de este botón: abierto siempre, empujaba la captura de goles y
                  // tarjetas dos pantallas hacia abajo, que es lo urgente en cancha. ?>
            <?php if (!($resultadoBloqueado && !$hayAlineacion) && $equiposConPlantilla > 0): ?>
            <button class="btn btn-sm btn-degradado rounded-pill px-3" type="button"
                    data-bs-toggle="collapse" data-bs-target="#cuerpoAlineacion"
                    aria-expanded="false" aria-controls="cuerpoAlineacion">
                <i class="bi bi-people me-1"></i><?= $hayAlineacion ? 'Ver / editar alineación' : 'Agregar alineación' ?>
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($resultadoBloqueado && !$hayAlineacion): ?>
        <p class="text-muted small mb-0">No se registró alineación para este encuentro.</p>
    <?php elseif ($equiposConPlantilla === 0): ?>
        <p class="text-muted small mb-0">
            Ninguno de los dos equipos tiene plantilla cargada todavía.
            <a href="<?= url('admin/equipos.php') ?>">Agrega <?= e(mb_strtolower(forma_genero($torneo['genero'] ?? null, 'jugadores', 'jugadoras'))) ?></a> para poder armar la alineación.
        </p>
    <?php else: ?>
    <?php // Plegado por defecto; el botón del encabezado lo abre. ?>
    <div class="collapse" id="cuerpoAlineacion">
    <form method="post" data-alineacion data-max-titulares="<?= $jugadoresEnCancha ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="guardar_alineacion">
        <input type="hidden" name="partido_id" value="<?= $partidoId ?>">

        <div class="row g-3">
            <?php foreach ($equiposDelPartido as $eid): $equipoLado = $equiposPorId[$eid] ?? null; if (!$equipoLado) { continue; } ?>
            <div class="col-lg-6">
                <div class="border rounded-4 p-3 h-100" data-equipo-alineacion="<?= $eid ?>">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <?= logo_equipo($equipoLado, 32) ?>
                        <span class="fw-semibold flex-grow-1"><?= e($equipoLado['nombre']) ?></span>
                        <span class="badge rounded-pill text-bg-light border" data-contador-titulares>
                            <span data-cuenta><?= (int) ($titularesPorEquipoActual[$eid] ?? 0) ?></span>/<?= $jugadoresEnCancha ?> titulares
                        </span>
                    </div>
                    <?php if (empty($jugadoresPorEquipo[$eid])): ?>
                        <p class="small text-muted mb-0">
                            Sin plantilla cargada.
                            <a href="<?= url('admin/jugadores.php?equipo_id=' . $eid) ?>">Agregar <?= e(mb_strtolower(forma_genero($torneo['genero'] ?? null, 'jugadores', 'jugadoras'))) ?></a>.
                        </p>
                    <?php else: ?>
                    <ul class="list-unstyled mb-0">
                        <?php
                        $plantilla = $jugadoresPorEquipo[$eid];
                        usort($plantilla, fn($a, $b) => strnatcmp((string) $a['dorsal'], (string) $b['dorsal']));
                        foreach ($plantilla as $j):
                            $jid = (int) $j['id'];
                            $filaAli = $alineacionPorJugador[$jid] ?? null;
                            // Sin alineación guardada todavía, la posición sugerida es la
                            // habitual del jugador en su plantilla.
                            $posicionActual = $filaAli !== null ? (string) $filaAli['posicion'] : (string) ($j['posicion'] ?? '');
                            $esTitular = $filaAli !== null && !empty($filaAli['titular']);
                            // Deuda por multas: si la copa bloquea morosos, el jugador no
                            // se puede marcar; si no, solo se advierte y el organizador decide.
                            $deudaJugador = $deudaPorJugador[$jid] ?? null;
                            $bloqueadoPorDeuda = $deudaJugador !== null && torneo_bloquea_morosos($torneo);
                            // Una suspensión por partidos siempre bloquea: no se puede
                            // "pagar" para jugar, hay que cumplirla.
                            $suspension = $suspendidosPartido[$jid] ?? null;
                        ?>
                        <li class="fila-alineacion d-flex align-items-center gap-2 py-1">
                            <?php // Con el resultado en firme la alineación queda de solo lectura,
                                  // igual que los eventos y el cronómetro. ?>
                            <input class="form-check-input check-titular m-0" type="checkbox" name="titular[]" value="<?= $jid ?>" id="titular-<?= $jid ?>" <?= $esTitular ? 'checked' : '' ?> <?= (empty($j['activo']) || $resultadoBloqueado || $bloqueadoPorDeuda || $suspension !== null) ? 'disabled' : '' ?>>
                            <label class="flex-grow-1 mb-0 d-flex align-items-center gap-2" for="titular-<?= $jid ?>" style="cursor:pointer;min-width:0;">
                                <span class="fw-bold">#<?= e($j['dorsal']) ?></span>
                                <span class="text-truncate"><?= e($j['nombre']) ?></span>
                                <?php if (empty($j['activo'])): ?><span class="badge rounded-pill text-bg-secondary small"><?= e(forma_genero($torneo['genero'] ?? null, 'Inactivo', 'Inactiva')) ?></span><?php endif; ?>
                                <?php if ($suspension !== null): ?>
                                <span class="badge rounded-pill text-bg-danger small" title="<?= e(disciplina_texto_suspension($suspension)) ?>">
                                    <i class="bi bi-person-x me-1"></i><?= $suspension['motivo'] === 'roja' ? 'Suspendido por roja' : 'Suspendido por amarillas' ?>
                                </span>
                                <?php endif; ?>
                                <?php if ($deudaJugador !== null): ?>
                                <a href="<?= url('admin/sanciones.php') ?>" class="badge rounded-pill text-bg-danger small text-decoration-none" title="<?= $bloqueadoPorDeuda ? 'No puede jugar hasta pagar' : 'Tiene multa pendiente' ?>">
                                    <i class="bi bi-cash-coin me-1"></i>Debe <?= e(sancion_monto_texto($torneo, $deudaJugador['total'])) ?>
                                </a>
                                <?php endif; ?>
                            </label>
                            <select name="posicion[<?= $jid ?>]" class="form-select form-select-sm" style="width:auto;" <?= $resultadoBloqueado ? 'disabled' : '' ?>>
                                <option value="">Pos.</option>
                                <?php foreach (posiciones_catalogo($deporte) as $clave => $pos): ?>
                                <option value="<?= e($clave) ?>" <?= $posicionActual === $clave ? 'selected' : '' ?>><?= e($pos['corta']) ?> · <?= e($pos['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if (!$resultadoBloqueado): ?>
        <div class="d-flex align-items-center gap-2 mt-3 flex-wrap">
            <button type="submit" class="btn btn-sm btn-degradado rounded-pill px-4"><i class="bi bi-check2 me-1"></i>Guardar alineación</button>
            <span class="small text-danger d-none" data-aviso-alineacion></span>
        </div>
        <?php endif; ?>
    </form>
    </div><?php // fin del collapse ?>
    <?php endif; ?>
</div>

<div class="row g-4">
    <?php if (!$resultadoBloqueado): ?>
    <div class="col-lg-5">
        <div class="card-suave p-4 mb-3">
            <h6 class="text-uppercase small fw-bold text-muted mb-3"><?= icono_balon_img($deporte, 16) ?> Agregar <?= e(mb_strtolower(etiqueta_anotacion($deporte))) ?></h6>
            <form method="post" class="row g-2">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="agregar_gol">
                <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
                <div class="col-12">
                    <select name="equipo_id" class="form-select form-select-sm" required>
                        <option value="">Equipo...</option>
                        <?php if ($equipoLocal): ?><option value="<?= $equipoLocal['id'] ?>"><?= e($equipoLocal['nombre']) ?></option><?php endif; ?>
                        <?php if ($equipoVisitante): ?><option value="<?= $equipoVisitante['id'] ?>"><?= e($equipoVisitante['nombre']) ?></option><?php endif; ?>
                    </select>
                </div>
                <div class="col-8">
                    <select name="jugador_id" class="form-select form-select-sm" data-filtra-jugador required>
                        <option value=""><?= e($etJugador) ?> que anota...</option>
                        <?php // Para equipos que todavía no mandaron su nómina: el gol cuenta
                              // para el marcador aunque no se sepa quién lo hizo. Sin esto
                              // habría que inventar un jugador solo para poder anotarlo. ?>
                        <option value="0">Sin identificar</option>
                        <?php foreach ($equiposDelPartido as $eid): foreach ($jugadoresPorEquipo[$eid] ?? [] as $j): ?>
                        <option value="<?= $j['id'] ?>" data-equipo="<?= $eid ?>">#<?= e($j['dorsal']) ?> <?= e($j['nombre']) ?></option>
                        <?php endforeach; endforeach; ?>
                    </select>
                </div>
                <div class="col-4">
                    <input type="number" min="0" name="minuto" class="form-control form-control-sm" placeholder="Min.">
                </div>
                <div class="col-8">
                    <select name="tipo_gol" class="form-select form-select-sm" data-aviso-autogol="avisoAutogol">
                        <?php foreach (tipos_anotacion_catalogo($deporte) as $tg): ?>
                        <option value="<?= e($tg) ?>"><?= e(tipos_anotacion_label($deporte)[$tg]) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php // Guía que aparece SOLO al elegir "Autogol". Sin ella, el error
                      // clásico de mesa es elegir al equipo beneficiado: el gol quedaría
                      // acreditado al revés. La regla de captura es una sola: se registra
                      // a quien la metió en propia, y la app se lo suma al rival. ?>
                <div class="col-12 d-none" id="avisoAutogol">
                    <div class="alert alert-warning py-2 px-3 small mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Autogol:</strong> elige el equipo y <?= e(mb_strtolower($etJugador)) ?> que la metió
                        <em>en su propia</em> portería. El gol se le suma al rival automáticamente — no elijas al equipo beneficiado.
                    </div>
                </div>
                <div class="col-12">
                    <select name="asistencia_jugador_id" class="form-select form-select-sm" data-filtra-jugador>
                        <option value="">Sin asistencia</option>
                        <?php foreach ($equiposDelPartido as $eid): foreach ($jugadoresPorEquipo[$eid] ?? [] as $j): ?>
                        <option value="<?= $j['id'] ?>" data-equipo="<?= $eid ?>">#<?= e($j['dorsal']) ?> <?= e($j['nombre']) ?></option>
                        <?php endforeach; endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-degradado rounded-pill px-3 w-100">Agregar <?= e(mb_strtolower(etiqueta_anotacion($deporte))) ?></button>
                </div>
            </form>
        </div>

        <div class="card-suave p-4 mb-3">
            <h6 class="text-uppercase small fw-bold text-muted mb-3"><i class="bi bi-square-fill text-warning"></i><i class="bi bi-square-fill text-danger me-1"></i>Agregar <?= $basketball ? 'falta' : 'tarjeta' ?> (<?= e(mb_strtolower(etiqueta_falta_leve($deporte))) ?> o <?= e(mb_strtolower(etiqueta_falta_grave($deporte))) ?>)</h6>
            <p class="small text-muted mb-2">
                <?php if ($basketball): ?>
                Al llegar a <?= LIMITE_FALTAS_EXPULSION ?> faltas personales en el partido, el jugador queda expulsado automáticamente (regla FIBA).
                <?php else: ?>
                Dos tarjetas amarillas en el mismo partido = expulsión por doble amarilla (regla IFAB). El aviso aparece solo al registrar la segunda.
                <?php endif; ?>
            </p>
            <form method="post" class="row g-2">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="agregar_tarjeta">
                <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
                <div class="col-12">
                    <select name="equipo_id" class="form-select form-select-sm" required>
                        <option value="">Equipo...</option>
                        <?php if ($equipoLocal): ?><option value="<?= $equipoLocal['id'] ?>"><?= e($equipoLocal['nombre']) ?></option><?php endif; ?>
                        <?php if ($equipoVisitante): ?><option value="<?= $equipoVisitante['id'] ?>"><?= e($equipoVisitante['nombre']) ?></option><?php endif; ?>
                    </select>
                </div>
                <div class="col-8">
                    <select name="jugador_id" class="form-select form-select-sm" data-filtra-jugador required>
                        <option value=""><?= e($etJugador) ?>...</option>
                        <?php // Una tarjeta sin identificar queda en la ficha, pero NO genera
                              // multa ni cuenta para suspensiones: no hay a quién cobrarle. ?>
                        <option value="0">Sin identificar</option>
                        <?php foreach ($equiposDelPartido as $eid): foreach ($jugadoresPorEquipo[$eid] ?? [] as $j): ?>
                        <option value="<?= $j['id'] ?>" data-equipo="<?= $eid ?>">#<?= e($j['dorsal']) ?> <?= e($j['nombre']) ?></option>
                        <?php endforeach; endforeach; ?>
                    </select>
                </div>
                <div class="col-4">
                    <input type="number" min="0" name="minuto" class="form-control form-control-sm" placeholder="Min.">
                </div>
                <div class="col-6">
                    <select name="color" class="form-select form-select-sm">
                        <option value="amarilla"><?= e(etiqueta_falta_leve($deporte)) ?></option>
                        <option value="roja"><?= e(etiqueta_falta_grave($deporte)) ?></option>
                    </select>
                </div>
                <div class="col-6">
                    <select name="motivo" class="form-select form-select-sm">
                        <?php foreach (motivos_falta_grave_catalogo($deporte) as $mr): ?>
                        <option value="<?= e($mr) ?>"><?= e(motivos_falta_grave_label($deporte)[$mr]) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">Solo aplica si es <?= e(mb_strtolower(etiqueta_falta_grave($deporte))) ?>.</div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-sm btn-degradado rounded-pill px-3 w-100">Agregar <?= $basketball ? 'falta' : 'tarjeta' ?></button>
                </div>
            </form>
        </div>

        <div class="card-suave p-4">
            <h6 class="text-uppercase small fw-bold text-muted mb-3"><i class="bi bi-arrow-left-right me-1"></i>Agregar cambio</h6>
            <form method="post" class="row g-2">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="agregar_cambio">
                <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
                <div class="col-12">
                    <select name="equipo_id" class="form-select form-select-sm" required>
                        <option value="">Equipo...</option>
                        <?php if ($equipoLocal): ?><option value="<?= $equipoLocal['id'] ?>"><?= e($equipoLocal['nombre']) ?></option><?php endif; ?>
                        <?php if ($equipoVisitante): ?><option value="<?= $equipoVisitante['id'] ?>"><?= e($equipoVisitante['nombre']) ?></option><?php endif; ?>
                    </select>
                </div>
                <div class="col-6">
                    <select name="jugador_id" class="form-select form-select-sm" data-filtra-jugador required>
                        <option value="">Sale...</option>
                        <?php foreach ($equiposDelPartido as $eid): foreach ($jugadoresPorEquipo[$eid] ?? [] as $j): ?>
                        <option value="<?= $j['id'] ?>" data-equipo="<?= $eid ?>">#<?= e($j['dorsal']) ?> <?= e($j['nombre']) ?></option>
                        <?php endforeach; endforeach; ?>
                    </select>
                </div>
                <div class="col-6">
                    <select name="jugador_entra_id" class="form-select form-select-sm" data-filtra-jugador required>
                        <option value="">Entra...</option>
                        <?php foreach ($equiposDelPartido as $eid): foreach ($jugadoresPorEquipo[$eid] ?? [] as $j): ?>
                        <option value="<?= $j['id'] ?>" data-equipo="<?= $eid ?>">#<?= e($j['dorsal']) ?> <?= e($j['nombre']) ?></option>
                        <?php endforeach; endforeach; ?>
                    </select>
                </div>
                <div class="col-4">
                    <input type="number" min="0" name="minuto" class="form-control form-control-sm" placeholder="Min.">
                </div>
                <div class="col-8 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-degradado rounded-pill px-3 w-100">Agregar cambio</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= $resultadoBloqueado ? 'col-12' : 'col-lg-7' ?>">
        <?php
        // Expulsión por acumulación, en ambos deportes: 5 faltas personales en basketball
        // (FIBA) o 2 amarillas en fútbol (doble amarilla, IFAB).
        $faltasPorJugador = faltas_por_jugador($eventos);
        $limiteExpulsion = limite_faltas_expulsion($deporte);
        $expulsados = array_filter($faltasPorJugador, fn($n) => $n >= $limiteExpulsion);
        ?>
        <?php if (!empty($expulsados)): ?>
        <div class="card-suave p-3 mb-3 border border-danger-subtle">
            <h6 class="text-uppercase small fw-bold text-danger mb-2"><i class="bi bi-exclamation-triangle me-1"></i><?= e(forma_genero($torneo['genero'] ?? null, 'Expulsados', 'Expulsadas')) ?> por acumulación</h6>
            <ul class="list-unstyled mb-0 small">
                <?php foreach ($expulsados as $jid => $n): $j = $jugadoresPorId[$jid] ?? null; ?>
                <li><?= e(jugador_nombre($j)) ?> — <?= e(texto_expulsion_acumulacion($deporte, $n)) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <div class="card-suave p-4">
            <h6 class="text-uppercase small fw-bold text-muted mb-3">Eventos cargados (<?= count($eventos) ?>)</h6>
            <?php if (empty($eventos)): ?>
                <p class="text-muted small mb-0">Todavía no hay <?= $basketball ? 'puntos, faltas' : 'goles, tarjetas' ?> ni cambios cargados en este partido.</p>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php // Iconos consistentes con la transmisión en vivo: el balón real del
                      // deporte para las anotaciones, cuadros de color para las faltas.
                $iconosEvento = [
                    'gol' => icono_balon_img($deporte, 16),
                    'amarilla' => '<i class="bi bi-square-fill text-warning"></i>',
                    'roja' => '<i class="bi bi-square-fill text-danger"></i>',
                    'cambio' => '<i class="bi bi-arrow-left-right text-info"></i>',
                ]; ?>
                <?php foreach ($eventos as $ev): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span class="small"><?= $iconosEvento[$ev['tipo']] ?? '' ?> <?= e(evento_descripcion($ev, $jugadoresPorId, $deporte)) ?> <span class="text-muted">— <?= e($equiposPorId[$ev['equipo_id']]['nombre'] ?? '') ?></span></span>
                    <?php if (!$resultadoBloqueado): ?>
                    <form method="post" data-confirm="¿Eliminar este evento?">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="eliminar_evento">
                        <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
                        <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button>
                    </form>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if ($fechaEsFutura): ?>
<div class="modal fade" id="modalFechaFutura" tabindex="-1" aria-hidden="true" data-bs-backdrop="static" data-modal-auto>
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-event me-2"></i>Encuentro con fecha futura</h5>
            </div>
            <div class="modal-body">
                <p class="mb-2">Este encuentro está programado para el <strong><?= e(formatear_fecha_larga($partido['fecha'])) ?></strong>, que aún no llega.</p>
                <p class="mb-0 text-muted small">¿Deseas actualizar la fecha del partido a hoy, o registrar los eventos igual sin cambiar la fecha?</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-dismiss="modal">Solo registrar eventos</button>
                <form method="post" class="mb-0">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="accion" value="actualizar_fecha_hoy">
                    <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
                    <button type="submit" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-calendar-check me-1"></i>Actualizar fecha a hoy</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
