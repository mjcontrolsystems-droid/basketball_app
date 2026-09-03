<?php
declare(strict_types=1);

/**
 * Vigencia de las multas.
 *
 * La regla de la liga: la multa que nace en la jornada N se paga ANTES de la jornada N+1.
 * En la hoja de solvencia de la jornada N el jugador todavía está limpio — la tarjeta
 * ocurrió jugando esa misma fecha.
 *
 * Esto ya se rompió una vez: el #22 aparecía moroso en la jornada 2 (la que jugó cuando
 * lo sancionaron) y hasta en la jornada 1, que ya se había jugado. Un jugador marcado
 * como deudor no puede entrar a la cancha, así que el error deja gente fuera sin motivo.
 */

grupo('Vigencia de las multas');

// Tres jornadas, un partido cada una.
$partidos = [
    ['id' => 1, 'jornada' => 1],
    ['id' => 2, 'jornada' => 2],
    ['id' => 3, 'jornada' => 3],
];

$multa = fn(int $jugador, int $partidoId, float $monto = 25.0) => [
    'jugador_id' => $jugador,
    'partido_id' => $partidoId,
    'monto' => $monto,
];

prueba('la multa de la jornada 2 no se cobra en la jornada 1', function () use ($partidos, $multa) {
    $deuda = sanciones_filtrar_vigentes([$multa(22, 2)], $partidos, 1);
    igual([], $deuda, 'esa jornada ya se jugó, no puede nacer deuda hacia atrás');
});

prueba('la multa de la jornada 2 tampoco se cobra en la jornada 2', function () use ($partidos, $multa) {
    $deuda = sanciones_filtrar_vigentes([$multa(22, 2)], $partidos, 2);
    igual([], $deuda, 'la tarjeta fue jugando esa misma fecha: todavía no vence');
});

prueba('la multa de la jornada 2 SÍ se cobra en la jornada 3', function () use ($partidos, $multa) {
    $deuda = sanciones_filtrar_vigentes([$multa(22, 2)], $partidos, 3);
    igual(1, count($deuda), 'el jugador aparece como deudor');
    igual(25.0, $deuda[22]['total']);
    igual(1, $deuda[22]['cantidad']);
});

prueba('varias multas del mismo jugador se suman', function () use ($partidos, $multa) {
    $deuda = sanciones_filtrar_vigentes(
        [$multa(22, 1, 25.0), $multa(22, 2, 50.0)],
        $partidos,
        3
    );
    igual(75.0, $deuda[22]['total']);
    igual(2, $deuda[22]['cantidad']);
});

prueba('cada jugador lleva su propia cuenta', function () use ($partidos, $multa) {
    $deuda = sanciones_filtrar_vigentes(
        [$multa(22, 1, 25.0), $multa(30, 1, 10.0)],
        $partidos,
        2
    );
    igual(25.0, $deuda[22]['total']);
    igual(10.0, $deuda[30]['total']);
});

prueba('una multa sin partido reconocible se cobra siempre', function () use ($partidos, $multa) {
    // Dato roto (el partido se borró, la sanción quedó huérfana). Se prefiere exigir de
    // más en un caso raro que dejar pasar a un moroso por un registro incompleto.
    $deuda = sanciones_filtrar_vigentes([$multa(22, 999)], $partidos, 1);
    igual(1, count($deuda), 'aparece aunque no se sepa de qué jornada viene');
});

prueba('la hoja de una jornada futura arrastra todo lo anterior', function () use ($partidos, $multa) {
    $deuda = sanciones_filtrar_vigentes(
        [$multa(22, 1), $multa(30, 2), $multa(40, 3)],
        $partidos,
        3
    );
    cierto(isset($deuda[22]), 'la de la jornada 1 se debe');
    cierto(isset($deuda[30]), 'la de la jornada 2 también');
    falso(isset($deuda[40]), 'la de la jornada 3 todavía no');
});
