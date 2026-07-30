<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/liga.php';

auth_requerir();
$torneo = admin_requerir_torneo_activo();

$partidoId = (int) ($_GET['partido_id'] ?? $_POST['partido_id'] ?? 0);
$partidos = db_leer('partidos', $torneo['id']);
$partido = db_buscar_por_id($partidos, $partidoId);
if ($partido === null) {
    http_response_code(404);
    exit('Encuentro no encontrado.');
}

$equipos = db_leer('equipos', $torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) { $equiposPorId[$eq['id']] = $eq; }
$equipoLocal = $equiposPorId[(int) $partido['equipo_local']] ?? null;
$equipoVisitante = $equiposPorId[(int) $partido['equipo_visitante']] ?? null;
$equiposDelPartido = array_filter([(int) $partido['equipo_local'], (int) $partido['equipo_visitante']]);

$jugadoresTodos = db_leer('jugadores', $torneo['id']);
$jugadoresPorEquipo = jugadores_por_equipo($jugadoresTodos);
$jugadoresPorId = jugadores_por_id($jugadoresTodos);
$etJugador = forma_genero($torneo['genero'] ?? null, 'Jugador', 'Jugadora');
$deporte = $torneo['deporte'] ?? null;
$basketball = es_basketball($deporte);

$urlLista = url('admin/partido_eventos.php?partido_id=' . $partidoId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $accion = (string) ($_POST['accion'] ?? '');

    // El partido se está jugando/registrando antes de la fecha programada: el modal de
    // confirmación ofrece adelantar la fecha del encuentro a hoy sin salir de la ficha.
    if ($accion === 'actualizar_fecha_hoy') {
        foreach ($partidos as &$p) {
            if ((int) $p['id'] === $partidoId) {
                $p['fecha'] = date('Y-m-d');
            }
        }
        unset($p);
        db_guardar('partidos', $partidos, $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', 'Fecha del encuentro actualizada a hoy.');
    }

    // Cronómetro del partido: controla qué minuto se sugiere al cargar un evento (ver
    // partido_cronometro_minuto() en includes/liga.php y el autocompletado del campo "Min."
    // en assets/js/app.js). 'iniciar' solo aplica desde 'detenido' para no reiniciar por
    // accidente un cronómetro que ya venía corriendo.
    if ($accion === 'cronometro_iniciar') {
        foreach ($partidos as &$p) {
            if ((int) $p['id'] === $partidoId && ($p['cronometro_estado'] ?? 'detenido') === 'detenido') {
                $p['cronometro_estado'] = 'corriendo';
                $p['cronometro_inicio'] = date('c');
                $p['cronometro_segundos'] = 0;
            }
        }
        unset($p);
        db_guardar('partidos', $partidos, $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', 'Cronómetro iniciado.');
    }

    if ($accion === 'cronometro_alternar_pausa') {
        foreach ($partidos as &$p) {
            if ((int) $p['id'] === $partidoId) {
                $estado = $p['cronometro_estado'] ?? 'detenido';
                if ($estado === 'corriendo') {
                    $p['cronometro_segundos'] = partido_cronometro_segundos($p);
                    $p['cronometro_estado'] = 'pausado';
                    $p['cronometro_inicio'] = null;
                } elseif ($estado === 'pausado') {
                    $p['cronometro_estado'] = 'corriendo';
                    $p['cronometro_inicio'] = date('c');
                }
            }
        }
        unset($p);
        db_guardar('partidos', $partidos, $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', 'Cronómetro actualizado.');
    }

    if ($accion === 'cronometro_finalizar') {
        foreach ($partidos as &$p) {
            if ((int) $p['id'] === $partidoId && in_array($p['cronometro_estado'] ?? 'detenido', ['corriendo', 'pausado'], true)) {
                $p['cronometro_segundos'] = partido_cronometro_segundos($p);
                $p['cronometro_estado'] = 'finalizado';
                $p['cronometro_inicio'] = null;
            }
        }
        unset($p);
        db_guardar('partidos', $partidos, $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', 'Cronómetro finalizado.');
    }

    if ($accion === 'eliminar_evento') {
        $id = (int) $_POST['id'];
        $eventos = db_leer_eventos_partido($torneo['id'], $partidoId);
        $eventoBorrado = db_buscar_por_id($eventos, $id);
        $eventos = array_values(array_filter($eventos, fn($ev) => (int) $ev['id'] !== $id));
        db_guardar_eventos_partido($torneo['id'], $partidoId, $eventos);
        // Si el evento borrado era un gol, el marcador cambia: se recalcula desde los
        // eventos restantes. Un evento que no era gol (tarjeta/cambio) no toca el marcador.
        if ($eventoBorrado !== null && ($eventoBorrado['tipo'] ?? '') === 'gol') {
            partido_recalcular_marcador($torneo['id'], $partidoId, $partidos, $deporte);
        }
        redirigir_con_mensaje($urlLista, 'success', 'Evento eliminado.');
    }

    if (in_array($accion, ['agregar_gol', 'agregar_tarjeta', 'agregar_cambio'], true)) {
        $equipoId = (int) ($_POST['equipo_id'] ?? 0);
        if (!in_array($equipoId, $equiposDelPartido, true)) {
            redirigir_con_mensaje($urlLista, 'error', 'Selecciona un equipo válido para este encuentro.');
        }
        $rosterEquipo = array_column($jugadoresPorEquipo[$equipoId] ?? [], 'id');
        $minuto = ($_POST['minuto'] ?? '') !== '' ? (int) $_POST['minuto'] : null;

        $evento = [
            'equipo_id' => $equipoId,
            'jugador_id' => null,
            'jugador_entra_id' => null,
            'minuto' => $minuto,
            'tipo_gol' => null,
            'asistencia_jugador_id' => null,
            'motivo' => null,
        ];

        if ($accion === 'agregar_gol') {
            $jugadorId = (int) ($_POST['jugador_id'] ?? 0);
            if (!in_array($jugadorId, $rosterEquipo, true)) {
                redirigir_con_mensaje($urlLista, 'error', forma_genero($torneo['genero'] ?? null, 'Selecciona un jugador válido de ese equipo.', 'Selecciona una jugadora válida de ese equipo.'));
            }
            $catalogoTipoGol = tipos_anotacion_catalogo($deporte);
            $tipoGolDefault = $catalogoTipoGol[0];
            $tipoGol = (string) ($_POST['tipo_gol'] ?? $tipoGolDefault);
            $asistenciaId = (int) ($_POST['asistencia_jugador_id'] ?? 0);

            $evento['tipo'] = 'gol';
            $evento['jugador_id'] = $jugadorId;
            $evento['tipo_gol'] = in_array($tipoGol, $catalogoTipoGol, true) ? $tipoGol : $tipoGolDefault;
            $evento['asistencia_jugador_id'] = in_array($asistenciaId, $rosterEquipo, true) ? $asistenciaId : null;
        }

        if ($accion === 'agregar_tarjeta') {
            $jugadorId = (int) ($_POST['jugador_id'] ?? 0);
            if (!in_array($jugadorId, $rosterEquipo, true)) {
                redirigir_con_mensaje($urlLista, 'error', forma_genero($torneo['genero'] ?? null, 'Selecciona un jugador válido de ese equipo.', 'Selecciona una jugadora válida de ese equipo.'));
            }
            $color = (string) ($_POST['color'] ?? 'amarilla') === 'roja' ? 'roja' : 'amarilla';
            $catalogoMotivo = motivos_falta_grave_catalogo($deporte);
            $motivoDefault = $catalogoMotivo[0];
            $motivo = (string) ($_POST['motivo'] ?? $motivoDefault);

            $evento['tipo'] = $color;
            $evento['jugador_id'] = $jugadorId;
            $evento['motivo'] = $color === 'roja' ? (in_array($motivo, $catalogoMotivo, true) ? $motivo : $motivoDefault) : null;
        }

        if ($accion === 'agregar_cambio') {
            $jugadorSaleId = (int) ($_POST['jugador_id'] ?? 0);
            $jugadorEntraId = (int) ($_POST['jugador_entra_id'] ?? 0);
            if (!in_array($jugadorSaleId, $rosterEquipo, true) || !in_array($jugadorEntraId, $rosterEquipo, true)) {
                redirigir_con_mensaje($urlLista, 'error', forma_genero($torneo['genero'] ?? null, 'Selecciona jugadores válidos de ese equipo.', 'Selecciona jugadoras válidas de ese equipo.'));
            }
            if ($jugadorSaleId === $jugadorEntraId) {
                redirigir_con_mensaje($urlLista, 'error', forma_genero($torneo['genero'] ?? null, 'El jugador que entra y el que sale no pueden ser el mismo.', 'La jugadora que entra y la que sale no pueden ser la misma.'));
            }

            $evento['tipo'] = 'cambio';
            $evento['jugador_id'] = $jugadorSaleId;
            $evento['jugador_entra_id'] = $jugadorEntraId;
        }

        $eventos = db_leer_eventos_partido($torneo['id'], $partidoId);
        $evento['id'] = db_siguiente_id_global('partido_eventos');
        $eventos[] = $evento;
        db_guardar_eventos_partido($torneo['id'], $partidoId, $eventos);
        // Al registrar un gol, el marcador se actualiza automáticamente reflejando
        // todos los goles del partido (fútbol +1; basketball +1/2/3; autogol al rival).
        if ($accion === 'agregar_gol') {
            partido_recalcular_marcador($torneo['id'], $partidoId, $partidos, $deporte);
        }
        redirigir_con_mensaje($urlLista, 'success', 'Evento agregado.');
    }
}

$eventos = db_leer_eventos_partido($torneo['id'], $partidoId);
usort($eventos, fn($a, $b) => ($a['minuto'] ?? 999) <=> ($b['minuto'] ?? 999));

// Marcador en vivo calculado directamente desde los goles registrados. Es la fuente de
// verdad del resultado: cada gol que se agrega abajo se refleja aquí y en la tabla.
[$marcadorLocalVivo, $marcadorVisitanteVivo] = marcador_desde_eventos(
    $eventos,
    (int) $partido['equipo_local'],
    (int) $partido['equipo_visitante'],
    $deporte
);

// El encuentro se está registrando antes de su fecha programada: se ofrece (vía modal)
// adelantar la fecha a hoy o seguir registrando eventos sin tocar la fecha.
$hoy = date('Y-m-d');
$fechaEsFutura = ($partido['fecha'] ?? '') > $hoy && ($partido['estado'] ?? '') !== 'jugado';

$seccion_activa = 'partidos';
$titulo_pagina = 'Ficha del partido';
require __DIR__ . '/includes/admin_layout_top.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
    <a href="<?= url('admin/partidos.php') ?>" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-arrow-left"></i></a>
    <div class="flex-grow-1">
        <h3 class="mb-0">Ficha del partido</h3>
        <div class="small text-muted"><?= $equipoLocal ? e($equipoLocal['nombre']) : '?' ?> vs <?= $equipoVisitante ? e($equipoVisitante['nombre']) : '?' ?> · <?= e(formatear_fecha_larga($partido['fecha'])) ?></div>
    </div>
    <a href="<?= e(url_copa('partido_vivo.php?id=' . $partidoId)) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-broadcast me-1"></i>Transmisión en vivo</a>
    <button type="button" class="btn btn-sm btn-outline-secondary btn-copiar-url" data-url="<?= e(url_copa('partido_vivo.php?id=' . $partidoId)) ?>" title="Copiar enlace de transmisión en vivo"><i class="bi bi-link-45deg"></i></button>
    <a href="<?= e(url_copa('partido.php?id=' . $partidoId . '&imprimir=1')) ?>" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download me-1"></i>Descargar PDF</a>
    <a href="<?= url('admin/partidos.php') ?>" class="btn btn-sm btn-degradado rounded-pill px-3"><i class="bi bi-check2-circle me-1"></i>Guardar y volver a Encuentros</a>
</div>

<div class="card-suave p-3 mb-4">
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
</div>

<?php
$cronometroEstado = $partido['cronometro_estado'] ?? 'detenido';
$cronometroSegundosIniciales = partido_cronometro_segundos($partido);
?>
<div class="card-suave p-3 mb-4 d-flex flex-row align-items-center justify-content-between flex-wrap gap-3"
    id="cronometroPartido" data-estado="<?= e($cronometroEstado) ?>"
    data-segundos="<?= (int) ($partido['cronometro_segundos'] ?? 0) ?>"
    data-inicio="<?= e($partido['cronometro_inicio'] ?? '') ?>">
    <div class="d-flex align-items-center gap-3">
        <div class="fs-2 fw-bold font-monospace" id="cronometroTexto"><?= e(sprintf('%02d:%02d', intdiv($cronometroSegundosIniciales, 60), $cronometroSegundosIniciales % 60)) ?></div>
        <div class="small text-muted">
            <?php if ($cronometroEstado === 'detenido'): ?>
                Cronómetro sin iniciar.
            <?php elseif ($cronometroEstado === 'corriendo'): ?>
                <i class="bi bi-record-circle text-danger me-1"></i>Corriendo — el minuto de cada evento se sugiere solo.
            <?php elseif ($cronometroEstado === 'pausado'): ?>
                <i class="bi bi-pause-circle me-1"></i>En pausa.
            <?php else: ?>
                Cronómetro finalizado.
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
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
        <form method="post" class="mb-0">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="cronometro_finalizar">
            <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="¿Finalizar el cronómetro de este partido?"><i class="bi bi-stop-fill me-1"></i>Finalizar</button>
        </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-suave p-4 mb-3">
            <h6 class="text-uppercase small fw-bold text-muted mb-3"><i class="bi bi-dribbble me-1"></i>Agregar <?= e(mb_strtolower(etiqueta_anotacion($deporte))) ?></h6>
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
                        <?php foreach ($equiposDelPartido as $eid): foreach ($jugadoresPorEquipo[$eid] ?? [] as $j): ?>
                        <option value="<?= $j['id'] ?>" data-equipo="<?= $eid ?>">#<?= e($j['dorsal']) ?> <?= e($j['nombre']) ?></option>
                        <?php endforeach; endforeach; ?>
                    </select>
                </div>
                <div class="col-4">
                    <input type="number" min="0" name="minuto" class="form-control form-control-sm" placeholder="Min.">
                </div>
                <div class="col-8">
                    <select name="tipo_gol" class="form-select form-select-sm">
                        <?php foreach (tipos_anotacion_catalogo($deporte) as $tg): ?>
                        <option value="<?= e($tg) ?>"><?= e(tipos_anotacion_label($deporte)[$tg]) ?></option>
                        <?php endforeach; ?>
                    </select>
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
            <?php if ($basketball): ?>
            <p class="small text-muted mb-2">Al llegar a <?= LIMITE_FALTAS_EXPULSION ?> faltas personales en el partido, el jugador queda expulsado automáticamente (regla FIBA).</p>
            <?php endif; ?>
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

    <div class="col-lg-7">
        <?php if ($basketball): ?>
        <?php $faltasPorJugador = faltas_por_jugador($eventos); ?>
        <?php $expulsados = array_filter($faltasPorJugador, fn($n) => $n >= LIMITE_FALTAS_EXPULSION); ?>
        <?php if (!empty($expulsados)): ?>
        <div class="card-suave p-3 mb-3 border border-danger-subtle">
            <h6 class="text-uppercase small fw-bold text-danger mb-2"><i class="bi bi-exclamation-triangle me-1"></i>Expulsados por faltas</h6>
            <ul class="list-unstyled mb-0 small">
                <?php foreach ($expulsados as $jid => $n): $j = $jugadoresPorId[$jid] ?? null; ?>
                <li><?= e(jugador_nombre($j)) ?> — <?= $n ?> faltas personales</li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <?php endif; ?>
        <div class="card-suave p-4">
            <h6 class="text-uppercase small fw-bold text-muted mb-3">Eventos cargados (<?= count($eventos) ?>)</h6>
            <?php if (empty($eventos)): ?>
                <p class="text-muted small mb-0">Todavía no hay <?= $basketball ? 'puntos, faltas' : 'goles, tarjetas' ?> ni cambios cargados en este partido.</p>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php $iconosEvento = ['gol' => $basketball ? '🏀' : '⚽', 'amarilla' => '🟨', 'roja' => '🟥', 'cambio' => '🔄']; ?>
                <?php foreach ($eventos as $ev): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <span class="small"><?= $iconosEvento[$ev['tipo']] ?? '' ?> <?= e(evento_descripcion($ev, $jugadoresPorId, $deporte)) ?> <span class="text-muted">— <?= e($equiposPorId[$ev['equipo_id']]['nombre'] ?? '') ?></span></span>
                    <form method="post" data-confirm="¿Eliminar este evento?">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="eliminar_evento">
                        <input type="hidden" name="partido_id" value="<?= $partidoId ?>">
                        <input type="hidden" name="id" value="<?= $ev['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-x-lg"></i></button>
                    </form>
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

<?php require __DIR__ . '/includes/admin_layout_bottom.php'; ?>
