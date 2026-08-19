<?php
declare(strict_types=1);

/**
 * El cuadro de eliminación directa: cómo se arma y cómo avanza.
 *
 * Sirve para los dos formatos que llegan a una fase final:
 *   - liga con fase final: los mejores de la tabla única entran al cuadro;
 *   - grupos + eliminación: los cruces salen de las tablas de grupo (ver grupos.php).
 *
 * A partir de ahí el avance es el mismo: los ganadores de una ronda arman la siguiente.
 * Nada se crea solo al cargar un resultado — cada ronda se genera con un botón, para que
 * el organizador pueda revisar los cruces antes de publicarlos y para que corregir un
 * marcador equivocado no dispare partidos fantasma.
 */

/**
 * Orden de siembra de un cuadro de N equipos.
 *
 * Devuelve las posiciones de la tabla en el orden en que se sientan en el cuadro, de forma
 * que los emparejamientos son las PAREJAS CONSECUTIVAS de la lista. Con 8 equipos da
 * [1, 8, 4, 5, 2, 7, 3, 6], o sea 1-8, 4-5, 2-7 y 3-6.
 *
 * No es un capricho: así el primero y el segundo de la tabla caen en mitades opuestas y
 * solo se pueden encontrar en la final, y cada equipo enfrenta al rival peor clasificado
 * que le corresponde. Es la siembra que usan todos los cuadros de verdad.
 *
 * @return array<int, int> Posiciones (1 = primero de la tabla).
 */
function eliminacion_orden_siembra(int $equipos): array
{
    if ($equipos < 2) {
        return [1];
    }

    $orden = [1, 2];
    while (count($orden) < $equipos) {
        $tamano = count($orden) * 2;
        $nuevo = [];
        foreach ($orden as $pos) {
            // Cada posición se "abre" en dos: ella misma y su complementaria del cuadro
            // del doble de tamaño. Es la construcción recursiva de toda siembra estándar.
            $nuevo[] = $pos;
            $nuevo[] = $tamano + 1 - $pos;
        }
        $orden = $nuevo;
    }

    return array_slice($orden, 0, $equipos);
}

/**
 * Cruces de la primera ronda de eliminación a partir de una tabla única.
 *
 * @param array $tabla Salida de calcular_tabla(), ya ordenada.
 * @param int $clasifican Cuántos entran al cuadro.
 * @return array{fase: string, cruces: array<int, array>, avisos: array<int, string>}
 */
function eliminacion_cruces_desde_tabla(array $tabla, int $clasifican): array
{
    $avisos = [];
    $clasifican = max(2, $clasifican);

    if (count($tabla) < $clasifican) {
        $avisos[] = 'Solo hay ' . count($tabla) . ' equipos en la tabla, así que el cuadro se arma con esos.';
        $clasifican = count($tabla);
    }

    // Un cuadro necesita una potencia de 2. Si el número de clasificados no lo es, se baja
    // a la potencia de 2 inmediatamente inferior en vez de inventar descansos.
    $potencia = 2;
    while ($potencia * 2 <= $clasifican) {
        $potencia *= 2;
    }
    if ($potencia !== $clasifican) {
        $avisos[] = "Con {$clasifican} clasificados no sale un cuadro parejo, así que entran los primeros {$potencia}.";
        $clasifican = $potencia;
    }

    if ($clasifican < 2) {
        return ['fase' => 'final', 'cruces' => [], 'avisos' => ['Hacen falta al menos 2 equipos para armar el cuadro.']];
    }

    $siembra = eliminacion_orden_siembra($clasifican);
    $cruces = [];
    for ($i = 0; $i < count($siembra); $i += 2) {
        $posLocal = $siembra[$i];
        $posVisitante = $siembra[$i + 1] ?? null;
        if ($posVisitante === null || !isset($tabla[$posLocal - 1], $tabla[$posVisitante - 1])) {
            continue;
        }
        $cruces[] = [
            'local' => $tabla[$posLocal - 1]['equipo'],
            'visitante' => $tabla[$posVisitante - 1]['equipo'],
            'etiqueta' => $posLocal . '° vs ' . $posVisitante . '° de la tabla',
        ];
    }

    return [
        'fase' => grupos_fase_para_cantidad($clasifican),
        'cruces' => $cruces,
        'avisos' => $avisos,
    ];
}

/**
 * Fases de eliminación de esta competencia, en el orden en que se juegan.
 *
 * @return array<int, string>
 */
function eliminacion_fases_ordenadas(array $torneo): array
{
    $fases = torneo_fases_playoff($torneo);

    return array_values(array_filter(FASES_PLAYOFF_CATALOGO, fn($f) => in_array($f, $fases, true)));
}

/**
 * Resultado de una ronda: quién ganó, quién perdió y qué falta.
 *
 * El tercer lugar no cuenta como ronda del cuadro: nadie avanza de ahí.
 *
 * @return array{ganadores: array, perdedores: array, pendientes: int, empatados: array}
 */
function eliminacion_resultado_ronda(array $partidos, string $fase, array $equiposPorId): array
{
    $deLaFase = array_values(array_filter($partidos, fn($p) => ($p['fase'] ?? '') === $fase));
    // Por fecha y hora: el orden importa porque los ganadores se emparejan de dos en dos
    // según cómo quedó armado el cuadro.
    usort($deLaFase, fn($a, $b) => strcmp(
        ($a['fecha'] ?? '') . ($a['hora'] ?? '') . str_pad((string) ($a['id'] ?? 0), 8, '0', STR_PAD_LEFT),
        ($b['fecha'] ?? '') . ($b['hora'] ?? '') . str_pad((string) ($b['id'] ?? 0), 8, '0', STR_PAD_LEFT)
    ));

    $ganadores = [];
    $perdedores = [];
    $pendientes = 0;
    $empatados = [];

    foreach ($deLaFase as $p) {
        if (($p['estado'] ?? '') !== 'jugado') {
            $pendientes++;
            continue;
        }
        $ml = (int) ($p['marcador_local'] ?? 0);
        $mv = (int) ($p['marcador_visitante'] ?? 0);
        $local = $equiposPorId[(int) $p['equipo_local']] ?? null;
        $visitante = $equiposPorId[(int) $p['equipo_visitante']] ?? null;
        if ($local === null || $visitante === null) {
            continue;
        }

        if ($ml === $mv) {
            // En eliminación no puede haber empate: alguien tiene que pasar. La app no
            // inventa un ganador (ni por penales ni por diferencia): lo dice y espera.
            $empatados[] = $local['nombre'] . ' vs ' . $visitante['nombre'];
            continue;
        }

        $ganadores[] = $ml > $mv ? $local : $visitante;
        $perdedores[] = $ml > $mv ? $visitante : $local;
    }

    return [
        'ganadores' => $ganadores,
        'perdedores' => $perdedores,
        'pendientes' => $pendientes,
        'empatados' => $empatados,
    ];
}

/**
 * Empareja una lista de equipos de dos en dos, en el orden en que viene.
 *
 * El orden ya trae la información del cuadro: los ganadores del primer y segundo partido
 * de una ronda se enfrentan en la siguiente. Por eso NO se reordena por posición ni por
 * nada más — reordenar aquí rompería la siembra que se armó al crear la primera ronda.
 *
 * @return array<int, array{local: array, visitante: array, etiqueta: string}>
 */
function eliminacion_emparejar(array $equipos, string $etiqueta = ''): array
{
    $cruces = [];
    for ($i = 0; $i + 1 < count($equipos); $i += 2) {
        $cruces[] = [
            'local' => $equipos[$i],
            'visitante' => $equipos[$i + 1],
            'etiqueta' => $etiqueta,
        ];
    }

    return $cruces;
}

/**
 * Fila de encuentro lista para guardar, con las columnas del cronómetro que la base exige.
 */
function eliminacion_fila_partido(int $id, array $local, array $visitante, string $fase, string $fecha, string $etiqueta): array
{
    return [
        'id' => $id,
        'jornada' => 1,
        'equipo_local' => (int) $local['id'],
        'equipo_visitante' => (int) $visitante['id'],
        'fecha' => $fecha,
        'hora' => '',
        'cancha' => '',
        'estado' => 'programado',
        'marcador_local' => null,
        'marcador_visitante' => null,
        'fase' => $fase,
        'arbitro' => '',
        'observaciones' => $etiqueta,
        'cronometro_estado' => 'detenido',
        'cronometro_inicio' => null,
        'cronometro_segundos' => 0,
        'cronometro_periodo' => 1,
        'cronometro_extra_min' => 0,
    ];
}

/**
 * Qué ronda se puede armar ahora mismo, para saber qué botón ofrecerle al organizador.
 *
 * Recorre las fases en orden y devuelve la primera que todavía no tiene encuentros. Si esa
 * fase depende de una anterior, informa si la anterior ya terminó.
 *
 * @return array{fase: string|null, label: string, origen: string|null, listo: bool, motivo: string}
 */
function eliminacion_siguiente_paso(array $torneo, array $partidos, array $equiposPorId): array
{
    $fases = eliminacion_fases_ordenadas($torneo);
    if (empty($fases)) {
        return ['fase' => null, 'label' => '', 'origen' => null, 'listo' => false, 'motivo' => 'Esta competencia no tiene fase final configurada.'];
    }

    $conteoPorFase = [];
    foreach ($partidos as $p) {
        $f = (string) ($p['fase'] ?? 'grupos');
        $conteoPorFase[$f] = ($conteoPorFase[$f] ?? 0) + 1;
    }

    foreach ($fases as $i => $fase) {
        if (!empty($conteoPorFase[$fase])) {
            continue; // esa ronda ya está creada
        }

        $label = FASES_LABEL[$fase] ?? $fase;

        // El tercer lugar sale de los PERDEDORES de la semifinal, no de los ganadores.
        $faseOrigen = $fase === 'tercer_lugar' ? 'semifinal' : ($fases[$i - 1] ?? null);

        if ($faseOrigen === null || empty($conteoPorFase[$faseOrigen])) {
            // Primera ronda del cuadro: sale de la tabla (o de las tablas de grupo).
            $pendientesRegular = count(array_filter(
                $partidos,
                fn($p) => ($p['fase'] ?? 'grupos') === 'grupos' && ($p['estado'] ?? '') !== 'jugado'
            ));

            return [
                'fase' => $fase,
                'label' => $label,
                'origen' => null,
                'listo' => $pendientesRegular === 0,
                'motivo' => $pendientesRegular === 0
                    ? ''
                    : "Faltan {$pendientesRegular} encuentros de la temporada regular por jugar. Las posiciones todavía pueden cambiar.",
            ];
        }

        $resultado = eliminacion_resultado_ronda($partidos, $faseOrigen, $equiposPorId);
        $labelOrigen = FASES_LABEL[$faseOrigen] ?? $faseOrigen;

        if (!empty($resultado['empatados'])) {
            return [
                'fase' => $fase,
                'label' => $label,
                'origen' => $faseOrigen,
                'listo' => false,
                'motivo' => 'Hay ' . count($resultado['empatados']) . ' encuentro(s) de ' . mb_strtolower($labelOrigen)
                    . ' que terminaron empatados (' . implode(', ', $resultado['empatados']) . '). En eliminación alguien tiene que pasar: define el ganador antes de seguir.',
            ];
        }

        return [
            'fase' => $fase,
            'label' => $label,
            'origen' => $faseOrigen,
            'listo' => $resultado['pendientes'] === 0,
            'motivo' => $resultado['pendientes'] === 0
                ? ''
                : "Faltan {$resultado['pendientes']} encuentro(s) de " . mb_strtolower($labelOrigen) . ' por jugar.',
        ];
    }

    return ['fase' => null, 'label' => '', 'origen' => null, 'listo' => false, 'motivo' => 'El cuadro ya está completo.'];
}
