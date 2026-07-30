<footer class="footer-copa mt-5">
    <div class="container">
        <div class="row gy-4">
            <div class="col-lg-4">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <?php if ($torneo): ?>
                        <span class="badge-pill-icon"><?= icono_deporte($torneo['deporte'], 20) ?></span>
                    <?php else: ?>
                        <?= icono_multideporte(36) ?>
                    <?php endif; ?>
                    <span class="fw-heading text-white fs-5"><?= e($torneo['nombre'] ?? 'Plataforma de Copas y Ligas') ?></span>
                </div>
                <?php if ($torneo): ?>
                <p class="small mb-3"><?= e($torneo['descripcion']) ?></p>
                <?php if (!empty($torneo['instagram'])): ?>
                <a href="<?= url_externa_segura($torneo['instagram']) ?>" target="_blank" rel="noopener" class="small"><i class="bi bi-instagram me-1"></i>Síguenos en Instagram</a>
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
        <div class="d-flex align-items-center justify-content-center gap-2 opacity-50">
            <img src="<?= url('assets/img/logo-empresa.png') ?>" alt="MJ Control Systems" height="28" width="28" style="object-fit:contain;">
            <p class="small mb-0">MJ Control Systems · Plataformas web inteligentes, control total de tu negocio.</p>
        </div>
    </div>
</footer>

<?php require __DIR__ . '/modal_compartir.php'; ?>
<?php require __DIR__ . '/modal_codigo.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
</body>
</html>
