<?php
/**
 * Dos caras en una sola página:
 *  - .solo-pantalla  : el formulario de opciones (qué imprimir), nunca sale en el papel.
 *  - .solo-impresion : la hoja limpia del calendario, oculta en pantalla salvo vista previa.
 * Mismo patrón que la ficha del partido (ver publico/partido.php).
 */
?>

<div class="solo-pantalla">
<header class="hero-copa" style="padding-bottom:2.5rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-printer me-1"></i>Para imprimir</p>
        <h1 class="text-white mb-2">Calendario de <span class="text-degradado">encuentros</span></h1>
        <p style="color:rgba(255,255,255,.75);" class="mb-0">Elige qué incluir y descárgalo como PDF o imprímelo para repartir a los equipos.</p>
    </div>
</header>

<section class="seccion pt-4">
    <div class="container" style="max-width:820px;">

        <form method="get" class="card-suave p-4 mb-4">
            <h6 class="text-uppercase small fw-bold text-muted mb-3"><i class="bi bi-sliders me-1"></i>Qué quieres imprimir</h6>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-semibold">Alcance</label>
                    <select name="alcance" id="selectAlcance" class="form-select">
                        <option value="todo" <?= $alcance === 'todo' ? 'selected' : '' ?>>Todo el calendario</option>
                        <option value="grupos" <?= $alcance === 'grupos' ? 'selected' : '' ?>>Solo la temporada regular</option>
                        <option value="jornada" <?= $alcance === 'jornada' ? 'selected' : '' ?>>Una jornada</option>
                        <?php if (!$esLiga && !empty($fasesTorneo)): ?>
                        <option value="fase" <?= $alcance === 'fase' ? 'selected' : '' ?>>Una fase de eliminación</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-6" id="grupoJornadaImprimir">
                    <label class="form-label small fw-semibold">Jornada</label>
                    <select name="jornada" class="form-select">
                        <?php foreach (array_keys($jornadas) as $numero): ?>
                        <option value="<?= (int) $numero ?>" <?= $jornadaElegida === (int) $numero ? 'selected' : '' ?>>Jornada <?= (int) $numero ?></option>
                        <?php endforeach; ?>
                        <?php if (empty($jornadas)): ?><option value="">Sin jornadas todavía</option><?php endif; ?>
                    </select>
                </div>

                <?php if (!$esLiga && !empty($fasesTorneo)): ?>
                <div class="col-md-6" id="grupoFaseImprimir">
                    <label class="form-label small fw-semibold">Fase</label>
                    <select name="fase" class="form-select">
                        <?php foreach ($fasesTorneo as $f): ?>
                        <option value="<?= e($f) ?>" <?= $faseElegida === $f ? 'selected' : '' ?>><?= e(FASES_LABEL[$f] ?? ucfirst($f)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="col-12">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="marcadores" value="1" id="chkMarcadores" <?= $conMarcadores ? 'checked' : '' ?>>
                        <label class="form-check-label" for="chkMarcadores">
                            Incluir los marcadores de los encuentros ya jugados
                            <span class="d-block small text-muted">Desmárcalo para repartir solo la programación (fecha, hora y sede).</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="d-flex gap-2 mt-4 flex-wrap">
                <button type="submit" class="btn btn-degradado rounded-pill px-4"><i class="bi bi-eye me-1"></i>Ver vista previa</button>
                <a href="<?= url_copa('calendario.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">Volver al calendario</a>
            </div>
        </form>

        <?php if ($alcance !== ''): ?>
            <?php if ($totalEncuentros === 0): ?>
            <div class="card-suave p-4 text-center text-muted">
                <i class="bi bi-calendar-x fs-3 d-block mb-2 opacity-50"></i>
                No hay encuentros registrados para esa selección.
            </div>
            <?php else: ?>
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                <span class="small text-muted"><i class="bi bi-check2-circle me-1"></i><?= (int) $totalEncuentros ?> encuentro<?= $totalEncuentros === 1 ? '' : 's' ?> en la hoja</span>
                <button type="button" class="btn btn-degradado rounded-pill px-4 btn-imprimir-pdf"><i class="bi bi-printer me-1"></i>Imprimir / Guardar PDF</button>
            </div>

            <?php // Vista previa en pantalla del mismo contenido que saldrá impreso ?>
            <div class="card-suave p-4 hoja-previa">
                <?php require __DIR__ . '/../parciales/calendario_hoja.php'; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
</div>

<?php // ---------- La hoja que de verdad se imprime ---------- ?>
<?php if ($alcance !== '' && $totalEncuentros > 0): ?>
<div class="solo-impresion ficha-imprimir">
    <?php require __DIR__ . '/../parciales/calendario_hoja.php'; ?>
</div>
<?php endif; ?>
