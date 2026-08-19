<?php
declare(strict_types=1);

/**
 * El podio: campeón, subcampeón y tercer lugar al cerrar la temporada.
 *
 * De dónde salen los tres depende del formato, y no es lo mismo:
 *   - Liga: los tres primeros de la tabla, que es donde se define el título.
 *   - Liga con fase final y grupos + eliminación: el campeón es quien gana la FINAL, no
 *     quien terminó primero en la tabla. Es el error clásico de un sistema de torneos
 *     mostrar al líder de la tabla como campeón cuando después hubo playoffs.
 *
 * El podio se calcula en vivo cada vez, no se guarda: si el organizador corrige el
 * marcador de la final, el campeón cambia solo. Lo único que se guarda es si ya está
 * publicado o no.
 */

/**
 * Campeón, subcampeón y tercer lugar, o null si todavía no se pueden determinar.
 *
 * @return array{campeon: array, subcampeon: array|null, tercero: array|null, origen: string}|null
 */
function podio_calcular(array $torneo, array $equipos, array $partidos, array $eventos = []): ?array
{
    $fases = eliminacion_fases_ordenadas($torneo);
    $equiposPorId = [];
    foreach ($equipos as $eq) {
        $equiposPorId[(int) $eq['id']] = $eq;
    }

    // --- Formatos con fase final: manda la final, no la tabla ---
    if (!empty($fases) && in_array('final', $fases, true)) {
        $final = podio_partido_decisivo($partidos, 'final');
        if ($final === null) {
            return null; // la final no se ha jugado: todavía no hay campeón
        }

        [$ganador, $perdedor] = podio_ganador_y_perdedor($final, $equiposPorId);
        if ($ganador === null) {
            return null; // final empatada y sin definir
        }

        return [
            'campeon' => $ganador,
            'subcampeon' => $perdedor,
            'tercero' => podio_tercer_lugar($torneo, $equipos, $partidos, $equiposPorId, $eventos),
            'origen' => 'final',
        ];
    }

    // --- Liga: los tres primeros de la tabla ---
    $tabla = calcular_tabla($equipos, $partidos, $torneo, $eventos);
    if (empty($tabla)) {
        return null;
    }
    // Sin ningún partido jugado la tabla existe pero está toda en cero: no hay campeón.
    $hayJugados = false;
    foreach ($partidos as $p) {
        if (($p['estado'] ?? '') === 'jugado') {
            $hayJugados = true;
            break;
        }
    }
    if (!$hayJugados) {
        return null;
    }

    return [
        'campeon' => $tabla[0]['equipo'],
        'subcampeon' => $tabla[1]['equipo'] ?? null,
        'tercero' => $tabla[2]['equipo'] ?? null,
        'origen' => 'tabla',
    ];
}

/**
 * El último encuentro jugado de una fase (normalmente el único).
 */
function podio_partido_decisivo(array $partidos, string $fase): ?array
{
    $deLaFase = array_values(array_filter(
        $partidos,
        fn($p) => ($p['fase'] ?? '') === $fase && ($p['estado'] ?? '') === 'jugado'
    ));
    if (empty($deLaFase)) {
        return null;
    }
    usort($deLaFase, fn($a, $b) => strcmp(($a['fecha'] ?? '') . ($a['hora'] ?? ''), ($b['fecha'] ?? '') . ($b['hora'] ?? '')));

    return end($deLaFase) ?: null;
}

/**
 * @return array{0: array|null, 1: array|null} [ganador, perdedor]. Ganador null si empataron.
 */
function podio_ganador_y_perdedor(array $partido, array $equiposPorId): array
{
    $local = $equiposPorId[(int) ($partido['equipo_local'] ?? 0)] ?? null;
    $visitante = $equiposPorId[(int) ($partido['equipo_visitante'] ?? 0)] ?? null;
    if ($local === null || $visitante === null) {
        return [null, null];
    }

    $ml = (int) ($partido['marcador_local'] ?? 0);
    $mv = (int) ($partido['marcador_visitante'] ?? 0);
    if ($ml === $mv) {
        return [null, null];
    }

    return $ml > $mv ? [$local, $visitante] : [$visitante, $local];
}

/**
 * El tercer lugar en un formato con eliminación.
 *
 * Si se jugó el partido por el tercer puesto, es su ganador y punto. Si la copa no lo
 * juega, se toma al mejor de los dos que perdieron la semifinal según la tabla de la
 * temporada regular — es un criterio, no un invento: es el mismo desempate que ya usa la
 * tabla y evita dejar el podio a medias.
 */
function podio_tercer_lugar(array $torneo, array $equipos, array $partidos, array $equiposPorId, array $eventos = []): ?array
{
    $tercerLugar = podio_partido_decisivo($partidos, 'tercer_lugar');
    if ($tercerLugar !== null) {
        [$ganador] = podio_ganador_y_perdedor($tercerLugar, $equiposPorId);
        if ($ganador !== null) {
            return $ganador;
        }
    }

    // Sin partido por el tercer puesto: los perdedores de la semifinal.
    $perdedoresSemi = [];
    foreach ($partidos as $p) {
        if (($p['fase'] ?? '') !== 'semifinal' || ($p['estado'] ?? '') !== 'jugado') {
            continue;
        }
        [, $perdedor] = podio_ganador_y_perdedor($p, $equiposPorId);
        if ($perdedor !== null) {
            $perdedoresSemi[(int) $perdedor['id']] = $perdedor;
        }
    }
    if (empty($perdedoresSemi)) {
        return null;
    }
    if (count($perdedoresSemi) === 1) {
        return reset($perdedoresSemi);
    }

    // El mejor de los dos según la tabla de la temporada regular.
    foreach (calcular_tabla($equipos, $partidos, $torneo, $eventos) as $fila) {
        $id = (int) $fila['equipo']['id'];
        if (isset($perdedoresSemi[$id])) {
            return $perdedoresSemi[$id];
        }
    }

    return reset($perdedoresSemi);
}

/**
 * ¿Ya se jugó todo lo que hay programado?
 *
 * Es la señal para avisarle al organizador que puede cerrar la temporada. No basta con
 * que exista un campeón: mientras queden partidos pendientes, publicar el podio sería
 * adelantarse.
 */
function podio_temporada_terminada(array $partidos): bool
{
    if (empty($partidos)) {
        return false;
    }
    foreach ($partidos as $p) {
        if (($p['estado'] ?? '') !== 'jugado') {
            return false;
        }
    }

    return true;
}

function torneo_podio_publicado(array $torneo): bool
{
    return !empty($torneo['podio_publicado']);
}

/**
 * En qué lugar del podio quedó un equipo, o 0 si no está.
 */
function podio_posicion_de(array $podio, int $equipoId): int
{
    foreach (['campeon' => 1, 'subcampeon' => 2, 'tercero' => 3] as $clave => $puesto) {
        if (!empty($podio[$clave]) && (int) $podio[$clave]['id'] === $equipoId) {
            return $puesto;
        }
    }

    return 0;
}

/**
 * Nombre del puesto, para textos y reportes.
 */
function podio_titulo(int $puesto, ?string $genero = null): string
{
    return match ($puesto) {
        1 => forma_genero($genero, 'Campeón', 'Campeona'),
        2 => forma_genero($genero, 'Subcampeón', 'Subcampeona'),
        3 => 'Tercer lugar',
        default => '',
    };
}
