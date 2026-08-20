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

// Regla IFAB (fútbol): dos tarjetas amarillas en el mismo partido = expulsión
// (doble amarilla). Se usa para avisar la expulsión igual que en basketball.
const LIMITE_AMARILLAS_EXPULSION_FUTBOL = 2;

/**
 * Faltas leves acumuladas que expulsan a un jugador en un mismo partido, según el
 * deporte: 5 faltas personales en basketball (FIBA), 2 amarillas en fútbol (IFAB).
 * Con esto ambos deportes tienen el mismo aviso automático de expulsión en la ficha.
 */
function limite_faltas_expulsion(?string $deporte): int
{
    return es_basketball($deporte) ? LIMITE_FALTAS_EXPULSION : LIMITE_AMARILLAS_EXPULSION_FUTBOL;
}

/**
 * Texto del motivo de expulsión por acumulación, según el deporte.
 */
function texto_expulsion_acumulacion(?string $deporte, int $cantidad): string
{
    return es_basketball($deporte)
        ? "{$cantidad} faltas personales (regla FIBA)"
        : 'doble tarjeta amarilla';
}

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
    // Pasa cuando el evento se cargó sin identificar al jugador, porque el equipo todavía
    // no tenía su plantilla. No es un error: el gol o la tarjeta sí ocurrieron.
    if ($jugador === null) {
        return 'Sin identificar';
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
            break;
        case 'amarilla':
            $texto = $minuto . etiqueta_falta_leve($deporte) . ' - ' . $nombreJugador;
            break;
        case 'roja':
            $catalogoMotivo = motivos_falta_grave_label($deporte);
            $motivo = $catalogoMotivo[$evento['motivo'] ?? ''] ?? '';
            $texto = $minuto . etiqueta_falta_grave($deporte) . ($motivo !== '' ? " ({$motivo})" : '') . ' - ' . $nombreJugador;
            break;
        case 'cambio':
            $entra = $jugadoresPorId[(int) ($evento['jugador_entra_id'] ?? 0)] ?? null;
            $texto = $minuto . 'Cambio - Entra ' . jugador_nombre($entra) . ', sale ' . $nombreJugador;
            break;
        default:
            $texto = $minuto . $nombreJugador;
    }

    // Periodo en que ocurrió el evento (1er/2do Tiempo en fútbol, Cuarto 1-4 en basketball),
    // guardado en el evento al momento de cargarlo (ver admin/partido_eventos.php). Los
    // eventos de antes de este campo no tienen 'periodo' guardado: se asumen del primero.
    $texto .= ' · ' . partido_periodo_etiqueta($deporte, (int) ($evento['periodo'] ?? 1));

    return $texto;
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

// ---------------------------------------------------------------------------
// Modalidades por deporte y reglas de tiempo/expulsión asociadas.
//
// Cada copa/liga declara su modalidad (torneos.modalidad) y, si el organizador lo
// necesita, una duración de periodo personalizada (torneos.duracion_periodo_min) —
// muchas ligas amateur juegan tiempos más cortos que los reglamentarios.
//
// Duraciones reglamentarias por modalidad:
//   Fútbol 11 (FIFA):        2 tiempos de 45 min
//   Fútbol 7:                2 tiempos de 25 min (uso más extendido)
//   Fútbol sala / 5 (futsal):2 tiempos de 20 min
//   Basketball FIBA (5v5):   4 cuartos de 10 min
//   Basketball NBA:          4 cuartos de 12 min
// ---------------------------------------------------------------------------

const MODALIDADES_POR_DEPORTE = [
    'futbol' => [
        'futbol11' => ['label' => 'Fútbol 11 (2 tiempos de 45 min)', 'duracion_min' => 45],
        'futbol7' => ['label' => 'Fútbol 7 (2 tiempos de 25 min)', 'duracion_min' => 25],
        'futbol5' => ['label' => 'Fútbol sala / 5 (2 tiempos de 20 min)', 'duracion_min' => 20],
    ],
    'basketball' => [
        'fiba' => ['label' => 'FIBA 5v5 (4 cuartos de 10 min)', 'duracion_min' => 10],
        'nba' => ['label' => 'Estilo NBA (4 cuartos de 12 min)', 'duracion_min' => 12],
    ],
];

// Modalidad que se asume cuando la copa no declaró ninguna (copas creadas antes de que
// existiera este campo): la más común de cada deporte.
const MODALIDAD_POR_DEFECTO = ['futbol' => 'futbol11', 'basketball' => 'fiba'];

// Compatibilidad con código previo que usaba una constante fija para basketball.
const DURACION_CUARTO_BASKETBALL_MIN = 10;

// ---------------------------------------------------------------------------
// Formato de la competencia: LIGA vs CAMPEONATO (columna torneos.modo).
//
//   'liga'  -> temporada regular y nada más: todo se decide en la tabla de puntos.
//              No hay fases de eliminación directa en ningún lado (ni al programar
//              encuentros, ni en el calendario público, ni en la tabla).
//   'copa'  -> campeonato: fase de grupos + el cuadro de eliminación directa que el
//              organizador haya habilitado (torneos.fases_playoff).
//
// Se resuelve siempre con torneo_es_liga()/torneo_fases_playoff() y nunca leyendo
// $torneo['fases_playoff'] directo, para que una liga jamás muestre playoffs aunque
// tenga fases guardadas de cuando era campeonato.
// ---------------------------------------------------------------------------

const FORMATO_LIGA = 'liga';
const FORMATO_CAMPEONATO = 'copa';
// Vive aquí y no en grupos.php porque FORMATOS_TORNEO_LABEL lo usa al cargarse, y este
// archivo se carga antes.
const FORMATO_GRUPOS = 'grupos';

// El valor guardado sigue siendo 'copa' por compatibilidad con las copas ya creadas, pero
// el nombre visible dice lo que REALMENTE hace: no divide en grupos, juega todos contra
// todos igual que la liga y encima le agrega la fase final. La etiqueta anterior hablaba
// de "fase de grupos" y hacía creer que no servía para una liga con semifinales.
const FORMATOS_TORNEO_LABEL = [
    FORMATO_LIGA => 'Liga (el campeón sale de la tabla)',
    FORMATO_CAMPEONATO => 'Liga con fase final (tabla + eliminación directa)',
    FORMATO_GRUPOS => 'Fase de grupos + eliminación (estilo mundial)',
];

function torneo_formato(array $torneo): string
{
    $modo = (string) ($torneo['modo'] ?? FORMATO_CAMPEONATO);

    return array_key_exists($modo, FORMATOS_TORNEO_LABEL) ? $modo : FORMATO_CAMPEONATO;
}

function torneo_es_liga(array $torneo): bool
{
    return torneo_formato($torneo) === FORMATO_LIGA;
}

/**
 * Fases de eliminación directa realmente vigentes para esta competencia: las que el
 * organizador habilitó, o ninguna en una liga, donde el título sale de la tabla.
 */
function torneo_fases_playoff(array $torneo): array
{
    return torneo_es_liga($torneo) ? [] : (array) ($torneo['fases_playoff'] ?? []);
}

/**
 * Palabra con la que el sitio se refiere a esta competencia ("la liga" / "el campeonato"),
 * para no llamarle "copa" a algo que el organizador configuró como liga.
 */
function torneo_sustantivo(array $torneo): string
{
    return torneo_es_liga($torneo) ? 'liga' : 'campeonato';
}

// ---------------------------------------------------------------------------
// Posiciones de la plantilla y alineación titular/banca.
// ---------------------------------------------------------------------------

const POSICIONES_POR_DEPORTE = [
    'futbol' => [
        'portero' => ['label' => 'Portero', 'corta' => 'POR'],
        'defensa' => ['label' => 'Defensa', 'corta' => 'DEF'],
        'medio' => ['label' => 'Mediocampista', 'corta' => 'MED'],
        'delantero' => ['label' => 'Delantero', 'corta' => 'DEL'],
    ],
    'basketball' => [
        'base' => ['label' => 'Base', 'corta' => 'B'],
        'escolta' => ['label' => 'Escolta', 'corta' => 'E'],
        'alero' => ['label' => 'Alero', 'corta' => 'A'],
        'ala_pivot' => ['label' => 'Ala-pívot', 'corta' => 'AP'],
        'pivot' => ['label' => 'Pívot', 'corta' => 'P'],
    ],
];

function posiciones_catalogo(?string $deporte): array
{
    return POSICIONES_POR_DEPORTE[es_basketball($deporte) ? 'basketball' : 'futbol'];
}

function posicion_label(?string $deporte, ?string $clave, bool $corta = false): string
{
    $catalogo = posiciones_catalogo($deporte);
    if ($clave === null || !isset($catalogo[$clave])) {
        return $corta ? '—' : 'Sin posición';
    }
    return $corta ? $catalogo[$clave]['corta'] : $catalogo[$clave]['label'];
}

// Cuántos jugadores pone cada equipo en la cancha según la modalidad de la copa. Es lo que
// define cuántos titulares admite la alineación de un encuentro (5, 7 u 11).
const JUGADORES_EN_CANCHA_POR_MODALIDAD = [
    'futbol11' => 11,
    'futbol7' => 7,
    'futbol5' => 5,
    'fiba' => 5,
    'nba' => 5,
];

function torneo_jugadores_en_cancha(array $torneo): int
{
    $deporte = es_basketball($torneo['deporte'] ?? null) ? 'basketball' : 'futbol';
    $modalidad = (string) ($torneo['modalidad'] ?? '');
    if (!isset(MODALIDADES_POR_DEPORTE[$deporte][$modalidad])) {
        $modalidad = MODALIDAD_POR_DEFECTO[$deporte];
    }
    return JUGADORES_EN_CANCHA_POR_MODALIDAD[$modalidad] ?? 5;
}

/**
 * Indexa la alineación de un partido por jugador_id, para saber de un vistazo si cada
 * jugador va de titular o de banca y en qué posición.
 *
 * @return array<int, array>
 */
function alineacion_por_jugador(array $alineacion): array
{
    $porJugador = [];
    foreach ($alineacion as $fila) {
        $porJugador[(int) $fila['jugador_id']] = $fila;
    }
    return $porJugador;
}

/**
 * Titulares de un equipo en un partido, ya resueltos a la ficha del jugador y ordenados
 * por posición (portero/base primero) y luego por dorsal.
 *
 * @return array<int, array{jugador: array, posicion: ?string}>
 */
function alineacion_de_equipo(array $alineacion, array $jugadoresPorId, int $equipoId, bool $titulares, ?string $deporte = null): array
{
    $orden = array_flip(array_keys(posiciones_catalogo($deporte)));
    $lista = [];
    foreach ($alineacion as $fila) {
        if ((int) $fila['equipo_id'] !== $equipoId || !empty($fila['titular']) !== $titulares) {
            continue;
        }
        $jugador = $jugadoresPorId[(int) $fila['jugador_id']] ?? null;
        if ($jugador === null) {
            continue;
        }
        $lista[] = ['jugador' => $jugador, 'posicion' => $fila['posicion'] ?: null];
    }
    usort($lista, function ($a, $b) use ($orden) {
        $pa = $orden[$a['posicion'] ?? ''] ?? 99;
        $pb = $orden[$b['posicion'] ?? ''] ?? 99;
        if ($pa !== $pb) {
            return $pa <=> $pb;
        }
        return strnatcmp((string) $a['jugador']['dorsal'], (string) $b['jugador']['dorsal']);
    });
    return $lista;
}

function modalidades_catalogo(?string $deporte): array
{
    return MODALIDADES_POR_DEPORTE[es_basketball($deporte) ? 'basketball' : 'futbol'];
}

/**
 * Duración de cada tiempo/cuarto de esta copa, en minutos. Prioridad: la duración
 * personalizada de la copa; si no, la reglamentaria de su modalidad; si no, la de la
 * modalidad por defecto del deporte.
 */
function torneo_duracion_periodo_min(array $torneo): int
{
    $personalizada = (int) ($torneo['duracion_periodo_min'] ?? 0);
    if ($personalizada > 0) {
        return $personalizada;
    }
    $deporte = es_basketball($torneo['deporte'] ?? null) ? 'basketball' : 'futbol';
    $modalidad = (string) ($torneo['modalidad'] ?? '');
    $catalogo = MODALIDADES_POR_DEPORTE[$deporte];
    if (!isset($catalogo[$modalidad])) {
        $modalidad = MODALIDAD_POR_DEFECTO[$deporte];
    }
    return $catalogo[$modalidad]['duracion_min'];
}

/**
 * Minutos extra (tiempo añadido / reposición) que el árbitro sumó al periodo que se está
 * jugando ahora mismo. Se reinician a 0 al pasar de periodo, igual que el cronómetro.
 */
function partido_minutos_extra(array $partido): int
{
    return max(0, (int) ($partido['cronometro_extra_min'] ?? 0));
}

/**
 * Cuánto dura, en segundos, el periodo que se está jugando: la duración configurada en la
 * copa (torneo_duracion_periodo_min) más los minutos extra agregados dentro del encuentro.
 * Es el número desde el que baja la cuenta regresiva en la ficha y en la transmisión en
 * vivo, en AMBOS deportes — el reloj arranca en los minutos que configuró el organizador
 * (15, 20, 45...) y corre hacia 00:00.
 */
function partido_duracion_periodo_segundos(array $partido, ?array $torneo = null): int
{
    $duracionMin = $torneo !== null ? torneo_duracion_periodo_min($torneo) : DURACION_CUARTO_BASKETBALL_MIN;
    return ($duracionMin + partido_minutos_extra($partido)) * 60;
}

/**
 * Segundos que le quedan al periodo actual (cuenta regresiva desde la duración configurada
 * de la copa + los minutos extra), nunca negativo. partido_cronometro_segundos() sigue
 * siendo la fuente de verdad (tiempo transcurrido); esto solo lo invierte para mostrarlo
 * como cuenta regresiva, que es como se ve un cronómetro en la cancha.
 */
function partido_cronometro_restante_segundos(array $partido, ?array $torneo = null): int
{
    return max(0, partido_duracion_periodo_segundos($partido, $torneo) - partido_cronometro_segundos($partido));
}

/**
 * Minuto en el que ARRANCA el periodo actual, para que el minuto sugerido a los eventos
 * siga la numeración real del partido en vez de volver a 0 en cada tiempo/cuarto: en
 * fútbol 11 el 2do tiempo empieza en el 45', el 3er cuarto FIBA en el 20'. Se calcula con
 * la duración reglamentaria configurada (los minutos extra de periodos ya cerrados no se
 * guardan, solo los del periodo en curso).
 */
function partido_minuto_base(array $partido, ?array $torneo = null): int
{
    $duracionMin = $torneo !== null ? torneo_duracion_periodo_min($torneo) : DURACION_CUARTO_BASKETBALL_MIN;
    $periodo = max(1, (int) ($partido['cronometro_periodo'] ?? 1));
    return ($periodo - 1) * $duracionMin;
}

/**
 * ¿El encuentro ya terminó? Cuenta como finalizado tanto si el organizador cerró el
 * resultado (estado 'jugado') como si dio por terminado el cronómetro. Lo usan la
 * transmisión en vivo y la ficha para dejar de anunciarse como "En vivo".
 */
function partido_finalizado(array $partido): bool
{
    return ($partido['estado'] ?? '') === 'jugado'
        || ($partido['cronometro_estado'] ?? 'detenido') === 'finalizado';
}

/**
 * Cuántos periodos tiene el partido según el deporte: 2 tiempos en fútbol, 4 cuartos en
 * basketball. Avanzar de periodo (ver la acción cronometro_siguiente_periodo en
 * admin/partido_eventos.php) devuelve el cronómetro a la duración completa configurada
 * para la copa y descarta el tiempo extra del periodo anterior: cada tiempo/cuarto lleva
 * su propio conteo, no es continuo entre periodos.
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
