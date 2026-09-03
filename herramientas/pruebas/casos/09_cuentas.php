<?php
declare(strict_types=1);

/**
 * Los saldos de los equipos.
 *
 * Es dinero. Un error aquí no se ve en pantalla pero sí en la reunión con los capitanes, y
 * ahí no hay forma de defenderse: o el número está bien o el organizador queda mal. Vale
 * la pena tener fijado exactamente cómo se suma.
 */

grupo('Saldo de cada equipo');

$equipos = [
    ['id' => 1, 'nombre' => 'Promoción 45'],
    ['id' => 2, 'nombre' => 'Promoción 52'],
];

$jugadores = [
    ['id' => 100, 'equipo_id' => 1],
    ['id' => 200, 'equipo_id' => 2],
];

$cargo = fn(int $equipoId, float $monto, string $origen = 'manual') => [
    'equipo_id' => $equipoId,
    'tipo' => MOVIMIENTO_CARGO,
    'origen' => $origen,
    'referencia' => null,
    'monto' => $monto,
];

$pago = fn(int $equipoId, float $monto) => [
    'equipo_id' => $equipoId,
    'tipo' => MOVIMIENTO_PAGO,
    'origen' => 'pago',
    'referencia' => null,
    'monto' => $monto,
];

$porEquipo = function (array $saldos): array {
    $out = [];
    foreach ($saldos as $fila) {
        $out[(int) $fila['equipo']['id']] = $fila;
    }
    return $out;
};

prueba('saldo = cargos + multas − pagos', function () use ($equipos, $jugadores, $cargo, $pago, $porEquipo) {
    $saldos = $porEquipo(cuentas_saldos(
        $equipos,
        [$cargo(1, 200.0, 'inscripcion'), $cargo(1, 50.0, 'arbitraje'), $pago(1, 100.0)],
        [100 => ['total' => 25.0]],
        $jugadores,
        true
    ));

    igual(250.0, $saldos[1]['cargos']);
    igual(25.0, $saldos[1]['multas']);
    igual(100.0, $saldos[1]['pagos']);
    igual(175.0, $saldos[1]['saldo'], '250 + 25 − 100');
});

prueba('un equipo sin movimientos queda en cero, no fuera de la lista', function () use ($equipos, $jugadores, $cargo, $porEquipo) {
    // Tiene que aparecer igual: si desaparece de la pantalla, nadie se acuerda de cobrarle.
    $saldos = $porEquipo(cuentas_saldos($equipos, [$cargo(1, 100.0)], [], $jugadores, false));
    cierto(isset($saldos[2]), 'el equipo 2 aparece');
    igual(0.0, $saldos[2]['saldo']);
});

prueba('las multas solo suman si la liga lo configuró', function () use ($equipos, $jugadores, $porEquipo) {
    $conMultas = $porEquipo(cuentas_saldos($equipos, [], [100 => ['total' => 25.0]], $jugadores, true));
    igual(25.0, $conMultas[1]['saldo'], 'con la casilla marcada, la multa entra al equipo');

    $sinMultas = $porEquipo(cuentas_saldos($equipos, [], [100 => ['total' => 25.0]], $jugadores, false));
    igual(0.0, $sinMultas[1]['saldo'], 'sin la casilla, la multa se queda en la cuenta del jugador');
    igual(0.0, $sinMultas[1]['multas']);
});

prueba('cada multa cae en el equipo de SU jugador', function () use ($equipos, $jugadores, $porEquipo) {
    $saldos = $porEquipo(cuentas_saldos(
        $equipos,
        [],
        [100 => ['total' => 25.0], 200 => ['total' => 60.0]],
        $jugadores,
        true
    ));
    igual(25.0, $saldos[1]['multas']);
    igual(60.0, $saldos[2]['multas']);
});

prueba('pagar de más deja saldo a favor, no se esconde en cero', function () use ($equipos, $jugadores, $cargo, $pago, $porEquipo) {
    // Pasa de verdad: un equipo adelanta la temporada completa. Redondear a cero haría
    // que se le cobrara otra vez.
    $saldos = $porEquipo(cuentas_saldos($equipos, [$cargo(1, 100.0), $pago(1, 150.0)], [], $jugadores, false));
    igual(-50.0, $saldos[1]['saldo']);
});

prueba('el movimiento de un equipo borrado no rompe la cuenta', function () use ($equipos, $jugadores, $cargo) {
    $saldos = cuentas_saldos($equipos, [$cargo(99, 500.0)], [], $jugadores, false);
    igual(2, count($saldos), 'siguen siendo los dos equipos que existen');
    foreach ($saldos as $fila) {
        igual(0.0, $fila['saldo'], 'y el cargo huérfano no se le carga a nadie');
    }
});

prueba('la lista se ordena por lo que deben', function () use ($equipos, $jugadores, $cargo) {
    $saldos = cuentas_saldos($equipos, [$cargo(2, 500.0), $cargo(1, 100.0)], [], $jugadores, false);
    igual(2, (int) $saldos[0]['equipo']['id'], 'primero el que más debe');
    igual(1, (int) $saldos[1]['equipo']['id']);
});

grupo('Totales de la liga');

prueba('los totales cuadran con los saldos', function () use ($equipos, $jugadores, $cargo, $pago) {
    $saldos = cuentas_saldos(
        $equipos,
        [$cargo(1, 200.0), $pago(1, 50.0), $cargo(2, 100.0)],
        [],
        $jugadores,
        false
    );
    $totales = cuentas_totales($saldos);

    igual(300.0, $totales['cargado']);
    igual(50.0, $totales['cobrado']);
    igual(250.0, $totales['pendiente'], '150 del primero y 100 del segundo');
    igual(2, $totales['equipos_deben']);
});

prueba('un equipo con saldo a favor no cuenta como deudor', function () use ($equipos, $jugadores, $cargo, $pago) {
    $saldos = cuentas_saldos($equipos, [$cargo(1, 100.0), $pago(1, 150.0)], [], $jugadores, false);
    $totales = cuentas_totales($saldos);
    igual(0, $totales['equipos_deben']);
    igual(0.0, $totales['pendiente'], 'lo pagado de más no se resta de lo que deben los demás');
});

grupo('Cargos automáticos');

$jugado = fn(int $id, int $jornada, int $local, int $visita, string $estado = 'jugado') => [
    'id' => $id,
    'jornada' => $jornada,
    'equipo_local' => $local,
    'equipo_visitante' => $visita,
    'estado' => $estado,
    'fecha' => '2026-09-05',
];

prueba('la inscripción se cobra una vez por equipo', function () use ($equipos, $jugado) {
    $pendientes = cuentas_cargos_pendientes($equipos, [], [], 200.0, 0.0);
    igual(2, count($pendientes), 'un cargo por equipo');
    igual(200.0, $pendientes[0]['monto']);
    igual('inscripcion', $pendientes[0]['origen']);
});

prueba('la inscripción ya cobrada no se vuelve a proponer', function () use ($equipos) {
    $yaCobrada = [[
        'equipo_id' => 1,
        'tipo' => MOVIMIENTO_CARGO,
        'origen' => 'inscripcion',
        'referencia' => null,
        'monto' => 200.0,
    ]];
    $pendientes = cuentas_cargos_pendientes($equipos, [], $yaCobrada, 200.0, 0.0);
    igual(1, count($pendientes), 'solo falta el equipo 2');
    igual(2, $pendientes[0]['equipo_id']);
});

prueba('el arbitraje se cobra a los DOS equipos de cada encuentro jugado', function () use ($equipos, $jugado) {
    $pendientes = cuentas_cargos_pendientes($equipos, [$jugado(1, 1, 1, 2)], [], 0.0, 40.0);
    igual(2, count($pendientes), 'local y visitante');
    igual(40.0, $pendientes[0]['monto']);
    igual(1, $pendientes[0]['referencia'], 'queda amarrado al partido');
});

prueba('un encuentro que aún no se juega no se cobra', function () use ($equipos, $jugado) {
    // Cobrar por adelantado un partido que puede reprogramarse obliga a devolver dinero.
    $pendientes = cuentas_cargos_pendientes($equipos, [$jugado(1, 1, 1, 2, 'programado')], [], 0.0, 40.0);
    igual([], $pendientes);
});

prueba('generar dos veces no cobra dos veces el mismo partido', function () use ($equipos, $jugado) {
    $yaCobrado = [
        ['equipo_id' => 1, 'tipo' => MOVIMIENTO_CARGO, 'origen' => 'arbitraje', 'referencia' => 1, 'monto' => 40.0],
        ['equipo_id' => 2, 'tipo' => MOVIMIENTO_CARGO, 'origen' => 'arbitraje', 'referencia' => 1, 'monto' => 40.0],
    ];
    $pendientes = cuentas_cargos_pendientes($equipos, [$jugado(1, 1, 1, 2)], $yaCobrado, 0.0, 40.0);
    igual([], $pendientes, 'no queda nada por generar');
});

prueba('una cuota en cero no genera ningún cargo', function () use ($equipos, $jugado) {
    // Así es como una liga que no cobra arbitraje simplemente no lo ve.
    igual([], cuentas_cargos_pendientes($equipos, [$jugado(1, 1, 1, 2)], [], 0.0, 0.0));
});

prueba('la liga sabe si lleva cuentas', function () {
    falso(torneo_lleva_cuentas(['cuota_inscripcion' => 0, 'cuota_arbitraje' => 0]));
    cierto(torneo_lleva_cuentas(['cuota_inscripcion' => 200, 'cuota_arbitraje' => 0]));
    cierto(torneo_lleva_cuentas(['cuota_inscripcion' => 0, 'cuota_arbitraje' => 40]));
});
