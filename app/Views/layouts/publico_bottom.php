<?php $torneo = $torneo ?? copa_actual(); ?>
<footer class="footer-copa mt-5">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <?= logo_torneo($torneo, 38) ?>
                    <span class="fw-heading text-white fs-5"><?= e($torneo['nombre'] ?? 'Plataforma de Copas y Ligas') ?></span>
                </div>
                <?php if ($torneo): ?>
                <p class="small mb-3"><?= e($torneo['descripcion']) ?></p>
                <?php // Solo se pintan las redes que el organizador llenó: un pie con
                      // iconos que no llevan a ningún lado se ve peor que no tenerlos. ?>
                <?php $redesCopa = redes_del_torneo($torneo); ?>
                <?php if (!empty($redesCopa)): ?>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($redesCopa as $red): ?>
                    <?php // La URL ya viene escapada desde redes_del_torneo(). ?>
                    <a href="<?= $red['url'] ?>" target="_blank" rel="noopener" class="small text-decoration-none" title="<?= e($red['texto']) ?>">
                        <i class="bi <?= e($red['icono']) ?> me-1"></i><?= e($red['texto']) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <?php else: ?>
                <p class="small mb-0">Un solo panel para administrar todas tus copas y ligas.</p>
                <?php endif; ?>
            </div>
            <?php if ($torneo): ?>
            <div class="col-lg-2 col-6">
                <h6 class="text-white mb-3">Torneo</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <li><a href="<?= url_copa('tabla.php') ?>">Tabla de posiciones</a></li>
                    <li><a href="<?= url_copa('calendario.php') ?>">Calendario</a></li>
                    <li><a href="<?= url_copa('equipos.php') ?>">Equipos</a></li>
                </ul>
            </div>
            <?php endif; ?>
            <div class="col-lg-2 col-6">
                <h6 class="text-white mb-3">Sitio</h6>
                <ul class="list-unstyled small d-flex flex-column gap-2">
                    <?php if ($torneo): ?>
                    <li><a href="<?= url_copa('patrocinadores.php') ?>">Patrocinadores</a></li>
                    <li><a href="<?= url_copa('organizador.php') ?>">Organizador</a></li>
                    <?php if (torneo_tiene_reglamento($torneo)): ?>
                    <li><a href="<?= url_copa('reglamento.php') ?>">Reglamento</a></li>
                    <?php endif; ?>
                    <?php endif; ?>
                    <li><a href="#" data-bs-toggle="modal" data-bs-target="#modalCompartir">Compartir sitio</a></li>
                    <li><a href="<?= url('login.php') ?>">Panel Organizador</a></li>
                </ul>
            </div>
            <?php if ($torneo): ?>
            <div class="col-lg-4">
                <h6 class="text-white mb-3">Sede principal</h6>
                <p class="small mb-1"><i class="bi bi-geo-alt me-2"></i><?= e($torneo['sede_principal'] ?? '') ?></p>
                <p class="small mb-0"><i class="bi bi-calendar3 me-2"></i>Temporada <?= e($torneo['temporada'] ?? '') ?></p>
            </div>
            <?php endif; ?>
        </div>
        <hr class="border-secondary opacity-25 my-4">
        <p class="small text-center mb-1 opacity-75">© <?= date('Y') ?> <?= e($torneo['nombre'] ?? 'Plataforma de Copas y Ligas') ?><?= $torneo ? ' · ' . e($torneo['subtitulo']) : '' ?></p>
        <?php // Contacto de MJ Control Systems, no del organizador de esta copa: el de la
              // copa vive en su propia página (ver publico/organizador.php). El correo va
              // como enlace mailto y con opacidad completa, porque si está para que la
              // gente escriba, tiene que poder leerse y tocarse desde el teléfono. ?>
        <div class="d-flex align-items-center justify-content-center gap-2 mb-1">
            <img src="<?= url('assets/img/logo-empresa.png') ?>" alt="MJ Control Systems" height="28" width="28" style="object-fit:contain;" class="opacity-75">
            <p class="small mb-0 opacity-75">MJ Control Systems · <?= e(LEMA_PLATAFORMA) ?></p>
        </div>
        <p class="small text-center mb-0">
            <a href="mailto:<?= e(CONTACTO_PLATAFORMA) ?>" class="link-contacto-plataforma">
                <i class="bi bi-envelope me-1"></i><?= e(CONTACTO_PLATAFORMA) ?>
            </a>
        </p>
    </div>
</footer>

<?php vista('parciales/modal_compartir', ['torneo' => $torneo ?? null] + (isset($compartir_url) ? ['compartir_url' => $compartir_url, 'compartir_titulo' => $compartir_titulo ?? ''] : [])); ?>
<?php vista('parciales/modal_codigo'); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<?php // SweetAlert2 antes de app.js: app.js lo usa para los avisos y las confirmaciones. ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.14.5/dist/sweetalert2.all.min.js"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
