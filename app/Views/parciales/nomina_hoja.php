<?php
/**
 * Hoja de la nómina. Se usa dos veces (vista previa e impresión): lo que se ve es lo que
 * sale en papel. Las casillas van VACÍAS a propósito — las llena el capitán con lapicero.
 */
$linea = '<span style="display:inline-block;border-bottom:1px solid #000;min-width:160px;">&nbsp;</span>';
?>
<div class="ficha-titulo">
    <h2>Nómina de <?= e($equipo['nombre']) ?></h2>
    <p>
        <?= e($torneo['nombre']) ?><?= !empty($torneo['temporada']) ? ' · Temporada ' . e($torneo['temporada']) : '' ?>
        · Se entrega al árbitro antes del encuentro
    </p>
</div>

<?php // ---------- Datos del encuentro: impresos si se conocen, en blanco si no ---------- ?>
<table class="ficha-datos">
    <tr>
        <td><strong>Jornada</strong></td>
        <td><?= $partidoHoja !== null ? (int) $partidoHoja['jornada'] : $linea ?></td>
        <td><strong>Fecha</strong></td>
        <td><?= $partidoHoja !== null ? e(formatear_fecha_corta((string) $partidoHoja['fecha'])) . ' · ' . e((string) $partidoHoja['hora']) : $linea ?></td>
    </tr>
    <tr>
        <td><strong>Rival</strong></td>
        <td><?= $rival !== null ? e($rival['nombre']) : $linea ?></td>
        <td><strong>Condición</strong></td>
        <td><?= $condicion !== '' ? e($condicion) : $linea ?></td>
    </tr>
</table>

<p style="font-size:12px;margin:10px 0 6px;">
    Marque con <strong>X</strong> la casilla <strong>T</strong> de quienes inician
    (<?= (int) $jugadoresEnCancha ?> titulares en esta modalidad). Los demás presentes quedan en banca.
</p>

<table class="ficha-tabla">
    <thead>
        <tr>
            <th style="width:7%;">T</th>
            <th style="width:10%;">#</th>
            <th>Jugador</th>
            <th style="width:24%;">Observación</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($plantilla as $j): $jid = (int) $j['id']; ?>
        <tr>
            <td style="text-align:center;"><span style="display:inline-block;width:14px;height:14px;border:1.5px solid #000;"></span></td>
            <td><strong><?= e($j['dorsal']) ?></strong></td>
            <td><?= e($j['nombre']) ?></td>
            <td style="font-size:11px;">
                <?php // Lo que la mesa debe verificar antes de dejarlo entrar. ?>
                <?php if (isset($suspendidos[$jid])): ?>
                    <strong>SUSPENDIDO</strong> — no puede jugar
                <?php elseif (isset($deudores[$jid])): ?>
                    <strong>DEBE <?= e(sancion_monto_texto($torneo, (float) $deudores[$jid]['total'])) ?></strong> — paga antes de entrar
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($plantilla)): ?>
        <tr><td colspan="4">Este equipo todavía no tiene jugadores registrados.</td></tr>
        <?php endif; ?>
    </tbody>
</table>

<?php // ---------- Firmas: lo que convierte el papel en un acta ---------- ?>
<table class="ficha-datos" style="margin-top:26px;">
    <tr>
        <td style="width:50%;text-align:center;padding-top:24px;">
            <span style="display:inline-block;border-top:1px solid #000;min-width:200px;padding-top:4px;">Firma del capitán</span>
        </td>
        <td style="width:50%;text-align:center;padding-top:24px;">
            <span style="display:inline-block;border-top:1px solid #000;min-width:200px;padding-top:4px;">Firma del árbitro / mesa</span>
        </td>
    </tr>
</table>

<div class="ficha-pie">
    <p>Generado el <?= e(date('d/m/Y H:i')) ?> · <?= e(SITE_ORIGIN . url_copa('nomina.php?id=' . (int) $equipo['id'])) ?></p>
    <p>MJ Control Systems · Plataformas web inteligentes, control total de tu negocio.</p>
</div>
