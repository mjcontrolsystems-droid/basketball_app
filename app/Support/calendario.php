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
 * Cuántos órdenes distintos se prueban al armar cada jornada, buscando que juegue la mayor
 * cantidad posible de equipos. Se corta antes en cuanto se alcanza el máximo teórico, que
 * es lo normal, así que el costo real es de uno o dos intentos por jornada.
 */
const CALENDARIO_INTENTOS_EMPAREJAR = 40;

/**
 * Reparte el fixture en jornadas que llenen el cupo de cada fin de semana.
 *
 * @param array<int> $equipoIds
 * @param int $vueltas 1 = solo ida, 2 = ida y vuelta.
 * @param int $cupoPorJornada Partidos que caben en total en un fin de semana.
 * @param int|null $semilla Para el sorteo de los adelantados. null = aleatorio de verdad.
 * @return array<int, array{principal: array, adelantados: array}>
 */
/** Cuántos planes distintos se prueban antes de quedarse con el mejor. */
const CALENDARIO_PLANES_A_PROBAR = 6;

/**
 * Arma el plan de jornadas y se queda con el más justo de varios intentos.
 *
 * Repartir bien es un tira y afloja: forzar que todos doblen la misma cantidad de veces
 * puede dejar un residuo feo al final y costar un fin de semana extra — que es justo lo
 * que no sobra cuando hay fecha de cierre. Así que en vez de imponer un criterio se
 * arman varios planes (con y sin equilibrio de dobletes, con distintos sorteos) y se
 * elige por orden: menos jornadas, menos equipos sin jugar, y carga más pareja.
 *
 * @param array<int> $equipoIds
 * @param array<int, array{0:int, 1:int}> $yaProgramados Cruces que no hay que volver a crear.
 */
function calendario_plan_jornadas(array $equipoIds, int $vueltas, int $cupoPorJornada, ?int $semilla = null, ?array $rondasPrearmadas = null, array $yaProgramados = [], array $doblesPrevios = []): array
{
    $mejor = null;
    $mejorPunt = null;

    foreach ([true, false] as $balancear) {
        for ($i = 0; $i < CALENDARIO_PLANES_A_PROBAR; $i++) {
            $plan = calendario_plan_intento(
                $equipoIds,
                $vueltas,
                $cupoPorJornada,
                $semilla === null ? null : $semilla + $i,
                $rondasPrearmadas,
                $yaProgramados,
                $balancear,
                $doblesPrevios
            );
            if (empty($plan)) {
                continue;
            }

            $punt = calendario_puntuar_plan($plan, $equipoIds, $doblesPrevios);
            if ($mejorPunt === null || $punt < $mejorPunt) {
                $mejorPunt = $punt;
                $mejor = $plan;
            }
        }
    }

    return $mejor ?? [];
}

/**
 * Puntúa un plan. Menor es mejor; se compara como cadena ordenable por eso los ceros.
 *
 * Orden de importancia:
 *   1. Cantidad de jornadas — cada una es un fin de semana más, y suele haber fecha tope.
 *   2. Equipos que se quedan sin jugar una jornada mientras otros juegan.
 *   3. Diferencia entre el que más veces dobla y el que menos.
 *   4. El máximo de dobletes que carga un solo equipo.
 */
function calendario_puntuar_plan(array $plan, array $equipoIds, array $doblesPrevios = []): string
{
    $dobles = array_fill_keys(array_map('intval', $equipoIds), 0);
    foreach ($doblesPrevios as $eq => $n) {
        if (isset($dobles[(int) $eq])) {
            $dobles[(int) $eq] = (int) $n;
        }
    }
    $huecos = 0;

    foreach ($plan as $jornada) {
        $juegan = [];
        foreach ($jornada['principal'] as [$a, $b]) {
            $juegan[(int) $a] = true;
            $juegan[(int) $b] = true;
        }
        foreach ($jornada['adelantados'] as [$a, $b]) {
            $juegan[(int) $a] = true;
            $juegan[(int) $b] = true;
            if (isset($dobles[(int) $a])) { $dobles[(int) $a]++; }
            if (isset($dobles[(int) $b])) { $dobles[(int) $b]++; }
        }
        foreach ($equipoIds as $eq) {
            if (!isset($juegan[(int) $eq])) {
                $huecos++;
            }
        }
    }

    $valores = array_values($dobles);
    $spread = $valores === [] ? 0 : max($valores) - min($valores);
    $tope = $valores === [] ? 0 : max($valores);

    return sprintf('%04d|%04d|%04d|%04d', count($plan), $huecos, $spread, $tope);
}

function calendario_plan_intento(array $equipoIds, int $vueltas, int $cupoPorJornada, ?int $semilla, ?array $rondasPrearmadas, array $yaProgramados, bool $balancearDobles, array $doblesPrevios = []): array
{
    // La fase de grupos manda sus propias rondas ya armadas (el todos contra todos de cada
    // grupo, mezclados para que una jornada tenga partidos de todos los grupos). El resto
    // del reparto — cupos por día, adelantados, fechas — funciona igual.
    $rondas = $rondasPrearmadas !== null ? array_values(array_filter($rondasPrearmadas)) : generar_fixture_round_robin($equipoIds, $vueltas);
    if (empty($rondas)) {
        return [];
    }

    // Cruces que ya están programados a mano y no hay que volver a crear. Es el caso de
    // quien ya publicó la primera jornada y solo quiere que la app arme el resto: sin
    // esto habría que borrar todo y rehacerlo, cambiando partidos ya avisados a los
    // equipos. Se comparan sin importar quién es local, porque el cruce es el mismo.
    if (!empty($yaProgramados)) {
        $fuera = [];
        foreach ($yaProgramados as [$a, $b]) {
            $par = [(int) $a, (int) $b];
            sort($par);
            $fuera[$par[0] . '-' . $par[1]] = true;
        }
        foreach ($rondas as $i => $ronda) {
            $rondas[$i] = array_values(array_filter($ronda, function ($cruce) use ($fuera) {
                $par = [(int) $cruce[0], (int) $cruce[1]];
                sort($par);
                return !isset($fuera[$par[0] . '-' . $par[1]]);
            }));
        }
        $rondas = array_values(array_filter($rondas));
        if (empty($rondas)) {
            return [];
        }
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

    // Todos los cruces que quedan por programar, con la ronda de la que salieron. La ronda
    // sirve como preferencia (se intenta respetar el orden del round-robin), no como regla.
    $bolsa = [];
    foreach ($rondas as $i => $ronda) {
        foreach ($ronda as $cruce) {
            $bolsa[] = ['ronda' => $i, 'cruce' => $cruce];
        }
    }

    $jornadas = [];
    // Cuántas veces le ha tocado a cada equipo jugar dos veces en un mismo fin de semana.
    // Arranca con lo que ya está publicado: si no, a quien ya dobló en la jornada 1 se le
    // vuelve a cargar la mano. Pasó con la Promoción 58, que terminó doblando 3 veces.
    $dobles = [];
    foreach ($doblesPrevios as $eq => $n) {
        $dobles[(int) $eq] = (int) $n;
    }

    while (!empty($bolsa)) {
        // --- Fase 1: que juegue la MAYOR cantidad posible de equipos, sin repetir ---
        //
        // Antes esto recorría las rondas en orden y tomaba lo que cupiera. Funcionaba
        // mientras cada ronda fuera un emparejamiento perfecto (cubre a todos los equipos
        // exactamente una vez), que es como sale del round-robin. Pero en cuanto se le
        // quitan cruces — porque ya estaban programados a mano, o porque se los llevó un
        // adelantado — la ronda deja de cubrir a todos, el llenado sigue con la ronda
        // siguiente y algunos equipos se quedaban SIN JUGAR esa jornada mientras otros
        // jugaban dos veces. Pasaba de verdad: 6 de 13 jornadas quedaban así.
        //
        // Ahora se busca el emparejamiento más grande posible. No hay una fórmula simple
        // para el óptimo exacto en un grafo cualquiera, así que se prueban varios órdenes
        // al azar y se guarda el mejor; en cuanto se alcanza el máximo teórico se corta.
        // El primer intento respeta el orden de las rondas, así que cuando el fixture está
        // intacto sale el mismo resultado de siempre a la primera.
        $disponibles = [];
        foreach ($bolsa as $item) {
            $disponibles[$item['cruce'][0]] = true;
            $disponibles[$item['cruce'][1]] = true;
        }
        $tope = min($cupoPorJornada, intdiv(count($disponibles), 2));

        $mejor = [];
        for ($intento = 0; $intento < CALENDARIO_INTENTOS_EMPAREJAR; $intento++) {
            if ($intento === 0) {
                // Orden natural: primero las rondas más tempranas.
                $orden = $bolsa;
                usort($orden, fn($a, $b) => $a['ronda'] <=> $b['ronda']);
            } else {
                $orden = $mt->shuffleArray($bolsa);
            }

            $usados = [];
            $seleccion = [];
            foreach ($orden as $item) {
                if (count($seleccion) >= $tope) {
                    break;
                }
                [$a, $b] = $item['cruce'];
                if (isset($usados[$a]) || isset($usados[$b])) {
                    continue;
                }
                $seleccion[] = $item;
                $usados[$a] = true;
                $usados[$b] = true;
            }

            if (count($seleccion) > count($mejor)) {
                $mejor = $seleccion;
            }
            if (count($mejor) >= $tope) {
                break;
            }
        }

        $principal = $mejor;
        $vecesPorEquipo = [];
        foreach ($principal as $item) {
            $vecesPorEquipo[$item['cruce'][0]] = 1;
            $vecesPorEquipo[$item['cruce'][1]] = 1;
        }

        // Lo que no entró en la fase 1.
        $clavesUsadas = [];
        foreach ($principal as $item) {
            $clavesUsadas[$item['ronda'] . ':' . $item['cruce'][0] . '-' . $item['cruce'][1]] = true;
        }
        $resto = array_values(array_filter($bolsa, fn($x) => !isset($clavesUsadas[$x['ronda'] . ':' . $x['cruce'][0] . '-' . $x['cruce'][1]])));

        // --- Fase 2: los adelantados ---
        // Los cupos que sobran se llenan con cruces de las ÚLTIMAS rondas. Solo sirve uno
        // cuyos DOS equipos jueguen exactamente una vez hoy: si alguno ya va dos veces, un
        // tercer partido en el mismo fin de semana es abuso; si va cero es que descansa.
        //
        // Entre los que califican se prefiere a quienes MENOS veces han doblado en la
        // temporada. Antes solo mandaba la ronda, y con 16 equipos y 9 cupos hay 14
        // adelantados para repartir entre 16 equipos: a unos les tocaba 3 veces y a otros
        // 1. Doblar es inevitable, pero que siempre le toque al mismo no.
        $adelantados = [];
        while (count($principal) + count($adelantados) < $cupoPorJornada) {
            $elegido = null;
            $mejorPeso = null;
            foreach ($resto as $k => $item) {
                [$a, $b] = $item['cruce'];
                if (($vecesPorEquipo[$a] ?? 0) !== 1 || ($vecesPorEquipo[$b] ?? 0) !== 1) {
                    continue;
                }
                // Carga acumulada primero; a igualdad, la ronda más tardía (se roba del final).
                $carga = $balancearDobles ? (($dobles[$a] ?? 0) + ($dobles[$b] ?? 0)) : 0;
                $peso = $carga * 1000 - $item['ronda'];
                if ($mejorPeso === null || $peso < $mejorPeso) {
                    $mejorPeso = $peso;
                    $elegido = $k;
                }
            }
            if ($elegido === null) {
                break;
            }
            [$a, $b] = $resto[$elegido]['cruce'];
            $adelantados[] = $resto[$elegido];
            $vecesPorEquipo[$a] = 2;
            $vecesPorEquipo[$b] = 2;
            $dobles[$a] = ($dobles[$a] ?? 0) + 1;
            $dobles[$b] = ($dobles[$b] ?? 0) + 1;
            unset($resto[$elegido]);
        }

        $bolsa = array_values($resto);

        if (empty($principal) && empty($adelantados)) {
            break; // ya no queda nada por programar
        }
        $jornadas[] = [
            'principal' => array_map(fn($x) => $x['cruce'], $principal),
            'adelantados' => array_map(fn($x) => $x['cruce'], $adelantados),
        ];
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
 * Reconstruye los contadores de turno y localía a partir de los partidos ya publicados.
 *
 * Al continuar un calendario, lo que ya se anunció no se puede tocar — pero sí se puede
 * compensar de aquí en adelante. Para eso hay que saber a qué tanda equivale la hora de
 * cada partido viejo, que se deduce de la hora de inicio y el intervalo del día.
 *
 * @param array<int, array{local:int, visitante:int, hora?:string, dia?:int}> $historial
 * @return array{0: array, 1: array, 2: array, 3: array}
 */
function calendario_historial_previo(array $historial, array $dias, int $simultaneos): array
{
    $vecesTurno = [];
    $acumulado = [];
    $vecesLocal = [];
    $cruceVisto = [];

    // Minuto de arranque más temprano e intervalo de referencia entre los días configurados.
    $arranque = null;
    $intervalo = 90;
    foreach ($dias as $d) {
        $partes = explode(':', trim((string) ($d['hora'] ?? '')));
        if (isset($partes[0], $partes[1]) && is_numeric($partes[0]) && is_numeric($partes[1])) {
            $min = ((int) $partes[0]) * 60 + (int) $partes[1];
            $arranque = $arranque === null ? $min : min($arranque, $min);
        }
        if ((int) ($d['intervalo'] ?? 0) > 0) {
            $intervalo = (int) $d['intervalo'];
        }
    }

    foreach ($historial as $p) {
        $local = (int) ($p['local'] ?? 0);
        $visitante = (int) ($p['visitante'] ?? 0);
        if ($local <= 0 || $visitante <= 0) {
            continue;
        }

        $tanda = 0;
        $partes = explode(':', trim((string) ($p['hora'] ?? '')));
        if ($arranque !== null && isset($partes[0], $partes[1]) && is_numeric($partes[0]) && is_numeric($partes[1])) {
            $min = ((int) $partes[0]) * 60 + (int) $partes[1];
            $tanda = max(0, intdiv($min - $arranque, max(1, $intervalo)));
        }

        foreach ([$local, $visitante] as $eq) {
            $vecesTurno[$eq][$tanda] = ($vecesTurno[$eq][$tanda] ?? 0) + 1;
            $acumulado[$eq] = ($acumulado[$eq] ?? 0) + $tanda;
        }
        $vecesLocal[$local] = ($vecesLocal[$local] ?? 0) + 1;
        $cruceVisto[min($local, $visitante) . '-' . max($local, $visitante)] = $local;
    }

    return [$vecesTurno, $acumulado, $vecesLocal, $cruceVisto];
}

/**
 * Guarda cómo juega la copa para poder reusarlo sin volver a preguntarlo.
 *
 * @param array $dias Lista de ['dia','partidos','hora','intervalo'].
 * @param array<string> $canchas
 * @param array<string> $excluidas Fechas Y-m-d que no se juegan.
 */
function calendario_config_serializar(array $dias, array $canchas, array $excluidas): string
{
    $limpios = [];
    foreach ($dias as $d) {
        $limpios[] = [
            'dia' => (int) ($d['dia'] ?? 0),
            'partidos' => max(0, (int) ($d['partidos'] ?? 0)),
            'hora' => (string) ($d['hora'] ?? '09:00'),
            'intervalo' => max(1, (int) ($d['intervalo'] ?? 90)),
        ];
    }

    return (string) json_encode([
        'dias' => $limpios,
        'canchas' => array_values(array_filter(array_map('trim', $canchas), fn($c) => $c !== '')),
        'excluidas' => array_values(array_unique(array_filter($excluidas))),
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Cómo juega la copa: días, cupos, hora, intervalo, canchas y fechas excluidas.
 *
 * Primero lo guardado. Si la copa es anterior a que esto se guardara, se deduce de los
 * encuentros que ya existen — así una liga vieja tampoco arma sus cuartos a ciegas.
 *
 * @return array{dias: array, canchas: array<string>, excluidas: array<string>}
 */
function calendario_config_del_torneo(?array $torneo, array $partidos = []): array
{
    $guardado = json_decode((string) ($torneo['calendario_config'] ?? ''), true);
    if (is_array($guardado) && !empty($guardado['dias'])) {
        return [
            'dias' => $guardado['dias'],
            'canchas' => (array) ($guardado['canchas'] ?? []),
            'excluidas' => (array) ($guardado['excluidas'] ?? []),
        ];
    }

    return calendario_config_deducida($partidos);
}

/**
 * Deduce el ritmo de la copa mirando los encuentros que ya se programaron.
 *
 * Se toma la temporada regular (las fases de eliminación son irregulares por naturaleza)
 * y de ahí salen: qué días de la semana se juega, cuántos partidos caben en cada uno, a
 * qué hora arranca y cada cuánto sale un partido.
 *
 * @return array{dias: array, canchas: array<string>, excluidas: array<string>}
 */
function calendario_config_deducida(array $partidos): array
{
    // 'grupos' es la clave con la que se guarda la temporada regular (ver FASES_LABEL).
    $regulares = array_values(array_filter(
        $partidos,
        fn($p) => ($p['fase'] ?? 'grupos') === 'grupos' && !empty($p['fecha'])
    ));
    if (empty($regulares)) {
        return ['dias' => [], 'canchas' => [], 'excluidas' => []];
    }

    // Agrupar por fecha para saber cuántos partidos entran en un día y a qué horas.
    $porFecha = [];
    $canchas = [];
    foreach ($regulares as $p) {
        $porFecha[(string) $p['fecha']][] = (string) ($p['hora'] ?? '');
        $cancha = trim((string) ($p['cancha'] ?? ''));
        if ($cancha !== '') {
            $canchas[$cancha] = true;
        }
    }

    $porDiaSemana = [];
    $minutosTodos = [];
    foreach ($porFecha as $fecha => $horas) {
        $ts = strtotime($fecha);
        if ($ts === false) {
            continue;
        }
        $dow = (int) date('w', $ts);
        $porDiaSemana[$dow]['cupo'] = max($porDiaSemana[$dow]['cupo'] ?? 0, count($horas));

        $minutos = [];
        foreach ($horas as $h) {
            $partes = explode(':', trim($h));
            if (isset($partes[0], $partes[1]) && is_numeric($partes[0]) && is_numeric($partes[1])) {
                $minutos[] = ((int) $partes[0]) * 60 + (int) $partes[1];
            }
        }
        if (empty($minutos)) {
            continue;
        }
        sort($minutos);
        $inicio = $minutos[0];
        $porDiaSemana[$dow]['inicio'] = min($porDiaSemana[$dow]['inicio'] ?? $inicio, $inicio);
        $minutosTodos[] = $minutos;
    }

    // El intervalo es la diferencia más chica entre dos horas consecutivas de un mismo día.
    $intervalo = 0;
    foreach ($minutosTodos as $minutos) {
        for ($i = 1; $i < count($minutos); $i++) {
            $dif = $minutos[$i] - $minutos[$i - 1];
            if ($dif > 0) {
                $intervalo = $intervalo === 0 ? $dif : min($intervalo, $dif);
            }
        }
    }
    $intervalo = $intervalo > 0 ? $intervalo : 90;

    ksort($porDiaSemana);
    $dias = [];
    foreach ($porDiaSemana as $dow => $datos) {
        $inicio = (int) ($datos['inicio'] ?? 540);
        $dias[] = [
            'dia' => $dow,
            'partidos' => (int) ($datos['cupo'] ?? 1),
            'hora' => sprintf('%02d:%02d', intdiv($inicio, 60), $inicio % 60),
            'intervalo' => $intervalo,
        ];
    }

    return ['dias' => $dias, 'canchas' => array_keys($canchas), 'excluidas' => []];
}

/**
 * Cuántas veces dobló cada equipo en las jornadas ya publicadas.
 *
 * Doblar es jugar dos veces la misma jornada. Sin este conteo el generador arranca de
 * cero y le puede volver a tocar al que ya dobló en lo que está publicado.
 *
 * @param array<int, array{local:int, visitante:int, jornada?:int}> $historial
 * @return array<int, int>
 */
function calendario_dobles_previos(array $historial): array
{
    $porJornada = [];
    foreach ($historial as $p) {
        $j = (int) ($p['jornada'] ?? 0);
        if ($j <= 0) {
            continue;
        }
        $porJornada[$j][] = (int) ($p['local'] ?? 0);
        $porJornada[$j][] = (int) ($p['visitante'] ?? 0);
    }

    $dobles = [];
    foreach ($porJornada as $equipos) {
        foreach (array_count_values($equipos) as $eq => $veces) {
            if ($veces > 1) {
                $dobles[(int) $eq] = ($dobles[(int) $eq] ?? 0) + ($veces - 1);
            }
        }
    }

    return $dobles;
}

/**
 * Ordena los partidos de un día para que el turno no le caiga siempre al mismo.
 *
 * Sin esto el orden lo decide el fixture, que no sabe nada de horas: en el primer
 * calendario de la liga de exalumnos hubo un equipo al que le tocaron 6 veces las 3 de
 * la tarde y otro al que le tocó una sola vez. Nadie pierde un partido por eso, pero es
 * lo primero que reclama la gente, y con razón.
 *
 * El criterio: para cada tanda se toma el partido cuyos dos equipos menos veces han
 * jugado a esa hora; si empatan, pasa primero el que más tarde ha venido jugando.
 *
 * @param array<int, array{0:int, 1:int, 2:bool}> $partidos
 * @param array<int, array<int, int>> $veces  [equipo][tanda] => cuántas veces
 * @param array<int, int> $acumulado  [equipo] => suma de tandas que le han tocado
 * @return array<int, array{0:int, 1:int, 2:bool}>
 */
function calendario_ordenar_turnos(array $partidos, array $veces, array $acumulado, int $simultaneos): array
{
    $pendientes = array_values($partidos);
    $simultaneos = max(1, $simultaneos);
    $orden = [];

    for ($k = 0; $pendientes !== []; $k++) {
        $tanda = intdiv($k, $simultaneos);
        $elegido = 0;
        $mejor = null;

        foreach ($pendientes as $i => $partido) {
            $a = (int) $partido[0];
            $b = (int) $partido[1];
            $repite = ($veces[$a][$tanda] ?? 0) + ($veces[$b][$tanda] ?? 0);
            $tarde = ($acumulado[$a] ?? 0) + ($acumulado[$b] ?? 0);
            // Evitar la repetición pesa mucho más que compensar; el segundo término solo
            // desempata, por eso va en una escala aparte.
            $peso = $repite * 1000 - $tarde;
            if ($mejor === null || $peso < $mejor) {
                $mejor = $peso;
                $elegido = $i;
            }
        }

        $partido = $pendientes[$elegido];
        unset($pendientes[$elegido]);
        $pendientes = array_values($pendientes);

        $veces[(int) $partido[0]][$tanda] = ($veces[(int) $partido[0]][$tanda] ?? 0) + 1;
        $veces[(int) $partido[1]][$tanda] = ($veces[(int) $partido[1]][$tanda] ?? 0) + 1;
        $acumulado[(int) $partido[0]] = ($acumulado[(int) $partido[0]] ?? 0) + $tanda;
        $acumulado[(int) $partido[1]] = ($acumulado[(int) $partido[1]] ?? 0) + $tanda;

        $orden[] = $partido;
    }

    return [$orden, $veces, $acumulado];
}

/**
 * Decide quién va de local para que la localía quede repartida.
 *
 * Si el mismo cruce ya se jugó antes (torneos de ida y vuelta) se invierte sin pensarlo:
 * ahí la localía la manda la vuelta, no el conteo. Si es la primera vez que se ven, va de
 * local el que menos veces lo ha sido.
 *
 * @return array{0:int, 1:int}
 */
function calendario_elegir_localia(int $a, int $b, array $vecesLocal, array $cruceVisto): array
{
    $clave = min($a, $b) . '-' . max($a, $b);
    if (isset($cruceVisto[$clave])) {
        return $cruceVisto[$clave] === $a ? [$b, $a] : [$a, $b];
    }

    return ($vecesLocal[$a] ?? 0) > ($vecesLocal[$b] ?? 0) ? [$b, $a] : [$a, $b];
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
        isset($opciones['rondas']) ? (array) $opciones['rondas'] : null,
        (array) ($opciones['ya_programados'] ?? []),
        calendario_dobles_previos((array) ($opciones['historial'] ?? []))
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

    // Contadores para repartir horarios y localías. Arrancan con lo que ya está publicado
    // (al continuar un calendario), porque si no se ignoraría que a alguien ya le tocó
    // tres veces el primer turno y se le volvería a cargar la mano.
    $simultaneos = max(1, count(array_filter(array_map('trim', $canchas), fn($c) => $c !== '')));
    [$vecesTurno, $acumuladoTurno, $vecesLocal, $cruceVisto] = calendario_historial_previo(
        (array) ($opciones['historial'] ?? []),
        $dias,
        $simultaneos
    );
    $localPublicadas = $vecesLocal; // lo ya publicado no se puede invertir

    foreach ($plan as $j => $jornada) {
        $repartidos = calendario_repartir_en_dias($jornada['principal'], $jornada['adelantados'], $cupos);
        $diasJornada = [];

        foreach ($repartidos as $i => $partidos) {
            if (empty($partidos)) {
                continue;
            }

            [$partidos, $vecesTurno, $acumuladoTurno] = calendario_ordenar_turnos(
                array_values($partidos),
                $vecesTurno,
                $acumuladoTurno,
                $simultaneos
            );

            $partidosDia = [];
            foreach ($partidos as $k => [$unoA, $unoB, $adelantado]) {
                [$local, $visitante] = calendario_elegir_localia((int) $unoA, (int) $unoB, $vecesLocal, $cruceVisto);
                $vecesLocal[$local] = ($vecesLocal[$local] ?? 0) + 1;
                $cruceVisto[min($local, $visitante) . '-' . max($local, $visitante)] = $local;

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

        // Al continuar un calendario ya empezado, la numeración sigue desde la última
        // jornada existente en vez de volver a 1.
        $calendario[] = ['numero' => $j + 1 + (int) ($opciones['jornada_inicial'] ?? 0), 'dias' => $diasJornada];
    }

    // Última pasada: emparejar las localías. Se hace al final y no sobre la marcha porque
    // decidirlo partido a partido depende del orden y salía disparejo según el sorteo.
    return calendario_balancear_localias($calendario, $localPublicadas);
}

/** Vueltas máximas de la pasada que empareja las localías. Corta sola mucho antes. */
const CALENDARIO_VUELTAS_LOCALIA = 200;

/**
 * Empareja las localías dando vuelta partidos hasta que nadie tenga 2 de más.
 *
 * Elegir local sobre la marcha ("va el que menos veces lo ha sido") es miope: depende del
 * orden en que salen los partidos y según el sorteo terminaba en 6-9 o en 7-8 de puro
 * azar. Esta pasada lo arregla después, con el calendario ya armado: busca un partido
 * donde el local ya tiene al menos 2 de más que su rival y lo invierte. Cada vuelta
 * reduce la diferencia, así que termina sola.
 *
 * Los cruces que aparecen dos veces (ida y vuelta) no se tocan: ahí la localía ya está
 * pareja por definición e invertir uno solo rompería la vuelta.
 *
 * @param array<int, int> $vecesLocalPrevias Localías de lo ya publicado, que no se puede tocar.
 */
function calendario_balancear_localias(array $calendario, array $vecesLocalPrevias = []): array
{
    // Índice de todos los partidos que sí se pueden invertir.
    $refs = [];
    $conteoPar = [];
    foreach ($calendario as $j => $jornada) {
        foreach ($jornada['dias'] as $d => $dia) {
            foreach ($dia['partidos'] as $p => $partido) {
                $a = (int) $partido['local'];
                $b = (int) $partido['visitante'];
                $clave = min($a, $b) . '-' . max($a, $b);
                $conteoPar[$clave] = ($conteoPar[$clave] ?? 0) + 1;
                $refs[] = ['j' => $j, 'd' => $d, 'p' => $p, 'clave' => $clave];
            }
        }
    }
    if (empty($refs)) {
        return $calendario;
    }

    $local = $vecesLocalPrevias;
    foreach ($calendario as $jornada) {
        foreach ($jornada['dias'] as $dia) {
            foreach ($dia['partidos'] as $partido) {
                $local[(int) $partido['local']] = ($local[(int) $partido['local']] ?? 0) + 1;
            }
        }
    }

    // Cuántos partidos juega cada equipo: define cuántos DEBERÍA jugar en casa.
    $jugados = [];
    foreach ($calendario as $jornada) {
        foreach ($jornada['dias'] as $dia) {
            foreach ($dia['partidos'] as $partido) {
                $jugados[(int) $partido['local']] = ($jugados[(int) $partido['local']] ?? 0) + 1;
                $jugados[(int) $partido['visitante']] = ($jugados[(int) $partido['visitante']] ?? 0) + 1;
            }
        }
    }

    // Solo los invertibles: un cruce de ida y vuelta ya está parejo por definición.
    $invertibles = array_values(array_filter($refs, fn($r) => ($conteoPar[$r['clave']] ?? 0) === 1));

    for ($vuelta = 0; $vuelta < CALENDARIO_VUELTAS_LOCALIA; $vuelta++) {
        // ¿Alguien tiene de más? Se atiende primero al que más se pasa.
        $sobra = null;
        $peor = 0;
        foreach ($jugados as $eq => $n) {
            $exceso = ($local[$eq] ?? 0) - (int) ceil($n / 2);
            if ($exceso > $peor) {
                $peor = $exceso;
                $sobra = (int) $eq;
            }
        }
        if ($sobra === null) {
            break;
        }

        // Cadena de partidos desde el que sobra hasta alguno que le falte, siguiendo
        // siempre "de local a visitante". Invertir toda la cadena le quita uno al primero
        // y le da uno al último; los del medio quedan igual. Con un cruce directo no
        // siempre alcanza: si el que sobra visita al que le falta, hay que dar la vuelta
        // por un tercero, y eso es justo lo que encuentra esta búsqueda.
        $previo = [$sobra => null];
        $cola = [$sobra];
        $destino = null;

        while ($cola !== [] && $destino === null) {
            $actual = array_shift($cola);
            foreach ($invertibles as $i => $r) {
                $partido = $calendario[$r['j']]['dias'][$r['d']]['partidos'][$r['p']];
                if ((int) $partido['local'] !== $actual) {
                    continue;
                }
                $siguiente = (int) $partido['visitante'];
                if (array_key_exists($siguiente, $previo)) {
                    continue;
                }
                $previo[$siguiente] = ['de' => $actual, 'ref' => $i];
                // Sirve de destino cualquiera que todavía pueda recibir un partido de
                // local sin pasarse de su cuota. Con 15 partidos la cuota es 7 u 8, así
                // que quien va en 7 sí puede recibir uno más: exigir que fuera menos de 7
                // dejaba sin arreglo el caso de un equipo con muchos de local y el resto
                // justo en la raya.
                if (($local[$siguiente] ?? 0) < (int) ceil(($jugados[$siguiente] ?? 0) / 2)) {
                    $destino = $siguiente;
                    break;
                }
                $cola[] = $siguiente;
            }
        }

        if ($destino === null) {
            break; // no hay forma de mejorarlo más
        }

        for ($nodo = $destino; $previo[$nodo] !== null; $nodo = $previo[$nodo]['de']) {
            $r = $invertibles[$previo[$nodo]['ref']];
            $partido = &$calendario[$r['j']]['dias'][$r['d']]['partidos'][$r['p']];
            [$partido['local'], $partido['visitante']] = [$partido['visitante'], $partido['local']];
            unset($partido);
        }
        $local[$sobra]--;
        $local[$destino] = ($local[$destino] ?? 0) + 1;
    }

    return $calendario;
}

/**
 * Le pone fecha, día, hora y cancha a los cruces de una fase de eliminación.
 *
 * Antes estos partidos nacían todos el mismo día, sin hora y sin cancha, y había que
 * acomodarlos a mano uno por uno. Eso rompía todo lo que se cuidó en la temporada
 * regular: cuatro cuartos de final el sábado con el domingo vacío, y el primer turno
 * cayéndole otra vez al mismo. Ahora se usa el mismo criterio:
 *
 *   - Los partidos se agrupan de dos en dos (esos dos ganadores se cruzan después, así
 *     que tienen que descansar lo mismo) y las parejas se reparten entre los días.
 *   - Dentro del día, el turno se le da a quien menos veces le ha tocado ese horario.
 *   - Se respetan las fechas que no se juegan.
 *
 * @param array<int, array{local: array, visitante: array, etiqueta: string}> $cruces
 * @param array $config Lo que devuelve calendario_config_del_torneo().
 * @return array<int, array{cruce: array, fecha: string, hora: string, cancha: string}>
 */
function calendario_ubicar_cruces(array $cruces, array $partidosExistentes, array $config, string $desdeFecha): array
{
    $cruces = array_values($cruces);
    if (empty($cruces)) {
        return [];
    }

    $dias = array_values($config['dias'] ?? []);
    $canchas = array_values(array_filter(array_map('trim', (array) ($config['canchas'] ?? [])), fn($c) => $c !== ''));

    // Sin días configurados no hay nada que repartir: todos el mismo día, como antes.
    if (empty($dias)) {
        return array_map(fn($c) => ['cruce' => $c, 'fecha' => $desdeFecha, 'hora' => '', 'cancha' => ''], $cruces);
    }

    $bloques = calendario_fechas(
        $desdeFecha,
        array_map(fn($d) => (int) ($d['dia'] ?? 0), $dias),
        max(1, (int) ceil(count($cruces) / max(1, count($dias)))) + 1,
        (array) ($config['excluidas'] ?? [])
    );
    if (empty($bloques)) {
        return array_map(fn($c) => ['cruce' => $c, 'fecha' => $desdeFecha, 'hora' => '', 'cancha' => ''], $cruces);
    }

    // Días disponibles, uno detrás de otro, con el cupo de cada uno.
    $slots = [];
    foreach ($bloques as $bloque) {
        foreach ($bloque as $i => $fecha) {
            $slots[] = [
                'fecha' => $fecha,
                'cupo' => max(1, (int) ($dias[$i]['partidos'] ?? 1)),
                'hora' => (string) ($dias[$i]['hora'] ?? '09:00'),
                'intervalo' => max(1, (int) ($dias[$i]['intervalo'] ?? 90)),
                'cruces' => [],
            ];
        }
    }

    // Repartir por parejas, rotando los días antes de repetir uno.
    $parejas = array_chunk($cruces, 2);
    $indice = 0;
    foreach ($parejas as $k => $pareja) {
        $destino = $k % count($slots);
        $vueltas = 0;
        while ($vueltas < count($slots) && count($slots[$destino]['cruces']) + count($pareja) > $slots[$destino]['cupo']) {
            $destino = ($destino + 1) % count($slots);
            $vueltas++;
        }
        foreach ($pareja as $c) {
            $slots[$destino]['cruces'][] = $c;
        }
        $indice = max($indice, $destino);
    }

    $simultaneos = max(1, count($canchas));
    [$vecesTurno, $acumuladoTurno] = calendario_historial_previo(
        array_map(fn($p) => [
            'local' => (int) ($p['equipo_local'] ?? 0),
            'visitante' => (int) ($p['equipo_visitante'] ?? 0),
            'hora' => (string) ($p['hora'] ?? ''),
        ], $partidosExistentes),
        $dias,
        $simultaneos
    );

    $salida = [];
    foreach ($slots as $slot) {
        if (empty($slot['cruces'])) {
            continue;
        }

        // Se reusa el mismo ordenador de turnos de la temporada regular. Necesita pares
        // de ids, así que se traducen los cruces y luego se vuelven a casar por posición.
        $pares = array_map(fn($c) => [(int) $c['local']['id'], (int) $c['visitante']['id'], false], $slot['cruces']);
        [$ordenados, $vecesTurno, $acumuladoTurno] = calendario_ordenar_turnos($pares, $vecesTurno, $acumuladoTurno, $simultaneos);

        foreach ($ordenados as $k => [$a, $b]) {
            $cruce = null;
            foreach ($slot['cruces'] as $c) {
                if ((int) $c['local']['id'] === (int) $a && (int) $c['visitante']['id'] === (int) $b) {
                    $cruce = $c;
                    break;
                }
            }
            if ($cruce === null) {
                continue;
            }
            $horario = calendario_hora_y_cancha($k, $slot['hora'], $slot['intervalo'], $canchas);
            $salida[] = [
                'cruce' => $cruce,
                'fecha' => $slot['fecha'],
                'hora' => $horario['hora'],
                'cancha' => $horario['cancha'],
            ];
        }
    }

    return $salida;
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
