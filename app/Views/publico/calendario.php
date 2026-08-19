
<header class="hero-copa" style="padding-bottom:3.5rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-calendar-week me-1"></i>Temporada <?= e($torneo['temporada']) ?></p>
        <h1 class="text-white mb-2">Calendario de <span class="text-degradado">Encuentros</span></h1>
        <?php // Ida y vuelta cambia lo que la afición espera del calendario (cada rival
              // aparece dos veces), así que se dice en el encabezado. ?>
        <p style="color:rgba(255,255,255,.75);" class="mb-0">
            <?= $esLiga ? 'Todas las jornadas de la temporada regular.' : 'Fase de grupos y eliminación directa.' ?>
            <?php if (torneo_vueltas($torneo) === 2): ?>Se juega a ida y vuelta: cada equipo enfrenta dos veces a cada rival, una en casa y otra de visita.<?php endif; ?>
        </p>
        <div class="mt-3">
            <a href="<?= url_copa('calendario_imprimir.php') ?>" class="btn btn-outline-luz btn-sm rounded-pill px-3"><i class="bi bi-printer me-1"></i>Imprimir calendario</a>
        </div>
    </div>
</header>

<section class="seccion pt-5">
    <div class="container">
        <?php if (!$esLiga): ?>
        <div class="d-flex flex-wrap gap-2 mb-4 justify-content-center">
            <?php foreach ($fasesValidas as $f): ?>
            <a href="<?= url_copa('calendario.php?fase=' . $f) ?>" class="btn btn-sm rounded-pill px-3 <?= $faseSeleccionada === $f ? 'btn-degradado' : 'btn-outline-secondary' ?>"><?= e(FASES_LABEL[$f]) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($faseSeleccionada === 'grupos'): ?>

            <?php if (empty($jornadas)): ?>
                <p class="text-muted text-center">Aún no hay encuentros <?= $esLiga ? 'programados' : 'de temporada regular programados' ?>.</p>
            <?php else: ?>
                <div class="d-flex flex-wrap gap-2 mb-4 justify-content-center">
                    <?php foreach (array_keys($jornadas) as $num): ?>
                    <a href="<?= url_copa('calendario.php?jornada=' . $num) ?>" class="btn btn-sm rounded-pill px-3 <?= $num === $jornadaSeleccionada ? 'btn-degradado' : 'btn-outline-secondary' ?>">Jornada <?= $num ?></a>
                    <?php endforeach; ?>
                </div>
                <div class="row row-cols-1 row-cols-lg-2 g-3">
                    <?php foreach ($jornadas[$jornadaSeleccionada] as $p): vista('parciales/tarjeta_partido_publica', compact('p', 'equiposPorId')); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php else: ?>

            <?php if (empty($playoffsPorFase[$faseSeleccionada])): ?>
                <div class="card-suave p-5 text-center mx-auto" style="max-width:480px;">
                    <i class="bi bi-trophy display-5 d-block mb-3" style="color:var(--color-acento);"></i>
                    <h5 class="mb-2"><?= e(FASES_LABEL[$faseSeleccionada]) ?></h5>
                    <p class="text-muted mb-0">Todavía no se ha definido el cuadro de <?= mb_strtolower(e(FASES_LABEL[$faseSeleccionada])) ?>. Vuelve pronto para ver los enfrentamientos.</p>
                </div>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-lg-2 g-3">
                    <?php foreach ($playoffsPorFase[$faseSeleccionada] as $p): vista('parciales/tarjeta_partido_publica', compact('p', 'equiposPorId')); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>
</section>

