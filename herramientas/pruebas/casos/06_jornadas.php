<?php
declare(strict_types=1);

/**
 * A qué jornada pertenece un encuentro según su fecha.
 *
 * El organizador ya no escribe la jornada a mano. Si esto se equivoca, un partido
 * reprogramado cae en la jornada que no es y arrastra todo: la hoja de solvencia de esa
 * fecha, las suspensiones (que se cuentan por orden de partidos) y la vigencia de las
 * multas.
 */

grupo('Jornada deducida de la fecha');

// Dos jornadas ya programadas: sábado 22 y sábado 29 de agosto.
$partidos = [
    ['id' => 1, 'jornada' => 1, 'fecha' => '2026-08-22', 'fase' => 'grupos'],
    ['id' => 2, 'jornada' => 1, 'fecha' => '2026-08-23', 'fase' => 'grupos'],
    ['id' => 3, 'jornada' => 2, 'fecha' => '2026-08-29', 'fase' => 'grupos'],
];

prueba('el domingo del mismo fin de semana es la misma jornada', function () use ($partidos) {
    igual(1, jornada_por_fecha($partidos, '2026-08-23'), 'domingo 23');
    igual(1, jornada_por_fecha($partidos, '2026-08-24'), 'un pendiente que se corre al lunes');
});

prueba('el fin de semana siguiente es la jornada que ya existe', function () use ($partidos) {
    igual(2, jornada_por_fecha($partidos, '2026-08-29'));
    igual(2, jornada_por_fecha($partidos, '2026-08-30'));
});

prueba('una fecha nueva abre la jornada siguiente', function () use ($partidos) {
    igual(3, jornada_por_fecha($partidos, '2026-09-05'), 'el sábado que sigue');
    igual(3, jornada_por_fecha($partidos, '2026-12-01'), 'una fecha muy posterior también');
});

prueba('sin encuentros previos, todo es jornada 1', function () {
    igual(1, jornada_por_fecha([], '2026-08-22'));
});

prueba('los playoffs no abren jornadas de temporada regular', function () {
    $conFinal = [
        ['id' => 1, 'jornada' => 1, 'fecha' => '2026-08-22', 'fase' => 'grupos'],
        ['id' => 9, 'jornada' => 5, 'fecha' => '2026-12-13', 'fase' => 'final'],
    ];
    igual(2, jornada_por_fecha($conFinal, '2026-08-29'),
        'la final no cuenta como jornada 5 para deducir la siguiente');
});

prueba('al editar, el encuentro no se compara consigo mismo', function () use ($partidos) {
    // Si el partido 3 se mueve solo a otra fecha, no debe verse a sí mismo como "una
    // jornada que ya existe en esa fecha".
    igual(2, jornada_por_fecha($partidos, '2026-09-05', 3),
        'queda en la jornada 2, que es la siguiente a la 1');
});

grupo('Tope para corregir la jornada a mano');

prueba('solo se puede llegar a una jornada más que la última', function () use ($partidos) {
    igual(3, jornada_maxima_permitida($partidos), 'hay jornadas 1 y 2, el tope es 3');
});

prueba('sin encuentros, el tope es la jornada 1', function () {
    igual(1, jornada_maxima_permitida([]));
});
