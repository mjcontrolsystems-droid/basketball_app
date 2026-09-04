<?php
declare(strict_types=1);

/**
 * Toda la copa en un archivo de Excel.
 *
 * Sirve para dos cosas distintas y las dos importan:
 *   - Trabajar los datos fuera de la app (cuadrar el dinero, armar un premio, cruzar con
 *     la lista de la directiva).
 *   - Que los datos de la liga NO queden atrapados en esta plataforma. Un organizador que
 *     no puede sacar su información está preso, y eso es de las primeras cosas que se
 *     preguntan antes de contratar cualquier sistema.
 *
 * Los datos se arman aquí y el .xlsx lo escribe Support/excel.php.
 */

auth_requerir();
$torneo = admin_requerir_torneo_activo();
// Mismo nivel que las cuentas: el archivo lleva montos y deudas de cada equipo.
requerir_permiso('cuentas');

$equipos = equipos_listar($torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[(int) $eq['id']] = $eq;
}
$partidos = partidos_listar($torneo['id']);
$jugadores = jugadores_listar($torneo['id']);
$jugadoresPorId = jugadores_por_id($jugadores);
$eventos = eventos_de_torneo($torneo['id']);
$deporte = $torneo['deporte'] ?? null;
$moneda = torneo_moneda($torneo);

$hojas = [];

// ---------- Tabla de posiciones ----------
$etAnota = etiqueta_anotaciones($deporte);
$filas = [['Pos.', 'Equipo', 'PJ', 'PG', 'PE', 'PP', $etAnota . ' a favor', $etAnota . ' en contra', 'Dif.', 'Puntos', '% Victorias']];
foreach (calcular_tabla($equipos, $partidos, $torneo, $eventos) as $f) {
    $filas[] = [
        (int) $f['posicion'],
        (string) $f['equipo']['nombre'],
        (int) $f['pj'], (int) $f['pg'], (int) $f['pe'], (int) $f['pp'],
        (int) $f['pf'], (int) $f['pc'], (int) $f['dif'], (int) $f['pts'],
        (int) $f['porcentaje'],
    ];
}
$hojas['Tabla'] = $filas;

// ---------- Goleadores ----------
$filas = [['#', 'Jugador', 'Equipo', etiqueta_anotaciones($deporte)]];
$puesto = 0;
foreach (calcular_goleadores($eventos, $jugadores, $equiposPorId, $deporte) as $g) {
    $puesto++;
    $filas[] = [
        $puesto,
        (string) ($g['jugador']['nombre'] ?? ''),
        (string) ($g['equipo']['nombre'] ?? ''),
        (int) ($g['goles'] ?? 0),
    ];
}
$hojas['Goleadores'] = $filas;

// ---------- Encuentros ----------
$filas = [['Jornada', 'Fase', 'Fecha', 'Hora', 'Cancha', 'Local', 'Visitante', 'Marcador local', 'Marcador visitante', 'Estado']];
$ordenados = $partidos;
usort($ordenados, fn($a, $b) => strcmp((string) $a['fecha'] . $a['hora'], (string) $b['fecha'] . $b['hora']));
foreach ($ordenados as $p) {
    $jugado = ($p['estado'] ?? '') === 'jugado';
    $filas[] = [
        (int) ($p['jornada'] ?? 0),
        (string) (FASES_LABEL[$p['fase'] ?? 'grupos'] ?? ''),
        (string) ($p['fecha'] ?? ''),
        (string) ($p['hora'] ?? ''),
        (string) ($p['cancha'] ?? ''),
        (string) ($equiposPorId[(int) $p['equipo_local']]['nombre'] ?? ''),
        (string) ($equiposPorId[(int) $p['equipo_visitante']]['nombre'] ?? ''),
        $jugado ? (int) $p['marcador_local'] : null,
        $jugado ? (int) $p['marcador_visitante'] : null,
        // El W.O. se marca aquí: en la tabla cuenta igual, pero quien revise el archivo
        // tiene que poder distinguir un 3-0 jugado de uno reglamentario.
        $jugado ? (!empty($p['por_default']) ? 'Jugado (W.O.)' : 'Jugado') : 'Programado',
    ];
}
$hojas['Encuentros'] = $filas;

// ---------- Sanciones ----------
$jornadaDePartido = [];
foreach ($partidos as $p) {
    $jornadaDePartido[(int) $p['id']] = (int) ($p['jornada'] ?? 0);
}
$filas = [['Fecha', 'Jornada', 'Jugador', 'Equipo', 'Tarjeta', 'Monto (' . $moneda . ')', 'Estado', 'Nota']];
foreach (sanciones_listar($torneo['id']) as $s) {
    $jug = $jugadoresPorId[(int) $s['jugador_id']] ?? null;
    $filas[] = [
        substr((string) ($s['creada_en'] ?? ''), 0, 10),
        $jornadaDePartido[(int) ($s['partido_id'] ?? 0)] ?? null,
        jugador_nombre($jug),
        (string) ($equiposPorId[(int) $s['equipo_id']]['nombre'] ?? ''),
        ucfirst((string) ($s['tipo'] ?? '')),
        (float) $s['monto'],
        ucfirst((string) ($s['estado'] ?? '')),
        (string) ($s['nota'] ?? ''),
    ];
}
$hojas['Sanciones'] = $filas;

// ---------- Cuentas: saldo por equipo y detalle de movimientos ----------
$movimientos = movimientos_listar($torneo['id']);
$multasAlEquipo = torneo_multas_al_equipo($torneo) && torneo_cobra_multas($torneo);
$deudaPorJugador = $multasAlEquipo ? sanciones_deuda_por_jugador($torneo['id']) : [];

$filas = [['Equipo', 'Cargos (' . $moneda . ')', 'Multas (' . $moneda . ')', 'Pagado (' . $moneda . ')', 'Saldo (' . $moneda . ')', 'Situación']];
foreach (cuentas_saldos($equipos, $movimientos, $deudaPorJugador, $jugadores, $multasAlEquipo) as $f) {
    $saldo = (float) $f['saldo'];
    $filas[] = [
        (string) $f['equipo']['nombre'],
        (float) $f['cargos'],
        (float) $f['multas'],
        (float) $f['pagos'],
        $saldo,
        $saldo > 0 ? 'Debe' : ($saldo < 0 ? 'A favor' : 'Al día'),
    ];
}
$hojas['Cuentas'] = $filas;

$filas = [['Fecha', 'Equipo', 'Tipo', 'Origen', 'Concepto', 'Monto (' . $moneda . ')', 'Nota']];
foreach ($movimientos as $m) {
    $filas[] = [
        (string) $m['fecha'],
        (string) ($equiposPorId[(int) $m['equipo_id']]['nombre'] ?? ''),
        $m['tipo'] === MOVIMIENTO_PAGO ? 'Pago' : 'Cargo',
        movimiento_origen_nombre((string) $m['origen']),
        (string) $m['concepto'],
        (float) $m['monto'],
        (string) $m['nota'],
    ];
}
$hojas['Movimientos'] = $filas;

// ---------- Plantillas ----------
$filas = [['Equipo', 'Dorsal', 'Nombre', 'Posición', 'Estado', 'Tiene foto']];
$ordenadosJug = $jugadores;
usort($ordenadosJug, function ($a, $b) use ($equiposPorId) {
    $ea = (string) ($equiposPorId[(int) $a['equipo_id']]['nombre'] ?? '');
    $eb = (string) ($equiposPorId[(int) $b['equipo_id']]['nombre'] ?? '');
    return $ea === $eb
        ? ((int) $a['dorsal'] <=> (int) $b['dorsal'])
        : strcmp($ea, $eb);
});
foreach ($ordenadosJug as $j) {
    $filas[] = [
        (string) ($equiposPorId[(int) $j['equipo_id']]['nombre'] ?? ''),
        // Como texto a propósito: hay dorsales "07" y el cero de adelante se perdería.
        (string) $j['dorsal'],
        (string) $j['nombre'],
        (string) posicion_label($deporte, (string) ($j['posicion'] ?? '')),
        !empty($j['activo']) ? 'Activo' : 'Inactivo',
        !empty($j['foto']) ? 'Sí' : 'No',
    ];
}
$hojas['Plantillas'] = $filas;

// ---------- Entregar ----------
try {
    $contenido = excel_generar($hojas);
} catch (RuntimeException $e) {
    redirigir_con_mensaje(url('admin/index.php'), 'error', $e->getMessage());
}

bitacora_registrar('exportacion_excel', 'Se exportó la copa a Excel', $torneo['id']);

$nombre = ($torneo['slug'] ?? 'copa') . '_' . date('Y-m-d') . '.xlsx';
excel_descargar($contenido, $nombre);
