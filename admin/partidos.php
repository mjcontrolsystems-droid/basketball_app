<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/tabla.php';
require_once __DIR__ . '/../includes/liga.php';

auth_requerir();
$torneo = admin_requerir_torneo_activo();

$equipos = db_leer('equipos', $torneo['id']);
$partidos = db_leer('partidos', $torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) { $equiposPorId[$eq['id']] = $eq; }

$accion = $_GET['accion'] ?? 'lista';
$idEditar = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$partidoEditar = $idEditar ? db_buscar_por_id($partidos, $idEditar) : null;
$errores = [];

$fasesValidas = array_merge(['grupos'], $torneo['fases_playoff']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (($_POST['accion'] ?? '') === 'eliminar') {
        $id = (int) $_POST['id'];
        $partidos = array_values(array_filter($partidos, fn($p) => $p['id'] !== $id));
        db_guardar('partidos', $partidos, $torneo['id']);
        db_guardar_eventos_partido($torneo['id'], $id, []);
        bitacora_registrar('partido_eliminado', 'Encuentro #' . $id . ' eliminado con todos sus eventos', $torneo['id']);
        redirigir_con_mensaje(url('admin/partidos.php'), 'success', 'Encuentro eliminado correctamente.');
    }

    // Interruptor rápido en la tarjeta del encuentro: alternar jugado/programado sin
    // abrir el formulario completo. Al marcarlo como jugado el marcador se toma de los
    // goles registrados en Eventos (queda 0-0 si no hay goles), no se captura a mano.
    if (($_POST['accion'] ?? '') === 'alternar_jugado') {
        $id = (int) $_POST['id'];
        $partidoActual = db_buscar_por_id($partidos, $id);
        if ($partidoActual === null) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', 'Encuentro no encontrado.');
        }

        if ($partidoActual['estado'] === 'jugado') {
            foreach ($partidos as &$p) {
                if ($p['id'] === $id) {
                    $p['estado'] = 'programado';
                }
            }
            unset($p);
            db_guardar('partidos', $partidos, $torneo['id']);
            bitacora_registrar('partido_reabierto', 'Encuentro #' . $id . ' reabierto para corrección de resultado', $torneo['id']);
            redirigir_con_mensaje(url('admin/partidos.php'), 'success', 'Encuentro reabierto para corrección. Márcalo como jugado de nuevo cuando termines.');
        }

        // El marcador se toma de los goles registrados en Eventos (fuente de verdad). Si
        // no hay goles queda 0-0, salvo que la copa no permita empates. Se conserva un
        // marcador histórico si ya estaba capturado y todavía no hay goles que lo sustituyan.
        [$mLocal, $mVisit] = marcador_jugado_desde_eventos($torneo['id'], $partidoActual, $torneo['deporte'] ?? null);
        if ($mLocal === $mVisit && empty($torneo['permite_empates'])) {
            redirigir_con_mensaje(url('admin/partido_eventos.php?partido_id=' . $id), 'error', 'Esta copa no permite empates: registra los ' . mb_strtolower(etiqueta_anotaciones($torneo['deporte'] ?? null)) . ' en Eventos para definir un ganador antes de marcar el encuentro como jugado.');
        }

        foreach ($partidos as &$p) {
            if ($p['id'] === $id) {
                $p['estado'] = 'jugado';
                $p['marcador_local'] = $mLocal;
                $p['marcador_visitante'] = $mVisit;
            }
        }
        unset($p);
        db_guardar('partidos', $partidos, $torneo['id']);
        bitacora_registrar('partido_jugado', "Encuentro #{$id} en firme con marcador {$mLocal}-{$mVisit}", $torneo['id']);
        redirigir_con_mensaje(url('admin/partidos.php'), 'success', 'Encuentro marcado como jugado. El resultado queda en firme.');
    }

    if (($_POST['accion'] ?? '') === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $local = (int) $_POST['equipo_local'];
        $visitante = (int) $_POST['equipo_visitante'];
        $estado = (string) $_POST['estado'];
        $fase = (string) ($_POST['fase'] ?? 'grupos');

        if (!in_array($fase, $fasesValidas, true)) {
            $fase = 'grupos';
        }

        if ($local === $visitante) {
            $errores[] = 'El equipo local y el visitante no pueden ser el mismo.';
        }
        if (!isset($equiposPorId[$local]) || !isset($equiposPorId[$visitante])) {
            $errores[] = 'Selecciona equipos válidos.';
        }

        // El marcador ya no se captura en este formulario: se calcula desde los goles
        // registrados en Eventos. Para un encuentro existente se deriva de sus goles
        // (conservando un marcador histórico si aún no hay goles); uno nuevo empieza 0-0.
        $partidoExistente = $id > 0 ? db_buscar_por_id($partidos, $id) : null;
        if ($partidoExistente !== null) {
            [$marcadorLocal, $marcadorVisitante] = marcador_jugado_desde_eventos($torneo['id'], $partidoExistente, $torneo['deporte'] ?? null);
        } else {
            $marcadorLocal = 0;
            $marcadorVisitante = 0;
        }

        if ($estado === 'jugado' && $marcadorLocal === $marcadorVisitante && empty($torneo['permite_empates'])) {
            $txtAnot = mb_strtolower(etiqueta_anotaciones($torneo['deporte'] ?? null));
            if ($partidoExistente !== null) {
                $errores[] = "Esta copa no permite empates: registra los {$txtAnot} en la ficha de Eventos para definir un ganador antes de marcar el encuentro como jugado.";
            } else {
                $errores[] = "Esta copa no permite empates. Guarda el encuentro como \"Programado\", registra los {$txtAnot} en Eventos y luego márcalo como jugado.";
            }
        }

        if (empty($errores)) {
            $datos = [
                'jornada' => (int) ($_POST['jornada'] ?: 1),
                'equipo_local' => $local,
                'equipo_visitante' => $visitante,
                'fecha' => (string) $_POST['fecha'],
                'hora' => (string) $_POST['hora'],
                'cancha' => trim((string) $_POST['cancha']),
                'estado' => $estado,
                // Marcador derivado de los goles. Un encuentro jugado siempre lleva marcador
                // (0-0 incluido); uno programado solo lo muestra si ya tiene goles cargados.
                'marcador_local' => ($estado === 'jugado' || $marcadorLocal !== 0 || $marcadorVisitante !== 0) ? $marcadorLocal : null,
                'marcador_visitante' => ($estado === 'jugado' || $marcadorLocal !== 0 || $marcadorVisitante !== 0) ? $marcadorVisitante : null,
                'fase' => $fase,
                'arbitro' => trim((string) ($_POST['arbitro'] ?? '')),
                'observaciones' => trim((string) ($_POST['observaciones'] ?? '')),
            ];

            if ($id > 0) {
                foreach ($partidos as &$p) {
                    if ($p['id'] === $id) {
                        $p = array_merge($p, $datos, ['id' => $id]);
                    }
                }
                unset($p);
                $mensaje = 'Encuentro actualizado correctamente.';
            } else {
                $datos['id'] = db_siguiente_id_global('partidos');
                // Estado inicial del cronómetro: estas columnas son NOT NULL en la base,
                // así que un encuentro NUEVO debe traerlas explícitas — sin esto, el
                // INSERT fallaba con "error inesperado" al programar cualquier encuentro.
                // En una edición no se tocan (array_merge conserva las del partido).
                $datos['cronometro_estado'] = 'detenido';
                $datos['cronometro_inicio'] = null;
                $datos['cronometro_segundos'] = 0;
                $datos['cronometro_periodo'] = 1;
                $partidos[] = $datos;
                $mensaje = 'Encuentro programado correctamente.';
            }

            db_guardar('partidos', $partidos, $torneo['id']);
            bitacora_registrar($id > 0 ? 'partido_editado' : 'partido_creado', 'Encuentro ' . ($id > 0 ? "#{$id}" : "#{$datos['id']}") . ' — ' . ($equiposPorId[$local]['nombre'] ?? '?') . ' vs ' . ($equiposPorId[$visitante]['nombre'] ?? '?') . ' (' . $datos['fecha'] . ')', $torneo['id']);
            redirigir_con_mensaje(url('admin/partidos.php'), 'success', $mensaje);
        } else {
            $partidoEditar = array_merge($_POST, ['id' => $id]);
            $accion = $id > 0 ? 'editar' : 'nuevo';
        }
    }
}

$jornadas = partidos_por_jornada($partidos);
$playoffsPorFase = partidos_playoffs_por_fase($partidos, $torneo['fases_playoff']);
$siguienteJornada = empty($jornadas) ? 1 : max(array_keys($jornadas));
$faseSeleccionada = $partidoEditar['fase'] ?? ($_GET['fase'] ?? 'grupos');
if (!in_array($faseSeleccionada, $fasesValidas, true)) {
    $faseSeleccionada = 'grupos';
}

$seccion_activa = 'partidos';
$titulo_pagina = 'Encuentros';
require __DIR__ . '/includes/admin_layout_top.php';
?>

<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
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
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Fase</label>
                <select name="fase" id="selectFase" class="form-select">
                    <?php foreach ($fasesValidas as $f): ?>
                    <option value="<?= e($f) ?>" <?= $faseSeleccionada === $f ? 'selected' : '' ?>><?= e(FASES_LABEL[$f]) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Las fases de eliminación directa no cuentan para la tabla de posiciones.</div>
            </div>
            <div class="col-md-6" id="grupoJornada">
                <label class="form-label small fw-semibold">Jornada</label>
                <input type="number" min="1" name="jornada" class="form-control" value="<?= e((string) ($partidoEditar['jornada'] ?? $siguienteJornada)) ?>">
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

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <h3 class="mb-0">Encuentros (<?= count($partidos) ?>)</h3>
        <a href="<?= url('admin/partidos.php?accion=nuevo') ?>" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Programar encuentro</a>
    </div>

    <ul class="nav nav-pills mb-4" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#panelGrupos" type="button">Fase de Grupos</button></li>
        <?php foreach ($torneo['fases_playoff'] as $f): ?>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#panel-<?= $f ?>" type="button"><?= e(FASES_LABEL[$f]) ?> <?= count($playoffsPorFase[$f]) > 0 ? '<span class="badge rounded-pill text-bg-secondary ms-1">' . count($playoffsPorFase[$f]) . '</span>' : '' ?></button></li>
        <?php endforeach; ?>
    </ul>

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
                <p class="text-muted">Aún no hay encuentros de fase de grupos programados.</p>
            <?php endif; ?>
        </div>

        <?php foreach ($torneo['fases_playoff'] as $f): ?>
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

<?php require __DIR__ . '/includes/admin_layout_bottom.php'; ?>
