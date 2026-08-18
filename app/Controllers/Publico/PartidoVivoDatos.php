<?php
declare(strict_types=1);


// La copa que resolvió el front controller a partir del slug de la URL.
$torneo = copa_actual();
// Endpoint público de solo lectura que consulta partido_vivo.php cada pocos segundos
// para refrescar el marcador y el feed de eventos sin recargar la página (transmisión
// en vivo en pantalla completa para la afición).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$id = (int) ($_GET['id'] ?? 0);
$partidos = partidos_listar($torneo['id']);
$partido = db_buscar_por_id($partidos, $id);

$equipos = equipos_listar($torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[$eq['id']] = $eq;
}

$local = $partido ? ($equiposPorId[$partido['equipo_local']] ?? null) : null;
$visit = $partido ? ($equiposPorId[$partido['equipo_visitante']] ?? null) : null;

if (!$partido || !$local || !$visit) {
    http_response_code(404);
    echo json_encode(['error' => 'Partido no encontrado']);
    exit;
}

$deporte = $torneo['deporte'] ?? null;
$jugadoresTodos = jugadores_listar($torneo['id']);
$jugadoresPorId = jugadores_por_id($jugadoresTodos);

$eventos = db_leer_eventos_partido($torneo['id'], $id);
usort($eventos, fn($a, $b) => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));

[$marcadorLocal, $marcadorVisitante] = marcador_desde_eventos(
    $eventos,
    (int) $partido['equipo_local'],
    (int) $partido['equipo_visitante'],
    $deporte
);

// Partidos históricos: si no hay eventos cargados (0-0) pero el partido ya tenía un
// marcador capturado antes del modelo de eventos, se muestra ese marcador en vez de
// pisarlo con 0-0 (misma protección que marcador_jugado_desde_eventos en liga.php).
if ($marcadorLocal === 0 && $marcadorVisitante === 0
    && ($partido['marcador_local'] !== null || $partido['marcador_visitante'] !== null)) {
    $marcadorLocal = (int) $partido['marcador_local'];
    $marcadorVisitante = (int) $partido['marcador_visitante'];
}

$eventosSalida = array_map(function (array $ev) use ($jugadoresPorId, $equiposPorId, $deporte) {
    return [
        'id' => (int) $ev['id'],
        'tipo' => $ev['tipo'],
        'minuto' => $ev['minuto'] !== null ? (int) $ev['minuto'] : null,
        'equipo' => $equiposPorId[$ev['equipo_id']]['nombre'] ?? '',
        'descripcion' => evento_descripcion($ev, $jugadoresPorId, $deporte),
    ];
}, $eventos);

echo json_encode([
    'estado' => $partido['estado'],
    // El partido se considera terminado tanto por resultado en firme como por cronómetro
    // finalizado; la transmisión usa esto para pasar de "En vivo" al cartel de FINAL.
    'finalizado' => partido_finalizado($partido),
    'marcador_local' => $marcadorLocal,
    'marcador_visitante' => $marcadorVisitante,
    'eventos' => $eventosSalida,
    // Cronómetro tal cual está guardado (sin sumarle lo corrido "ahora mismo"): el cliente
    // hace su propio tic en vivo a partir de estos tres valores, igual que en el panel admin.
    'cronometro_estado' => $partido['cronometro_estado'] ?? 'detenido',
    'cronometro_segundos' => (int) ($partido['cronometro_segundos'] ?? 0),
    'cronometro_inicio' => $partido['cronometro_inicio'],
    // Desde cuántos segundos baja la cuenta regresiva del periodo actual: los minutos
    // configurados en la copa + el tiempo extra que el árbitro haya agregado en vivo.
    'duracion_segundos' => partido_duracion_periodo_segundos($partido, $torneo),
    'minutos_extra' => partido_minutos_extra($partido),
    'periodo_etiqueta' => partido_periodo_etiqueta($deporte, (int) ($partido['cronometro_periodo'] ?? 1)),
], JSON_UNESCAPED_UNICODE);
