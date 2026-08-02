<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/tabla.php';
require_once __DIR__ . '/includes/liga.php';
require_once __DIR__ . '/includes/usuarios.php';
require_once __DIR__ . '/includes/torneo_actual.php';

$id = (int) ($_GET['id'] ?? 0);
$partidos = db_leer('partidos', $torneo['id']);
$partido = db_buscar_por_id($partidos, $id);

$equipos = db_leer('equipos', $torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[$eq['id']] = $eq;
}

$local = $partido ? ($equiposPorId[$partido['equipo_local']] ?? null) : null;
$visit = $partido ? ($equiposPorId[$partido['equipo_visitante']] ?? null) : null;

// La ficha de partido está disponible para cualquier copa o liga; si el partido o
// alguno de los equipos no existe (o no pertenece a esta copa), es un 404.
if (!$partido || !$local || !$visit) {
    http_response_code(404);
    $titulo_pagina = 'Partido no encontrado';
    require __DIR__ . '/includes/layout_top.php';
    echo '<div class="container seccion text-center"><h1>Partido no encontrado</h1><a href="' . url_copa('calendario.php') . '" class="btn btn-degradado rounded-pill mt-3">Volver al calendario</a></div>';
    require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$jugado = $partido['estado'] === 'jugado';
$deporte = $torneo['deporte'] ?? null;
$basketball = es_basketball($deporte);

$jugadoresTodos = db_leer('jugadores', $torneo['id']);
$jugadoresPorId = jugadores_por_id($jugadoresTodos);

$eventos = db_leer_eventos_partido($torneo['id'], $id);
$goles = array_values(array_filter($eventos, fn($e) => $e['tipo'] === 'gol'));
$amarillas = array_values(array_filter($eventos, fn($e) => $e['tipo'] === 'amarilla'));
$rojas = array_values(array_filter($eventos, fn($e) => $e['tipo'] === 'roja'));
$cambios = array_values(array_filter($eventos, fn($e) => $e['tipo'] === 'cambio'));

// El admin puede ir cargando goles/tarjetas/cambios desde antes de marcar el partido como
// "jugado" (se captura el marcador al final). La ficha/descarga debe estar disponible en
// cuanto haya algo que mostrar, no solo cuando el estado ya es "jugado" — si no, el botón
// "Descargar PDF" de la pantalla de Eventos manda a una página vacía sin nada para imprimir.
$hayFicha = $jugado || !empty($eventos) || !empty($partido['observaciones']);

// Para dejar constancia de quién descargó la ficha (control interno): solo aplica si
// quien la pide tiene sesión de organizador iniciada; un visitante público no queda registrado.
$usuarioImprime = null;
if (!empty($_SESSION['usuario_autenticado']) && !empty($_SESSION['usuario_id'])) {
    $usuarioImprime = usuarios_obtener_por_id((int) $_SESSION['usuario_id']);
}

// Para la ficha imprimible (ver .solo-impresion más abajo): "-" cuando el dato no aplica,
// para que se vea como un formulario lleno a mano, no como una página web recortada.
function ficha_valor(?string $valor): string
{
    $valor = trim((string) $valor);
    return $valor === '' ? '—' : e($valor);
}

$titulo_pagina = $local['nombre'] . ' vs ' . $visit['nombre'] . ' — ' . $torneo['nombre'];
$pagina_activa = 'calendario';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="solo-pantalla">
<header class="hero-copa" style="padding-bottom:3rem;">
    <div class="container">
        <p class="kicker mb-2"><i class="bi bi-calendar3 me-1"></i><?= e(formatear_fecha_larga($partido['fecha'])) ?> · <?= e($partido['hora']) ?></p>
        <div class="d-flex align-items-center justify-content-center gap-4 flex-wrap text-center">
            <a href="<?= url_copa('equipo.php?id=' . $local['id']) ?>" class="d-flex flex-column align-items-center gap-2 text-decoration-none text-white" style="width:40%;">
                <?= logo_equipo($local, 72) ?>
                <span class="fw-bold"><?= e($local['nombre']) ?></span>
            </a>
            <div class="fs-1 fw-bold text-white">
                <?php if ($jugado): ?>
                    <?= (int) $partido['marcador_local'] ?> - <?= (int) $partido['marcador_visitante'] ?>
                <?php else: ?>
                    VS
                <?php endif; ?>
            </div>
            <a href="<?= url_copa('equipo.php?id=' . $visit['id']) ?>" class="d-flex flex-column align-items-center gap-2 text-decoration-none text-white" style="width:40%;">
                <?= logo_equipo($visit, 72) ?>
                <span class="fw-bold"><?= e($visit['nombre']) ?></span>
            </a>
        </div>
        <p class="text-center mt-3 mb-0" style="color:rgba(255,255,255,.75);">
            <i class="bi bi-geo-alt me-1"></i><?= e($partido['cancha']) ?>
            <?php if (!empty($partido['arbitro'])): ?> · <i class="bi bi-person-badge me-1"></i>Árbitro: <?= e($partido['arbitro']) ?><?php endif; ?>
        </p>
        <?php if ($hayFicha): ?>
        <div class="text-center mt-3 d-flex justify-content-center gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-luz btn-sm rounded-pill px-3 btn-imprimir-pdf"><i class="bi bi-download me-1"></i>Descargar PDF</button>
            <?php if ($jugado): ?>
            <a href="<?= url_copa('partido_imagen.php?id=' . $id) ?>" class="btn btn-outline-luz btn-sm rounded-pill px-3"><i class="bi bi-image me-1"></i>Imagen para compartir</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</header>

<section class="seccion pt-4">
    <div class="container" style="max-width:760px;">
        <?php if (!$hayFicha): ?>
            <div class="card-suave p-4 text-center text-muted">
                <i class="bi bi-clock-history fs-3 d-block mb-2 opacity-50"></i>
                Este partido todavía no se ha jugado.
            </div>
        <?php elseif (empty($eventos)): ?>
            <div class="card-suave p-4 text-center text-muted">
                <i class="bi bi-clipboard-data fs-3 d-block mb-2 opacity-50"></i>
                Todavía no se ha cargado la ficha de este partido.
            </div>
        <?php else: ?>

            <?php if (!empty($goles)): ?>
            <div class="card-suave p-4 mb-3">
                <h6 class="text-uppercase small fw-bold text-muted mb-3"><?= icono_balon_img($deporte, 18) ?> <?= e(etiqueta_anotaciones($deporte)) ?></h6>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($goles as $ev): ?>
                    <li class="mb-2 small"><span class="fw-semibold"><?= e($equiposPorId[$ev['equipo_id']]['nombre'] ?? '') ?>:</span> <?= e(evento_descripcion($ev, $jugadoresPorId, $deporte)) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($amarillas)): ?>
            <div class="card-suave p-4 mb-3">
                <h6 class="text-uppercase small fw-bold text-muted mb-3"><i class="bi bi-square-fill text-warning me-1"></i><?= e(etiqueta_faltas_leves($deporte)) ?></h6>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($amarillas as $ev): ?>
                    <li class="mb-2 small"><span class="fw-semibold"><?= e($equiposPorId[$ev['equipo_id']]['nombre'] ?? '') ?>:</span> <?= e(evento_descripcion($ev, $jugadoresPorId, $deporte)) ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php // Expulsión por acumulación en ambos deportes: 5 faltas (FIBA) o doble amarilla (IFAB) ?>
                <?php $expulsados = array_filter(faltas_por_jugador($amarillas), fn($n) => $n >= limite_faltas_expulsion($deporte)); ?>
                <?php foreach ($expulsados as $jid => $n): ?>
                <p class="small text-danger fw-semibold mt-2 mb-0"><i class="bi bi-exclamation-triangle me-1"></i><?= e(jugador_nombre($jugadoresPorId[$jid] ?? null)) ?> — <?= e(forma_genero($torneo['genero'] ?? null, 'expulsado', 'expulsada')) ?> por <?= e(texto_expulsion_acumulacion($deporte, $n)) ?>.</p>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if (!empty($rojas)): ?>
            <div class="card-suave p-4 mb-3">
                <h6 class="text-uppercase small fw-bold text-muted mb-3"><i class="bi bi-square-fill text-danger me-1"></i><?= e(etiqueta_faltas_graves($deporte)) ?></h6>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($rojas as $ev): ?>
                    <li class="mb-2 small"><span class="fw-semibold"><?= e($equiposPorId[$ev['equipo_id']]['nombre'] ?? '') ?>:</span> <?= e(evento_descripcion($ev, $jugadoresPorId, $deporte)) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <?php if (!empty($cambios)): ?>
            <div class="card-suave p-4 mb-3">
                <h6 class="text-uppercase small fw-bold text-muted mb-3"><i class="bi bi-arrow-left-right text-info me-1"></i>Cambios</h6>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($cambios as $ev): ?>
                    <li class="mb-2 small"><span class="fw-semibold"><?= e($equiposPorId[$ev['equipo_id']]['nombre'] ?? '') ?>:</span> <?= e(evento_descripcion($ev, $jugadoresPorId, $deporte)) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

        <?php endif; ?>

        <?php if (!empty($partido['observaciones'])): ?>
        <div class="card-suave p-4">
            <h6 class="text-uppercase small fw-bold text-muted mb-2">Observaciones</h6>
            <p class="mb-0 small"><?= nl2br(e($partido['observaciones'])) ?></p>
        </div>
        <?php endif; ?>
    </div>
</section>
</div>

<?php // Ficha imprimible: solo se muestra en el PDF/impresión (ver @media print en style.css).
      // Es un documento aparte -no la página web recortada- con el mismo look de un formulario
      // de arbitraje lleno a mano: sin logos grandes ni fondo de color, solo los datos. ?>
<div class="solo-impresion ficha-imprimir">
    <div class="ficha-titulo">
        <h2><?= e($torneo['nombre']) ?></h2>
        <p>Ficha oficial de partido</p>
    </div>

    <table class="ficha-datos">
        <tr>
            <td><strong>Equipo local</strong></td><td><?= e($local['nombre']) ?></td>
            <td><strong>Equipo visitante</strong></td><td><?= e($visit['nombre']) ?></td>
        </tr>
        <tr>
            <td><strong>Marcador</strong></td><td><?= $jugado ? (int) $partido['marcador_local'] . ' - ' . (int) $partido['marcador_visitante'] : '—' ?></td>
            <td><strong>Fecha</strong></td><td><?= e(formatear_fecha_larga($partido['fecha'])) . ' · ' . e($partido['hora']) ?></td>
        </tr>
        <tr>
            <td><strong>Cancha</strong></td><td><?= ficha_valor($partido['cancha']) ?></td>
            <td><strong>Árbitro</strong></td><td><?= empty($partido['arbitro']) ? '<span class="ficha-linea-blanco"></span>' : e($partido['arbitro']) ?></td>
        </tr>
    </table>

    <h3><?= $basketball ? '🏀' : '⚽' ?> <?= e(etiqueta_anotaciones($deporte)) ?></h3>
    <table class="ficha-tabla">
        <thead><tr><th>Min.</th><th>Equipo</th><th>Jugador</th><th>Tipo</th><th>Asistencia</th></tr></thead>
        <tbody>
            <?php foreach ($goles as $ev): ?>
            <tr>
                <td><?= $ev['minuto'] !== null ? e((string) $ev['minuto']) . "'" : '—' ?></td>
                <td><?= e($equiposPorId[$ev['equipo_id']]['nombre'] ?? '—') ?></td>
                <td><?= e(jugador_nombre($jugadoresPorId[(int) ($ev['jugador_id'] ?? 0)] ?? null)) ?></td>
                <td><?= e(tipos_anotacion_label($deporte)[$ev['tipo_gol'] ?? ''] ?? '—') ?></td>
                <td><?= !empty($ev['asistencia_jugador_id']) ? e(jugador_nombre($jugadoresPorId[(int) $ev['asistencia_jugador_id']] ?? null)) : '—' ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($goles)): ?><tr><td colspan="5">Sin <?= e(mb_strtolower(etiqueta_anotaciones($deporte))) ?> registrados.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <h3>🟨 <?= e(etiqueta_faltas_leves($deporte)) ?></h3>
    <table class="ficha-tabla">
        <thead><tr><th>Min.</th><th>Equipo</th><th>Jugador</th></tr></thead>
        <tbody>
            <?php foreach ($amarillas as $ev): ?>
            <tr>
                <td><?= $ev['minuto'] !== null ? e((string) $ev['minuto']) . "'" : '—' ?></td>
                <td><?= e($equiposPorId[$ev['equipo_id']]['nombre'] ?? '—') ?></td>
                <td><?= e(jugador_nombre($jugadoresPorId[(int) ($ev['jugador_id'] ?? 0)] ?? null)) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($amarillas)): ?><tr><td colspan="3">Sin <?= e(mb_strtolower(etiqueta_faltas_leves($deporte))) ?> registradas.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <h3>🟥 <?= e(etiqueta_faltas_graves($deporte)) ?></h3>
    <table class="ficha-tabla">
        <thead><tr><th>Min.</th><th>Equipo</th><th>Jugador</th><th>Motivo</th></tr></thead>
        <tbody>
            <?php foreach ($rojas as $ev): ?>
            <tr>
                <td><?= $ev['minuto'] !== null ? e((string) $ev['minuto']) . "'" : '—' ?></td>
                <td><?= e($equiposPorId[$ev['equipo_id']]['nombre'] ?? '—') ?></td>
                <td><?= e(jugador_nombre($jugadoresPorId[(int) ($ev['jugador_id'] ?? 0)] ?? null)) ?></td>
                <td><?= e(motivos_falta_grave_label($deporte)[$ev['motivo'] ?? ''] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($rojas)): ?><tr><td colspan="4">Sin <?= e(mb_strtolower(etiqueta_faltas_graves($deporte))) ?> registradas.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <h3>🔄 Cambios</h3>
    <table class="ficha-tabla">
        <thead><tr><th>Min.</th><th>Equipo</th><th>Sale</th><th>Entra</th></tr></thead>
        <tbody>
            <?php foreach ($cambios as $ev): ?>
            <tr>
                <td><?= $ev['minuto'] !== null ? e((string) $ev['minuto']) . "'" : '—' ?></td>
                <td><?= e($equiposPorId[$ev['equipo_id']]['nombre'] ?? '—') ?></td>
                <td><?= e(jugador_nombre($jugadoresPorId[(int) ($ev['jugador_id'] ?? 0)] ?? null)) ?></td>
                <td><?= e(jugador_nombre($jugadoresPorId[(int) ($ev['jugador_entra_id'] ?? 0)] ?? null)) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($cambios)): ?><tr><td colspan="4">Sin cambios registrados.</td></tr><?php endif; ?>
        </tbody>
    </table>

    <h3>Observaciones</h3>
    <p class="ficha-observaciones"><?= !empty($partido['observaciones']) ? nl2br(e($partido['observaciones'])) : '—' ?></p>

    <div class="ficha-firma">
        <div class="ficha-firma-linea">Firma del árbitro</div>
    </div>

    <div class="ficha-pie">
        <?php if ($usuarioImprime): ?>
        <p>Impreso por: <?= e($usuarioImprime['nombre'] ?: $usuarioImprime['email']) ?> · <?= e(date('d/m/Y H:i')) ?></p>
        <?php endif; ?>
        <p>MJ Control Systems · Plataformas web inteligentes, control total de tu negocio.</p>
        <p>Contrataciones: mjcontrolsystems@gmail.com</p>
    </div>
</div>

<?php // El auto-print de ?imprimir=1 vive en assets/js/app.js (data-imprimir-al-cargar):
      // un <script> inline aquí quedaba BLOQUEADO por el CSP del sitio y nunca corría. ?>
<?php if ($hayFicha && ($_GET['imprimir'] ?? '') === '1'): ?>
<span data-imprimir-al-cargar hidden></span>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
