<header class="hero-copa" style="padding-bottom:3.5rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-person-badge me-1"></i>Conoce a</p>
        <h1 class="text-white mb-2">La <span class="text-degradado">Organizadora</span></h1>
        <p style="color:rgba(255,255,255,.75);" class="mb-0">La persona detrás de <?= e($torneo['nombre']) ?>.</p>
    </div>
</header>

<section class="seccion pt-5">
    <div class="container">
        <div class="row g-4 justify-content-center">
            <div class="col-lg-5">
                <div class="card-suave p-4 text-center h-100">
                    <?php if (!empty($organizador['foto'])): ?>
                        <img src="<?= e(url_imagen($organizador['foto'])) ?>" alt="<?= e($organizador['nombre']) ?>" class="rounded-circle mx-auto mb-3" width="120" height="120" style="object-fit:cover;">
                    <?php else: ?>
                        <div class="avatar-organizador mx-auto mb-3" style="width:120px;height:120px;font-size:2.4rem;"><?= e(iniciales_de($organizador['nombre'])) ?></div>
                    <?php endif; ?>
                    <h4 class="mb-1"><?= e($organizador['nombre']) ?></h4>
                    <p class="text-muted mb-3"><?= e($organizador['cargo']) ?></p>
                    <p class="mb-4"><?= nl2br(e($organizador['bio'] ?? '')) ?></p>

                    <div class="d-flex flex-column gap-2">
                        <?php if (!empty($organizador['email'])): ?>
                        <a href="mailto:<?= e($organizador['email']) ?>" class="btn btn-outline-secondary rounded-pill">
                            <i class="bi bi-envelope me-2"></i><?= e($organizador['email']) ?>
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($organizador['telefono'])): ?>
                        <a href="tel:<?= e(preg_replace('/\s+/', '', $organizador['telefono'])) ?>" class="btn btn-outline-secondary rounded-pill">
                            <i class="bi bi-telephone me-2"></i><?= e($organizador['telefono']) ?>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card-suave p-4 h-100">
                    <h5 class="mb-1"><i class="bi bi-chat-heart me-2"></i>Déjale un comentario anónimo</h5>
                    <p class="text-muted small mb-4">No pedimos tu nombre ni tu correo: tu comentario es 100% anónimo. Solo te pedimos mantener el respeto — los mensajes con lenguaje inapropiado no se publican.</p>
                    <form method="post" novalidate>
                        <div class="d-none" aria-hidden="true">
                            <label>No llenar este campo<input type="text" name="sitio_web" tabindex="-1" autocomplete="off"></label>
                        </div>
                        <div class="mb-3">
                            <textarea name="mensaje" class="form-control" rows="6" maxlength="800" placeholder="Escribe aquí tu comentario, sugerencia o felicitación..." required></textarea>
                        </div>
                        <button type="submit" class="btn btn-degradado rounded-pill px-4"><i class="bi bi-send me-2"></i>Enviar comentario anónimo</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
