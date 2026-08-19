<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h3 class="mb-1">Sanciones y multas</h3>
        <p class="text-muted small mb-0">Las multas se generan solas al registrar una tarjeta. Aquí llevas el cobro.</p>
    </div>
    <a href="<?= e(url_copa('solvencia.php')) ?>" target="_blank" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-printer me-1"></i>Hoja de solvencia</a>
</div>

<?php if ($sinTarifas): ?>
<div class="card-suave p-4 text-center">
    <i class="bi bi-cash-coin fs-2 d-block mb-2 opacity-50"></i>
    <h5 class="mb-2">Esta liga todavía no cobra multas</h5>
    <p class="text-muted small mb-3">Define cuánto cuesta una amarilla y una roja para que la app empiece a generar las multas sola y controle quién puede jugar.</p>
    <div><a href="<?= url('admin/torneos.php?accion=editar&id=' . (int) $torneo['id']) ?>" class="btn btn-degradado rounded-pill px-4">Configurar las multas</a></div>
</div>

<?php else: ?>

<?php // Corte de caja: lo cobrado, lo que falta y lo perdonado ?>
<div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
        <div class="stat-tile"><span class="stat-icono"><i class="bi bi-cash-stack"></i></span><div class="text-muted small mb-1">Recaudado</div><div class="fs-4 fw-bold text-success"><?= e(sancion_monto_texto($torneo, $resumen['recaudado'])) ?></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-tile"><span class="stat-icono"><i class="bi bi-hourglass-split"></i></span><div class="text-muted small mb-1">Por cobrar</div><div class="fs-4 fw-bold text-danger"><?= e(sancion_monto_texto($torneo, $resumen['pendiente'])) ?></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-tile"><span class="stat-icono"><i class="bi bi-person-x"></i></span><div class="text-muted small mb-1">Sanciones pendientes</div><div class="fs-4 fw-bold"><?= (int) $resumen['cantidad_pendiente'] ?></div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="stat-tile"><span class="stat-icono"><i class="bi bi-gift"></i></span><div class="text-muted small mb-1">Condonado</div><div class="fs-4 fw-bold text-muted"><?= e(sancion_monto_texto($torneo, $resumen['condonado'])) ?></div></div>
    </div>
</div>

<?php $filtros = ['pendiente' => 'Por cobrar', 'pagada' => 'Pagadas', 'condonada' => 'Condonadas', 'todas' => 'Todas']; ?>
<ul class="nav nav-pills mb-4">
    <?php foreach ($filtros as $clave => $etiqueta): ?>
    <li class="nav-item">
        <a class="nav-link <?= $filtroEstado === $clave ? 'active' : '' ?>" href="<?= url('admin/sanciones.php?estado=' . $clave) ?>"><?= e($etiqueta) ?></a>
    </li>
    <?php endforeach; ?>
</ul>

<?php if (empty($sanciones)): ?>
<div class="card-suave p-4 text-center text-muted">
    <i class="bi bi-check2-circle fs-3 d-block mb-2 opacity-50"></i>
    <?= $filtroEstado === 'pendiente' ? 'No hay multas pendientes de cobro. Todos los jugadores están solventes.' : 'No hay sanciones en esta vista.' ?>
</div>
<?php else: ?>

<?php foreach ($porEquipo as $equipoId => $lista): ?>
<?php $deuda = $deudaEquipo[$equipoId] ?? 0; ?>
<div class="card-suave p-3 mb-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <div class="d-flex align-items-center gap-2">
            <?= isset($equiposPorId[$equipoId]) ? logo_equipo($equiposPorId[$equipoId], 32) : '' ?>
            <span class="fw-semibold"><?= e($equiposPorId[$equipoId]['nombre'] ?? 'Equipo') ?></span>
            <?php if ($deuda > 0): ?>
            <span class="badge rounded-pill text-bg-danger">Debe <?= e(sancion_monto_texto($torneo, $deuda)) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($deuda > 0): ?>
        <?php // Cuando el capitán paga todo de una vez ?>
        <form method="post" class="mb-0" data-confirm="¿Registrar el pago de TODAS las multas pendientes de <?= e($equiposPorId[$equipoId]['nombre'] ?? 'este equipo') ?> (<?= e(sancion_monto_texto($torneo, $deuda)) ?>)?">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="cobrar_equipo">
            <input type="hidden" name="equipo_id" value="<?= (int) $equipoId ?>">
            <button type="submit" class="btn btn-sm btn-degradado rounded-pill px-3"><i class="bi bi-cash-coin me-1"></i>Cobrar todo</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr class="small text-muted">
                    <th>Jugador</th>
                    <th>Motivo</th>
                    <th class="text-end">Monto</th>
                    <th>Estado</th>
                    <th style="width:180px;"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($lista as $s): ?>
                <?php
                    $jug = $jugadoresPorId[$s['jugador_id']] ?? null;
                    $esRoja = $s['tipo'] === 'roja';
                    $badge = ['pendiente' => 'text-bg-danger', 'pagada' => 'text-bg-success', 'condonada' => 'text-bg-secondary'][$s['estado']] ?? 'text-bg-secondary';
                    $etiquetaEstado = ['pendiente' => 'Por cobrar', 'pagada' => 'Pagada', 'condonada' => 'Condonada'][$s['estado']] ?? $s['estado'];
                ?>
                <tr>
                    <td data-label="Jugador"><span class="fw-semibold"><?= e(jugador_nombre($jug)) ?></span></td>
                    <td data-label="Motivo">
                        <i class="bi bi-square-fill <?= $esRoja ? 'text-danger' : 'text-warning' ?> me-1"></i>
                        <?= $esRoja ? 'Roja' : 'Amarilla' ?>
                        <span class="text-muted small">· Encuentro #<?= (int) $s['partido_id'] ?></span>
                    </td>
                    <td class="text-end fw-semibold" data-label="Monto"><?= e(sancion_monto_texto($torneo, $s['monto'])) ?></td>
                    <td data-label="Estado">
                        <span class="badge rounded-pill <?= $badge ?>"><?= e($etiquetaEstado) ?></span>
                        <?php if (!empty($s['cobrada_en'])): ?>
                        <div class="small text-muted"><?= e(date('d/m/Y H:i', strtotime((string) $s['cobrada_en']))) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-end" data-label="">
                        <div class="d-flex gap-1 justify-content-end flex-wrap">
                            <?php if ($s['estado'] === 'pendiente'): ?>
                            <form method="post" class="mb-0" data-confirm="¿Registrar el pago de <?= e(sancion_monto_texto($torneo, $s['monto'])) ?> de <?= e(jugador_nombre($jug)) ?>?">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="accion" value="cobrar">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-success"><i class="bi bi-check-lg me-1"></i>Pagó</button>
                            </form>
                            <form method="post" class="mb-0" data-confirm="¿Condonar esta multa? El jugador quedará habilitado pero no se contará como dinero recibido.">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="accion" value="condonar">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Perdonar la multa"><i class="bi bi-gift"></i></button>
                            </form>
                            <?php else: ?>
                            <form method="post" class="mb-0" data-confirm="¿Volver a marcar esta sanción como pendiente?">
                                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="accion" value="reabrir">
                                <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Deshacer"><i class="bi bi-arrow-counterclockwise"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>
<?php endif; ?>
