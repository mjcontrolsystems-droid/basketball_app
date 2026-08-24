<?php
/**
 * Perfil público del jugador: sus números de la temporada.
 */
?>
<header class="hero-copa" style="padding-bottom:2.5rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-person me-1"></i><?= e(forma_genero($torneo['genero'] ?? null, 'Jugador', 'Jugadora')) ?> de <?= e($equipo['nombre'] ?? '') ?></p>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <?php if ($equipo): ?><?= logo_equipo($equipo, 64) ?><?php endif; ?>
            <div>
                <h1 class="text-white mb-1">#<?= e($jugador['dorsal']) ?> <?= e($jugador['nombre']) ?></h1>
                <p style="color:rgba(255,255,255,.75);" class="mb-0">
                    <?= e(posicion_label($deporte, (string) ($jugador['posicion'] ?? '')) ?: 'Sin posición asignada') ?>
                    <?= empty($jugador['activo']) ? ' · Inactivo' : '' ?>
                </p>
            </div>
        </div>
        <div class="mt-3">
            <a href="<?= url_copa('equipo.php?id=' . (int) ($equipo['id'] ?? 0)) ?>" class="btn btn-outline-luz btn-sm rounded-pill px-3">Volver a <?= e($equipo['nombre'] ?? 'su equipo') ?></a>
        </div>
    </div>
</header>

<section class="seccion pt-4">
    <div class="container" style="max-width:900px;">

        <?php // Lo urgente arriba: si no puede jugar la próxima fecha, es lo primero a saber. ?>
        <?php if ($suspension !== null): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm d-flex align-items-start gap-2">
            <i class="bi bi-slash-circle-fill mt-1"></i>
            <div>
                <div class="fw-semibold">Suspendido para el próximo encuentro</div>
                <?php // 'detalle' es la explicación legible ("Roja directa, cumple 1 de 2"...);
                      // 'motivo' es la clave técnica y solo sirve de respaldo. ?>
                <div class="small"><?= e((string) ($suspension['detalle'] ?? ($suspension['motivo'] ?? 'Por sanción disciplinaria'))) ?></div>
            </div>
        </div>
        <?php elseif ($cobraMultas && $debe > 0): ?>
        <div class="alert alert-warning rounded-4 border-0 shadow-sm d-flex align-items-start gap-2">
            <i class="bi bi-cash-coin mt-1"></i>
            <div>
                <div class="fw-semibold">Tiene multas pendientes por <?= e(sancion_monto_texto($torneo, $debe)) ?></div>
                <div class="small">Se pagan en la mesa antes del próximo encuentro.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php // ---------- Números de la temporada ---------- ?>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="stat-tile text-center h-100">
                    <div class="text-muted small mb-1">⚽ <?= e(etiqueta_anotaciones($deporte)) ?></div>
                    <div class="fs-2 fw-bold"><?= (int) $totalPuntos ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-tile text-center h-100">
                    <div class="text-muted small mb-1">🟨 <?= e(etiqueta_faltas_leves($deporte)) ?></div>
                    <div class="fs-2 fw-bold"><?= count($amarillas) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-tile text-center h-100">
                    <div class="text-muted small mb-1">🟥 <?= e(etiqueta_faltas_graves($deporte)) ?></div>
                    <div class="fs-2 fw-bold"><?= count($rojas) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="stat-tile text-center h-100">
                    <?php if ($cobraMultas): ?>
                    <div class="text-muted small mb-1">💰 Multas</div>
                    <div class="fs-5 fw-bold <?= $debe > 0 ? 'text-danger' : 'text-success' ?>">
                        <?= $debe > 0 ? 'Debe ' . e(sancion_monto_texto($torneo, $debe)) : 'Solvente' ?>
                    </div>
                    <?php else: ?>
                    <div class="text-muted small mb-1">Autogoles</div>
                    <div class="fs-2 fw-bold"><?= (int) $autogoles ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php // ---------- Detalle de goles: en qué partido, clicable a la ficha ---------- ?>
        <?php if (!empty($anotaciones)): ?>
        <div class="card-suave p-4 mb-4">
            <h5 class="mb-3"><?= e(etiqueta_anotaciones($deporte)) ?> de la temporada</h5>
            <div class="table-responsive">
                <table class="table tabla-posiciones align-middle mb-0">
                    <thead>
                        <tr class="small text-muted">
                            <th>Partido</th>
                            <th>Fecha</th>
                            <th class="text-center">Minuto</th>
                            <th class="text-center"><?= $basketball ? 'Puntos' : 'Tipo' ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($anotaciones as $a): $pa = $a['partido']; ?>
                        <tr class="<?= $pa ? 'fila-clicable' : '' ?>" <?= $pa ? 'data-href="' . e(url_copa('partido.php?id=' . (int) $pa['id'])) . '"' : '' ?>>
                            <td class="td-equipo" data-label="Partido">
                                <?php if ($pa): ?>
                                <span class="fw-semibold"><?= e($equiposPorId[$pa['equipo_local']]['nombre'] ?? '') ?> <?= (int) $pa['marcador_local'] ?>-<?= (int) $pa['marcador_visitante'] ?> <?= e($equiposPorId[$pa['equipo_visitante']]['nombre'] ?? '') ?></span>
                                <?php else: ?>—<?php endif; ?>
                            </td>
                            <td data-label="Fecha"><span class="small text-muted"><?= $pa ? e(formatear_fecha_corta((string) $pa['fecha'])) : '—' ?></span></td>
                            <td class="text-center" data-label="Minuto"><?= $a['minuto'] !== null ? e((string) $a['minuto']) . "'" : '—' ?></td>
                            <td class="text-center" data-label="<?= $basketball ? 'Puntos' : 'Tipo' ?>">
                                <?= $basketball ? '+' . (int) $a['valor'] : e(tipos_anotacion_label($deporte)[$a['tipo_gol']] ?? 'Jugada') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php // ---------- Multas, con su estado una por una ---------- ?>
        <?php if ($cobraMultas && !empty($multas)): ?>
        <div class="card-suave p-4 mb-4">
            <h5 class="mb-3">Multas</h5>
            <div class="table-responsive">
                <table class="table tabla-posiciones align-middle mb-0">
                    <thead>
                        <tr class="small text-muted">
                            <th>Motivo</th>
                            <th class="text-end">Monto</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($multas as $m): ?>
                        <?php $badge = ['pendiente' => 'text-bg-danger', 'pagada' => 'text-bg-success', 'condonada' => 'text-bg-secondary'][$m['estado']] ?? 'text-bg-secondary'; ?>
                        <tr>
                            <td class="td-equipo" data-label="Motivo">
                                <i class="bi bi-square-fill <?= ($m['tipo'] ?? '') === 'roja' ? 'text-danger' : 'text-warning' ?> me-1"></i>
                                <?= ($m['tipo'] ?? '') === 'roja' ? 'Roja' : 'Amarilla' ?>
                                <span class="text-muted small">· Encuentro #<?= (int) $m['partido_id'] ?></span>
                            </td>
                            <td class="text-end fw-semibold" data-label="Monto"><?= e(sancion_monto_texto($torneo, (float) $m['monto'])) ?></td>
                            <td data-label="Estado">
                                <span class="badge rounded-pill <?= $badge ?>">
                                    <?= e(['pendiente' => 'Por pagar', 'pagada' => 'Pagada', 'condonada' => 'Condonada'][$m['estado']] ?? $m['estado']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($anotaciones) && empty($amarillas) && empty($rojas) && empty($multas)): ?>
        <div class="card-suave p-4 text-center text-muted">
            Todavía no registra <?= e(mb_strtolower(etiqueta_anotaciones($deporte))) ?> ni tarjetas en la temporada.
        </div>
        <?php endif; ?>

    </div>
</section>
