<?php
/**
 * Cuentas de los equipos.
 *
 * Dos vistas en un archivo: la lista de saldos (a quién hay que cobrarle) y el detalle de
 * un equipo (qué se le cobró y qué pagó). La lista se abre ordenada por deuda, no
 * alfabéticamente: nadie entra aquí a leer nombres.
 */
$moneda = fn(float $m) => sancion_monto_texto($torneo, $m);
$hoy = date('Y-m-d');
?>

<?php if ($equipoDetalle !== null): ?>

    <?php // ---------- Detalle de un equipo ----------
          // La clase hoja-cuenta la usa el CSS de impresión: al mandar esta pantalla a
          // papel se quedan los totales y los movimientos, y desaparecen los formularios
          // de captura y los botones. Lo que sale es el comprobante que se le entrega al
          // capitán, sin tener que armar una página aparte. ?>
    <div class="hoja-cuenta">
    <div class="d-flex align-items-center gap-2 mb-4 flex-wrap">
        <a href="<?= url('admin/cuentas.php') ?>" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-arrow-left"></i></a>
        <?= logo_equipo($equipoDetalle, 44) ?>
        <div class="flex-grow-1" style="min-width:0;">
            <h3 class="mb-0 text-truncate"><?= e($equipoDetalle['nombre']) ?></h3>
            <div class="small text-muted">Estado de cuenta con la liga</div>
        </div>
        <?php // Se imprime esta misma pantalla: el navegador la manda a papel o a PDF, y
              // el CSS de impresión deja solo la tabla de movimientos y los totales. Es el
              // comprobante que se le entrega al capitán cuando pide su estado de cuenta. ?>
        <button type="button" class="btn btn-outline-secondary rounded-pill px-3 btn-imprimir-pdf">
            <i class="bi bi-printer me-1"></i>Imprimir estado de cuenta
        </button>
    </div>

    <?php $saldo = (float) ($saldoEquipo['saldo'] ?? 0); ?>
    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3">
            <div class="stat-tile text-center"><div class="fs-4 fw-bold"><?= e($moneda((float) ($saldoEquipo['cargos'] ?? 0))) ?></div><div class="small text-muted">Cargos</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-tile text-center"><div class="fs-4 fw-bold"><?= e($moneda((float) ($saldoEquipo['multas'] ?? 0))) ?></div><div class="small text-muted">Multas de jugadores</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-tile text-center"><div class="fs-4 fw-bold text-success"><?= e($moneda((float) ($saldoEquipo['pagos'] ?? 0))) ?></div><div class="small text-muted">Pagado</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="stat-tile text-center">
                <div class="fs-4 fw-bold <?= $saldo > 0 ? 'text-danger' : 'text-success' ?>"><?= e($moneda(abs($saldo))) ?></div>
                <div class="small text-muted"><?= $saldo > 0 ? 'Debe' : ($saldo < 0 ? 'A favor' : 'Al día') ?></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <?php // Registrar un pago. Es lo que se hace parado en la cancha con el
                  // teléfono en la mano, así que va arriba y con pocos campos. ?>
            <form method="post" class="card-suave p-4 mb-4">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="pago">
                <input type="hidden" name="equipo_id" value="<?= (int) $equipoDetalle['id'] ?>">
                <h5 class="mb-3"><i class="bi bi-cash-coin me-1"></i>Registrar un pago</h5>
                <div class="row g-2">
                    <div class="col-7">
                        <label class="form-label small fw-semibold">Monto</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= e(torneo_moneda($torneo)) ?></span>
                            <input type="number" step="0.01" min="0.01" name="monto" class="form-control" required
                                   value="<?= $saldo > 0 ? e(number_format($saldo, 2, '.', '')) : '' ?>" data-seleccionar-al-tocar>
                        </div>
                        <div class="form-text">Viene con el saldo completo; cámbialo si es un abono.</div>
                    </div>
                    <div class="col-5">
                        <label class="form-label small fw-semibold">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= e($hoy) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-semibold">Quién pagó / recibo (opcional)</label>
                        <input type="text" name="nota" class="form-control" maxlength="200" placeholder="Ej: Lo entregó el capitán, recibo 014">
                    </div>
                </div>
                <button type="submit" class="btn btn-degradado rounded-pill px-4 mt-3"><i class="bi bi-check2 me-1"></i>Registrar pago</button>
            </form>

            <form method="post" class="card-suave p-4">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="cargo">
                <input type="hidden" name="equipo_id" value="<?= (int) $equipoDetalle['id'] ?>">
                <h5 class="mb-3"><i class="bi bi-plus-circle me-1"></i>Agregar un cargo</h5>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label small fw-semibold">De qué es</label>
                        <input type="text" name="concepto" class="form-control" maxlength="160" required placeholder="Ej: Reposición de balón">
                    </div>
                    <div class="col-7">
                        <label class="form-label small fw-semibold">Monto</label>
                        <div class="input-group">
                            <span class="input-group-text"><?= e(torneo_moneda($torneo)) ?></span>
                            <input type="number" step="0.01" min="0.01" name="monto" class="form-control" required>
                        </div>
                    </div>
                    <div class="col-5">
                        <label class="form-label small fw-semibold">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="<?= e($hoy) ?>">
                    </div>
                </div>
                <button type="submit" class="btn btn-outline-secondary rounded-pill px-4 mt-3">Agregar cargo</button>
            </form>
        </div>

        <div class="col-lg-7">
            <?php if (!empty($multasEquipo)): ?>
            <div class="card-suave p-4 mb-4">
                <h5 class="mb-1"><i class="bi bi-card-heading me-1"></i>Multas de sus jugadores</h5>
                <p class="small text-muted">
                    Están sumadas al saldo, pero se cobran y se dan por pagadas en la pantalla de
                    <a href="<?= url('admin/sanciones.php') ?>">Sanciones</a>, que es la que habilita al jugador.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            <?php foreach ($multasEquipo as $m): ?>
                            <tr>
                                <td>#<?= e($m['jugador']['dorsal']) ?> <?= e($m['jugador']['nombre']) ?></td>
                                <td class="text-muted small"><?= (int) $m['cantidad'] ?> pendiente(s)</td>
                                <td class="text-end fw-semibold"><?= e($moneda($m['total'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

            <div class="card-suave p-4">
                <h5 class="mb-3"><i class="bi bi-journal-text me-1"></i>Movimientos</h5>
                <?php if (empty($movimientosEquipo)): ?>
                    <p class="text-muted mb-0">Todavía no hay movimientos en la cuenta de este equipo.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table align-middle mb-0">
                        <thead>
                            <tr class="small text-muted">
                                <th>Fecha</th>
                                <th>Concepto</th>
                                <th class="text-end">Cargo</th>
                                <th class="text-end">Pago</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($movimientosEquipo as $m): ?>
                            <?php $esPago = $m['tipo'] === MOVIMIENTO_PAGO; ?>
                            <tr>
                                <td class="small text-nowrap"><?= e(formatear_fecha_corta($m['fecha'])) ?></td>
                                <td>
                                    <?= e($m['concepto']) ?>
                                    <div class="small text-muted">
                                        <?= e(movimiento_origen_nombre($m['origen'])) ?><?= $m['nota'] !== '' ? ' · ' . e($m['nota']) : '' ?>
                                    </div>
                                </td>
                                <td class="text-end"><?= $esPago ? '' : e($moneda($m['monto'])) ?></td>
                                <td class="text-end text-success"><?= $esPago ? e($moneda($m['monto'])) : '' ?></td>
                                <td class="text-end">
                                    <form method="post" data-confirm="¿Borrar este movimiento? Si fue un error de captura está bien; si es una corrección, es mejor anotar el movimiento contrario para que quede el rastro.">
                                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                        <input type="hidden" name="accion" value="eliminar">
                                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                        <input type="hidden" name="equipo_id" value="<?= (int) $equipoDetalle['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Borrar movimiento"><i class="bi bi-trash"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    </div>

<?php else: ?>

    <?php // ---------- Lista de saldos ---------- ?>
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <div>
            <h3 class="mb-0">Cuentas de los equipos</h3>
            <div class="small text-muted">Ordenadas por lo que deben, de mayor a menor.</div>
        </div>
        <?php // La exportación vive aquí porque es donde uno está cuando necesita cuadrar
              // el dinero con algo de fuera de la app. ?>
        <a href="<?= url('admin/exportar.php') ?>" class="btn btn-outline-secondary rounded-pill px-3">
            <i class="bi bi-file-earmark-spreadsheet me-1"></i>Exportar todo a Excel
        </a>
        <?php if (!empty($pendientes)): ?>
        <form method="post" data-confirm="Se van a generar <?= count($pendientes) ?> cargos por <?= e($moneda($montoPendiente)) ?>. ¿Continuamos?">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="generar">
            <button type="submit" class="btn btn-degradado rounded-pill px-3">
                <i class="bi bi-receipt me-1"></i>Generar <?= count($pendientes) ?> cargos (<?= e($moneda($montoPendiente)) ?>)
            </button>
        </form>
        <?php endif; ?>
    </div>

    <?php if ($cuotaInscripcion <= 0 && $cuotaArbitraje <= 0): ?>
    <div class="alert alert-info rounded-4 border-0 small">
        <div class="fw-semibold mb-1"><i class="bi bi-info-circle me-1"></i>Esta liga todavía no tiene cuotas configuradas</div>
        Ponle la inscripción por equipo y el arbitraje por partido en
        <a href="<?= url('admin/torneos.php?accion=editar&id=' . (int) $torneo['id']) ?>">Configuración de la copa</a>
        y los cargos se generan solos. Mientras tanto puedes llevar cuentas agregando cargos a mano en cada equipo.
    </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-6 col-md-3"><div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-receipt"></i></span><div class="fs-4 fw-bold"><?= e($moneda($totales['cargado'])) ?></div><div class="small text-muted">Cargado</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-cash-stack"></i></span><div class="fs-4 fw-bold text-success"><?= e($moneda($totales['cobrado'])) ?></div><div class="small text-muted">Cobrado</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-hourglass-split"></i></span><div class="fs-4 fw-bold text-danger"><?= e($moneda($totales['pendiente'])) ?></div><div class="small text-muted">Por cobrar</div></div></div>
        <div class="col-6 col-md-3"><div class="stat-tile text-center"><span class="stat-icono"><i class="bi bi-people"></i></span><div class="fs-4 fw-bold"><?= (int) $totales['equipos_deben'] ?></div><div class="small text-muted">Equipos deben</div></div></div>
    </div>

    <?php if (empty($saldos)): ?>
    <div class="card-suave p-4 text-center text-muted">
        <i class="bi bi-people display-6 d-block mb-2 opacity-50"></i>
        Todavía no hay equipos en esta copa.
    </div>
    <?php else: ?>
    <div class="card-suave p-4">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr class="small text-muted">
                        <th>Equipo</th>
                        <th class="text-end">Cargos</th>
                        <?php if ($multasAlEquipo): ?><th class="text-end">Multas</th><?php endif; ?>
                        <th class="text-end">Pagado</th>
                        <th class="text-end">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($saldos as $fila): ?>
                    <?php $s = (float) $fila['saldo']; ?>
                    <tr class="fila-clicable" data-href="<?= e(url('admin/cuentas.php?equipo_id=' . (int) $fila['equipo']['id'])) ?>">
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <?= logo_equipo($fila['equipo'], 32) ?>
                                <span class="fw-semibold"><?= e($fila['equipo']['nombre']) ?></span>
                            </div>
                        </td>
                        <td class="text-end"><?= e($moneda((float) $fila['cargos'])) ?></td>
                        <?php if ($multasAlEquipo): ?><td class="text-end"><?= e($moneda((float) $fila['multas'])) ?></td><?php endif; ?>
                        <td class="text-end text-success"><?= e($moneda((float) $fila['pagos'])) ?></td>
                        <td class="text-end fw-bold <?= $s > 0 ? 'text-danger' : ($s < 0 ? 'text-primary' : 'text-success') ?>">
                            <?= $s === 0.0 ? 'Al día' : e($moneda(abs($s))) ?>
                            <?php if ($s < 0): ?><span class="small d-block text-muted">a favor</span><?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>
