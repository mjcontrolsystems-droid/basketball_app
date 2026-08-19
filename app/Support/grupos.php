<?php
declare(strict_types=1);

/**
 * Fase de grupos estilo mundial.
 *
 * 16 equipos en 4 grupos de 4: dentro del grupo juegan todos contra todos, y los mejores
 * de cada grupo cruzan a eliminación directa. Es un formato mucho más corto que la liga
 * completa — 24 partidos de grupos contra los 120 de un todos contra todos de 16 — y por
 * eso sirve para torneos de pocas fechas.
 *
 * La regla que le da sentido al cruce es que el primero de un grupo se enfrenta al segundo
 * de OTRO grupo, y que los dos clasificados de un mismo grupo caen en mitades opuestas del
 * cuadro. Así dos equipos que ya se enfrentaron en la fase de grupos no se vuelven a ver
 * hasta la final, que es lo que hace interesante el sorteo.
 */

// FORMATO_GRUPOS se define en liga.php, junto a los otros formatos.

/**
 * Letras de los grupos: A, B, C... Se usan letras y no números porque es como se dice en
 * la cancha ("grupo A") y como se imprime en el calendario.
 *
 * @return array<int, string>
 */
function grupos_letras(int $cantidad): array
{
    $letras = [];
    for ($i = 0; $i < max(0, min(26, $cantidad)); $i++) {
        $letras[] = chr(65 + $i);
    }

    return $letras;
}

/**
 * Cuántos grupos tiene esta competencia (0 = no usa el formato de grupos).
 */
function torneo_num_grupos(array $torneo): int
{
    if (($torneo['modo'] ?? '') !== FORMATO_GRUPOS) {
        return 0;
    }

    return max(0, min(26, (int) ($torneo['num_grupos'] ?? 0)));
}

function torneo_tiene_grupos(array $torneo): bool
{
    return torneo_num_grupos($torneo) >= 2;
}

/**
 * Cuántos clasifican de cada grupo. Se acota al tamaño del grupo más chico para que no se
 * configure "pasan 3" en grupos de 2, que dejaría el cuadro con huecos.
 */
function torneo_clasifican_por_grupo(array $torneo, ?int $equiposEnElGrupoMasChico = null): int
{
    $c = max(1, (int) ($torneo['clasifican_por_grupo'] ?? 2));
    if ($equiposEnElGrupoMasChico !== null) {
        $c = min($c, max(1, $equiposEnElGrupoMasChico));
    }

    return $c;
}

/**
 * Agrupa los equipos por su letra de grupo.
 *
 * Los equipos sin grupo asignado quedan aparte, en la clave '', para que la pantalla pueda
 * avisar que falta sortearlos en vez de esconderlos.
 *
 * @return array<string, array> Letra => equipos, más '' con los que no tienen grupo.
 */
function equipos_por_grupo(array $equipos, int $numGrupos): array
{
    $salida = [];
    foreach (grupos_letras($numGrupos) as $l) {
        $salida[$l] = [];
    }
    $salida[''] = [];

    foreach ($equipos as $eq) {
        $g = strtoupper(trim((string) ($eq['grupo'] ?? '')));
        $salida[array_key_exists($g, $salida) ? $g : ''][] = $eq;
    }

    if (empty($salida[''])) {
        unset($salida['']);
    }

    return $salida;
}

/**
 * Sortea los equipos en los grupos.
 *
 * Las cabezas de serie se reparten primero, una por grupo, para que no caigan dos juntas
 * y no salga un grupo de la muerte con los cuatro mejores. El resto se sortea y se va
 * repartiendo en orden, lo que además deja todos los grupos del mismo tamaño (o con un
 * equipo de diferencia cuando el total no es múltiplo del número de grupos).
 *
 * @param int|null $semilla Para poder repetir un sorteo. null = sorteo nuevo de verdad.
 * @return array<int, string> equipoId => letra de grupo.
 */
function grupos_sortear(array $equipos, int $numGrupos, ?int $semilla = null): array
{
    $letras = grupos_letras($numGrupos);
    if (count($letras) < 2 || empty($equipos)) {
        return [];
    }

    $rnd = new \Random\Randomizer(
        $semilla === null ? new \Random\Engine\Secure() : new \Random\Engine\Mt19937($semilla)
    );

    $cabezas = [];
    $resto = [];
    foreach ($equipos as $eq) {
        if (!empty($eq['cabeza_serie'])) {
            $cabezas[] = $eq;
        } else {
            $resto[] = $eq;
        }
    }

    // Se barajan las dos bolsas por separado: así el sorteo es real pero las cabezas
    // siguen quedando repartidas una por grupo.
    $cabezas = $rnd->shuffleArray($cabezas);
    $resto = $rnd->shuffleArray($resto);

    // Si hay más cabezas que grupos, las que sobran vuelven a la bolsa común: no se puede
    // garantizar una por grupo y es preferible eso a inventarse una regla rara.
    if (count($cabezas) > count($letras)) {
        $resto = array_merge(array_slice($cabezas, count($letras)), $resto);
        $cabezas = array_slice($cabezas, 0, count($letras));
        $resto = $rnd->shuffleArray($resto);
    }

    $asignacion = [];
    foreach ($cabezas as $i => $eq) {
        $asignacion[(int) $eq['id']] = $letras[$i];
    }

    // El resto empieza a repartirse por el grupo que quedó con menos equipos, para que los
    // grupos terminen parejos aunque haya menos cabezas que grupos.
    $conteo = array_fill_keys($letras, 0);
    foreach ($asignacion as $l) {
        $conteo[$l]++;
    }

    foreach ($resto as $eq) {
        asort($conteo);
        $destino = array_key_first($conteo);
        $asignacion[(int) $eq['id']] = $destino;
        $conteo[$destino]++;
    }

    return $asignacion;
}

/**
 * Rondas de la fase de grupos, listas para el generador de calendario.
 *
 * Cada grupo juega su propio todos contra todos, y las rondas de todos los grupos se
 * juntan: la ronda 1 del calendario trae la ronda 1 del grupo A, la del B, la del C y la
 * del D. Así una jornada tiene partidos de todos los grupos, que es como se ve un mundial,
 * en vez de despachar un grupo entero antes de empezar el siguiente.
 *
 * @return array<int, array<int, array{0:int,1:int}>>
 */
function grupos_rondas(array $equipos, int $numGrupos, int $vueltas = 1): array
{
    $porGrupo = equipos_por_grupo($equipos, $numGrupos);
    unset($porGrupo['']);

    $rondasPorGrupo = [];
    $maximo = 0;
    foreach ($porGrupo as $lista) {
        if (count($lista) < 2) {
            continue;
        }
        $ids = array_map(fn($e) => (int) $e['id'], $lista);
        $rondas = generar_fixture_round_robin($ids, $vueltas);
        $rondasPorGrupo[] = $rondas;
        $maximo = max($maximo, count($rondas));
    }

    $combinadas = [];
    for ($r = 0; $r < $maximo; $r++) {
        $ronda = [];
        foreach ($rondasPorGrupo as $rondas) {
            foreach ($rondas[$r] ?? [] as $cruce) {
                $ronda[] = $cruce;
            }
        }
        if (!empty($ronda)) {
            $combinadas[] = $ronda;
        }
    }

    return $combinadas;
}

/**
 * Tabla de posiciones de cada grupo, marcando quién clasifica.
 *
 * @return array<string, array{tabla: array, clasifican: int}>
 */
function grupos_tablas(array $equipos, array $partidos, array $torneo, array $eventos = []): array
{
    $numGrupos = torneo_num_grupos($torneo);
    $porGrupo = equipos_por_grupo($equipos, $numGrupos);
    unset($porGrupo['']);

    $minimo = PHP_INT_MAX;
    foreach ($porGrupo as $lista) {
        if (!empty($lista)) {
            $minimo = min($minimo, count($lista));
        }
    }
    $clasifican = torneo_clasifican_por_grupo($torneo, $minimo === PHP_INT_MAX ? null : $minimo);

    $salida = [];
    foreach ($porGrupo as $letra => $lista) {
        // Se le pasan solo los equipos del grupo: calcular_tabla ignora los partidos de
        // equipos que no están en la lista, así que la tabla sale con los cruces internos.
        // El propio $torneo hace de reglas: ya trae permite_empates y los puntos por
        // resultado, que es todo lo que calcular_tabla necesita.
        $salida[$letra] = [
            'tabla' => calcular_tabla($lista, $partidos, $torneo, $eventos),
            'clasifican' => $clasifican,
        ];
    }

    return $salida;
}

/**
 * Arma los cruces de la primera ronda de eliminación a partir de las tablas de grupo.
 *
 * El emparejamiento cruza grupos de dos en dos: en la pareja (A, B) salen 1°A contra 2°B y
 * 1°B contra 2°A, y esos dos partidos se mandan a mitades OPUESTAS del cuadro. Por eso el
 * orden de la lista importa: la primera mitad de los cruces alimenta una semifinal y la
 * segunda mitad la otra, así que dos equipos del mismo grupo solo se pueden reencontrar en
 * la final.
 *
 * @param array<string, array> $tablas Salida de grupos_tablas().
 * @return array{fase: string, cruces: array<int, array>, avisos: array<int, string>}
 */
function grupos_cruces_eliminacion(array $tablas, int $clasifican): array
{
    $letras = array_keys($tablas);
    sort($letras);
    $avisos = [];

    // Posición N del grupo X, o null si el grupo no tiene tantos equipos.
    $clasificado = function (string $letra, int $posicion) use ($tablas, &$avisos) {
        $tabla = $tablas[$letra]['tabla'] ?? [];
        if (!isset($tabla[$posicion - 1])) {
            $avisos[] = "El grupo {$letra} no tiene un {$posicion}° lugar.";
            return null;
        }
        return [
            'equipo' => $tabla[$posicion - 1]['equipo'],
            'etiqueta' => $posicion . '° ' . $letra,
        ];
    };

    $primeraMitad = [];
    $segundaMitad = [];

    if ($clasifican >= 2) {
        // Grupos de dos en dos. Si el número de grupos es impar, el último se empareja
        // consigo mismo: su 1° contra su 2°, que no es ideal pero es mejor que dejarlo
        // fuera del cuadro.
        for ($i = 0; $i < count($letras); $i += 2) {
            $x = $letras[$i];
            $y = $letras[$i + 1] ?? $letras[$i];
            if ($x === $y) {
                $avisos[] = "Como hay un número impar de grupos, el grupo {$x} cruza consigo mismo.";
            }
            $primeraMitad[] = [$clasificado($x, 1), $clasificado($y, 2)];
            $segundaMitad[] = [$clasificado($y, 1), $clasificado($x, 2)];
        }
    } else {
        // Solo pasa el primero: no hay riesgo de reencuentro, así que se cruza el primer
        // grupo con el último, el segundo con el penúltimo, como un cuadro sembrado.
        $izq = 0;
        $der = count($letras) - 1;
        $turno = 0;
        while ($izq < $der) {
            $par = [$clasificado($letras[$izq], 1), $clasificado($letras[$der], 1)];
            if ($turno % 2 === 0) {
                $primeraMitad[] = $par;
            } else {
                $segundaMitad[] = $par;
            }
            $izq++;
            $der--;
            $turno++;
        }
    }

    $cruces = [];
    foreach (array_merge($primeraMitad, $segundaMitad) as $par) {
        if ($par[0] === null || $par[1] === null) {
            continue;
        }
        $cruces[] = [
            'local' => $par[0]['equipo'],
            'visitante' => $par[1]['equipo'],
            'etiqueta' => $par[0]['etiqueta'] . ' vs ' . $par[1]['etiqueta'],
        ];
    }

    return [
        'fase' => grupos_fase_para_cantidad(count($cruces) * 2),
        'cruces' => $cruces,
        'avisos' => array_values(array_unique($avisos)),
    ];
}

/**
 * Qué fase le toca a una ronda con esta cantidad de equipos. 8 equipos son cuartos, 4 son
 * semifinales, 2 la final.
 */
function grupos_fase_para_cantidad(int $equipos): string
{
    return match (true) {
        $equipos >= 32 => 'dieciseisavos',
        $equipos >= 16 => 'octavos',
        $equipos >= 8 => 'cuartos',
        $equipos >= 4 => 'semifinal',
        default => 'final',
    };
}

/**
 * Aviso si la combinación de grupos y clasificados no arma un cuadro limpio.
 *
 * Un cuadro de eliminación necesita una potencia de 2 (4, 8, 16...). Con 5 grupos y 2
 * clasificados salen 10 equipos, que no cabe en ningún cuadro sin repechajes o descansos
 * inventados. Se avisa antes de generar nada en vez de armar un cruce raro y que el
 * organizador lo descubra el día del partido.
 *
 * @return string Vacío si la configuración está bien.
 */
function grupos_aviso_cuadro(int $numGrupos, int $clasifican): string
{
    $total = $numGrupos * $clasifican;
    if ($total < 2) {
        return 'Con esta configuración no clasifica nadie a la eliminación.';
    }
    if (($total & ($total - 1)) !== 0) {
        $sugerencias = [];
        foreach ([4, 8, 16, 32] as $potencia) {
            if ($numGrupos > 0 && $potencia % $numGrupos === 0) {
                $sugerencias[] = intdiv($potencia, $numGrupos) . ' por grupo (cuadro de ' . $potencia . ')';
            }
        }

        return "Con {$numGrupos} grupos y {$clasifican} clasificados salen {$total} equipos, y un cuadro de eliminación necesita 4, 8, 16 o 32."
            . ($sugerencias ? ' Te cuadraría: ' . implode(', o ', $sugerencias) . '.' : ' Cambia el número de grupos o cuántos pasan.');
    }

    return '';
}

/**
 * Cuántos partidos tiene la fase de grupos con esta configuración, para avisarlo antes de
 * generar nada.
 *
 * @return array{partidos: int, rondas: int, por_grupo: array<string, int>}
 */
function grupos_resumen(array $equipos, int $numGrupos, int $vueltas = 1): array
{
    $porGrupo = equipos_por_grupo($equipos, $numGrupos);
    unset($porGrupo['']);

    $total = 0;
    $rondas = 0;
    $detalle = [];
    foreach ($porGrupo as $letra => $lista) {
        $n = count($lista);
        $partidos = $n < 2 ? 0 : (int) ($n * ($n - 1) / 2) * max(1, $vueltas);
        $detalle[$letra] = $n;
        $total += $partidos;
        if ($n >= 2) {
            $rondas = max($rondas, ($n % 2 === 0 ? $n - 1 : $n) * max(1, $vueltas));
        }
    }

    return ['partidos' => $total, 'rondas' => $rondas, 'por_grupo' => $detalle];
}
