<?php
declare(strict_types=1);

/**
 * Marcador calculado desde la ficha, W.O. y portería menos vencida.
 *
 * El marcador ya no se captura a mano: sale de los goles registrados. Eso quiere decir
 * que un error aquí cambia la tabla sin que nadie toque la tabla. El autogol es el caso
 * con regla no obvia (se le anota al que la metió en propia, pero el gol es del rival) y
 * el W.O. es el que no debe repartir goles a nadie.
 */

grupo('Marcador y W.O.');

$gol = fn(int $equipoId, string $tipoGol = 'jugada') => [
    'tipo' => 'gol',
    'equipo_id' => $equipoId,
    'tipo_gol' => $tipoGol,
];

prueba('cada gol suma a su equipo', function () use ($gol) {
    [$local, $visitante] = marcador_desde_eventos(
        [$gol(10), $gol(10), $gol(20)],
        10,
        20
    );
    igual(2, $local);
    igual(1, $visitante);
});

prueba('el autogol se le suma al RIVAL del que lo metió', function () use ($gol) {
    // El evento se registra al jugador que la metió en propia (equipo 10), pero el gol
    // es del visitante. Si esto se invierte, el marcador queda al revés y nadie lo nota
    // hasta que el equipo reclama.
    [$local, $visitante] = marcador_desde_eventos([$gol(10, 'autogol')], 10, 20);
    igual(0, $local, 'el que lo metió en propia no suma');
    igual(1, $visitante, 'el rival sí');
});

prueba('en basketball cada canasta vale lo suyo', function () use ($gol) {
    [$local, $visitante] = marcador_desde_eventos(
        [$gol(10, 'libre'), $gol(10, 'doble'), $gol(10, 'triple'), $gol(20, 'doble')],
        10,
        20,
        'basketball'
    );
    igual(6, $local, '1 + 2 + 3');
    igual(2, $visitante);
});

prueba('en basketball no existe el autogol', function () use ($gol) {
    // 'autogol' no está en el catálogo de basketball; si llegara por un dato viejo, la
    // anotación tiene que quedarse en su equipo y no cruzarse.
    [$local] = marcador_desde_eventos([$gol(10, 'autogol')], 10, 20, 'basketball');
    igual(1, $local, 'vale 1 punto y se queda donde está');
});

prueba('las tarjetas y los cambios no tocan el marcador', function () use ($gol) {
    $eventos = [
        $gol(10),
        ['tipo' => 'amarilla', 'equipo_id' => 10],
        ['tipo' => 'roja', 'equipo_id' => 20],
        ['tipo' => 'cambio', 'equipo_id' => 20],
    ];
    igual([1, 0], marcador_desde_eventos($eventos, 10, 20));
});

prueba('el marcador reglamentario del W.O. depende del deporte', function () {
    igual([3, 0], marcador_por_default('futbol'));
    igual([3, 0], marcador_por_default(null), 'sin deporte se asume fútbol');
    igual([20, 0], marcador_por_default('basketball'), 'regla FIBA');
});

grupo('Portería menos vencida');

$jugado = fn(int $id, int $local, int $visitante, int $ml, int $mv, array $extra = []) => array_merge([
    'id' => $id,
    'equipo_local' => $local,
    'equipo_visitante' => $visitante,
    'marcador_local' => $ml,
    'marcador_visitante' => $mv,
    'estado' => 'jugado',
], $extra);

prueba('un W.O. no cuenta para la portería menos vencida', function () use ($jugado) {
    // El 3-0 es reglamentario: ni el que ganó defendió nada ni al que no se presentó le
    // marcaron tres. Si esto entra, el premio se lo lleva quien más W.O. le regalaron.
    $equiposPorId = [
        1 => ['id' => 1, 'nombre' => 'A'],
        2 => ['id' => 2, 'nombre' => 'B'],
        3 => ['id' => 3, 'nombre' => 'C'],
    ];
    $partidos = [
        $jugado(1, 1, 2, 1, 0),
        $jugado(2, 3, 1, 3, 0, ['por_default' => true]),
    ];

    $ranking = calcular_porteria_menos_vencida($equiposPorId, $partidos, []);

    igual(2, count($ranking), 'solo A y B tienen partidos válidos; C solo tuvo el W.O.');
    igual(1, $ranking[0]['equipo']['id'], 'A no recibió goles');
    igual(0, $ranking[0]['goles_contra']);
    igual(1, $ranking[0]['jugados'], 'el W.O. tampoco le suma partido a A');
    igual(1, $ranking[1]['goles_contra'], 'B recibió uno');
});

prueba('ordena por promedio, no por total de goles recibidos', function () use ($jugado) {
    // Un equipo con 4 goles en 4 partidos está mejor que uno con 3 en 2. Ordenar por
    // total premiaría a quien ha jugado menos.
    $equiposPorId = [
        1 => ['id' => 1, 'nombre' => 'Cuatro partidos'],
        2 => ['id' => 2, 'nombre' => 'Dos partidos'],
        9 => ['id' => 9, 'nombre' => 'Rival'],
    ];
    $partidos = [
        $jugado(1, 1, 9, 0, 1),
        $jugado(2, 1, 9, 0, 1),
        $jugado(3, 1, 9, 0, 1),
        $jugado(4, 1, 9, 0, 1),   // equipo 1: 4 GC en 4 -> promedio 1.0
        $jugado(5, 2, 9, 0, 2),
        $jugado(6, 2, 9, 0, 1),   // equipo 2: 3 GC en 2 -> promedio 1.5
    ];

    $ranking = calcular_porteria_menos_vencida($equiposPorId, $partidos, []);

    $puesto = [];
    foreach ($ranking as $i => $fila) {
        $puesto[(int) $fila['equipo']['id']] = $i;
    }

    cierto($puesto[1] < $puesto[2], 'el de 4 goles en 4 partidos va antes que el de 3 en 2');
    igual(1.0, $ranking[$puesto[1]]['promedio'], 'promedio del equipo 1');
    igual(1.5, $ranking[$puesto[2]]['promedio'], 'promedio del equipo 2');
});
