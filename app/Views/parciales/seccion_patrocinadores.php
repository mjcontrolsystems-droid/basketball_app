<?php
declare(strict_types=1);
/**
 * Espera $patrocOficiales, $patrocOro, $patrocPlata ya filtrados por nivel, y $torneo
 * (puede venir null desde index.php cuando no hay copa activa, aunque en la práctica
 * esta sección solo se incluye desde páginas que sí tienen una).
 */
?>
<section class="seccion sponsors-section" id="patrocinadores">
    <div class="container">
        <div class="seccion-titulo mb-4 text-center">
            <p class="eyebrow mb-1">Gracias a</p>
            <h2 class="mb-2">Nuestros Patrocinadores</h2>
            <p class="text-muted mx-auto" style="max-width:520px;">Marcas que creen en el crecimiento del <?= e(nombre_deporte($torneo['deporte'] ?? null)) ?><?= e(sufijo_genero_deporte($torneo['genero'] ?? null)) ?> y hacen posible cada jornada.</p>
        </div>

        <?php if (!empty($patrocOficiales)): ?>
        <div class="d-flex flex-wrap justify-content-center gap-4 mb-5">
            <?php foreach ($patrocOficiales as $p): ?>
            <a href="<?= url_externa_segura($p['url']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                <div class="sponsor-card nivel-oficial position-relative">
                    <span class="tier-pill oficial position-absolute top-0 start-50 translate-middle"><?= e(nivel_patrocinio_label($p['nivel'])) ?></span>
                    <?= badge_patrocinador($p) ?>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($patrocOro)): ?>
        <div class="marquee-track-wrap mb-4">
            <div class="marquee-track">
                <?php foreach (array_merge($patrocOro, $patrocOro) as $p): ?>
                <a href="<?= url_externa_segura($p['url']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                    <div class="sponsor-card"><?= badge_patrocinador($p) ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($patrocPlata)): ?>
        <div class="marquee-track-wrap">
            <div class="marquee-track reversa">
                <?php foreach (array_merge($patrocPlata, $patrocPlata) as $p): ?>
                <a href="<?= url_externa_segura($p['url']) ?>" target="_blank" rel="noopener" class="text-decoration-none">
                    <div class="sponsor-card"><?= badge_patrocinador($p) ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
