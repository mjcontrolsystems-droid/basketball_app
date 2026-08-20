<?php
/**
 * Tarjeta pública de un encuentro, usada por el calendario del sitio.
 *
 * Espera $p (el partido) y $equiposPorId. Si alguno de los dos equipos ya no existe
 * (se borró del torneo), no pinta nada en vez de reventar.
 */
$local = $equiposPorId[$p['equipo_local']] ?? null;
$visit = $equiposPorId[$p['equipo_visitante']] ?? null;
if (!$local || !$visit) {
    return;
}
$jugado = $p['estado'] === 'jugado';
// Todos los encuentros se pueden abrir, no solo los jugados. Antes solo era clicable el
// que ya tenía resultado, y el hincha que quería ver a qué hora y en qué cancha juega su
// equipo el sábado se quedaba sin poder entrar. La ficha ya sabe mostrar un partido
// pendiente: enseña fecha, hora, sede y la alineación si está cargada.
$clicable = true;
?>
<div class="col">
    <div class="partido-card h-100 <?= $clicable ? 'fila-clicable' : '' ?>" <?= $clicable ? 'data-href="' . e(url_copa('partido.php?id=' . $p['id'])) . '"' : '' ?>>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?= formatear_fecha_larga($p['fecha']) ?> · <?= e($p['hora']) ?></span>
            <?php if ($jugado): ?>
                <span class="badge badge-estado-jugado rounded-pill px-3 py-2"><i class="bi bi-check-circle me-1"></i>Finalizado</span>
            <?php else: ?>
                <span class="badge badge-estado-programado rounded-pill px-3 py-2"><i class="bi bi-clock me-1"></i>Programado</span>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center justify-content-between">
            <a href="<?= url_copa('equipo.php?id=' . $local['id']) ?>" class="equipo-col text-decoration-none text-dark">
                <?= logo_equipo($local, 56) ?>
                <span class="nombre <?= $jugado && $p['marcador_local'] > $p['marcador_visitante'] ? 'text-success' : '' ?>"><?= e($local['nombre']) ?></span>
            </a>
            <div class="marcador text-center">
                <?php if ($jugado): ?>
                    <?= (int) $p['marcador_local'] ?> - <?= (int) $p['marcador_visitante'] ?>
                <?php else: ?>
                    <span class="text-muted fs-5">VS</span>
                <?php endif; ?>
            </div>
            <a href="<?= url_copa('equipo.php?id=' . $visit['id']) ?>" class="equipo-col text-decoration-none text-dark">
                <?= logo_equipo($visit, 56) ?>
                <span class="nombre <?= $jugado && $p['marcador_visitante'] > $p['marcador_local'] ? 'text-success' : '' ?>"><?= e($visit['nombre']) ?></span>
            </a>
        </div>
        <p class="text-center small text-muted mt-2 mb-0"><i class="bi bi-geo-alt me-1"></i><?= e($p['cancha']) ?></p>
    </div>
</div>
