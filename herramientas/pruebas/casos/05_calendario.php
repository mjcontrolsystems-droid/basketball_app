<?php
declare(strict_types=1);

/**
 * Motor de calendario.
 *
 * De aquí sale la temporada entera de un botón, así que un error se multiplica por 120
 * encuentros y se descubre tarde. Ya pasó: la final se generó el 19 de diciembre cuando
 * la copa cerraba el 13, porque los playoffs arrancaban "+7 días" desde un domingo.
 *
 * Lo que se fija: que no se repita ningún cruce, que todos jueguen lo mismo, que nadie
 * juegue dos veces el mismo día, que las fechas excluidas se salten de verdad, y —lo más
 * importante— que con fecha de cierre configurada la temporada ATERRICE en esa fecha.
 */

grupo('Motor de calendario');

$equipos8 = [1, 2, 3, 4, 5, 6, 7, 8];

// Sábados, 4 partidos por fecha: con 8 equipos es exactamente una ronda por fin de semana.
$sabado = [['dia' => 6, 'partidos' => 4, 'hora' => '09:00', 'intervalo' => 90]];

/** Aplana el calendario a una lista simple de partidos con su jornada y su fecha. */
$aplanar = function (array $calendario): array {
    $out = [];
    foreach ($calendario as $jornada) {
        foreach ($jornada['dias'] as $dia) {
            foreach ($dia['partidos'] as $p) {
                $out[] = [
                    'jornada' => (int) $jornada['numero'],
                    'fecha' => (string) $dia['fecha'],
                    'local' => (int) $p['local'],
                    'visitante' => (int) $p['visitante'],
                ];
            }
        }
    }
    return $out;
};

$calendario = calendario_generar($equipos8, [
    'vueltas' => 1,
    'dias' => $sabado,
    'canchas' => ['Cancha 1', 'Cancha 2'],
    'fecha_inicio' => '2026-08-22',   // sábado
    'excluidas' => [],
    'semilla' => 20260822,
]);
$juegos = $aplanar($calendario);

prueba('con 8 equipos a una vuelta salen 7 jornadas y 28 encuentros', function () use ($calendario, $juegos) {
    igual(7, count($calendario), 'jornadas');
    igual(28, count($juegos), 'encuentros');
});

prueba('ningún cruce se repite', function () use ($juegos) {
    $vistos = [];
    foreach ($juegos as $j) {
        $par = min($j['local'], $j['visitante']) . '-' . max($j['local'], $j['visitante']);
        cierto(!isset($vistos[$par]), "el cruce {$par} salió dos veces");
        $vistos[$par] = true;
    }
    igual(28, count($vistos), 'los 28 cruces posibles, cada uno una vez');
});

prueba('todos juegan la misma cantidad de partidos', function () use ($juegos, $equipos8) {
    $cuenta = array_fill_keys($equipos8, 0);
    foreach ($juegos as $j) {
        $cuenta[$j['local']]++;
        $cuenta[$j['visitante']]++;
    }
    foreach ($cuenta as $equipo => $n) {
        igual(7, $n, "partidos del equipo {$equipo}");
    }
});

prueba('nadie juega dos veces en la misma jornada', function () use ($juegos) {
    $porJornada = [];
    foreach ($juegos as $j) {
        foreach ([$j['local'], $j['visitante']] as $equipo) {
            $clave = $j['jornada'] . ':' . $equipo;
            cierto(!isset($porJornada[$clave]), "el equipo {$equipo} juega dos veces en la jornada {$j['jornada']}");
            $porJornada[$clave] = true;
        }
    }
});

prueba('la localía queda repartida', function () use ($juegos, $equipos8) {
    // Con 7 partidos lo justo es 3 o 4 de local. Se comprueba con holgura (2 a 5) porque
    // el reparto depende del sorteo; lo que no puede pasar es que alguien juegue casi
    // todo de local o casi todo de visita.
    $locales = array_fill_keys($equipos8, 0);
    foreach ($juegos as $j) {
        $locales[$j['local']]++;
    }
    foreach ($locales as $equipo => $n) {
        cierto($n >= 2 && $n <= 5, "el equipo {$equipo} juega {$n} de local en 7 partidos");
    }
});

prueba('las jornadas empiezan en la fecha de inicio y siguen cada sábado', function () use ($calendario) {
    $fechas = [];
    foreach ($calendario as $jornada) {
        foreach ($jornada['dias'] as $dia) {
            $fechas[] = (string) $dia['fecha'];
        }
    }
    igual('2026-08-22', $fechas[0], 'la primera fecha es la de inicio');
    igual('2026-08-29', $fechas[1]);
    igual('2026-10-03', $fechas[6], 'la séptima jornada');
    foreach ($fechas as $f) {
        igual('6', date('w', (int) strtotime($f)), "la fecha {$f} debía caer en sábado");
    }
});

prueba('una fecha excluida se salta y la jornada se corre a la semana siguiente', function () use ($equipos8, $sabado, $aplanar) {
    $calendario = calendario_generar($equipos8, [
        'vueltas' => 1,
        'dias' => $sabado,
        'canchas' => ['Cancha 1'],
        'fecha_inicio' => '2026-08-22',
        'excluidas' => ['2026-08-29'],
        'semilla' => 20260822,
    ]);
    $juegos = $aplanar($calendario);

    igual(28, count($juegos), 'no se pierde ningún encuentro');
    foreach ($juegos as $j) {
        cierto($j['fecha'] !== '2026-08-29', 'no debía programarse nada el 29 de agosto');
    }
    igual('2026-09-05', $juegos[array_search(2, array_column($juegos, 'jornada'), true)]['fecha'],
        'la jornada 2 se corre al 5 de septiembre');
});

grupo('La temporada aterriza en la fecha de cierre');

prueba('con fecha de cierre, la última jornada cae exactamente ese día', function () use ($equipos8, $sabado, $aplanar) {
    // Este es el caso que se rompió en producción: el calendario terminaba donde se le
    // acababan los partidos, no donde cerraba la copa. Con 28 encuentros y 13 sábados
    // disponibles, el cupo se reparte (3,3,2,2...) para llenar hasta el final.
    $calendario = calendario_generar($equipos8, [
        'vueltas' => 1,
        'dias' => $sabado,
        'canchas' => ['Cancha 1'],
        'fecha_inicio' => '2026-08-22',
        'excluidas' => [],
        'semilla' => 20260822,
        'fecha_fin' => '2026-11-14',
        'bloques_playoffs' => 0,
    ]);
    $juegos = $aplanar($calendario);

    igual(28, count($juegos), 'se programan todos los encuentros igual');
    igual(13, count($calendario), 'se estiran a los 13 sábados disponibles');

    $fechas = array_column($juegos, 'fecha');
    sort($fechas);
    igual('2026-11-14', end($fechas), 'la última fecha es la de cierre de la copa');
});

prueba('las jornadas cargadas van primero y el cierre queda liviano', function () {
    // 120 partidos en 14 jornadas no da exacto: salen de 9 y de 8, y las de 9 van al
    // principio, cuando la tabla todavía no aprieta.
    $cupos = calendario_cupos_repartidos(120, 14, 9);
    igual(14, count($cupos));
    igual(120, array_sum($cupos), 'no se pierde ni se inventa ningún partido');
    igual(9, $cupos[0], 'la primera es de las cargadas');
    igual(8, $cupos[13], 'la última es de las livianas');
    cierto($cupos[0] >= $cupos[13], 'las cargadas van primero');
});

prueba('el reparto nunca pasa del cupo real de la cancha', function () {
    foreach (calendario_cupos_repartidos(100, 10, 8) as $cupo) {
        cierto($cupo <= 8, 'no se pueden meter más partidos de los que caben en el día');
    }
});

prueba('contar los fines de semana disponibles hasta el cierre', function () {
    igual(13, calendario_bloques_hasta('2026-08-22', [6], '2026-11-14', []),
        'sábados del 22 de agosto al 14 de noviembre, inclusive');
    igual(12, calendario_bloques_hasta('2026-08-22', [6], '2026-11-14', ['2026-08-29']),
        'al saltar un sábado, cabe una jornada menos antes del cierre');
});

grupo('Resumen del fixture');

prueba('los números que se le prometen al organizador antes de generar', function () {
    igual(['jornadas' => 7, 'partidos' => 28, 'descansa' => false], fixture_resumen(8, 1));
    igual(['jornadas' => 15, 'partidos' => 120, 'descansa' => false], fixture_resumen(16, 1));
    igual(['jornadas' => 30, 'partidos' => 240, 'descansa' => false], fixture_resumen(16, 2));
});

prueba('con equipos impares alguien descansa cada jornada', function () {
    $r = fixture_resumen(7, 1);
    igual(7, $r['jornadas'], 'hace falta una jornada más que equipos-1');
    igual(21, $r['partidos']);
    cierto($r['descansa']);
});

prueba('el round robin arma todos los cruces sin repetir', function () {
    $rondas = generar_fixture_round_robin([1, 2, 3, 4, 5, 6], 1);
    $pares = [];
    foreach ($rondas as $ronda) {
        foreach ($ronda as $cruce) {
            $pares[min($cruce[0], $cruce[1]) . '-' . max($cruce[0], $cruce[1])] = true;
        }
    }
    igual(15, count($pares), '6 equipos = 15 cruces posibles');
});
