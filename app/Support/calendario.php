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
function calendario_plan_jornadas(array $equipoIds, int $vueltas, int $cupoPorJornada, ?int $semilla = null, ?array $rondasPrearmadas = null, array $yaProgramados = []): array
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
                $balancear
            );
            if (empty($plan)) {
                continue;
            }

            $punt = calendario_puntuar_plan($plan, $equipoIds);
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
function calendario_puntuar_plan(array $plan, array $equipoIds): string
{
    $dobles = array_fill_keys(array_map('intval', $equipoIds), 0);
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

function calendario_plan_intento(array $equipoIds, int $vueltas, int $cupoPorJornada, ?int $semilla, ?array $rondasPrearmadas, array $yaProgramados, bool $balancearDobles): array
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
    $dobles = [];

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
        (array) ($opciones['ya_programados'] ?? [])
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
