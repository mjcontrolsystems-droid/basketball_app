<header class="hero-copa" style="padding-bottom:3.5rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-heart-fill me-1"></i>Aliados del torneo</p>
        <h1 class="text-white mb-2">Nuestros <span class="text-degradado">Patrocinadores</span></h1>
        <p style="color:rgba(255,255,255,.75);max-width:560px;" class="mb-0">Marcas que apuestan por el <?= e(nombre_deporte($torneo['deporte'] ?? null)) ?><?= e(sufijo_genero_deporte($torneo['genero'] ?? null)) ?> y hacen posible <?= e($torneo['nombre']) ?>.</p>
    </div>
</header>

<div class="pt-4"></div>
<?php vista('parciales/seccion_patrocinadores', compact('torneo', 'patrocOficiales', 'patrocOro', 'patrocPlata')); ?>

<section class="seccion pt-0">
    <div class="container">
        <div class="card-suave p-4 p-md-5 text-center">
            <h4 class="mb-2">¿Quieres patrocinar la próxima temporada?</h4>
            <p class="text-muted mb-3">Contáctanos y forma parte de <?= e($torneo['nombre']) ?>.</p>
            <a href="mailto:<?= e(torneo_organizador($torneo)['email'] ?? '') ?>" class="btn btn-degradado rounded-pill px-4">Escribir al organizador</a>
        </div>
    </div>
</section>
