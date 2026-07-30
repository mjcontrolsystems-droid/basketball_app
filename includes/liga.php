<?php
declare(strict_types=1);

// Catálogos del modo liga para fútbol. Se reutilizan las mismas columnas de
// partido_eventos ('gol'/'amarilla'/'roja'/'cambio') para basketball más abajo,
// cambiando solo los catálogos y las etiquetas según $torneo['deporte'].
const TIPOS_GOL_CATALOGO = ['jugada', 'penal', 'tiro_libre', 'autogol'];

const TIPOS_GOL_LABEL = [
    'jugada' => 'Jugada',
    'penal' => 'Penal',
    'tiro_libre' => 'Tiro libre',
    'autogol' => 'Autogol',
];

const MOTIVOS_ROJA_CATALOGO = ['directa', 'doble_amarilla'];

const MOTIVOS_ROJA_LABEL = [
    'directa' => 'Roja directa',
    'doble_amarilla' => 'Doble amarilla',
];

// Basketball: 'gol' se reutiliza como "anotación" (1/2/3 puntos), 'amarilla' como falta
// personal (acumulable, expulsa al llegar a 5 según reglas FIBA) y 'roja' como falta
// grave con motivo (técnica/antideportiva/descalificante), igual de estructura que fútbol.
const TIPOS_PUNTO_CATALOGO = ['libre', 'doble', 'triple'];

const TIPOS_PUNTO_LABEL = [
    'libre' => 'Tiro libre (1 pt)',
    'doble' => 'Canasta (2 pts)',
    'triple' => 'Triple (3 pts)',
];

const TIPOS_PUNTO_VALOR = [
    'libre' => 1,
    'doble' => 2,
    'triple' => 3,
];

const MOTIVOS_FALTA_CATALOGO = ['tecnica', 'antideportiva', 'descalificante'];

const MOTIVOS_FALTA_LABEL = [
    'tecnica' => 'Falta técnica',
    'antideportiva' => 'Falta antideportiva',
    'descalificante' => 'Falta descalificante',
];

// Regla FIBA (la más usada en ligas amateur): al llegar a esta cantidad de faltas
// personales en un mismo partido, el jugador queda expulsado del resto del encuentro.
const LIMITE_FALTAS_EXPULSION = 5;

function es_basketball(?string $deporte): bool
{
    return $deporte === 'basketball';
}

function tipos_anotacion_catalogo(?string $deporte): array
{
    return es_basketball($deporte) ? TIPOS_PUNTO_CATALOGO : TIPOS_GOL_CATALOGO;
}

function tipos_anotacion_label(?string $deporte): array
{
    return es_basketball($deporte) ? TIPOS_PUNTO_LABEL : TIPOS_GOL_LABEL;
}

function motivos_falta_grave_catalogo(?string $deporte): array
{
    return es_basketball($deporte) ? MOTIVOS_FALTA_CATALOGO : MOTIVOS_ROJA_CATALOGO;
}

function motivos_falta_grave_label(?string $deporte): array
{
    return es_basketball($deporte) ? MOTIVOS_FALTA_LABEL : MOTIVOS_ROJA_LABEL;
}

// Textos según deporte, usados en los encabezados de admin/partido_eventos.php, la
// ficha pública partido.php y la tabla de posiciones (columnas TA/TR).
function etiqueta_anotacion(?string $deporte): string
{
    return es_basketball($deporte) ? 'Punto' : 'Gol';
}

function etiqueta_anotaciones(?string $deporte): string
{
    return es_basketball($deporte) ? 'Puntos' : 'Goles';
}

function etiqueta_falta_leve(?string $deporte): string
{
    return es_basketball($deporte) ? 'Falta personal' : 'Tarjeta amarilla';
}

function etiqueta_faltas_leves(?string $deporte): string
{
    return es_basketball($deporte) ? 'Faltas personales' : 'Tarjetas amarillas';
}

function etiqueta_falta_grave(?string $deporte): string
{
    return es_basketball($deporte) ? 'Falta descalificante' : 'Tarjeta roja';
}

function etiqueta_faltas_graves(?string $deporte): string
{
    return es_basketball($deporte) ? 'Faltas descalificantes' : 'Tarjetas rojas';
}

function etiqueta_ta(?string $deporte): string
{
    return es_basketball($deporte) ? 'FP' : 'TA';
}

function etiqueta_tr(?string $deporte): string
{
    return es_basketball($deporte) ? 'FD' : 'TR';
}

/**
 * Agrupa la plantilla de jugadores por equipo_id, para llenar los <select> de un
 * partido puntual solo con los jugadores de los dos equipos que lo disputan.
 */
function jugadores_por_equipo(array $jugadores): array
{
    $porEquipo = [];
    foreach ($jugadores as $j) {
        $porEquipo[(int) $j['equipo_id']][] = $j;
    }
    return $porEquipo;
}

/**
 * Indexa la plantilla por id, para resolver rápido "jugador_id" -> nombre al describir eventos.
 */
function jugadores_por_id(array $jugadores): array
{
    $porId = [];
    foreach ($jugadores as $j) {
        $porId[(int) $j['id']] = $j;
    }
    return $porId;
}

function jugador_nombre(?array $jugador): string
{
    if ($jugador === null) {
        return 'Jugador no registrado';
    }
    return '#' . $jugador['dorsal'] . ' ' . $jugador['nombre'];
}

/**
 * Cuenta las faltas personales ('amarilla') acumuladas por cada jugador en la lista de
 * eventos dada (normalmente los de un solo partido), para detectar quién llegó al límite
 * de expulsión por faltas (LIMITE_FALTAS_EXPULSION). Solo tiene sentido en basketball,
 * pero funciona igual para cualquier deporte porque solo cuenta ocurrencias.
 */
function faltas_por_jugador(array $eventos): array
{
    $conteo = [];
    foreach ($eventos as $evento) {
        if ($evento['tipo'] !== 'amarilla') {
            continue;
        }
        $jugadorId = (int) ($evento['jugador_id'] ?? 0);
        if ($jugadorId === 0) {
            continue;
        }
        $conteo[$jugadorId] = ($conteo[$jugadorId] ?? 0) + 1;
    }
    return $conteo;
}

/**
 * Describe un evento del partido en una línea legible ("34' Gol de penal - #10 Juan Pérez"
 * en fútbol, "3er cuarto Canasta (2 pts) - #10 Juan Pérez" en basketball), reutilizado
 * tanto en admin/partido_eventos.php como en la ficha pública partido.php.
 */
function evento_descripcion(array $evento, array $jugadoresPorId, ?string $deporte = null): string
{
    $basketball = es_basketball($deporte);
    $minuto = $evento['minuto'] !== null ? $evento['minuto'] . "' " : '';
    $jugador = $jugadoresPorId[(int) ($evento['jugador_id'] ?? 0)] ?? null;
    $nombreJugador = jugador_nombre($jugador);

    switch ($evento['tipo']) {
        case 'gol':
            $tipoLabel = tipos_anotacion_label($deporte)[$evento['tipo_gol'] ?? ''] ?? '';
            if ($basketball) {
                // El label del tipo de canasta ya se lee bien solo ("Triple (3 pts)"), a
                // diferencia de fútbol donde "Gol" es el sustantivo y el tipo es un extra.
                $texto = $minuto . ($tipoLabel !== '' ? $tipoLabel : 'Punto') . ' - ' . $nombreJugador;
            } else {
                // "Jugada" es el caso por defecto en fútbol y no aporta nada al texto.
                $mostrarTipo = $tipoLabel !== '' && $tipoLabel !== 'Jugada';
                $texto = $minuto . 'Gol' . ($mostrarTipo ? " ({$tipoLabel})" : '') . ' - ' . $nombreJugador;
            }
            $asistencia = $jugadoresPorId[(int) ($evento['asistencia_jugador_id'] ?? 0)] ?? null;
            if ($asistencia !== null) {
                $texto .= ' (asistencia de ' . jugador_nombre($asistencia) . ')';
            }
            return $texto;
        case 'amarilla':
            return $minuto . etiqueta_falta_leve($deporte) . ' - ' . $nombreJugador;
        case 'roja':
            $catalogoMotivo = motivos_falta_grave_label($deporte);
            $motivo = $catalogoMotivo[$evento['motivo'] ?? ''] ?? '';
            return $minuto . etiqueta_falta_grave($deporte) . ($motivo !== '' ? " ({$motivo})" : '') . ' - ' . $nombreJugador;
        case 'cambio':
            $entra = $jugadoresPorId[(int) ($evento['jugador_entra_id'] ?? 0)] ?? null;
            return $minuto . 'Cambio - Entra ' . jugador_nombre($entra) . ', sale ' . $nombreJugador;
        default:
            return $minuto . $nombreJugador;
    }
}

/**
 * Calcula el marcador [local, visitante] de un partido a partir de sus eventos de
 * anotación ('gol'). En fútbol cada gol vale 1 y un AUTOGOL suma al equipo RIVAL del
 * que lo cometió (el evento guarda el equipo del jugador que se marcó en propia). En
 * basketball cada anotación suma su valor real (1/2/3 puntos según el tipo de canasta).
 *
 * @return array{0:int,1:int} [marcadorLocal, marcadorVisitante]
 */
function marcador_desde_eventos(array $eventos, int $equipoLocalId, int $equipoVisitanteId, ?string $deporte = null): array
{
    $basketball = es_basketball($deporte);
    $local = 0;
    $visitante = 0;

    foreach ($eventos as $ev) {
        if (($ev['tipo'] ?? '') !== 'gol') {
            continue;
        }
        $equipoId = (int) ($ev['equipo_id'] ?? 0);
        $valor = $basketball ? (TIPOS_PUNTO_VALOR[$ev['tipo_gol'] ?? ''] ?? 1) : 1;

        // Autogol (solo fútbol): el punto va al equipo contrario al del jugador.
        $esAutogol = !$basketball && ($ev['tipo_gol'] ?? '') === 'autogol';
        $equipoAcreditado = $esAutogol
            ? ($equipoId === $equipoLocalId ? $equipoVisitanteId : $equipoLocalId)
            : $equipoId;

        if ($equipoAcreditado === $equipoLocalId) {
            $local += $valor;
        } elseif ($equipoAcreditado === $equipoVisitanteId) {
            $visitante += $valor;
        }
    }

    return [$local, $visitante];
}

/**
 * Recalcula el marcador de un partido a partir de sus eventos de gol y lo escribe en el
 * registro del partido, para que la tabla de posiciones (que lee marcador_local/visitante)
 * quede al día. Modifica $partidos por referencia y persiste con db_guardar. NO cambia el
 * estado 'programado'/'jugado'. Devuelve [local, visitante].
 *
 * @param array<int,array> $partidos Colección completa de partidos de la copa (por referencia).
 * @return array{0:int,1:int}
 */
function partido_recalcular_marcador(int $torneoId, int $partidoId, array &$partidos, ?string $deporte): array
{
    $partido = db_buscar_por_id($partidos, $partidoId);
    if ($partido === null) {
        return [0, 0];
    }

    $eventos = db_leer_eventos_partido($torneoId, $partidoId);
    [$local, $visitante] = marcador_desde_eventos(
        $eventos,
        (int) $partido['equipo_local'],
        (int) $partido['equipo_visitante'],
        $deporte
    );

    foreach ($partidos as &$p) {
        if ((int) $p['id'] === $partidoId) {
            $p['marcador_local'] = $local;
            $p['marcador_visitante'] = $visitante;
        }
    }
    unset($p);

    db_guardar('partidos', $partidos, $torneoId);
    return [$local, $visitante];
}

/**
 * Segundos transcurridos del cronómetro del partido: los ya acumulados (cronometro_segundos)
 * más lo corrido desde cronometro_inicio si en este momento está 'corriendo'. Es la fuente de
 * verdad tanto para mostrar el cronómetro como para sugerir el minuto al cargar un evento.
 */
function partido_cronometro_segundos(array $partido): int
{
    $segundos = (int) ($partido['cronometro_segundos'] ?? 0);
    if (($partido['cronometro_estado'] ?? 'detenido') === 'corriendo' && !empty($partido['cronometro_inicio'])) {
        $inicio = strtotime((string) $partido['cronometro_inicio']);
        if ($inicio !== false) {
            $segundos += max(0, time() - $inicio);
        }
    }
    return $segundos;
}

function partido_cronometro_minuto(array $partido): int
{
    return intdiv(partido_cronometro_segundos($partido), 60);
}

/**
 * Cuántos periodos tiene el partido según el deporte: 2 tiempos en fútbol, 4 cuartos en
 * basketball. Avanzar de periodo (ver la acción cronometro_siguiente_periodo en
 * admin/partido_eventos.php) NO reinicia el cronómetro: solo cambia la etiqueta que se
 * muestra, el reloj sigue corriendo de forma continua durante todo el partido.
 */
function partido_periodo_maximo(?string $deporte): int
{
    return es_basketball($deporte) ? 4 : 2;
}

function partido_periodo_etiqueta(?string $deporte, int $periodo): string
{
    if (es_basketball($deporte)) {
        return 'Cuarto ' . max(1, $periodo);
    }
    $etiquetas = [1 => '1er Tiempo', 2 => '2do Tiempo'];
    return $etiquetas[$periodo] ?? ($periodo . 'do Tiempo');
}

/**
 * Marcador [local, visitante] con el que se debe dar por jugado un partido, calculado
 * desde sus goles. Protege datos históricos: si todavía no hay goles registrados (0-0)
 * pero el partido ya tenía un marcador capturado antes de este modelo, se conserva ese
 * marcador en vez de borrarlo a 0-0.
 *
 * @return array{0:int,1:int}
 */
function marcador_jugado_desde_eventos(int $torneoId, array $partido, ?string $deporte): array
{
    $eventos = db_leer_eventos_partido($torneoId, (int) $partido['id']);
    [$local, $visitante] = marcador_desde_eventos(
        $eventos,
        (int) $partido['equipo_local'],
        (int) $partido['equipo_visitante'],
        $deporte
    );

    $sinGoles = ($local === 0 && $visitante === 0);
    $teniaMarcador = ($partido['marcador_local'] ?? null) !== null || ($partido['marcador_visitante'] ?? null) !== null;
    if ($sinGoles && $teniaMarcador) {
        $local = (int) $partido['marcador_local'];
        $visitante = (int) $partido['marcador_visitante'];
    }

    return [$local, $visitante];
}

/**
 * Ranking de máximos anotadores a partir de los eventos de todos los partidos de la
 * copa/liga. En fútbol cuenta goles (los autogoles no suman al goleador, van a favor
 * del marcador del rival pero no son "su" gol). En basketball suma el valor real de
 * cada anotación (1/2/3 puntos) en vez de solo contar ocurrencias.
 *
 * @return array<int, array{jugador: array, equipo: ?array, goles: int}> Ordenado de más a menos.
 */
function calcular_goleadores(array $eventos, array $jugadores, array $equiposPorId, ?string $deporte = null): array
{
    $basketball = es_basketball($deporte);
    $jugadoresPorId = jugadores_por_id($jugadores);

    $conteo = [];
    foreach ($eventos as $evento) {
        if ($evento['tipo'] !== 'gol') {
            continue;
        }
        if (!$basketball && ($evento['tipo_gol'] ?? '') === 'autogol') {
            continue;
        }
        $jugadorId = (int) ($evento['jugador_id'] ?? 0);
        if (!isset($jugadoresPorId[$jugadorId])) {
            continue;
        }
        $valor = $basketball ? (TIPOS_PUNTO_VALOR[$evento['tipo_gol'] ?? ''] ?? 1) : 1;
        $conteo[$jugadorId] = ($conteo[$jugadorId] ?? 0) + $valor;
    }

    $goleadores = [];
    foreach ($conteo as $jugadorId => $goles) {
        $jugador = $jugadoresPorId[$jugadorId];
        $goleadores[] = [
            'jugador' => $jugador,
            'equipo' => $equiposPorId[(int) $jugador['equipo_id']] ?? null,
            'goles' => $goles,
        ];
    }

    usort($goleadores, fn($a, $b) => $b['goles'] <=> $a['goles']);
    return $goleadores;
}
