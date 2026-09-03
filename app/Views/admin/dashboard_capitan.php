<?php
/**
 * Lo primero que ve el capitán al entrar.
 *
 * No es el panel de la copa recortado: es su equipo. Responde de un vistazo las tres cosas
 * que hoy pregunta por WhatsApp —cuándo juego, quién no puede jugar, dónde está mi nómina—
 * y le deja a mano el botón para arreglar su plantilla.
 */
$deuda = fn(float $monto) => sancion_monto_texto($torneo, $monto);
$hayProblemas = !empty($misSuspendidos) || !empty($misDeudores);
?>

<div class="d-flex flex-wrap align-items-center gap-3 mb-4">
    <?= logo_equipo($miEquipo, 64) ?>
    <div class="flex-grow-1" style="min-width:0;">
        <h3 class="mb-0 text-truncate"><?= e($miEquipo['nombre']) ?></h3>
        <div class="small text-muted">
            <?= e($torneo['nombre']) ?>
            <?php if ($miFila !== null): ?>
                · Posición <?= (int) $miFila['posicion'] ?> con <?= (int) $miFila['pts'] ?> puntos
            <?php endif; ?>
        </div>
    </div>
    <a href="<?= url('admin/jugadores.php?equipo_id=' . (int) $miEquipo['id']) ?>" class="btn btn-degradado rounded-pill px-3">
        <i class="bi bi-people me-1"></i>Mi plantilla (<?= count($misActivos) ?>)
    </a>
</div>

<div class="row g-3">
    <?php // ---------- Próximo partido ---------- ?>
    <div class="col-lg-6">
        <div class="card-suave p-4 h-100">
            <h5 class="mb-3"><i class="bi bi-calendar-event me-1"></i>Próximo encuentro</h5>
            <?php if ($proximoMio === null): ?>
                <p class="text-muted mb-0">No tienes encuentros programados por ahora.</p>
            <?php else: ?>
                <?php
                $esLocal = (int) $proximoMio['equipo_local'] === (int) $miEquipo['id'];
                $rival = $equiposPorId[$esLocal ? (int) $proximoMio['equipo_visitante'] : (int) $proximoMio['equipo_local']] ?? null;
                ?>
                <div class="d-flex align-items-center gap-3 mb-3">
                    <?= $rival ? logo_equipo($rival, 48) : '' ?>
                    <div>
                        <div class="fw-semibold">vs <?= e($rival['nombre'] ?? 'Por definir') ?></div>
                        <div class="small text-muted"><?= $esLocal ? 'Juegas de local' : 'Juegas de visitante' ?></div>
                    </div>
                </div>
                <ul class="list-unstyled small mb-3">
                    <li><i class="bi bi-calendar3 me-2 text-muted"></i><?= e(formatear_fecha_larga((string) $proximoMio['fecha'])) ?> · <?= e((string) $proximoMio['hora']) ?></li>
                    <?php if (trim((string) ($proximoMio['cancha'] ?? '')) !== ''): ?>
                    <li><i class="bi bi-geo-alt me-2 text-muted"></i><?= e((string) $proximoMio['cancha']) ?></li>
                    <?php endif; ?>
                    <li><i class="bi bi-flag me-2 text-muted"></i>Jornada <?= (int) ($proximoMio['jornada'] ?? 0) ?></li>
                </ul>
                <?php // La nómina es el papel que se entrega al árbitro. Se le deja aquí
                      // para que no tenga que buscarla en el sitio público. ?>
                <a href="<?= e(url_copa('nomina.php?id=' . (int) $miEquipo['id'] . '&partido=' . (int) $proximoMio['id'])) ?>"
                   target="_blank" class="btn btn-outline-secondary rounded-pill px-3">
                    <i class="bi bi-clipboard-check me-1"></i>Imprimir mi nómina
                </a>
            <?php endif; ?>
        </div>
    </div>

    <?php // ---------- Quién no puede jugar ----------
          // Lo más útil de esta pantalla. Un jugador que llega a la cancha y se entera ahí
          // de que está suspendido o debiendo es la discusión de todos los domingos. ?>
    <div class="col-lg-6">
        <div class="card-suave p-4 h-100">
            <h5 class="mb-3"><i class="bi bi-exclamation-triangle me-1"></i>Antes del próximo partido</h5>

            <?php if (!$hayProblemas): ?>
                <div class="text-success">
                    <i class="bi bi-check-circle-fill me-1"></i>
                    Todos habilitados: nadie de tu equipo está suspendido ni debe multas.
                </div>
            <?php else: ?>

                <?php if (!empty($misSuspendidos)): ?>
                <div class="mb-3">
                    <div class="fw-semibold small text-uppercase text-muted mb-2">No pueden jugar</div>
                    <?php foreach ($misSuspendidos as $jid => $info): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <span><?= e(jugador_nombre($jugadoresPorId[$jid] ?? null)) ?></span>
                        <span class="badge rounded-pill text-bg-danger"><?= e((string) ($info['detalle'] ?? 'Suspendido')) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($misDeudores)): ?>
                <div>
                    <div class="fw-semibold small text-uppercase text-muted mb-2">Deben multas</div>
                    <?php foreach ($misDeudores as $jid => $info): ?>
                    <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                        <span><?= e(jugador_nombre($jugadoresPorId[$jid] ?? null)) ?></span>
                        <span class="badge rounded-pill text-bg-warning text-dark"><?= e($deuda((float) $info['total'])) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <p class="small text-muted mt-2 mb-0">
                        Se paga a la mesa antes del encuentro. Mientras no se pague, ese jugador no puede entrar.
                    </p>
                </div>
                <?php endif; ?>

            <?php endif; ?>
        </div>
    </div>

    <?php // ---------- Lo que debe el equipo ----------
          // De solo lectura: el capitán consulta, el organizador cobra. Antes esto se
          // preguntaba por WhatsApp cada semana y la respuesta salía de un cuaderno. ?>
    <?php if ($miCuenta !== null): ?>
    <?php $saldoEquipo = (float) $miCuenta['saldo']; ?>
    <div class="col-12">
        <div class="card-suave p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h5 class="mb-0"><i class="bi bi-wallet2 me-1"></i>Cuenta con la liga</h5>
                <div class="text-end">
                    <div class="fs-4 fw-bold <?= $saldoEquipo > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= e(sancion_monto_texto($torneo, abs($saldoEquipo))) ?>
                    </div>
                    <div class="small text-muted"><?= $saldoEquipo > 0 ? 'pendiente de pago' : ($saldoEquipo < 0 ? 'a favor' : 'al día') ?></div>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-4"><div class="stat-tile text-center"><div class="fw-bold"><?= e(sancion_monto_texto($torneo, (float) $miCuenta['cargos'])) ?></div><div class="small text-muted">Cargos</div></div></div>
                <div class="col-4"><div class="stat-tile text-center"><div class="fw-bold"><?= e(sancion_monto_texto($torneo, (float) $miCuenta['multas'])) ?></div><div class="small text-muted">Multas</div></div></div>
                <div class="col-4"><div class="stat-tile text-center"><div class="fw-bold text-success"><?= e(sancion_monto_texto($torneo, (float) $miCuenta['pagos'])) ?></div><div class="small text-muted">Pagado</div></div></div>
            </div>

            <?php if (!empty($misMovimientos)): ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <tbody>
                        <?php foreach ($misMovimientos as $m): ?>
                        <?php $esPago = $m['tipo'] === MOVIMIENTO_PAGO; ?>
                        <tr>
                            <td class="small text-nowrap text-muted"><?= e(formatear_fecha_corta($m['fecha'])) ?></td>
                            <td class="small"><?= e($m['concepto']) ?></td>
                            <td class="text-end small fw-semibold <?= $esPago ? 'text-success' : '' ?>">
                                <?= $esPago ? '−' : '' ?><?= e(sancion_monto_texto($torneo, (float) $m['monto'])) ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <p class="small text-muted mt-3 mb-0">
                Los pagos se entregan a la organización de la liga. Si ves algo que no cuadra, avísale al organizador.
            </p>
        </div>
    </div>
    <?php endif; ?>

    <?php // ---------- Últimos resultados ---------- ?>
    <?php if (!empty($ultimosMios)): ?>
    <div class="col-12">
        <div class="card-suave p-4">
            <h5 class="mb-3"><i class="bi bi-clock-history me-1"></i>Tus últimos encuentros</h5>
            <div class="row row-cols-1 row-cols-md-3 g-3">
                <?php foreach ($ultimosMios as $p): ?>
                <?php
                $local = $equiposPorId[(int) $p['equipo_local']] ?? null;
                $visita = $equiposPorId[(int) $p['equipo_visitante']] ?? null;
                ?>
                <div class="col">
                    <div class="border rounded-4 p-3 h-100 fila-clicable" data-href="<?= e(url_copa('partido.php?id=' . (int) $p['id'])) ?>">
                        <div class="small text-muted mb-2"><?= e(formatear_fecha_corta((string) $p['fecha'])) ?></div>
                        <div class="d-flex flex-nowrap align-items-center justify-content-between gap-2">
                            <span class="small text-truncate"><?= e($local['nombre'] ?? '?') ?></span>
                            <span class="fw-bold"><?= (int) $p['marcador_local'] ?>-<?= (int) $p['marcador_visitante'] ?></span>
                            <span class="small text-truncate text-end"><?= e($visita['nombre'] ?? '?') ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
