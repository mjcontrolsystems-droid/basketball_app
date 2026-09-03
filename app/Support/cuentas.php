<?php
declare(strict_types=1);

/**
 * El cálculo del saldo de cada equipo.
 *
 * Está separado del acceso a la base a propósito: es un cálculo de dinero, y el dinero es
 * justo donde un error no se ve en pantalla pero sí en la reunión con los capitanes. Aquí
 * entran arreglos y sale un arreglo, así que se puede comprobar con casos de prueba.
 *
 * La cuenta de un equipo tiene tres partes:
 *   cargos   lo que la liga le cobró (inscripción, arbitraje, cargos manuales)
 *   multas   las tarjetas de SUS jugadores, solo si la liga configuró que suman al equipo
 *   pagos    lo que ya entregó
 *
 * saldo = cargos + multas − pagos. Positivo = debe; cero = al día; negativo = pagó de más
 * (pasa, y hay que poder verlo en vez de esconderlo).
 */

/**
 * Saldo de cada equipo, ordenado de mayor deuda a menor.
 *
 * @param array $equipos          Equipos de la copa.
 * @param array $movimientos      Movimientos de la copa (ver Models/Cuenta.php).
 * @param array $deudaPorJugador  jugador_id => ['total' => float] con las multas PENDIENTES.
 * @param array $jugadores        Para saber de qué equipo es cada jugador multado.
 * @param bool  $multasAlEquipo   Si las multas de tarjetas suman al saldo del equipo.
 * @return array<int, array{equipo: array, cargos: float, pagos: float, multas: float, saldo: float, movimientos: int}>
 */
function cuentas_saldos(array $equipos, array $movimientos, array $deudaPorJugador, array $jugadores, bool $multasAlEquipo): array
{
    $porEquipo = [];
    foreach ($equipos as $eq) {
        $porEquipo[(int) $eq['id']] = [
            'equipo' => $eq,
            'cargos' => 0.0,
            'pagos' => 0.0,
            'multas' => 0.0,
            'saldo' => 0.0,
            'movimientos' => 0,
        ];
    }

    foreach ($movimientos as $m) {
        $id = (int) ($m['equipo_id'] ?? 0);
        if (!isset($porEquipo[$id])) {
            continue;   // movimiento de un equipo que ya no existe
        }
        $monto = abs((float) ($m['monto'] ?? 0));
        if (($m['tipo'] ?? '') === MOVIMIENTO_PAGO) {
            $porEquipo[$id]['pagos'] += $monto;
        } else {
            $porEquipo[$id]['cargos'] += $monto;
        }
        $porEquipo[$id]['movimientos']++;
    }

    if ($multasAlEquipo) {
        // La multa es del jugador —y a él lo bloquea en la nómina—, pero el que junta y
        // paga es el capitán. Se leen de las sanciones y no se copian al libro: tener el
        // mismo dato en dos lugares garantiza que algún día dejen de coincidir.
        $equipoDeJugador = [];
        foreach ($jugadores as $j) {
            $equipoDeJugador[(int) $j['id']] = (int) $j['equipo_id'];
        }
        foreach ($deudaPorJugador as $jugadorId => $deuda) {
            $equipoId = $equipoDeJugador[(int) $jugadorId] ?? 0;
            if (!isset($porEquipo[$equipoId])) {
                continue;
            }
            $porEquipo[$equipoId]['multas'] += (float) ($deuda['total'] ?? 0);
        }
    }

    foreach ($porEquipo as &$fila) {
        $fila['saldo'] = round($fila['cargos'] + $fila['multas'] - $fila['pagos'], 2);
    }
    unset($fila);

    $lista = array_values($porEquipo);
    // De mayor deuda a menor: la pantalla se abre para ver a quién hay que cobrarle, no
    // para leer la lista alfabética.
    usort($lista, function ($a, $b) {
        if ($a['saldo'] !== $b['saldo']) {
            return $b['saldo'] <=> $a['saldo'];
        }
        return strcmp((string) $a['equipo']['nombre'], (string) $b['equipo']['nombre']);
    });

    return $lista;
}

/**
 * Los totales de la liga entera, para el encabezado de la pantalla.
 *
 * @return array{cargado: float, cobrado: float, pendiente: float, equipos_deben: int}
 */
function cuentas_totales(array $saldos): array
{
    $cargado = 0.0;
    $cobrado = 0.0;
    $pendiente = 0.0;
    $deben = 0;

    foreach ($saldos as $fila) {
        $cargado += $fila['cargos'] + $fila['multas'];
        $cobrado += $fila['pagos'];
        if ($fila['saldo'] > 0) {
            $pendiente += $fila['saldo'];
            $deben++;
        }
    }

    return [
        'cargado' => round($cargado, 2),
        'cobrado' => round($cobrado, 2),
        'pendiente' => round($pendiente, 2),
        'equipos_deben' => $deben,
    ];
}

/**
 * Qué cargos automáticos FALTAN por generar, sin tocar la base.
 *
 * Devuelve la lista de movimientos a crear. Se calcula aparte de crearlos para poder
 * decirle al organizador cuántos y de qué antes de que apriete el botón — generar cobros
 * a ciegas sobre 16 equipos es de las cosas que uno quiere ver antes.
 *
 * @param array $partidos Encuentros de la copa; el arbitraje se cobra por los JUGADOS.
 * @return array<int, array{equipo_id:int, origen:string, concepto:string, monto:float, referencia:?int}>
 */
function cuentas_cargos_pendientes(array $equipos, array $partidos, array $movimientos, float $cuotaInscripcion, float $cuotaArbitraje): array
{
    $pendientes = [];

    if ($cuotaInscripcion > 0) {
        foreach ($equipos as $eq) {
            if (!movimientos_tiene_inscripcion($movimientos, (int) $eq['id'])) {
                $pendientes[] = [
                    'equipo_id' => (int) $eq['id'],
                    'origen' => 'inscripcion',
                    'concepto' => 'Inscripción a la temporada',
                    'monto' => $cuotaInscripcion,
                    'referencia' => null,
                ];
            }
        }
    }

    if ($cuotaArbitraje > 0) {
        $cobrados = movimientos_arbitrajes_cobrados($movimientos);
        foreach ($partidos as $p) {
            // Solo los ya jugados: cobrar por adelantado un encuentro que puede
            // reprogramarse obliga a devolver dinero, y eso nadie lo lleva bien.
            if (($p['estado'] ?? '') !== 'jugado') {
                continue;
            }
            $partidoId = (int) $p['id'];
            $fecha = (string) ($p['fecha'] ?? '');
            foreach ([(int) $p['equipo_local'], (int) $p['equipo_visitante']] as $equipoId) {
                if (isset($cobrados[$equipoId][$partidoId])) {
                    continue;
                }
                $pendientes[] = [
                    'equipo_id' => $equipoId,
                    'origen' => 'arbitraje',
                    'concepto' => 'Arbitraje jornada ' . (int) ($p['jornada'] ?? 0)
                        . ($fecha !== '' ? ' · ' . formatear_fecha_corta($fecha) : ''),
                    'monto' => $cuotaArbitraje,
                    'referencia' => $partidoId,
                ];
            }
        }
    }

    return $pendientes;
}

function torneo_lleva_cuentas(array $torneo): bool
{
    return (float) ($torneo['cuota_inscripcion'] ?? 0) > 0
        || (float) ($torneo['cuota_arbitraje'] ?? 0) > 0;
}

function torneo_multas_al_equipo(array $torneo): bool
{
    return !empty($torneo['multas_al_equipo']);
}
