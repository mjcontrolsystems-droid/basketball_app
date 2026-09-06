<?php
/**
 * Hoja de la nómina. Se pinta dos veces: la vista previa en pantalla y la copia que sale
 * en papel.
 *
 * Las casillas ya no se llenan solo con lapicero. En la vista previa son casillas de
 * verdad: el capitán marca desde el teléfono a quién trae, y al imprimir la X aparece ya
 * puesta en el papel. Marcar veinte cuadritos a mano, parado en la cancha y con el juego
 * por empezar, era el paso donde se equivocaban.
 *
 * Nada de esto se guarda: es una ayuda para imprimir, no un registro. Quien no marque
 * nada obtiene la hoja en blanco de siempre y la llena con lapicero.
 *
 * $modoHoja distingue las dos copias: 'pantalla' lleva las casillas marcables y
 * 'impresion' los cuadritos que se pintan solos con lo que se marcó arriba (ver app.js).
 */
$modoHoja = $modoHoja ?? 'impresion';
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

<?php // Se marca a los PRESENTES que jugarán, no a los titulares: en estas ligas hay
      // cambios libres y al final participan todos los que llegaron. Lo que el árbitro
      // necesita verificar es quiénes están habilitados para entrar en algún momento. ?>
<p style="font-size:12px;margin:10px 0 6px;">
    Marque con <strong>X</strong> la casilla de los jugadores <strong>presentes que participarán
    en este encuentro</strong>. Quien no esté marcado no podrá ingresar al terreno de juego.
</p>

<?php if ($modoHoja === 'pantalla' && !empty($plantilla)): ?>
<p class="small text-muted mb-3 solo-pantalla">
    <i class="bi bi-hand-index me-1"></i>Puedes marcarlos aquí antes de imprimir y salen con la X ya puesta.
    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-2" data-marcar-todos>
        Marcar todos
    </button>
    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 ms-1" data-desmarcar-todos>
        Ninguno
    </button>
</p>
<?php endif; ?>

<table class="ficha-tabla">
    <thead>
        <tr>
            <th style="width:9%;">Juega</th>
            <th style="width:10%;">#</th>
            <th>Jugador</th>
            <th style="width:24%;">Observación</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($plantilla as $j): $jid = (int) $j['id']; ?>
        <tr>
            <td style="text-align:center;">
                <?php if ($modoHoja === 'pantalla'): ?>
                    <?php // Un suspendido o un moroso no debería marcarse: la casilla se
                          // deja igual pero avisada, porque la decisión final la toma la
                          // mesa con el papel en la mano. ?>
                    <input type="checkbox" class="check-juega" data-jugador="<?= $jid ?>"
                           aria-label="Marcar a <?= e($j['nombre']) ?> como presente">
                <?php else: ?>
                    <span class="casilla-juega" data-jugador="<?= $jid ?>"></span>
                <?php endif; ?>
            </td>
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
