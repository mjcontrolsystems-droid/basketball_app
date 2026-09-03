<?php
declare(strict_types=1);

/**
 * Tabla de posiciones.
 *
 * Es el número que todo el mundo mira y por el que se reclama. Lo que se fija aquí:
 * que los puntos salgan de las reglas de la copa, que un triunfo por default cuente
 * igual que uno jugado, que los partidos de playoffs NO ensucien la tabla regular, y
 * el orden de los desempates.
 */

grupo('Tabla de posiciones');

$equipo = fn(int $id, string $nombre) => ['id' => $id, 'nombre' => $nombre];

$partido = fn(int $id, int $jornada, int $local, int $visitante, ?int $ml, ?int $mv, array $extra = []) => array_merge([
    'id' => $id,
    'jornada' => $jornada,
    'equipo_local' => $local,
    'equipo_visitante' => $visitante,
    'marcador_local' => $ml,
    'marcador_visitante' => $mv,
    'estado' => 'jugado',
    'fase' => 'grupos',
    'fecha' => '2026-08-' . str_pad((string) (21 + $jornada), 2, '0', STR_PAD_LEFT),
    'hora' => '09:00',
], $extra);

// Reglas de fútbol: 3 por victoria, 1 por empate, 0 por derrota.
$futbol = ['permite_empates' => true, 'puntos_victoria' => 3, 'puntos_empate' => 1, 'puntos_derrota' => 0];

$equipos = [$equipo(1, 'Promo 52'), $equipo(2, 'Promo 45'), $equipo(3, 'Promo 41')];

$partidos = [
    $partido(1, 1, 1, 2, 2, 1),                                  // 52 gana 2-1
    $partido(2, 1, 3, 1, 0, 0),                                  // empate 0-0
    $partido(3, 2, 2, 3, 3, 0, ['por_default' => true]),         // 45 gana por W.O.
    $partido(4, 3, 1, 2, 5, 0, ['fase' => 'final']),             // final: no cuenta
];

$tabla = calcular_tabla($equipos, $partidos, $futbol);
$porId = [];
foreach ($tabla as $fila) {
    $porId[$fila['equipo']['id']] = $fila;
}

prueba('los puntos salen de las reglas de la copa', function () use ($porId) {
    igual(4, $porId[1]['pts'], 'una victoria y un empate');
    igual(3, $porId[2]['pts'], 'una victoria y una derrota');
    igual(1, $porId[3]['pts'], 'un empate y una derrota');
});

prueba('un triunfo por default cuenta en la tabla como cualquier otro', function () use ($porId) {
    igual(2, $porId[2]['pj'], 'partidos jugados del que ganó por W.O.');
    igual(1, $porId[2]['pg'], 'el W.O. suma como victoria');
    igual(4, $porId[2]['pf'], 'los 3 goles reglamentarios entran al goleo a favor');
    igual(3, $porId[3]['pc'], 'y en contra del que no se presentó');
});

prueba('los partidos de playoffs no ensucian la tabla regular', function () use ($porId) {
    igual(2, $porId[1]['pj'], 'la final no suma partido jugado');
    igual(2, $porId[1]['pf'], 'los 5 goles de la final no entran a la tabla');
});

prueba('el orden es por puntos', function () use ($tabla) {
    igual(1, $tabla[0]['equipo']['id']);
    igual(2, $tabla[1]['equipo']['id']);
    igual(3, $tabla[2]['equipo']['id']);
    igual(1, $tabla[0]['posicion'], 'la posición se numera desde 1');
});

prueba('con los mismos puntos manda la diferencia de goles', function () use ($equipo, $partido, $futbol) {
    $equipos = [$equipo(1, 'A'), $equipo(2, 'B'), $equipo(3, 'C'), $equipo(4, 'D')];
    $partidos = [
        $partido(1, 1, 1, 2, 5, 0),   // A gana con +5
        $partido(2, 1, 3, 4, 1, 0),   // C gana con +1
    ];
    $tabla = calcular_tabla($equipos, $partidos, $futbol);
    igual(1, $tabla[0]['equipo']['id'], 'A (+5) va antes que C (+1) con los mismos 3 puntos');
    igual(3, $tabla[1]['equipo']['id']);
});

prueba('basketball puntúa distinto: 2 por ganar y 1 por perder', function () use ($equipo, $partido) {
    $reglas = ['permite_empates' => false, 'puntos_victoria' => 2, 'puntos_empate' => 0, 'puntos_derrota' => 1];
    $equipos = [$equipo(1, 'A'), $equipo(2, 'B')];
    $tabla = calcular_tabla($equipos, [$partido(1, 1, 1, 2, 80, 70)], $reglas);
    igual(2, $tabla[0]['pts'], 'el que gana');
    igual(1, $tabla[1]['pts'], 'el que pierde igual suma 1');
});

prueba('un partido programado todavía no cuenta', function () use ($equipo, $partido, $futbol) {
    $equipos = [$equipo(1, 'A'), $equipo(2, 'B')];
    $partidos = [$partido(1, 1, 1, 2, null, null, ['estado' => 'programado'])];
    $tabla = calcular_tabla($equipos, $partidos, $futbol);
    igual(0, $tabla[0]['pj'], 'nadie tiene partidos jugados');
    igual(0, $tabla[0]['pts']);
});

prueba('las jornadas agrupan solo temporada regular', function () use ($partido) {
    $jornadas = partidos_por_jornada([
        $partido(1, 1, 1, 2, 1, 0),
        $partido(2, 1, 3, 4, 1, 0),
        $partido(3, 2, 1, 3, 1, 0),
        $partido(4, 3, 1, 2, 1, 0, ['fase' => 'semifinal']),
    ]);
    igual([1, 2], array_keys($jornadas), 'la semifinal no abre una jornada');
    igual(2, count($jornadas[1]), 'dos encuentros en la jornada 1');
});
