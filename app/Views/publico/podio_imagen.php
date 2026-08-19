<header class="hero-copa" style="padding-bottom:2.5rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-trophy-fill me-1"></i>Para compartir</p>
        <h1 class="text-white mb-2">Podio de la <span class="text-degradado">temporada</span></h1>
        <p style="color:rgba(255,255,255,.75);" class="mb-0">Descárgala y súbela a Instagram, o compártela por WhatsApp.</p>
    </div>
</header>

<section class="seccion pt-4">
    <div class="container text-center" style="max-width:640px;">
        <?php // Los datos viajan en data-attributes porque el CSP del sitio no permite
              // JavaScript inline: el script los lee del dataset. ?>
        <div id="datosPodio"
            data-torneo="<?= e($torneo['nombre']) ?>"
            data-temporada="<?= e($torneo['temporada']) ?>"
            data-color1="<?= e(color_hex_valido($torneo['color_primario'] ?? null, '#241a3a')) ?>"
            data-color2="<?= e(color_hex_valido($torneo['color_acento'] ?? null, '#7b2ff7')) ?>"
            data-titulo1="<?= e(podio_titulo(1, $torneo['genero'] ?? null)) ?>"
            data-titulo2="<?= e(podio_titulo(2, $torneo['genero'] ?? null)) ?>"
            data-titulo3="<?= e(podio_titulo(3, $torneo['genero'] ?? null)) ?>"
            <?php foreach (['campeon' => 1, 'subcampeon' => 2, 'tercero' => 3] as $clave => $puesto): ?>
            data-eq<?= $puesto ?>-nombre="<?= e($podio[$clave]['nombre'] ?? '') ?>"
            data-eq<?= $puesto ?>-color="<?= e(color_hex_valido($podio[$clave]['color_primario'] ?? null, '#7b2ff7')) ?>"
            data-eq<?= $puesto ?>-logo="<?= !empty($podio[$clave]['logo']) ? e(url_imagen((string) $podio[$clave]['logo'])) : '' ?>"
            <?php endforeach; ?>
            >
        </div>

        <canvas id="canvasPodio" width="1080" height="1080" class="card-suave" style="width:100%;height:auto;max-width:480px;"></canvas>

        <div class="d-flex justify-content-center gap-2 mt-4 flex-wrap">
            <a id="btnDescargarPodio" download="podio.png" class="btn btn-degradado rounded-pill px-4"><i class="bi bi-download me-1"></i>Descargar imagen</a>
            <a href="<?= url_copa('index.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">Volver al inicio</a>
        </div>
        <p class="small text-muted mt-3">La imagen se genera en tu dispositivo con los colores de la copa y de los equipos.</p>
    </div>
</section>

<script src="<?= asset_url('assets/js/podio_imagen.js') ?>"></script>
