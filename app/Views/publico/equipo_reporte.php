<div class="solo-pantalla">
<header class="hero-copa" style="padding-bottom:2.5rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-file-earmark-text me-1"></i>Reporte del equipo</p>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <?= logo_equipo($equipo, 64) ?>
            <div>
                <h1 class="text-white mb-1"><?= e($equipo['nombre']) ?></h1>
                <p style="color:rgba(255,255,255,.75);" class="mb-0">
                    <?php if ($filaEquipo !== null): ?>
                    <?= (int) $filaEquipo['posicion'] ?>° lugar de <?= (int) $totalEquipos ?> · <?= (int) $filaEquipo['pts'] ?> puntos
                    <?php else: ?>
                    Todavía sin partidos en la tabla
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-degradado btn-sm rounded-pill px-3 btn-imprimir-pdf"><i class="bi bi-printer me-1"></i>Imprimir / Guardar PDF</button>
            <a href="<?= url_copa('equipo.php?id=' . (int) $equipo['id']) ?>" class="btn btn-outline-luz btn-sm rounded-pill px-3">Volver al equipo</a>
        </div>
    </div>
</header>

<section class="seccion pt-4">
    <div class="container" style="max-width:900px;">
        <?php if (!empty($suspendidosProximo) || !empty($deudaEquipo)): ?>
        <div class="alert alert-warning rounded-4 border-0 shadow-sm d-flex align-items-start gap-2">
            <i class="bi bi-exclamation-triangle-fill mt-1"></i>
            <div>
                <div class="fw-semibold">Hay jugadores que no podrán jugar el próximo encuentro</div>
                <div class="small">Revisa el detalle en la hoja: suspendidos por tarjetas y multas pendientes.</div>
            </div>
        </div>
        <?php endif; ?>

        <?php // Vista previa idéntica a la hoja impresa ?>
        <div class="card-suave p-4 hoja-previa">
            <?php require __DIR__ . '/../parciales/equipo_hoja.php'; ?>
        </div>
    </div>
</section>
</div>

<?php // ---------- La hoja que se imprime ---------- ?>
<div class="solo-impresion ficha-imprimir">
    <?php require __DIR__ . '/../parciales/equipo_hoja.php'; ?>
</div>
