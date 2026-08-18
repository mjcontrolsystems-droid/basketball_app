<header class="hero-copa">
    <div class="container">
        <div class="row align-items-center gy-5">
            <div class="col-lg-7">
                <p class="kicker mb-3"><i class="bi bi-stars me-1"></i>Torneos y ligas, a tu manera</p>
                <h1 class="text-white mb-3">Encuentra tu <span class="text-degradado">copa o liga</span></h1>
                <p class="fs-5 mb-4" style="color:rgba(255,255,255,.8);max-width:520px;">Escribe el código de 6 caracteres que te compartió el organizador, o abre el enlace o QR que te enviaron.</p>

                <form method="get" action="<?= url('codigo.php') ?>" class="card-suave p-4 p-md-5 mb-3" style="max-width:480px;">
                    <label class="d-block small fw-semibold text-muted mb-2"><i class="bi bi-key-fill me-1"></i>Código de la copa o liga</label>
                    <div class="d-flex flex-column flex-sm-row gap-2">
                        <input type="text" name="c" class="form-control form-control-lg text-center text-uppercase fw-bold" style="letter-spacing:.35em;font-size:1.6rem;" maxlength="6" placeholder="ABC123" autocomplete="off" required autofocus>
                        <button type="submit" class="btn btn-degradado btn-lg rounded-pill px-4">Entrar</button>
                    </div>
                </form>

                <p class="small mb-0" style="color:rgba(255,255,255,.6);">¿Eres organizador? <a href="<?= url('login.php') ?>" class="fw-semibold" style="color:var(--color-acento);">Inicia sesión</a></p>
            </div>
            <div class="col-lg-5">
                <div class="balones-3d">
                    <img src="<?= url('assets/img/balon-basketball.png') ?>" alt="" class="balon-real balon-flotante-1" style="width:230px;height:230px;top:0;left:10px;">
                    <img src="<?= url('assets/img/balon-futbol.png') ?>" alt="" class="balon-real balon-flotante-2" style="width:150px;height:150px;bottom:0;right:15px;">
                </div>
            </div>
        </div>
    </div>
</header>

<section class="seccion">
    <div class="container">
        <div class="seccion-titulo mb-4 text-center">
            <p class="eyebrow mb-1">Cómo funciona</p>
            <h2 class="mb-0">Tu organizador ya hizo el trabajo difícil</h2>
        </div>
        <div class="row g-4">
            <div class="col-md-4">
                <div class="card-suave p-4 h-100 text-center">
                    <span class="badge-pill-icon mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;"><i class="bi bi-key-fill"></i></span>
                    <p class="eyebrow mb-1">Paso 1</p>
                    <h5 class="mb-2">Pide el código</h5>
                    <p class="text-muted small mb-0">El organizador de tu copa o liga te comparte un código de 6 caracteres, un QR o un enlace directo.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-suave p-4 h-100 text-center">
                    <span class="badge-pill-icon mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;"><i class="bi bi-lightning-charge-fill"></i></span>
                    <p class="eyebrow mb-1">Paso 2</p>
                    <h5 class="mb-2">Entra en segundos</h5>
                    <p class="text-muted small mb-0">Escribe el código arriba, escanea el QR o abre el enlace: no necesitas crear cuenta para ver la copa.</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card-suave p-4 h-100 text-center">
                    <span class="badge-pill-icon mx-auto mb-3" style="width:56px;height:56px;font-size:1.4rem;"><i class="bi bi-broadcast"></i></span>
                    <p class="eyebrow mb-1">Paso 3</p>
                    <h5 class="mb-2">Sigue todo en vivo</h5>
                    <p class="text-muted small mb-0">Tabla de posiciones, calendario, resultados y, en modo liga, goles y tarjetas de cada partido.</p>
                </div>
            </div>
        </div>
    </div>
</section>
