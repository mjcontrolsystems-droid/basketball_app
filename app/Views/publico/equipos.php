<header class="hero-copa" style="padding-bottom:3.5rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-people me-1"></i>Temporada <?= e($torneo['temporada']) ?></p>
        <h1 class="text-white mb-2">Equipos de <span class="text-degradado"><?= e($torneo['nombre']) ?></span></h1>
        <p style="color:rgba(255,255,255,.75);" class="mb-0"><?= count($equipos) ?> equipos compitiendo por el título.</p>
    </div>
</header>

<section class="seccion pt-5">
    <div class="container">
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
            <?php foreach ($equipos as $eq): ?>
            <div class="col">
                <a href="<?= url_copa('equipo.php?id=' . $eq['id']) ?>" class="text-decoration-none text-dark">
                    <div class="card-suave p-4 h-100 text-center">
                        <div class="mx-auto mb-3"><?= logo_equipo($eq, 90) ?></div>
                        <h5 class="mb-1"><?= e($eq['nombre']) ?></h5>
                        <p class="text-muted small mb-2"><i class="bi bi-geo-alt me-1"></i><?= e($eq['ciudad']) ?></p>
                        <span class="pos-num mx-auto d-inline-flex">#<?= $posicionPorEquipo[$eq['id']] ?? '-' ?></span>
                    </div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
