<?php
declare(strict_types=1);

/**
 * Estado de cuenta de cada equipo con la liga.
 *
 * Junta en una sola pantalla lo que el organizador llevaba en un cuaderno: quién pagó la
 * inscripción, a quién le falta el arbitraje de la fecha pasada y cuánto juntan las
 * multas de cada plantilla. Cuando un capitán reclama, hay algo que enseñarle.
 */

auth_requerir();
$torneo = admin_requerir_torneo_activo();
requerir_permiso('cuentas');

$urlLista = url('admin/cuentas.php');
$equipos = equipos_listar($torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[(int) $eq['id']] = $eq;
}
$partidos = partidos_listar($torneo['id']);

$cuotaInscripcion = (float) ($torneo['cuota_inscripcion'] ?? 0);
$cuotaArbitraje = (float) ($torneo['cuota_arbitraje'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $accionPost = (string) ($_POST['accion'] ?? '');

    // --- Generar los cargos que faltan ---
    //
    // No corre solo al abrir la pantalla: cobrarle a 16 equipos es una decisión, no un
    // efecto secundario de entrar a mirar. El botón dice cuántos y de qué antes.
    if ($accionPost === 'generar') {
        $movimientos = movimientos_listar($torneo['id']);
        $pendientes = cuentas_cargos_pendientes($equipos, $partidos, $movimientos, $cuotaInscripcion, $cuotaArbitraje);

        if (empty($pendientes)) {
            redirigir_con_mensaje($urlLista, 'error', 'No hay cargos nuevos que generar. Ya está todo cobrado hasta la última fecha jugada.');
        }

        $creados = 0;
        $total = 0.0;
        foreach ($pendientes as $p) {
            if (movimiento_registrar(
                $torneo['id'],
                $p['equipo_id'],
                MOVIMIENTO_CARGO,
                $p['origen'],
                $p['concepto'],
                $p['monto'],
                $p['referencia']
            )) {
                $creados++;
                $total += $p['monto'];
            }
        }

        bitacora_registrar('cuentas_cargos_generados', "{$creados} cargos generados por " . sancion_monto_texto($torneo, $total), $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', "Se generaron {$creados} cargos por " . sancion_monto_texto($torneo, $total) . '.');
    }

    // --- Registrar un pago ---
    if ($accionPost === 'pago') {
        $equipoId = (int) ($_POST['equipo_id'] ?? 0);
        $monto = (float) str_replace(',', '', (string) ($_POST['monto'] ?? '0'));
        $nota = trim((string) ($_POST['nota'] ?? ''));
        $fecha = (string) ($_POST['fecha'] ?? '');

        if (!isset($equiposPorId[$equipoId])) {
            redirigir_con_mensaje($urlLista, 'error', 'Ese equipo no es de esta copa.');
        }
        if ($monto <= 0) {
            redirigir_con_mensaje(url('admin/cuentas.php?equipo_id=' . $equipoId), 'error', 'El monto del pago tiene que ser mayor que cero.');
        }

        movimiento_registrar(
            $torneo['id'],
            $equipoId,
            MOVIMIENTO_PAGO,
            'pago',
            'Pago recibido',
            $monto,
            null,
            $nota,
            $fecha !== '' ? $fecha : null
        );

        bitacora_registrar('cuenta_pago', 'Pago de ' . sancion_monto_texto($torneo, $monto) . ' de ' . $equiposPorId[$equipoId]['nombre'], $torneo['id']);
        redirigir_con_mensaje(url('admin/cuentas.php?equipo_id=' . $equipoId), 'success', 'Pago registrado: ' . sancion_monto_texto($torneo, $monto) . '.');
    }

    // --- Cargo manual ---
    if ($accionPost === 'cargo') {
        $equipoId = (int) ($_POST['equipo_id'] ?? 0);
        $monto = (float) str_replace(',', '', (string) ($_POST['monto'] ?? '0'));
        $concepto = trim((string) ($_POST['concepto'] ?? ''));
        $fecha = (string) ($_POST['fecha'] ?? '');

        if (!isset($equiposPorId[$equipoId])) {
            redirigir_con_mensaje($urlLista, 'error', 'Ese equipo no es de esta copa.');
        }
        if ($concepto === '') {
            redirigir_con_mensaje(url('admin/cuentas.php?equipo_id=' . $equipoId), 'error', 'Escribe de qué es el cargo: dentro de un mes nadie va a recordarlo.');
        }
        if ($monto <= 0) {
            redirigir_con_mensaje(url('admin/cuentas.php?equipo_id=' . $equipoId), 'error', 'El monto tiene que ser mayor que cero.');
        }

        movimiento_registrar(
            $torneo['id'],
            $equipoId,
            MOVIMIENTO_CARGO,
            'manual',
            $concepto,
            $monto,
            null,
            '',
            $fecha !== '' ? $fecha : null
        );

        bitacora_registrar('cuenta_cargo', 'Cargo de ' . sancion_monto_texto($torneo, $monto) . " a {$equiposPorId[$equipoId]['nombre']}: {$concepto}", $torneo['id']);
        redirigir_con_mensaje(url('admin/cuentas.php?equipo_id=' . $equipoId), 'success', 'Cargo agregado.');
    }

    // --- Borrar un movimiento ---
    //
    // Solo para deshacer una equivocación recién hecha. La corrección de verdad es anotar
    // el movimiento contrario, que deja rastro de que hubo una corrección.
    if ($accionPost === 'eliminar') {
        $id = (int) ($_POST['id'] ?? 0);
        $equipoId = (int) ($_POST['equipo_id'] ?? 0);
        movimiento_eliminar($id, $torneo['id']);
        bitacora_registrar('cuenta_movimiento_borrado', "Movimiento #{$id} borrado de la cuenta de " . ($equiposPorId[$equipoId]['nombre'] ?? '?'), $torneo['id']);
        redirigir_con_mensaje(url('admin/cuentas.php?equipo_id=' . $equipoId), 'success', 'Movimiento borrado.');
    }
}

// --- Datos de la pantalla ---
$movimientos = movimientos_listar($torneo['id']);
$jugadores = jugadores_listar($torneo['id']);
$jugadoresPorId = jugadores_por_id($jugadores);

// Las multas pendientes de cada jugador. Se leen de las sanciones, que siguen siendo su
// dueño: aquí solo se suman al equipo si la liga lo configuró así.
$multasAlEquipo = torneo_multas_al_equipo($torneo) && torneo_cobra_multas($torneo);
$deudaPorJugador = $multasAlEquipo ? sanciones_deuda_por_jugador($torneo['id']) : [];

$saldos = cuentas_saldos($equipos, $movimientos, $deudaPorJugador, $jugadores, $multasAlEquipo);
$totales = cuentas_totales($saldos);
$pendientes = cuentas_cargos_pendientes($equipos, $partidos, $movimientos, $cuotaInscripcion, $cuotaArbitraje);
$montoPendiente = array_sum(array_map(fn($p) => (float) $p['monto'], $pendientes));

// --- Detalle de un equipo ---
$equipoId = (int) ($_GET['equipo_id'] ?? 0);
$equipoDetalle = $equiposPorId[$equipoId] ?? null;
$movimientosEquipo = [];
$multasEquipo = [];
$saldoEquipo = null;
if ($equipoDetalle !== null) {
    $movimientosEquipo = array_values(array_filter($movimientos, fn($m) => (int) $m['equipo_id'] === $equipoId));
    foreach ($saldos as $fila) {
        if ((int) $fila['equipo']['id'] === $equipoId) {
            $saldoEquipo = $fila;
        }
    }
    // Las multas se listan una por una y no como un total: el capitán necesita saber a
    // quién cobrarle, no cuánto junta.
    foreach ($deudaPorJugador as $jid => $deuda) {
        $jug = $jugadoresPorId[$jid] ?? null;
        if ($jug !== null && (int) $jug['equipo_id'] === $equipoId) {
            $multasEquipo[] = ['jugador' => $jug, 'total' => (float) $deuda['total'], 'cantidad' => (int) ($deuda['cantidad'] ?? 1)];
        }
    }
}

$seccion_activa = 'cuentas';
$titulo_pagina = 'Cuentas';

vista_admin('admin/cuentas', compact(
    'cuotaArbitraje',
    'cuotaInscripcion',
    'equipoDetalle',
    'equipos',
    'equiposPorId',
    'montoPendiente',
    'movimientosEquipo',
    'multasAlEquipo',
    'multasEquipo',
    'pendientes',
    'saldoEquipo',
    'saldos',
    'seccion_activa',
    'titulo_pagina',
    'torneo',
    'totales'
));
