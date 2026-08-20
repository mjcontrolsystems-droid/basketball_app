<?php
declare(strict_types=1);

/**
 * Control de multas por tarjeta: quién debe, cuánto, y el registro de los cobros.
 */

auth_requerir();
$torneo = admin_requerir_torneo_activo();
requerir_permiso('sanciones');

$urlLista = url('admin/sanciones.php');

// Si la liga no cobra multas no hay nada que administrar: la vista muestra un aviso
// que lleva a configurarlas, en vez de una pantalla vacía sin explicación.
$sinTarifas = !torneo_cobra_multas($torneo);

$equipos = equipos_listar($torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[$eq['id']] = $eq;
}
$jugadores = jugadores_listar($torneo['id']);
$jugadoresPorId = jugadores_por_id($jugadores);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $accion = (string) ($_POST['accion'] ?? '');
    $nota = trim((string) ($_POST['nota'] ?? ''));

    if ($accion === 'cobrar' || $accion === 'condonar' || $accion === 'reabrir') {
        $id = (int) ($_POST['id'] ?? 0);
        $estado = $accion === 'cobrar' ? SANCION_PAGADA : ($accion === 'condonar' ? SANCION_CONDONADA : SANCION_PENDIENTE);
        if (sancion_actualizar_estado($id, $torneo['id'], $estado, $nota)) {
            $etiquetas = ['cobrar' => 'Pago registrado.', 'condonar' => 'Sanción condonada.', 'reabrir' => 'Sanción marcada como pendiente de nuevo.'];
            bitacora_registrar('sancion_' . $accion, 'Sanción #' . $id, $torneo['id']);
            redirigir_con_mensaje($urlLista, 'success', $etiquetas[$accion]);
        }
        redirigir_con_mensaje($urlLista, 'error', 'No se pudo actualizar la sanción.');
    }

    // El capitán paga de una vez todas las de su equipo.
    if ($accion === 'cobrar_equipo') {
        $equipoId = (int) ($_POST['equipo_id'] ?? 0);
        $cobradas = sanciones_cobrar_equipo($torneo['id'], $equipoId, $nota);
        $nombreEquipo = $equiposPorId[$equipoId]['nombre'] ?? '';
        bitacora_registrar('sancion_cobro_equipo', "{$nombreEquipo}: {$cobradas} sanciones cobradas", $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', "Se registraron {$cobradas} pago(s) de {$nombreEquipo}.");
    }
}

$filtroEstado = (string) ($_GET['estado'] ?? SANCION_PENDIENTE);
if (!in_array($filtroEstado, [SANCION_PENDIENTE, SANCION_PAGADA, SANCION_CONDONADA, 'todas'], true)) {
    $filtroEstado = SANCION_PENDIENTE;
}

$sanciones = sanciones_listar($torneo['id'], $filtroEstado === 'todas' ? '' : $filtroEstado);
$resumen = sanciones_resumen($torneo['id']);

// Agrupadas por equipo: así el organizador cobra "por mesa" cuando llega el capitán.
$porEquipo = [];
foreach ($sanciones as $s) {
    $porEquipo[$s['equipo_id']][] = $s;
}

// Cuánto debe cada equipo en total (solo pendientes), para el botón de cobro en lote.
$deudaEquipo = [];
foreach (sanciones_listar($torneo['id'], SANCION_PENDIENTE) as $s) {
    $deudaEquipo[$s['equipo_id']] = ($deudaEquipo[$s['equipo_id']] ?? 0) + $s['monto'];
}

$seccion_activa = 'sanciones';
$titulo_pagina = 'Sanciones y multas';

vista_admin('admin/sanciones', compact(
    'deudaEquipo',
    'equiposPorId',
    'filtroEstado',
    'jugadoresPorId',
    'porEquipo',
    'resumen',
    'sanciones',
    'seccion_activa',
    'sinTarifas',
    'titulo_pagina',
    'torneo'
));
