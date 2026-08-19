<header class="hero-copa" style="padding-bottom:2.5rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-journal-text me-1"></i>Normativa oficial</p>
        <h1 class="text-white mb-2">Reglamento del <span class="text-degradado">campeonato</span></h1>
        <p style="color:rgba(255,255,255,.75);max-width:620px;" class="mb-0">
            Las reglas oficiales de <?= e($torneo['nombre']) ?>. Consúltalo aquí o descárgalo para tenerlo a mano.
        </p>
        <div class="mt-3 d-flex gap-2 flex-wrap">
            <a href="<?= e(url_reglamento($torneo, true)) ?>" class="btn btn-degradado btn-sm rounded-pill px-3"><i class="bi bi-download me-1"></i>Descargar PDF</a>
            <a href="<?= e(url_reglamento($torneo)) ?>" target="_blank" rel="noopener" class="btn btn-outline-luz btn-sm rounded-pill px-3"><i class="bi bi-box-arrow-up-right me-1"></i>Abrir en pestaña nueva</a>
        </div>
    </div>
</header>

<section class="seccion pt-4">
    <div class="container" style="max-width:900px;">
        <?php // Visor embebido: en escritorio muestra el PDF completo. En móvil muchos
              // navegadores no incrustan PDFs, así que el <object> cae al mensaje interno
              // con los botones, que sí funcionan en todos lados. ?>
        <div class="card-suave p-2 p-md-3">
            <object data="<?= e(url_reglamento($torneo)) ?>" type="application/pdf" class="visor-reglamento w-100" aria-label="Reglamento del campeonato">
                <div class="p-4 text-center">
                    <i class="bi bi-file-earmark-pdf-fill text-danger fs-1 d-block mb-2"></i>
                    <p class="mb-3">Tu navegador no puede mostrar el PDF aquí mismo, pero puedes abrirlo o descargarlo.</p>
                    <div class="d-flex gap-2 justify-content-center flex-wrap">
                        <a href="<?= e(url_reglamento($torneo)) ?>" target="_blank" rel="noopener" class="btn btn-degradado rounded-pill px-4"><i class="bi bi-eye me-1"></i>Ver reglamento</a>
                        <a href="<?= e(url_reglamento($torneo, true)) ?>" class="btn btn-outline-secondary rounded-pill px-4"><i class="bi bi-download me-1"></i>Descargar</a>
                    </div>
                </div>
            </object>
        </div>

        <p class="small text-muted text-center mt-3 mb-0">
            <i class="bi bi-info-circle me-1"></i>Documento publicado por la organización de <?= e($torneo['nombre']) ?>.
        </p>
    </div>
</section>
