<?php
/**
 * Hoja del calendario: se usa DOS veces en calendario_imprimir.php — como vista previa
 * en pantalla y como el documento que sale en el papel. Por eso vive en un parcial:
 * lo que ves en la vista previa es exactamente lo que se imprime.
 *
 * Espera: $torneo, $bloques (cada uno con titulo + partidos), $equiposPorId, $conMarcadores.
 */
?>
<div class="ficha-titulo">
    <h2><?= e($torneo['nombre']) ?></h2>
    <p>
        Calendario de encuentros<?= !empty($torneo['temporada']) ? ' · Temporada ' . e($torneo['temporada']) : '' ?>
        <?= !$conMarcadores ? ' · Programación' : '' ?>
    </p>
</div>

<?php foreach ($bloques as $bloque): ?>
<h3><?= e($bloque['titulo']) ?></h3>
<table class="ficha-tabla">
    <thead>
        <tr>
            <th style="width:16%;">Fecha</th>
            <th style="width:9%;">Hora</th>
            <th>Local</th>
            <?php if ($conMarcadores): ?><th style="width:11%;">Resultado</th><?php endif; ?>
            <th>Visitante</th>
            <th style="width:20%;">Cancha / Sede</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($bloque['partidos'] as $p): ?>
        <?php
            $local = $equiposPorId[$p['equipo_local']] ?? null;
            $visit = $equiposPorId[$p['equipo_visitante']] ?? null;
            $jugado = ($p['estado'] ?? '') === 'jugado';
        ?>
        <tr>
            <td><?= e(formatear_fecha_larga($p['fecha'])) ?></td>
            <td><?= e($p['hora']) ?></td>
            <td><?= e($local['nombre'] ?? '—') ?></td>
            <?php if ($conMarcadores): ?>
            <td style="text-align:center;">
                <?= $jugado ? (int) $p['marcador_local'] . ' - ' . (int) $p['marcador_visitante'] : 'vs' ?>
            </td>
            <?php endif; ?>
            <td><?= e($visit['nombre'] ?? '—') ?></td>
            <td><?= e($p['cancha'] ?? '') !== '' ? e($p['cancha']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endforeach; ?>

<div class="ficha-pie">
    <p>Generado el <?= e(date('d/m/Y')) ?> · <?= e(SITE_ORIGIN . url_copa('calendario.php')) ?></p>
    <p>MJ Control Systems · Plataformas web inteligentes, control total de tu negocio.</p>
</div>
