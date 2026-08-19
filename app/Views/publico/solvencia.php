<div class="solo-pantalla">
<header class="hero-copa" style="padding-bottom:2.5rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-shield-check me-1"></i>Antes del partido</p>
        <h1 class="text-white mb-2">Solvencia de <span class="text-degradado">jugadores</span></h1>
        <p style="color:rgba(255,255,255,.75);" class="mb-0">
            <?php if ($bloquea): ?>
            Los jugadores con multa pendiente no pueden jugar hasta ponerse al día.
            <?php else: ?>
            Jugadores con multa pendiente. La organización decide si pueden jugar.
            <?php endif; ?>
        </p>
    </div>
</header>

<section class="seccion pt-4">
    <div class="container" style="max-width:900px;">

        <?php if (!$cobraMultas): ?>
        <div class="card-suave p-4 text-center text-muted">
            <i class="bi bi-info-circle fs-3 d-block mb-2 opacity-50"></i>
            Esta liga no cobra multas por tarjeta.
        </div>

        <?php else: ?>

        <?php if (!empty($jornadas)): ?>
        <div class="d-flex flex-wrap gap-2 mb-4 justify-content-center">
            <?php foreach (array_keys($jornadas) as $numero): ?>
            <a href="<?= url_copa('solvencia.php?jornada=' . (int) $numero) ?>" class="btn btn-sm rounded-pill px-3 <?= $jornadaElegida === (int) $numero ? 'btn-degradado' : 'btn-outline-secondary' ?>">Jornada <?= (int) $numero ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
            <span class="small text-muted">
                <?php if ($totalMorosos === 0): ?>
                <i class="bi bi-check2-circle text-success me-1"></i>Todos los jugadores están solventes
                <?php else: ?>
                <i class="bi bi-exclamation-triangle text-danger me-1"></i><?= (int) $totalMorosos ?> jugador<?= $totalMorosos === 1 ? '' : 'es' ?> con multa pendiente
                <?php endif; ?>
            </span>
            <button type="button" class="btn btn-degradado rounded-pill px-4 btn-imprimir-pdf"><i class="bi bi-printer me-1"></i>Imprimir para la cancha</button>
        </div>

        <?php // Tarjeta por encuentro: es como se revisa antes del pitazo ?>
        <?php foreach ($encuentros as $en): ?>
        <div class="card-suave p-3 mb-3">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                <span class="fw-semibold"><?= e($en['local']['nombre'] ?? '?') ?> vs <?= e($en['visitante']['nombre'] ?? '?') ?></span>
                <span class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?= e(formatear_fecha_larga($en['partido']['fecha'])) ?> · <?= e($en['partido']['hora']) ?></span>
            </div>

            <div class="row g-3">
                <?php foreach ([['local', 'morosos_local'], ['visitante', 'morosos_visitante']] as [$claveEquipo, $claveMorosos]): ?>
                <div class="col-md-6">
                    <div class="small fw-semibold mb-1"><?= e($en[$claveEquipo]['nombre'] ?? '?') ?></div>
                    <?php if (empty($en[$claveMorosos])): ?>
                    <div class="small text-success"><i class="bi bi-check2-circle me-1"></i>Plantel completo habilitado</div>
                    <?php else: ?>
                    <ul class="list-unstyled mb-0 small">
                        <?php foreach ($en[$claveMorosos] as $m): ?>
                        <li class="text-danger">
                            <i class="bi bi-x-circle me-1"></i><?= e(jugador_nombre($m['jugador'])) ?>
                            — debe <?= e(sancion_monto_texto($torneo, $m['total'])) ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($encuentros)): ?>
        <div class="card-suave p-4 text-center text-muted">
            <i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>
            No hay encuentros en esta jornada.
        </div>
        <?php endif; ?>

        <?php endif; ?>
    </div>
</section>
</div>

<?php // ---------- Hoja para imprimir y llevar a la cancha ---------- ?>
<?php if ($cobraMultas): ?>
<div class="solo-impresion ficha-imprimir">
    <div class="ficha-titulo">
        <h2><?= e($torneo['nombre']) ?></h2>
        <p>Jugadores NO habilitados por multa pendiente · Jornada <?= (int) $jornadaElegida ?></p>
    </div>

    <?php foreach ($encuentros as $en): ?>
    <h3><?= e($en['local']['nombre'] ?? '?') ?> vs <?= e($en['visitante']['nombre'] ?? '?') ?> — <?= e(formatear_fecha_larga($en['partido']['fecha'])) ?> <?= e($en['partido']['hora']) ?></h3>
    <table class="ficha-tabla">
        <thead>
            <tr><th style="width:34%;">Equipo</th><th>Jugador</th><th style="width:18%;">Debe</th><th style="width:14%;">Pagó</th></tr>
        </thead>
        <tbody>
            <?php $hayAlguno = false; ?>
            <?php foreach ([['local', 'morosos_local'], ['visitante', 'morosos_visitante']] as [$claveEquipo, $claveMorosos]): ?>
                <?php foreach ($en[$claveMorosos] as $m): $hayAlguno = true; ?>
                <tr>
                    <td><?= e($en[$claveEquipo]['nombre'] ?? '?') ?></td>
                    <td><?= e(jugador_nombre($m['jugador'])) ?></td>
                    <td><?= e(sancion_monto_texto($torneo, $m['total'])) ?></td>
                    <?php // Casilla en blanco: si en la cancha no hay señal, se marca a
                          // mano aquí y se registra en la app al volver. ?>
                    <td><span class="ficha-linea-blanco"></span></td>
                </tr>
                <?php endforeach; ?>
            <?php endforeach; ?>
            <?php if (!$hayAlguno): ?>
            <tr><td colspan="4">Ambos planteles están solventes.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    <?php endforeach; ?>

    <?php if (empty($encuentros)): ?>
    <p>No hay encuentros en esta jornada.</p>
    <?php endif; ?>

    <div class="ficha-firma">
        <div class="ficha-firma-linea">Firma del organizador</div>
    </div>

    <div class="ficha-pie">
        <p>Generado el <?= e(date('d/m/Y H:i')) ?> · <?= e(SITE_ORIGIN . url_copa('solvencia.php')) ?></p>
        <p>MJ Control Systems · Plataformas web inteligentes, control total de tu negocio.</p>
    </div>
</div>
<?php endif; ?>
