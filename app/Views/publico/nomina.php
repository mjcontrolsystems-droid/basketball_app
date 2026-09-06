<?php
/**
 * Nómina para el árbitro: se imprime con las casillas VACÍAS y el capitán marca a mano
 * quiénes van de titulares. La hoja se pinta dos veces (pantalla e impresión), igual que
 * el reporte del equipo.
 */
$casilla = '<span style="display:inline-block;width:14px;height:14px;border:1.5px solid #000;vertical-align:middle;"></span>';
?>
<div class="solo-pantalla">
<header class="hero-copa" style="padding-bottom:2.5rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-clipboard-check me-1"></i>Nómina para el árbitro</p>
        <div class="d-flex align-items-center gap-3 flex-wrap">
            <?= logo_equipo($equipo, 64) ?>
            <div>
                <h1 class="text-white mb-1"><?= e($equipo['nombre']) ?></h1>
                <p style="color:rgba(255,255,255,.75);" class="mb-0">
                    Se imprime, el capitán marca con lapicero a los presentes que jugarán, firma y la entrega a la mesa.
                </p>
            </div>
        </div>
        <div class="mt-3 d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-degradado btn-sm rounded-pill px-3 btn-imprimir-pdf"><i class="bi bi-printer me-1"></i>Imprimir nómina</button>
            <a href="<?= url_copa('equipo.php?id=' . (int) $equipo['id']) ?>" class="btn btn-outline-luz btn-sm rounded-pill px-3">Volver al equipo</a>
        </div>
        <?php if (count($proximosDelEquipo) > 1): ?>
        <?php // Cada botón dice fecha Y rival. Con la fecha sola, los dos encuentros de un
              // equipo que juega dos veces la misma jornada salían con la misma etiqueta y
              // no había forma de saber cuál se estaba imprimiendo. Los ya jugados llevan
              // un visto: se pueden reimprimir, pero se ve que son de una fecha pasada. ?>
        <div class="mt-3 d-flex gap-2 flex-wrap align-items-center">
            <span class="small" style="color:rgba(255,255,255,.75);">Para el encuentro de:</span>
            <?php // Seis como máximo: cada botón ahora lleva fecha y rival, y en un
                  // teléfono ocho de estos llenan la pantalla antes de la propia hoja. ?>
            <?php foreach (array_slice($proximosDelEquipo, 0, 6) as $pp): ?>
            <?php
            $esLocalPP = (int) $pp['equipo_local'] === (int) $equipo['id'];
            $rivalPP = $equiposPorId[$esLocalPP ? (int) $pp['equipo_visitante'] : (int) $pp['equipo_local']] ?? null;
            $yaJugado = ($pp['estado'] ?? '') === 'jugado';
            ?>
            <a href="<?= url_copa('nomina.php?id=' . (int) $equipo['id'] . '&partido=' . (int) $pp['id']) ?>"
               class="btn btn-sm rounded-pill px-3 <?= $partidoHoja && (int) $partidoHoja['id'] === (int) $pp['id'] ? 'btn-degradado' : 'btn-outline-luz' ?>"
               title="Jornada <?= (int) ($pp['jornada'] ?? 0) ?><?= $yaJugado ? ' · ya jugado' : '' ?>">
                <?php if ($yaJugado): ?><i class="bi bi-check2 me-1"></i><?php endif; ?>
                <?= e(formatear_fecha_corta((string) $pp['fecha'])) ?>
                <?php if ($rivalPP !== null): ?>
                <span class="opacity-75">· <?= e($rivalPP['nombre']) ?></span>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</header>

<section class="seccion pt-4">
    <div class="container" style="max-width:820px;">
        <div class="card-suave p-4 hoja-previa">
            <?php require __DIR__ . '/../parciales/nomina_hoja.php'; ?>
        </div>
    </div>
</section>
</div>

<?php // ---------- La hoja que se imprime ---------- ?>
<div class="solo-impresion ficha-imprimir">
    <?php require __DIR__ . '/../parciales/nomina_hoja.php'; ?>
</div>
