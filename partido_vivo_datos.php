<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/liga.php';
require_once __DIR__ . '/includes/torneo_actual.php';

// Endpoint público de solo lectura que consulta partido_vivo.php cada pocos segundos
// para refrescar el marcador y el feed de eventos sin recargar la página (transmisión
// en vivo en pantalla completa para la afición).
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$id = (int) ($_GET['id'] ?? 0);
$partidos = db_leer('partidos', $torneo['id']);
$partido = db_buscar_por_id($partidos, $id);

$equipos = db_leer('equipos', $torneo['id']);
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
$jugadoresTodos = db_leer('jugadores', $torneo['id']);
$jugadoresPorId = jugadores_por_id($jugadoresTodos);

$eventos = db_leer_eventos_partido($torneo['id'], $id);
usort($eventos, fn($a, $b) => ((int) ($a['id'] ?? 0)) <=> ((int) ($b['id'] ?? 0)));

[$marcadorLocal, $marcadorVisitante] = marcador_desde_eventos(
    $eventos,
    (int) $partido['equipo_local'],
    (int) $partido['equipo_visitante'],
    $deporte
);

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
    'marcador_local' => $marcadorLocal,
    'marcador_visitante' => $marcadorVisitante,
    'eventos' => $eventosSalida,
    // Cronómetro tal cual está guardado (sin sumarle lo corrido "ahora mismo"): el cliente
    // hace su propio tic en vivo a partir de estos tres valores, igual que en el panel admin.
    'cronometro_estado' => $partido['cronometro_estado'] ?? 'detenido',
    'cronometro_segundos' => (int) ($partido['cronometro_segundos'] ?? 0),
    'cronometro_inicio' => $partido['cronometro_inicio'],
], JSON_UNESCAPED_UNICODE);
