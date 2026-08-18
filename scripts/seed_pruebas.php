<?php
declare(strict_types=1);

/**
 * Carga datos de ejemplo en una base de PRUEBA para poder recorrer el sitio completo en
 * local (no toca nada de producción: exige que la URL de conexión apunte a otra base).
 *
 * Crea a propósito los dos formatos y los dos deportes, porque son los caminos que se
 * comportan distinto en el código:
 *   - "Liga Municipal"  -> fútbol, formato LIGA, ida y vuelta, con logo propio.
 *   - "Copa Estrellas"  -> basketball, formato CAMPEONATO, con fases de playoff.
 *
 * Uso:  DATABASE_URL=postgresql://... php scripts/seed_pruebas.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Solo CLI.');
}

$url = getenv('DATABASE_URL') ?: '';
if ($url === '') {
    fwrite(STDERR, "Falta DATABASE_URL.\n");
    exit(1);
}

// Salvaguarda: este script BORRA todo antes de sembrar. Que jamás pueda correr contra la
// base de producción por un descuido con las variables de entorno.
if (!str_contains($url, 'copa_test')) {
    fwrite(STDERR, "ABORTADO: DATABASE_URL no apunta a la base de prueba (copa_test).\n");
    exit(1);
}

$partes = parse_url($url);
parse_str($partes['query'] ?? '', $opciones);
$pdo = new PDO(
    sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $partes['host'],
        $partes['port'] ?? 5432,
        ltrim($partes['path'] ?? '', '/'),
        $opciones['sslmode'] ?? 'prefer'
    ),
    $partes['user'] ?? '',
    $partes['pass'] ?? '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
);

echo "Limpiando...\n";
$pdo->exec('TRUNCATE partido_alineacion, partido_eventos, partidos, jugadores, equipos, patrocinadores, comentarios, visitas_diarias, bitacora, torneos, usuarios, correos_autorizados, imagenes RESTART IDENTITY CASCADE');

// --- Usuario organizador -----------------------------------------------------
$pdo->prepare('INSERT INTO correos_autorizados (email, limite_torneos) VALUES (:e, 5)')
    ->execute([':e' => 'prueba@local.test']);

$stmt = $pdo->prepare(
    'INSERT INTO usuarios (usuario, email, password_hash, nombre, cargo, telefono, bio)
     VALUES (:u, :e, :p, :n, :c, :t, :b) RETURNING id'
);
$stmt->execute([
    ':u' => 'prueba',
    ':e' => 'prueba@local.test',
    ':p' => password_hash('prueba123', PASSWORD_DEFAULT),
    ':n' => 'Organizador de Prueba',
    ':c' => 'Coordinador general',
    ':t' => '5555-0000',
    ':b' => 'Cuenta de prueba local.',
]);
$usuarioId = (int) $stmt->fetchColumn();

// --- Un logo real (PNG 1x1 morado) para probar que reemplaza al balón ---------
$pngMorado = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mM8U89QDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
$stmt = $pdo->prepare('INSERT INTO imagenes (mime, datos) VALUES (:m, :d) RETURNING id');
$stmt->bindValue(':m', 'image/png');
$stmt->bindValue(':d', $pngMorado, PDO::PARAM_LOB);
$stmt->execute();
$logoId = (string) $stmt->fetchColumn();

// --- Torneos -----------------------------------------------------------------
function crearTorneo(PDO $pdo, int $usuarioId, array $datos): int
{
    $columnas = array_keys($datos);
    $marcadores = implode(', ', array_map(fn($c) => ":{$c}", $columnas));
    $sql = 'INSERT INTO torneos (' . implode(', ', $columnas) . ', usuario_id, codigo) VALUES ('
        . $marcadores . ', :usuario_id, :codigo) RETURNING id';
    $stmt = $pdo->prepare($sql);
    foreach ($datos as $c => $v) {
        $stmt->bindValue(":{$c}", $v);
    }
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    $stmt->bindValue(':codigo', strtoupper(substr(md5($datos['slug']), 0, 6)));
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

$ligaId = crearTorneo($pdo, $usuarioId, [
    'slug' => 'liga-municipal',
    'nombre' => 'Liga Municipal',
    'subtitulo' => 'Torneo Apertura',
    'temporada' => '2026',
    'descripcion' => 'Ocho equipos, ida y vuelta, y el título se define en la tabla.',
    'sede_principal' => 'Polideportivo Municipal',
    'logo' => $logoId,
    'color_primario' => '#1e7a46',
    'color_secundario' => '#f4a300',
    'color_acento' => '#ffd166',
    'fecha_inicio' => '2026-03-01',
    'fecha_fin' => '2026-07-30',
    'formato' => 'Todos contra todos, ida y vuelta',
    'hero_frase' => 'El barrio entero en la cancha',
    'deporte' => 'futbol',
    'genero' => 'masculino',
    'modo' => 'liga',
    'vueltas' => 2,
    'modalidad' => 'futbol7',
    'duracion_periodo_min' => 25,
    'num_equipos' => 4,
    'fases_playoff' => '{}',
    'permite_empates' => 'true',
    'puntos_victoria' => 3,
    'puntos_empate' => 1,
    'puntos_derrota' => 0,
    'es_predeterminado' => 'false',
    'activo' => 'true',
]);

$copaId = crearTorneo($pdo, $usuarioId, [
    'slug' => 'copa-estrellas',
    'nombre' => 'Copa Estrellas',
    'subtitulo' => 'Femenino 2026',
    'temporada' => '2026',
    'descripcion' => 'Fase de grupos y eliminación directa hasta la final.',
    'sede_principal' => 'Gimnasio Central',
    'logo' => '',
    'color_primario' => '#7b2ff7',
    'color_secundario' => '#ff6b35',
    'color_acento' => '#ffc93c',
    'fecha_inicio' => '2026-04-10',
    'fecha_fin' => '2026-08-20',
    'formato' => 'Grupos + playoffs',
    'hero_frase' => 'Cada balón cuenta',
    'deporte' => 'basketball',
    'genero' => 'femenino',
    'modo' => 'copa',
    'vueltas' => 1,
    'modalidad' => 'fiba',
    'duracion_periodo_min' => null,
    'num_equipos' => 4,
    'fases_playoff' => '{semifinal,final}',
    'permite_empates' => 'false',
    'puntos_victoria' => 2,
    'puntos_empate' => 0,
    'puntos_derrota' => 1,
    'es_predeterminado' => 'false',
    'activo' => 'true',
]);

// --- Equipos y plantillas -----------------------------------------------------
$equipoId = 0;
$jugadorId = 0;

function crearEquipos(PDO $pdo, int $torneoId, array $nombres, int &$equipoId): array
{
    $ids = [];
    $stmt = $pdo->prepare(
        'INSERT INTO equipos (id, torneo_id, nombre, ciudad, sede, entrenador, fundacion, color_primario, color_secundario, logo, descripcion)
         VALUES (:id, :t, :n, :ciudad, :sede, :dt, :f, :c1, :c2, :logo, :d)'
    );
    $paleta = [['#c1121f', '#fdf0d5'], ['#003049', '#669bbc'], ['#2a9d8f', '#e9c46a'], ['#6a4c93', '#f7b801']];
    foreach ($nombres as $i => $nombre) {
        $equipoId++;
        $stmt->execute([
            ':id' => $equipoId,
            ':t' => $torneoId,
            ':n' => $nombre,
            ':ciudad' => 'Ciudad ' . ($i + 1),
            ':sede' => 'Cancha ' . ($i + 1),
            ':dt' => 'DT ' . $nombre,
            ':f' => (string) (1990 + $i),
            ':c1' => $paleta[$i % 4][0],
            ':c2' => $paleta[$i % 4][1],
            ':logo' => '',
            ':d' => 'Equipo de prueba ' . $nombre,
        ]);
        $ids[] = $equipoId;
    }
    return $ids;
}

function crearPlantilla(PDO $pdo, int $torneoId, int $equipoIdEquipo, array $posiciones, int &$jugadorId): array
{
    $ids = [];
    $stmt = $pdo->prepare(
        'INSERT INTO jugadores (id, torneo_id, equipo_id, dorsal, nombre, activo, posicion)
         VALUES (:id, :t, :e, :d, :n, :a, :p)'
    );
    $nombres = ['Álvarez', 'Bermúdez', 'Castillo', 'Duarte', 'Escobar', 'Fuentes', 'Gómez', 'Herrera', 'Ibáñez', 'Juárez', 'Klein', 'López'];
    foreach ($posiciones as $i => $posicion) {
        $jugadorId++;
        $stmt->execute([
            ':id' => $jugadorId,
            ':t' => $torneoId,
            ':e' => $equipoIdEquipo,
            ':d' => (string) ($i + 1),
            ':n' => $nombres[$i % count($nombres)],
            // Uno inactivo por equipo, para ver que la alineación lo excluye.
            ':a' => $i === count($posiciones) - 1 ? 'false' : 'true',
            ':p' => $posicion,
        ]);
        $ids[] = $jugadorId;
    }
    return $ids;
}

$equiposLiga = crearEquipos($pdo, $ligaId, ['Deportivo Norte', 'Atlético Sur', 'Unión Este', 'Racing Oeste'], $equipoId);
$posFutbol = ['portero', 'defensa', 'defensa', 'medio', 'medio', 'delantero', 'delantero', 'medio', 'defensa'];
$plantillasLiga = [];
foreach ($equiposLiga as $eq) {
    $plantillasLiga[$eq] = crearPlantilla($pdo, $ligaId, $eq, $posFutbol, $jugadorId);
}

$equiposCopa = crearEquipos($pdo, $copaId, ['Estrellas FC', 'Cometas', 'Nebulosas', 'Galaxias'], $equipoId);
$posBasket = ['base', 'escolta', 'alero', 'ala_pivot', 'pivot', 'base', 'alero'];
foreach ($equiposCopa as $eq) {
    crearPlantilla($pdo, $copaId, $eq, $posBasket, $jugadorId);
}

// --- Partidos -----------------------------------------------------------------
$partidoId = 0;
$stmtPartido = $pdo->prepare(
    'INSERT INTO partidos (id, torneo_id, jornada, equipo_local, equipo_visitante, fecha, hora, cancha, estado,
        marcador_local, marcador_visitante, fase, arbitro, observaciones,
        cronometro_estado, cronometro_inicio, cronometro_segundos, cronometro_periodo, cronometro_extra_min)
     VALUES (:id, :t, :j, :l, :v, :fecha, :hora, :cancha, :estado, :ml, :mv, :fase, :arb, :obs,
        :ce, :ci, :cs, :cp, :cx)'
);

function crearPartido(PDOStatement $stmt, int &$partidoId, array $d): int
{
    $partidoId++;
    $stmt->execute([
        ':id' => $partidoId,
        ':t' => $d['torneo'],
        ':j' => $d['jornada'] ?? 1,
        ':l' => $d['local'],
        ':v' => $d['visitante'],
        ':fecha' => $d['fecha'],
        ':hora' => $d['hora'] ?? '19:00',
        ':cancha' => $d['cancha'] ?? 'Cancha central',
        ':estado' => $d['estado'] ?? 'programado',
        ':ml' => $d['ml'] ?? null,
        ':mv' => $d['mv'] ?? null,
        ':fase' => $d['fase'] ?? 'grupos',
        ':arb' => $d['arbitro'] ?? 'Árbitro de prueba',
        ':obs' => $d['obs'] ?? '',
        ':ce' => $d['crono'] ?? 'detenido',
        ':ci' => $d['crono_inicio'] ?? null,
        ':cs' => $d['crono_seg'] ?? 0,
        ':cp' => $d['periodo'] ?? 1,
        ':cx' => $d['extra'] ?? 0,
    ]);
    return $partidoId;
}

// Liga: uno jugado (alimenta la tabla), uno EN CURSO con cronómetro corriendo y tiempo
// extra (para la transmisión en vivo), y uno futuro.
$pJugado = crearPartido($stmtPartido, $partidoId, [
    'torneo' => $ligaId, 'jornada' => 1, 'local' => $equiposLiga[0], 'visitante' => $equiposLiga[1],
    'fecha' => '2026-03-01', 'estado' => 'jugado', 'ml' => 2, 'mv' => 1,
    'obs' => 'Partido de prueba ya cerrado.',
]);
$pEnVivo = crearPartido($stmtPartido, $partidoId, [
    'torneo' => $ligaId, 'jornada' => 2, 'local' => $equiposLiga[2], 'visitante' => $equiposLiga[3],
    'fecha' => date('Y-m-d'), 'estado' => 'programado',
    'crono' => 'corriendo', 'crono_inicio' => date('c', time() - 400), 'crono_seg' => 120,
    'periodo' => 1, 'extra' => 2,
]);
crearPartido($stmtPartido, $partidoId, [
    'torneo' => $ligaId, 'jornada' => 3, 'local' => $equiposLiga[0], 'visitante' => $equiposLiga[2],
    'fecha' => '2026-06-15',
]);

// Copa: un partido de grupos jugado y una semifinal programada (para ver las fases).
$pCopa = crearPartido($stmtPartido, $partidoId, [
    'torneo' => $copaId, 'jornada' => 1, 'local' => $equiposCopa[0], 'visitante' => $equiposCopa[1],
    'fecha' => '2026-04-10', 'estado' => 'jugado', 'ml' => 58, 'mv' => 47,
]);
crearPartido($stmtPartido, $partidoId, [
    'torneo' => $copaId, 'jornada' => 1, 'local' => $equiposCopa[2], 'visitante' => $equiposCopa[3],
    'fecha' => '2026-07-01', 'fase' => 'semifinal',
]);

// --- Eventos del partido jugado ------------------------------------------------
$eventoId = 0;
$stmtEvento = $pdo->prepare(
    'INSERT INTO partido_eventos (id, torneo_id, partido_id, tipo, equipo_id, jugador_id, jugador_entra_id,
        minuto, tipo_gol, asistencia_jugador_id, motivo, periodo)
     VALUES (:id, :t, :p, :tipo, :e, :j, :je, :min, :tg, :asis, :mot, :per)'
);
$rosterLocal = $plantillasLiga[$equiposLiga[0]];
$rosterVisita = $plantillasLiga[$equiposLiga[1]];
$eventos = [
    ['tipo' => 'gol', 'e' => $equiposLiga[0], 'j' => $rosterLocal[5], 'min' => 12, 'tg' => 'jugada', 'asis' => $rosterLocal[3], 'per' => 1],
    ['tipo' => 'amarilla', 'e' => $equiposLiga[1], 'j' => $rosterVisita[1], 'min' => 20, 'per' => 1],
    ['tipo' => 'gol', 'e' => $equiposLiga[1], 'j' => $rosterVisita[6], 'min' => 31, 'tg' => 'penal', 'per' => 2],
    ['tipo' => 'cambio', 'e' => $equiposLiga[0], 'j' => $rosterLocal[6], 'je' => $rosterLocal[7], 'min' => 38, 'per' => 2],
    ['tipo' => 'gol', 'e' => $equiposLiga[0], 'j' => $rosterLocal[7], 'min' => 44, 'tg' => 'jugada', 'per' => 2],
    ['tipo' => 'roja', 'e' => $equiposLiga[1], 'j' => $rosterVisita[2], 'min' => 47, 'mot' => 'directa', 'per' => 2],
];
foreach ($eventos as $ev) {
    $eventoId++;
    $stmtEvento->execute([
        ':id' => $eventoId, ':t' => $ligaId, ':p' => $pJugado, ':tipo' => $ev['tipo'], ':e' => $ev['e'],
        ':j' => $ev['j'] ?? null, ':je' => $ev['je'] ?? null, ':min' => $ev['min'] ?? null,
        ':tg' => $ev['tg'] ?? null, ':asis' => $ev['asis'] ?? null, ':mot' => $ev['mot'] ?? null,
        ':per' => $ev['per'] ?? 1,
    ]);
}

// Un gol en el partido en vivo, para que el feed no arranque vacío.
$eventoId++;
$stmtEvento->execute([
    ':id' => $eventoId, ':t' => $ligaId, ':p' => $pEnVivo, ':tipo' => 'gol',
    ':e' => $equiposLiga[2], ':j' => $plantillasLiga[$equiposLiga[2]][5], ':je' => null, ':min' => 6,
    ':tg' => 'jugada', ':asis' => null, ':mot' => null, ':per' => 1,
]);

// --- Alineaciones ---------------------------------------------------------------
$stmtAli = $pdo->prepare(
    'INSERT INTO partido_alineacion (torneo_id, partido_id, jugador_id, equipo_id, titular, posicion)
     VALUES (:t, :p, :j, :e, :tit, :pos)'
);
// Fútbol 7: 7 titulares por equipo, el resto (activos) a la banca.
foreach ([$equiposLiga[0], $equiposLiga[1]] as $eq) {
    foreach ($plantillasLiga[$eq] as $i => $jid) {
        if ($i >= count($posFutbol) - 1) {
            continue; // el inactivo no se convoca
        }
        $stmtAli->execute([
            ':t' => $ligaId, ':p' => $pJugado, ':j' => $jid, ':e' => $eq,
            ':tit' => $i < 7 ? 'true' : 'false', ':pos' => $posFutbol[$i],
        ]);
    }
}

// --- Patrocinadores y comentarios -------------------------------------------------
$stmtPatro = $pdo->prepare('INSERT INTO patrocinadores (id, torneo_id, nombre, nivel, url, logo, orden) VALUES (:id, :t, :n, :niv, :u, :l, :o)');
$niveles = ['oficial', 'oro', 'plata'];
$idPatro = 0;
foreach ([$ligaId, $copaId] as $t) {
    foreach ($niveles as $i => $nivel) {
        $idPatro++;
        $stmtPatro->execute([':id' => $idPatro, ':t' => $t, ':n' => 'Patrocinador ' . ucfirst($nivel), ':niv' => $nivel, ':u' => 'https://example.com', ':l' => '', ':o' => $i]);
    }
}

$pdo->prepare('INSERT INTO comentarios (id, torneo_id, mensaje, fecha, leido) VALUES (1, :t, :m, :f, 0)')
    ->execute([':t' => $ligaId, ':m' => 'Excelente organización, felicidades.', ':f' => date('Y-m-d')]);

$pdo->prepare('INSERT INTO visitas_diarias (torneo_id, fecha, visitas) VALUES (:t, CURRENT_DATE, 42)')
    ->execute([':t' => $ligaId]);

echo "Listo.\n";
echo "  Liga Municipal (fútbol, LIGA, ida y vuelta, con logo): /liga-municipal\n";
echo "  Copa Estrellas (basketball, CAMPEONATO, con playoffs): /copa-estrellas\n";
echo "  Partido jugado con ficha y alineación: id={$pJugado}\n";
echo "  Partido EN VIVO (cronómetro corriendo + 2 min extra): id={$pEnVivo}\n";
echo "  Partido de copa jugado: id={$pCopa}\n";
echo "  Login: prueba@local.test / prueba123\n";
