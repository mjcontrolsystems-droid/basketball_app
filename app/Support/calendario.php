<?php
declare(strict_types=1);

/**
 * Armado del calendario a partir de los días que se juegan.
 *
 * El generador anterior repartía una ronda por jornada y una jornada por fecha: con 16
 * equipos salían 8 partidos el mismo día, que es imposible si solo tienes cuatro canchas
 * y un sábado. Este arma el calendario al revés, desde la realidad de la cancha: el
 * organizador dice qué días juega y cuántos partidos caben en cada uno, y de ahí salen
 * las jornadas, las fechas, las horas y las canchas.
 *
 * El caso que motivó todo esto: sábado 4 partidos y domingo 5, o sea 9 por fin de semana,
 * cuando una ronda de 16 equipos son 8. Ese noveno cupo se llena ADELANTANDO un partido
 * de las últimas rondas del torneo.
 *
 * Por qué de las ÚLTIMAS rondas y no de cualquier lado: cada ronda del round-robin es un
 * emparejamiento perfecto, o sea que cubre a los 16 equipos exactamente una vez. Si los
 * adelantos de las primeras 8 jornadas salen todos de la última ronda, esos 8 partidos
 * tocan a los 16 equipos una sola vez cada uno — el reparto sale parejo solo, sin llevar
 * ninguna cuenta. Y como los dos equipos del partido adelantado ya juegan en la ronda de
 * esa jornada, siempre se les puede poner su partido normal el primer día y el adelantado
 * el último, que es justo lo que se necesita.
 */

/**
 * Días de la semana, indexados como los devuelve date('w'): 0 = domingo.
 */
const CALENDARIO_DIAS = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
    0 => 'Domingo',
];

/**
 * Tope de semanas que se pueden saltar seguidas buscando una fecha libre. Sin esto, una
 * lista de fechas excluidas mal cargada dejaría el generador dando vueltas para siempre.
 */
const CALENDARIO_MAX_SALTOS = 52;

/**
 * Reparte el fixture en jornadas que llenen el cupo de cada fin de semana.
 *
 * @param array<int> $equipoIds
 * @param int $vueltas 1 = solo ida, 2 = ida y vuelta.
 * @param int $cupoPorJornada Partidos que caben en total en un fin de semana.
 * @param int|null $semilla Para el sorteo de los adelantados. null = aleatorio de verdad.
 * @return array<int, array{principal: array, adelantados: array}>
 */
function calendario_plan_jornadas(array $equipoIds, int $vueltas, int $cupoPorJornada, ?int $semilla = null, ?array $rondasPrearmadas = null): array
{
    // La fase de grupos manda sus propias rondas ya armadas (el todos contra todos de cada
    // grupo, mezclados para que una jornada tenga partidos de todos los grupos). El resto
    // del reparto — cupos por día, adelantados, fechas — funciona igual.
    $rondas = $rondasPrearmadas !== null ? array_values(array_filter($rondasPrearmadas)) : generar_fixture_round_robin($equipoIds, $vueltas);
    if (empty($rondas)) {
        return [];
    }

    // El sorteo: se baraja el orden DENTRO de cada ronda de la que se va a robar. El
    // conjunto de equipos que termina doblando es el mismo (por eso el reparto es parejo),
    // lo que cambia es a quién le toca en qué jornada. Así es un sorteo de verdad y no
    // siempre le toca al mismo equipo la jornada 1.
    $mt = new \Random\Randomizer(
        $semilla === null ? new \Random\Engine\Secure() : new \Random\Engine\Mt19937($semilla)
    );
    foreach ($rondas as $i => $r) {
        $rondas[$i] = $mt->shuffleArray($r);
    }

    $jornadas = [];
    $totalRondas = count($rondas);

    while (true) {
        $vecesPorEquipo = [];
        $principal = [];

        // --- Fase 1: llenar la jornada sin que nadie repita ---
        // Se toma de las rondas en orden. Como cada ronda cubre a todos los equipos, en
        // cuanto se agota la primera ya nadie tiene el cupo libre y la fase se detiene
        // sola. Si el cupo del fin de semana es MENOR que una ronda, la ronda queda a
        // medias y su resto abre la jornada siguiente: ahí simplemente descansan los
        // equipos que no cupieron, que es lo que pasa en una liga con pocas canchas.
        for ($i = 0; $i < $totalRondas && count($principal) < $cupoPorJornada; $i++) {
            foreach ($rondas[$i] as $pos => $cruce) {
                if (count($principal) >= $cupoPorJornada) {
                    break;
                }
                if (($vecesPorEquipo[$cruce[0]] ?? 0) !== 0 || ($vecesPorEquipo[$cruce[1]] ?? 0) !== 0) {
                    continue;
                }
                $principal[] = $cruce;
                $vecesPorEquipo[$cruce[0]] = 1;
                $vecesPorEquipo[$cruce[1]] = 1;
                unset($rondas[$i][$pos]);
            }
            $rondas[$i] = array_values($rondas[$i]);
        }

        // --- Fase 2: los adelantados ---
        // Los cupos que sobran se llenan con cruces de las ÚLTIMAS rondas. Solo sirve uno
        // cuyos DOS equipos jueguen exactamente una vez hoy: si alguno ya va dos veces, un
        // tercer partido en el mismo fin de semana es abuso; si va cero es que descansa.
        $adelantados = [];
        for ($i = $totalRondas - 1; $i >= 0 && count($principal) + count($adelantados) < $cupoPorJornada; $i--) {
            foreach ($rondas[$i] as $pos => $cruce) {
                if (count($principal) + count($adelantados) >= $cupoPorJornada) {
                    break;
                }
                if (($vecesPorEquipo[$cruce[0]] ?? 0) !== 1 || ($vecesPorEquipo[$cruce[1]] ?? 0) !== 1) {
                    continue;
                }
                $adelantados[] = $cruce;
                $vecesPorEquipo[$cruce[0]] = 2;
                $vecesPorEquipo[$cruce[1]] = 2;
                unset($rondas[$i][$pos]);
            }
            $rondas[$i] = array_values($rondas[$i]);
        }

        if (empty($principal) && empty($adelantados)) {
            break; // ya no queda nada por programar
        }
        $jornadas[] = ['principal' => $principal, 'adelantados' => $adelantados];
    }

    return $jornadas;
}

/**
 * Reparte los partidos de una jornada entre los días que se juegan.
 *
 * El orden importa y no es caprichoso:
 *   1. Los adelantados van al ÚLTIMO día. Son el segundo partido de esos equipos, así que
 *      tienen que ir después, no antes.
 *   2. El partido normal de esos mismos equipos va al PRIMER día, para que les quede la
 *      noche de por medio y no jueguen dos veces el mismo día.
 *   3. El resto rellena los cupos que quedan.
 *
 * @param array $principal Cruces de la ronda de esta jornada.
 * @param array $adelantados Cruces traídos de rondas posteriores.
 * @param array<int> $cupos Partidos que caben cada día, en orden.
 * @return array<int, array<int, array{0:int,1:int,2:bool}>> Por día: [local, visitante, esAdelantado].
 */
function calendario_repartir_en_dias(array $principal, array $adelantados, array $cupos): array
{
    $dias = array_fill(0, max(1, count($cupos)), []);
    $ultimo = count($dias) - 1;

    $doblan = [];
    foreach ($adelantados as [$l, $v]) {
        $doblan[$l] = true;
        $doblan[$v] = true;
    }

    // 1 y 2: primero los que doblan, para asegurarles el cupo del primer día.
    $sobrantes = [];
    foreach ($principal as [$l, $v]) {
        if (isset($doblan[$l]) || isset($doblan[$v])) {
            $dias[0][] = [$l, $v, false];
        } else {
            $sobrantes[] = [$l, $v];
        }
    }
    foreach ($adelantados as [$l, $v]) {
        $dias[$ultimo][] = [$l, $v, true];
    }

    // 3: el resto, en el primer día donde quepa y donde ninguno de los dos ya juegue.
    foreach ($sobrantes as [$l, $v]) {
        $colocado = false;
        foreach ($dias as $i => $lista) {
            $tope = $cupos[$i] ?? 0;
            if (count($lista) >= $tope) {
                continue;
            }
            $choca = false;
            foreach ($lista as [$a, $b]) {
                if ($a === $l || $a === $v || $b === $l || $b === $v) {
                    $choca = true;
                    break;
                }
            }
            if (!$choca) {
                $dias[$i][] = [$l, $v, false];
                $colocado = true;
                break;
            }
        }
        // Si no cupo en ningún lado (cupos mal configurados), va al día con más espacio
        // libre antes que perderse: mejor un día apretado que un partido que desaparece.
        if (!$colocado) {
            $mejor = 0;
            $libre = -PHP_INT_MAX;
            foreach ($dias as $i => $lista) {
                $hueco = ($cupos[$i] ?? 0) - count($lista);
                if ($hueco > $libre) {
                    $libre = $hueco;
                    $mejor = $i;
                }
            }
            $dias[$mejor][] = [$l, $v, false];
        }
    }

    return $dias;
}

/**
 * Fechas de cada jornada, saltando las que el organizador excluyó.
 *
 * Cada jornada es un bloque semanal: si se juega sábado y domingo, la jornada 2 cae el
 * sábado y domingo siguientes. Si alguna fecha del bloque está excluida (un feriado, una
 * fecha que todavía no se confirma), el bloque COMPLETO se corre una semana y las jornadas
 * que vienen atrás se corren con él — no se parte una jornada en dos fines de semana.
 *
 * @param string $fechaInicio Primer día de juego (Y-m-d).
 * @param array<int> $diasSemana Días que se juegan, como date('w').
 * @param int $cantidadJornadas
 * @param array<string> $excluidas Fechas Y-m-d que no se juegan.
 * @return array<int, array<int, string>> Por jornada, la fecha de cada día.
 */
function calendario_fechas(string $fechaInicio, array $diasSemana, int $cantidadJornadas, array $excluidas = []): array
{
    $tsInicio = strtotime($fechaInicio);
    if ($tsInicio === false || $cantidadJornadas < 1 || empty($diasSemana)) {
        return [];
    }

    // Desplazamiento de cada día respecto al primero. Con sábado y domingo da [0, 1];
    // con miércoles y sábado da [0, 3]. Así el bloque respeta el orden real de la semana.
    $primero = (int) date('w', $tsInicio);
    $offsets = [];
    foreach ($diasSemana as $d) {
        $offsets[] = ((int) $d - $primero + 7) % 7;
    }
    sort($offsets);

    $excluidas = array_flip(array_map('strval', $excluidas));
    $fechas = [];
    $semana = 0;

    for ($j = 0; $j < $cantidadJornadas; $j++) {
        $saltos = 0;
        while (true) {
            $bloque = [];
            $choca = false;
            foreach ($offsets as $off) {
                $dias = $semana * 7 + $off;
                $ts = strtotime("+{$dias} days", $tsInicio);
                $f = date('Y-m-d', $ts !== false ? $ts : $tsInicio);
                if (isset($excluidas[$f])) {
                    $choca = true;
                    break;
                }
                $bloque[] = $f;
            }
            if (!$choca || $saltos >= CALENDARIO_MAX_SALTOS) {
                $fechas[] = $bloque;
                $semana++;
                break;
            }
            $semana++;
            $saltos++;
        }
    }

    return $fechas;
}

/**
 * Hora y cancha del partido número $indice de un día.
 *
 * Con varias canchas los partidos corren en paralelo: con 2 canchas, los partidos 1 y 2
 * son a la misma hora en canchas distintas, el 3 y el 4 una tanda después.
 *
 * @param array<string> $canchas Nombres de cancha. Vacío = una sola sede sin nombre.
 * @return array{hora: string, cancha: string}
 */
function calendario_hora_y_cancha(int $indice, string $horaInicio, int $intervaloMin, array $canchas): array
{
    $canchas = array_values(array_filter(array_map('trim', $canchas), fn($c) => $c !== ''));
    $simultaneos = max(1, count($canchas));
    $tanda = intdiv($indice, $simultaneos);

    // Se trabaja en minutos y no con strtotime encadenado: una hora mal escrita haría que
    // strtotime devuelva false y date() reviente. Aquí lo peor que pasa es que se use 09:00.
    $partes = explode(':', trim($horaInicio));
    $h = isset($partes[0]) && is_numeric($partes[0]) ? (int) $partes[0] : 9;
    $m = isset($partes[1]) && is_numeric($partes[1]) ? (int) $partes[1] : 0;
    if ($h < 0 || $h > 23 || $m < 0 || $m > 59) {
        $h = 9;
        $m = 0;
    }

    $minutos = $h * 60 + $m + $tanda * max(0, $intervaloMin);
    // Si la tanda se pasa de medianoche se queda en 23:59 en vez de dar la vuelta al día:
    // un partido a las 00:30 del día siguiente sería un error de configuración, no algo
    // que el generador deba inventar por su cuenta.
    $minutos = min($minutos, 23 * 60 + 59);

    return [
        'hora' => sprintf('%02d:%02d', intdiv($minutos, 60), $minutos % 60),
        'cancha' => $canchas === [] ? '' : $canchas[$indice % count($canchas)],
    ];
}

/**
 * Arma el calendario completo, listo para convertirse en encuentros.
 *
 * @param array<int> $equipoIds
 * @param array $opciones vueltas, dias (lista de ['dia','partidos','hora','intervalo']),
 *   canchas, fecha_inicio, excluidas, semilla.
 * @return array<int, array{numero:int, dias: array<int, array{fecha:string, nombre:string, partidos: array}>}>
 */
function calendario_generar(array $equipoIds, array $opciones): array
{
    $dias = array_values($opciones['dias'] ?? []);
    if (empty($dias)) {
        return [];
    }

    $cupos = array_map(fn($d) => max(0, (int) ($d['partidos'] ?? 0)), $dias);
    $cupoTotal = array_sum($cupos);
    if ($cupoTotal < 1) {
        return [];
    }

    $plan = calendario_plan_jornadas(
        $equipoIds,
        (int) ($opciones['vueltas'] ?? 1),
        $cupoTotal,
        isset($opciones['semilla']) ? (int) $opciones['semilla'] : null,
        isset($opciones['rondas']) ? (array) $opciones['rondas'] : null
    );
    if (empty($plan)) {
        return [];
    }

    $fechas = calendario_fechas(
        (string) ($opciones['fecha_inicio'] ?? ''),
        array_map(fn($d) => (int) ($d['dia'] ?? 0), $dias),
        count($plan),
        (array) ($opciones['excluidas'] ?? [])
    );
    if (empty($fechas)) {
        return [];
    }

    $canchas = (array) ($opciones['canchas'] ?? []);
    $calendario = [];

    foreach ($plan as $j => $jornada) {
        $repartidos = calendario_repartir_en_dias($jornada['principal'], $jornada['adelantados'], $cupos);
        $diasJornada = [];

        foreach ($repartidos as $i => $partidos) {
            if (empty($partidos)) {
                continue;
            }
            $partidosDia = [];
            foreach (array_values($partidos) as $k => [$local, $visitante, $adelantado]) {
                $horario = calendario_hora_y_cancha(
                    $k,
                    (string) ($dias[$i]['hora'] ?? '09:00'),
                    (int) ($dias[$i]['intervalo'] ?? 90),
                    $canchas
                );
                $partidosDia[] = [
                    'local' => $local,
                    'visitante' => $visitante,
                    'adelantado' => $adelantado,
                    'hora' => $horario['hora'],
                    'cancha' => $horario['cancha'],
                ];
            }
            $diasJornada[] = [
                'fecha' => $fechas[$j][$i] ?? ($fechas[$j][0] ?? ''),
                'nombre' => CALENDARIO_DIAS[(int) ($dias[$i]['dia'] ?? 0)] ?? '',
                'partidos' => $partidosDia,
            ];
        }

        $calendario[] = ['numero' => $j + 1, 'dias' => $diasJornada];
    }

    return $calendario;
}

/**
 * Fechas que quedarían reservadas para los playoffs, después de la última jornada.
 *
 * Solo se reservan FECHAS, no se crean encuentros: todavía no se sabe quién clasifica, y
 * un partido de semifinal necesita sus dos equipos. El organizador ve las fechas en la
 * vista previa para planificar la cancha, y programa los cruces cuando termine la liga.
 *
 * El reparto sigue el orden natural de una final: las rondas con varios partidos abren el
 * fin de semana y la final lo cierra, para que el campeón se decida de último.
 *
 * @return array<int, array{fase:string, label:string, fecha:string, dia:string, partidos:int}>
 */
function calendario_previa_playoffs(array $torneo, array $calendario, array $diasConfig, array $excluidas = []): array
{
    $fases = torneo_fases_playoff($torneo);
    if (empty($fases) || empty($calendario) || empty($diasConfig)) {
        return [];
    }

    // Se ordenan según el catálogo y no según cómo el organizador marcó las casillas.
    $ordenadas = array_values(array_filter(FASES_PLAYOFF_CATALOGO, fn($f) => in_array($f, $fases, true)));
    if (empty($ordenadas)) {
        return [];
    }

    // Los playoffs arrancan el fin de semana siguiente al último de la temporada regular.
    $ultima = end($calendario);
    $ultimaFecha = '';
    foreach ($ultima['dias'] as $dia) {
        $ultimaFecha = $dia['fecha'] !== '' ? $dia['fecha'] : $ultimaFecha;
    }
    if ($ultimaFecha === '') {
        return [];
    }

    $tsSiguiente = strtotime('+7 days', (int) strtotime($ultimaFecha));
    if ($tsSiguiente === false) {
        return [];
    }

    // Cuántos partidos lleva cada fase: la final y el tercer lugar son uno solo; las demás
    // se van duplicando hacia atrás (1 final -> 2 semis -> 4 cuartos...).
    $partidosPorFase = [];
    $cuenta = 1;
    foreach (array_reverse($ordenadas) as $f) {
        if ($f === 'tercer_lugar' || $f === 'final') {
            $partidosPorFase[$f] = 1;
            continue;
        }
        $partidosPorFase[$f] = $cuenta = max(2, $cuenta * 2);
    }

    // Todos los días de playoffs disponibles, uno detrás de otro. Se piden fines de semana
    // de sobra (uno por fase más dos) y se reutiliza el mismo cálculo de fechas de la
    // temporada regular, para que también respete las fechas excluidas.
    $bloques = calendario_fechas(
        date('Y-m-d', $tsSiguiente),
        array_map(fn($d) => (int) $d['dia'], $diasConfig),
        count($ordenadas) + 2,
        $excluidas
    );

    $slots = [];
    foreach ($bloques as $iBloque => $bloque) {
        foreach ($bloque as $i => $fecha) {
            $slots[] = [
                'fecha' => $fecha,
                'dia' => CALENDARIO_DIAS[(int) $diasConfig[$i]['dia']] ?? '',
                'cupo' => max(0, (int) ($diasConfig[$i]['partidos'] ?? 0)),
                'usado' => 0,
                'semana' => $iBloque,
            ];
        }
    }
    if (empty($slots)) {
        return [];
    }

    $salida = [];
    $desde = 0; // primer día libre: una fase nunca empieza antes de que termine la anterior

    foreach ($ordenadas as $f) {
        $partidos = $partidosPorFase[$f] ?? 1;

        // El tercer lugar y la final se juegan el MISMO día: el tercer lugar abre la
        // jornada y la final la cierra. Por eso el tercer lugar no adelanta el puntero.
        $comparteConLaFinal = $f === 'tercer_lugar' && in_array('final', $ordenadas, true);

        // Los partidos se agrupan de dos en dos porque esos dos ganadores se enfrentan
        // en la ronda siguiente: si se jugaran en días distintos, uno llegaría con un día
        // menos de descanso. Por eso una pareja nunca se parte, pero DOS parejas sí pueden
        // ir en días distintos — que es lo que reparte los cuartos entre sábado y domingo
        // en vez de amontonarlos todos el sábado.
        $parejas = [];
        $restan = $partidos;
        while ($restan > 0) {
            $parejas[] = min(2, $restan);
            $restan -= 2;
        }

        $porSlot = [];
        $ultimoUsado = $desde;

        foreach ($parejas as $k => $tamano) {
            // Se rota entre los días disponibles antes de repetir uno: así con cuartos de
            // final salen 2 el sábado y 2 el domingo, y no 4 el sábado con el domingo libre.
            $intentos = 0;
            $indice = $desde + ($k % max(1, count($diasConfig)));
            while ($intentos < count($slots)) {
                $slot = $slots[$indice] ?? null;
                if ($slot !== null && $slot['usado'] + $tamano <= $slot['cupo']) {
                    break;
                }
                $indice++;
                $intentos++;
            }
            if (!isset($slots[$indice])) {
                break; // no hay más días configurados; el resto se programa a mano
            }

            $slots[$indice]['usado'] += $tamano;
            $porSlot[$indice] = ($porSlot[$indice] ?? 0) + $tamano;
            $ultimoUsado = max($ultimoUsado, $indice);
        }

        if (empty($porSlot)) {
            continue;
        }
        ksort($porSlot);

        $dias = [];
        foreach ($porSlot as $indice => $cuantos) {
            $dias[] = [
                'fecha' => $slots[$indice]['fecha'],
                'nombre' => $slots[$indice]['dia'],
                'partidos' => $cuantos,
            ];
        }

        $salida[] = [
            'fase' => $f,
            'label' => FASES_LABEL[$f] ?? $f,
            'dias' => $dias,
            'total' => array_sum(array_column($dias, 'partidos')),
            'desde' => $dias[0]['fecha'],
            'hasta' => $dias[count($dias) - 1]['fecha'],
        ];

        // La fase siguiente arranca el día DESPUÉS del último que usó esta, salvo el
        // tercer lugar, que le deja el mismo día a la final.
        $desde = $comparteConLaFinal ? $ultimoUsado : $ultimoUsado + 1;
    }

    return $salida;
}

/**
 * Resumen para la vista previa: cuántos partidos por día lleva cada jornada y qué equipos
 * juegan dos veces. Es lo que el organizador aprueba ANTES de que se cree nada.
 *
 * @return array{jornadas: array, total: int, adelantados: int, dobles_por_equipo: array}
 */
function calendario_resumen(array $calendario, array $equiposPorId = []): array
{
    $filas = [];
    $total = 0;
    $adelantados = 0;
    $doblesPorEquipo = [];

    foreach ($calendario as $jornada) {
        $porDia = [];
        $dobles = [];
        $primeraFecha = '';
        $ultimaFecha = '';

        foreach ($jornada['dias'] as $dia) {
            $porDia[] = ['nombre' => $dia['nombre'], 'fecha' => $dia['fecha'], 'cantidad' => count($dia['partidos'])];
            $total += count($dia['partidos']);
            if ($primeraFecha === '') {
                $primeraFecha = $dia['fecha'];
            }
            $ultimaFecha = $dia['fecha'];

            foreach ($dia['partidos'] as $p) {
                if (!empty($p['adelantado'])) {
                    $adelantados++;
                    foreach ([$p['local'], $p['visitante']] as $id) {
                        $dobles[$id] = true;
                        $doblesPorEquipo[$id] = ($doblesPorEquipo[$id] ?? 0) + 1;
                    }
                }
            }
        }

        $nombresDobles = [];
        foreach (array_keys($dobles) as $id) {
            $nombresDobles[] = $equiposPorId[$id]['nombre'] ?? ('Equipo ' . $id);
        }

        $filas[] = [
            'numero' => $jornada['numero'],
            'dias' => $porDia,
            'desde' => $primeraFecha,
            'hasta' => $ultimaFecha,
            'total' => array_sum(array_column($porDia, 'cantidad')),
            'dobles' => $nombresDobles,
        ];
    }

    return [
        'jornadas' => $filas,
        'total' => $total,
        'adelantados' => $adelantados,
        'dobles_por_equipo' => $doblesPorEquipo,
    ];
}
