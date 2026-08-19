<?php
declare(strict_types=1);

/**
 * Suspensiones por partidos (distintas de las multas, que viven en Models/Sancion.php).
 *
 * Reglas típicas de una liga:
 *   - Tarjeta roja  -> el jugador se pierde los próximos N partidos de su equipo.
 *   - Acumulación   -> cada X amarillas (4 en la mayoría de ligas) también cuesta partidos.
 *     El contador NO se reinicia por partido: se acumula a lo largo del torneo, y al llegar
 *     al múltiplo (4, 8, 12...) se dispara una suspensión nueva.
 *
 * El cálculo es DINÁMICO: no se guarda "suspendido hasta el partido X", se deduce cada vez
 * a partir de las tarjetas registradas. Así, si el organizador corrige la ficha de un
 * partido viejo (borra una tarjeta mal puesta), las suspensiones se recalculan solas sin
 * quedar registros fantasma.
 *
 * "Los próximos N partidos" se cuentan sobre el calendario del equipo en orden
 * cronológico, estén jugados o no: es como funciona en la práctica (te pierdes la
 * siguiente fecha), y no depende de que el organizador haya capturado los resultados.
 */

// Valores por defecto cuando la copa no los ha configurado.
const AMARILLAS_PARA_SUSPENSION_DEFECTO = 4;
const PARTIDOS_SUSPENSION_DEFECTO = 1;

/**
 * Cuántas amarillas acumuladas cuestan una suspensión (0 = la liga no suspende por
 * acumulación).
 */
function torneo_amarillas_para_suspension(array $torneo): int
{
    return max(0, (int) ($torneo['amarillas_para_suspension'] ?? 0));
}

/**
 * Partidos que se pierde el jugador por cada roja (0 = la liga no suspende por roja).
 */
function torneo_partidos_suspension_roja(array $torneo): int
{
    return max(0, (int) ($torneo['partidos_suspension_roja'] ?? 0));
}

/**
 * Partidos que se pierde al completar la acumulación de amarillas.
 */
function torneo_partidos_suspension_amarillas(array $torneo): int
{
    return max(0, (int) ($torneo['partidos_suspension_amarillas'] ?? 0));
}

/**
 * true si la copa aplica suspensiones por partidos de alguna de las dos formas.
 */
function torneo_aplica_suspensiones(array $torneo): bool
{
    return torneo_partidos_suspension_roja($torneo) > 0
        || (torneo_amarillas_para_suspension($torneo) > 0 && torneo_partidos_suspension_amarillas($torneo) > 0);
}

/**
 * Clave de orden cronológico de un partido, para saber cuál va "después" de cuál.
 */
function disciplina_orden_partido(array $partido): string
{
    return ($partido['fecha'] ?? '') . ' ' . ($partido['hora'] ?? '') . ' ' . str_pad((string) ($partido['id'] ?? 0), 8, '0', STR_PAD_LEFT);
}

/**
 * Calendario de un equipo ordenado cronológicamente (ids de partido).
 *
 * @param array $partidos Todos los partidos de la copa
 * @return array<int,int> posición => id de partido
 */
function disciplina_calendario_equipo(array $partidos, int $equipoId): array
{
    $suyos = array_values(array_filter(
        $partidos,
        fn($p) => (int) $p['equipo_local'] === $equipoId || (int) $p['equipo_visitante'] === $equipoId
    ));
    usort($suyos, fn($a, $b) => strcmp(disciplina_orden_partido($a), disciplina_orden_partido($b)));
    return array_map(fn($p) => (int) $p['id'], $suyos);
}

/**
 * Suspensiones que arrastra cada jugador, calculadas desde sus tarjetas.
 *
 * Devuelve, por jugador, la lista de "castigos": en qué partido se originó cada uno,
 * por qué motivo y cuántos partidos cubre.
 *
 * @return array<int,array<int,array{motivo:string,partido_id:int,partidos:int,detalle:string}>>
 */
function disciplina_castigos_por_jugador(int $torneoId, array $torneo, array $partidos, array $jugadoresPorId): array
{
    if (!torneo_aplica_suspensiones($torneo)) {
        return [];
    }

    $partidosPorId = [];
    foreach ($partidos as $p) {
        $partidosPorId[(int) $p['id']] = $p;
    }

    // Tarjetas de cada jugador, en orden cronológico del partido donde ocurrieron.
    $tarjetas = [];
    foreach (eventos_de_torneo($torneoId) as $ev) {
        $tipo = (string) ($ev['tipo'] ?? '');
        if (!in_array($tipo, ['amarilla', 'roja'], true)) {
            continue;
        }
        $jugadorId = (int) ($ev['jugador_id'] ?? 0);
        $partidoId = (int) ($ev['partido_id'] ?? 0);
        if ($jugadorId <= 0 || !isset($partidosPorId[$partidoId])) {
            continue;
        }
        $tarjetas[$jugadorId][] = [
            'tipo' => $tipo,
            'partido_id' => $partidoId,
            'orden' => disciplina_orden_partido($partidosPorId[$partidoId]),
        ];
    }

    $porRoja = torneo_partidos_suspension_roja($torneo);
    $cadaAmarillas = torneo_amarillas_para_suspension($torneo);
    $porAmarillas = torneo_partidos_suspension_amarillas($torneo);

    $castigos = [];
    foreach ($tarjetas as $jugadorId => $lista) {
        usort($lista, fn($a, $b) => strcmp($a['orden'], $b['orden']));

        $amarillasAcumuladas = 0;
        foreach ($lista as $t) {
            if ($t['tipo'] === 'roja' && $porRoja > 0) {
                $castigos[$jugadorId][] = [
                    'motivo' => 'roja',
                    'partido_id' => $t['partido_id'],
                    'partidos' => $porRoja,
                    'detalle' => 'Tarjeta roja',
                ];
                continue;
            }

            if ($t['tipo'] === 'amarilla' && $cadaAmarillas > 0 && $porAmarillas > 0) {
                $amarillasAcumuladas++;
                // Al llegar al múltiplo (4, 8, 12...) se dispara la suspensión.
                if ($amarillasAcumuladas % $cadaAmarillas === 0) {
                    $castigos[$jugadorId][] = [
                        'motivo' => 'amarillas',
                        'partido_id' => $t['partido_id'],
                        'partidos' => $porAmarillas,
                        'detalle' => $amarillasAcumuladas . ' amarillas acumuladas',
                    ];
                }
            }
        }
    }

    return $castigos;
}

/**
 * Jugadores suspendidos para un partido concreto.
 *
 * Un castigo originado en el partido Mo cubre los siguientes N partidos del equipo según
 * su calendario. Si el partido objetivo cae dentro de esa ventana, el jugador no puede
 * alinearse.
 *
 * @return array<int,array{motivo:string,detalle:string,restantes:int}> jugador_id => info
 */
function disciplina_suspendidos_para_partido(int $torneoId, array $partidoObjetivo, array $torneo, array $partidos, array $jugadoresPorId): array
{
    if (!torneo_aplica_suspensiones($torneo)) {
        return [];
    }

    $castigos = disciplina_castigos_por_jugador($torneoId, $torneo, $partidos, $jugadoresPorId);
    if (empty($castigos)) {
        return [];
    }

    $objetivoId = (int) $partidoObjetivo['id'];
    $suspendidos = [];

    foreach ($castigos as $jugadorId => $lista) {
        $jug = $jugadoresPorId[$jugadorId] ?? null;
        if ($jug === null) {
            continue;
        }
        $calendario = disciplina_calendario_equipo($partidos, (int) $jug['equipo_id']);
        $posObjetivo = array_search($objetivoId, $calendario, true);
        if ($posObjetivo === false) {
            continue;   // ese jugador no juega este partido
        }

        foreach ($lista as $castigo) {
            $posOrigen = array_search($castigo['partido_id'], $calendario, true);
            if ($posOrigen === false) {
                continue;
            }
            // Ventana de castigo: los N partidos siguientes al de la infracción.
            $desde = $posOrigen + 1;
            $hasta = $posOrigen + $castigo['partidos'];
            if ($posObjetivo >= $desde && $posObjetivo <= $hasta) {
                $suspendidos[$jugadorId] = [
                    'motivo' => $castigo['motivo'],
                    'detalle' => $castigo['detalle'],
                    'restantes' => $hasta - $posObjetivo + 1,
                ];
                break;   // basta un castigo vigente
            }
        }
    }

    return $suspendidos;
}

/**
 * Texto corto para mostrar en pantalla: "Suspendido por roja (1 partido)".
 */
function disciplina_texto_suspension(array $info): string
{
    $partidos = (int) ($info['restantes'] ?? 1);
    $plural = $partidos === 1 ? 'partido' : 'partidos';
    return $info['detalle'] . ' — no puede jugar ' . ($partidos === 1 ? 'este' : "los próximos {$partidos}") . ' ' . $plural;
}

/**
 * Tras registrar una tarjeta, indica si esta detonó una suspensión, para avisarlo en el
 * momento (que es cuando el organizador puede comunicárselo al equipo).
 *
 * @return string Mensaje listo para mostrar, o '' si esa tarjeta no suspende a nadie.
 */
function disciplina_aviso_por_tarjeta(int $torneoId, array $torneo, array $evento, array $jugadoresPorId): string
{
    if (!torneo_aplica_suspensiones($torneo)) {
        return '';
    }
    $tipo = (string) ($evento['tipo'] ?? '');
    $jugadorId = (int) ($evento['jugador_id'] ?? 0);
    $jug = $jugadoresPorId[$jugadorId] ?? null;
    if ($jug === null) {
        return '';
    }

    if ($tipo === 'roja' && torneo_partidos_suspension_roja($torneo) > 0) {
        $n = torneo_partidos_suspension_roja($torneo);
        return ' ' . jugador_nombre($jug) . ' queda suspendido ' . $n . ' partido' . ($n === 1 ? '' : 's') . ' por la roja.';
    }

    if ($tipo === 'amarilla' && torneo_amarillas_para_suspension($torneo) > 0 && torneo_partidos_suspension_amarillas($torneo) > 0) {
        // Cuenta cuántas amarillas lleva ya en el torneo (incluida esta).
        $total = 0;
        foreach (eventos_de_torneo($torneoId) as $ev) {
            if (($ev['tipo'] ?? '') === 'amarilla' && (int) ($ev['jugador_id'] ?? 0) === $jugadorId) {
                $total++;
            }
        }
        $cada = torneo_amarillas_para_suspension($torneo);
        if ($total > 0 && $total % $cada === 0) {
            $n = torneo_partidos_suspension_amarillas($torneo);
            return ' ' . jugador_nombre($jug) . ' llegó a ' . $total . ' amarillas y queda suspendido ' . $n . ' partido' . ($n === 1 ? '' : 's') . '.';
        }
    }

    return '';
}
