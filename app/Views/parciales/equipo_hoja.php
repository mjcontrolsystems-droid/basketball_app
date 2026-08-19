<?php
/**
 * Hoja del reporte de equipo. Se usa DOS veces (vista previa en pantalla y documento
 * impreso), así que lo que ves antes de imprimir es exactamente lo que sale en el papel.
 */
?>
<div class="ficha-titulo">
    <h2><?= e($equipo['nombre']) ?></h2>
    <p>
        <?= e($torneo['nombre']) ?><?= !empty($torneo['temporada']) ? ' · Temporada ' . e($torneo['temporada']) : '' ?>
        <?= !empty($equipo['ciudad']) ? ' · ' . e($equipo['ciudad']) : '' ?>
    </p>
</div>

<?php // ---------- Podio ----------
      // Solo aparece si la temporada cerró y el equipo quedó entre los tres. Va arriba de
      // todo porque es lo primero que un club quiere ver en su reporte de cierre. ?>
<?php if (!empty($podioEquipo)): ?>
<p class="ficha-podio" style="text-align:center;font-weight:600;border:2px solid #000;padding:8px;margin:0 0 14px;">
    <?= $podioEquipo === 1 ? '🥇' : ($podioEquipo === 2 ? '🥈' : '🥉') ?>
    <?= e(podio_titulo($podioEquipo, $torneo['genero'] ?? null)) ?>
    de <?= e($torneo['nombre']) ?><?= !empty($torneo['temporada']) ? ' · Temporada ' . e($torneo['temporada']) : '' ?>
</p>
<?php endif; ?>

<?php // ---------- Situación en la tabla ---------- ?>
<h3>Posición en la tabla</h3>
<?php if ($filaEquipo !== null): ?>
<table class="ficha-tabla">
    <thead>
        <tr>
            <th>Puesto</th><th>PJ</th><th>PG</th>
            <?php if (!empty($torneo['permite_empates'])): ?><th>PE</th><?php endif; ?>
            <th>PP</th><th>PF</th><th>PC</th><th>DIF</th><th>PTS</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td><strong><?= (int) $filaEquipo['posicion'] ?>° de <?= (int) $totalEquipos ?></strong></td>
            <td><?= (int) $filaEquipo['pj'] ?></td>
            <td><?= (int) $filaEquipo['pg'] ?></td>
            <?php if (!empty($torneo['permite_empates'])): ?><td><?= (int) $filaEquipo['pe'] ?></td><?php endif; ?>
            <td><?= (int) $filaEquipo['pp'] ?></td>
            <td><?= (int) $filaEquipo['pf'] ?></td>
            <td><?= (int) $filaEquipo['pc'] ?></td>
            <td><?= $filaEquipo['dif'] >= 0 ? '+' : '' ?><?= (int) $filaEquipo['dif'] ?></td>
            <td><strong><?= (int) $filaEquipo['pts'] ?></strong></td>
        </tr>
    </tbody>
</table>
<?php else: ?>
<p>Este equipo todavía no aparece en la tabla de posiciones.</p>
<?php endif; ?>

<?php // ---------- Quién no puede jugar la próxima fecha ---------- ?>
<?php if (!empty($suspendidosProximo) || !empty($deudaEquipo)): ?>
<h3>Atención: no habilitados para el próximo encuentro</h3>
<table class="ficha-tabla">
    <thead><tr><th style="width:45%;">Jugador</th><th>Situación</th></tr></thead>
    <tbody>
        <?php foreach ($suspendidosProximo as $jid => $info): ?>
        <tr>
            <td><?= e(jugador_nombre($jugadoresPorId[$jid] ?? null)) ?></td>
            <td>SUSPENDIDO — <?= e($info['detalle']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php foreach ($deudaEquipo as $jid => $info): ?>
        <tr>
            <td><?= e(jugador_nombre($jugadoresPorId[$jid] ?? null)) ?></td>
            <td>Multa pendiente de <?= e(sancion_monto_texto($torneo, $info['total'])) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php // ---------- Próximos encuentros ---------- ?>
<h3>Próximos encuentros</h3>
<table class="ficha-tabla">
    <thead><tr><th style="width:20%;">Fecha</th><th style="width:10%;">Hora</th><th>Rival</th><th style="width:14%;">Condición</th><th style="width:24%;">Cancha</th></tr></thead>
    <tbody>
        <?php foreach (array_slice($proximos, 0, 10) as $p): ?>
        <?php
            $esLocal = (int) $p['equipo_local'] === (int) $equipo['id'];
            $rivalId = $esLocal ? (int) $p['equipo_visitante'] : (int) $p['equipo_local'];
        ?>
        <tr>
            <td><?= e(formatear_fecha_larga($p['fecha'])) ?></td>
            <td><?= e($p['hora']) ?></td>
            <td><?= e($equiposPorId[$rivalId]['nombre'] ?? '—') ?></td>
            <td><?= $esLocal ? 'Local' : 'Visitante' ?></td>
            <td><?= e($p['cancha'] ?? '') !== '' ? e($p['cancha']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($proximos)): ?>
        <tr><td colspan="5">No hay encuentros pendientes.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php // ---------- Resultados ---------- ?>
<h3>Últimos resultados</h3>
<table class="ficha-tabla">
    <thead><tr><th style="width:20%;">Fecha</th><th>Rival</th><th style="width:16%;">Resultado</th><th style="width:14%;">Marcador</th></tr></thead>
    <tbody>
        <?php foreach (array_slice($jugados, 0, 10) as $p): ?>
        <?php
            $esLocal = (int) $p['equipo_local'] === (int) $equipo['id'];
            $rivalId = $esLocal ? (int) $p['equipo_visitante'] : (int) $p['equipo_local'];
            $propios = (int) ($esLocal ? $p['marcador_local'] : $p['marcador_visitante']);
            $ajenos = (int) ($esLocal ? $p['marcador_visitante'] : $p['marcador_local']);
            $signo = $propios > $ajenos ? 'Ganó' : ($propios < $ajenos ? 'Perdió' : 'Empató');
        ?>
        <tr>
            <td><?= e(formatear_fecha_larga($p['fecha'])) ?></td>
            <td><?= e($equiposPorId[$rivalId]['nombre'] ?? '—') ?></td>
            <td><?= $signo ?></td>
            <td><?= $propios ?> - <?= $ajenos ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($jugados)): ?>
        <tr><td colspan="4">Todavía no ha jugado ningún encuentro.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php // ---------- Plantilla y estadísticas individuales ---------- ?>
<h3>Plantilla y estadísticas · <?= e(etiqueta_anotaciones($deporte)) ?>: <?= (int) $totalAnotaciones ?> · <?= e(etiqueta_faltas_leves($deporte)) ?>: <?= (int) $totalAmarillas ?> · <?= e(etiqueta_faltas_graves($deporte)) ?>: <?= (int) $totalRojas ?></h3>
<table class="ficha-tabla">
    <thead>
        <tr>
            <th style="width:8%;">#</th>
            <th>Jugador</th>
            <th style="width:16%;">Posición</th>
            <th style="width:12%;"><?= e(etiqueta_anotaciones($deporte)) ?></th>
            <th style="width:12%;"><?= e(etiqueta_ta($deporte)) ?></th>
            <th style="width:12%;"><?= e(etiqueta_tr($deporte)) ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($plantilla as $j): ?>
        <?php $st = $statsJugador[(int) $j['id']] ?? ['anotaciones' => 0, 'amarillas' => 0, 'rojas' => 0]; ?>
        <tr>
            <td><?= e($j['dorsal']) ?></td>
            <td>
                <?= e($j['nombre']) ?>
                <?php if (empty($j['activo'])): ?> (inactivo)<?php endif; ?>
                <?php if (isset($suspendidosProximo[(int) $j['id']])): ?> — SUSPENDIDO<?php endif; ?>
            </td>
            <td><?= e(posicion_label($deporte, (string) ($j['posicion'] ?? '')) ?: '—') ?></td>
            <td><?= (int) $st['anotaciones'] ?></td>
            <td><?= (int) $st['amarillas'] ?></td>
            <td><?= (int) $st['rojas'] ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($plantilla)): ?>
        <tr><td colspan="6">Sin plantilla registrada.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<div class="ficha-pie">
    <p>Generado el <?= e(date('d/m/Y H:i')) ?> · <?= e(SITE_ORIGIN . url_copa('equipo.php?id=' . (int) $equipo['id'])) ?></p>
    <p>MJ Control Systems · Plataformas web inteligentes, control total de tu negocio.</p>
</div>
