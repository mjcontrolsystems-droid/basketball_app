<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($local['nombre']) ?> vs <?= e($visit['nombre']) ?> — En vivo</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= asset_url('assets/css/style.css') ?>" rel="stylesheet">
    <link rel="icon" href="<?= url('assets/img/logo.png') ?>" type="image/png">
    <?= torneo_variables_css($torneo) ?>
</head>
<body class="pagina-vivo">

<img src="<?= $urlBalon ?>" alt="" class="balon-fondo-vivo">

<?php
// data-duracion-segundos: de aquí arranca la cuenta regresiva. Son los minutos que el
// organizador configuró para la copa (15, 20, 45...) más el tiempo extra agregado dentro
// del encuentro; el reloj corre hacia 00:00 en AMBOS deportes.
?>
<div id="partidoVivo" class="pagina-vivo-contenido" data-url-datos="<?= $urlDatos ?>" data-url-balon="<?= $urlBalon ?>"
    data-texto-gol="<?= e($textoGol) ?>" data-texto-amarilla="<?= e($textoAmarilla) ?>"
    data-texto-roja="<?= e($textoRoja) ?>" data-texto-cambio="<?= e($textoCambio) ?>"
    data-cronometro-estado="<?= e($partido['cronometro_estado'] ?? 'detenido') ?>"
    data-cronometro-segundos="<?= (int) ($partido['cronometro_segundos'] ?? 0) ?>"
    data-cronometro-inicio="<?= e($partido['cronometro_inicio'] ?? '') ?>"
    data-duracion-segundos="<?= partido_duracion_periodo_segundos($partido, $torneo) ?>"
    data-finalizado="<?= $finalizado ? '1' : '0' ?>"
    data-nombre-local="<?= e($local['nombre']) ?>" data-nombre-visitante="<?= e($visit['nombre']) ?>">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 pagina-vivo-cabecera">
        <div id="badgeEnVivo" class="d-flex align-items-center gap-2 badge-en-vivo <?= $finalizado ? 'badge-finalizado' : '' ?>">
            <span class="punto-en-vivo"></span>
            <span id="estadoPartido" class="fw-heading text-uppercase small"><?= $finalizado ? 'Finalizado' : 'En vivo' ?></span>
            <span class="opacity-50">·</span>
            <i class="bi bi-stopwatch"></i>
            <span id="cronometroVivo" class="cronometro-vivo font-monospace">00:00</span>
            <span id="periodoVivo" class="badge rounded-pill bg-light text-dark"><?= e(partido_periodo_etiqueta($deporte, (int) ($partido['cronometro_periodo'] ?? 1))) ?></span>
            <span id="extraVivo" class="badge rounded-pill bg-warning text-dark<?= $minutosExtra > 0 ? '' : ' d-none' ?>">+<?= $minutosExtra ?> min</span>
        </div>
        <span class="small opacity-75 fw-semibold d-flex align-items-center gap-2">
            <?= logo_torneo($torneo, 26) ?><?= e($torneo['nombre']) ?> · <?= e(formatear_fecha_larga($partido['fecha'])) ?>
        </span>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-outline-luz rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalCompartir">
                <i class="bi bi-qr-code me-1"></i>Compartir
            </button>
            <button type="button" id="btnPantallaCompleta" class="btn btn-sm btn-outline-luz rounded-pill px-3">
                <i class="bi bi-arrows-fullscreen me-1"></i>Pantalla completa
            </button>
        </div>
    </div>

    <div class="marcador-vivo">
        <div class="marcador-vivo-equipo" style="--color-equipo:<?= e($colorLocal) ?>;">
            <div class="marcador-vivo-logo"><?= logo_equipo($local, 100) ?></div>
            <span class="marcador-vivo-nombre"><?= e($local['nombre']) ?></span>
        </div>
        <div class="marcador-vivo-numeros">
            <span id="marcadorLocal" class="marcador-vivo-num"><?= $marcadorLocalActual ?></span>
            <span class="marcador-vivo-guion">-</span>
            <span id="marcadorVisitante" class="marcador-vivo-num"><?= $marcadorVisitanteActual ?></span>
        </div>
        <div class="marcador-vivo-equipo" style="--color-equipo:<?= e($colorVisit) ?>;">
            <div class="marcador-vivo-logo"><?= logo_equipo($visit, 100) ?></div>
            <span class="marcador-vivo-nombre"><?= e($visit['nombre']) ?></span>
        </div>
    </div>

    <?php
    // Cartel de cierre bajo el marcador. Se pinta ya visible si el partido llegó terminado;
    // si termina con la página abierta, partido_vivo.js le agrega .aviso-final-visible.
    $textoFinal = '';
    if ($finalizado) {
        if ($marcadorLocalActual > $marcadorVisitanteActual) {
            $textoFinal = 'Gana ' . $local['nombre'] . ' ' . $marcadorLocalActual . '-' . $marcadorVisitanteActual;
        } elseif ($marcadorVisitanteActual > $marcadorLocalActual) {
            $textoFinal = 'Gana ' . $visit['nombre'] . ' ' . $marcadorVisitanteActual . '-' . $marcadorLocalActual;
        } else {
            $textoFinal = 'Empate ' . $marcadorLocalActual . '-' . $marcadorVisitanteActual;
        }
    }
    ?>
    <div id="avisoFinal" class="aviso-final<?= $finalizado ? ' aviso-final-visible' : '' ?>">
        <span class="aviso-final-titulo"><i class="bi bi-flag-fill me-2"></i>Partido finalizado</span>
        <span id="avisoFinalDetalle" class="aviso-final-detalle"><?= e($textoFinal) ?></span>
    </div>

    <div class="pagina-vivo-feed">
        <h6 class="text-uppercase small fw-bold opacity-75 mb-3"><i class="bi bi-broadcast me-1"></i><?= e(etiqueta_anotaciones($deporte)) ?>, tarjetas y cambios</h6>
        <ul id="feedEventos" class="feed-eventos list-unstyled mb-0">
            <li class="feed-evento-vacio text-center opacity-50 py-4">Esperando el inicio del partido...</li>
        </ul>
    </div>

    <?php if (!empty($alineacion)): ?>
    <div class="pagina-vivo-alineaciones">
        <h6 class="text-uppercase small fw-bold opacity-75 mb-3"><i class="bi bi-diagram-3 me-1"></i>Alineaciones</h6>
        <div class="row g-3">
            <?php foreach ([$local, $visit] as $equipoLado): ?>
            <div class="col-md-6">
                <div class="alineacion-equipo">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <?= logo_equipo($equipoLado, 30) ?>
                        <span class="fw-semibold"><?= e($equipoLado['nombre']) ?></span>
                    </div>
                    <?php foreach ([true, false] as $esTitular): ?>
                        <?php $lista = alineacion_de_equipo($alineacion, $jugadoresPorId, (int) $equipoLado['id'], $esTitular, $deporte); ?>
                        <?php if (empty($lista)) { continue; } ?>
                        <div class="small text-uppercase opacity-50 mt-2 mb-1"><?= $esTitular ? 'Titulares' : 'Banca' ?></div>
                        <ul class="list-unstyled mb-0 small">
                            <?php foreach ($lista as $fila): ?>
                            <li class="d-flex align-items-center gap-2 py-1">
                                <span class="punto-titular<?= $esTitular ? ' es-titular' : '' ?>"></span>
                                <span class="fw-semibold">#<?= e($fila['jugador']['dorsal']) ?></span>
                                <span class="flex-grow-1"><?= e($fila['jugador']['nombre']) ?></span>
                                <span class="badge rounded-pill bg-light text-dark"><?= e(posicion_label($deporte, $fila['posicion'], true)) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<div id="bannerEvento" class="banner-evento">
    <span id="bannerEventoIcono" class="banner-evento-icono"></span>
    <span id="bannerEventoTexto" class="banner-evento-texto"></span>
</div>

<?php vista('parciales/modal_compartir', compact('torneo', 'compartir_url', 'compartir_titulo')); ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="<?= asset_url('assets/js/app.js') ?>"></script>
<script src="<?= asset_url('assets/js/partido_vivo.js') ?>"></script>
</body>
</html>
