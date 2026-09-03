<?php
declare(strict_types=1);

/**
 * El mensaje de la jornada para el grupo.
 *
 * Este texto sale de la app y lo lee gente que no entra al sistema: 16 equipos con sus
 * jugadores. Si dice mal una hora, una cancha o a quién no le toca jugar, el error viaja
 * a doscientas personas y ya no se puede corregir. Vale la pena tener el formato fijado.
 */

grupo('Mensaje de la jornada');

$copa = ['nombre' => 'Somos Hall'];

$encuentro = fn(string $fecha, string $hora, string $cancha, string $local, string $visita, array $avisos = []) => [
    'fecha' => $fecha,
    'hora' => $hora,
    'cancha' => $cancha,
    'local' => $local,
    'visitante' => $visita,
    'avisos' => $avisos,
];

prueba('el mensaje completo, tal cual se va a pegar', function () use ($copa, $encuentro) {
    $texto = mensaje_jornada(
        $copa,
        3,
        [$encuentro('2026-09-05', '09:00', 'Cancha 1', 'Promoción 45', 'Promoción 52', ['NO JUEGA: Kerwin (Promoción 45) — Tarjeta roja'])],
        'https://somoshall.com'
    );

    $esperado = implode("\n", [
        '*JORNADA 3* — Somos Hall',
        '',
        '*Sábado 05 Sep*',
        '',
        'Promoción 45  vs  Promoción 52',
        '  09:00 · Cancha 1',
        '  NO JUEGA: Kerwin (Promoción 45) — Tarjeta roja',
        '',
        'Tabla, resultados y nóminas:',
        'https://somoshall.com',
    ]);

    igual($esperado, $texto);
});

prueba('los encuentros se agrupan por día', function () use ($copa, $encuentro) {
    // Una jornada de fin de semana se juega sábado y domingo, y la primera pregunta en el
    // grupo siempre es qué día juega cada quien.
    $texto = mensaje_jornada($copa, 4, [
        $encuentro('2026-09-06', '09:00', 'Cancha 1', 'C', 'D'),
        $encuentro('2026-09-05', '09:00', 'Cancha 1', 'A', 'B'),
    ]);

    $posSabado = strpos($texto, 'Sábado 05 Sep');
    $posDomingo = strpos($texto, 'Domingo 06 Sep');

    cierto($posSabado !== false, 'aparece el sábado');
    cierto($posDomingo !== false, 'aparece el domingo');
    cierto($posSabado < $posDomingo, 'el sábado va antes que el domingo aunque llegaran al revés');
});

prueba('los avisos van pegados a su propio encuentro', function () use ($copa, $encuentro) {
    // Y no en una lista al final: el capitán tiene que ver lo suyo sin leer los avisos de
    // los otros siete partidos.
    $texto = mensaje_jornada($copa, 1, [
        $encuentro('2026-09-05', '09:00', 'Cancha 1', 'A', 'B', ['DEBE Q25.00: Diego (B) — paga antes de jugar']),
        $encuentro('2026-09-05', '10:30', 'Cancha 1', 'C', 'D'),
    ]);

    $posAviso = strpos($texto, 'DEBE Q25.00');
    $posSegundo = strpos($texto, 'C  vs  D');
    cierto($posAviso < $posSegundo, 'el aviso queda arriba del segundo encuentro, con el suyo');
});

prueba('un encuentro sin hora ni cancha no deja renglones vacíos', function () use ($copa, $encuentro) {
    $texto = mensaje_jornada($copa, 1, [$encuentro('2026-09-05', '', '', 'A', 'B')]);
    falso(str_contains($texto, ' · '), 'no queda el separador colgando');
    cierto(str_contains($texto, 'A  vs  B'));
});

prueba('el recordatorio del organizador se agrega al final', function () use ($copa, $encuentro) {
    $texto = mensaje_jornada($copa, 1, [$encuentro('2026-09-05', '09:00', 'Cancha 1', 'A', 'B')], '', 'Lleven la nómina firmada.');
    cierto(str_contains($texto, 'Lleven la nómina firmada.'));
    falso(str_contains($texto, 'Tabla, resultados'), 'sin enlace no se inventa el pie');
});

prueba('una jornada sin encuentros lo dice, no sale un mensaje vacío', function () use ($copa) {
    $texto = mensaje_jornada($copa, 9, []);
    cierto(str_contains($texto, 'JORNADA 9'));
    cierto(str_contains($texto, 'no hay encuentros programados'));
});

grupo('Renglones de aviso');

prueba('el suspendido dice el motivo, no solo que no juega', function () {
    igual(
        'NO JUEGA: Kerwin López (Promoción 45) — Tarjeta roja',
        mensaje_aviso_suspendido('Kerwin López', 'Promoción 45', 'Tarjeta roja')
    );
});

prueba('el deudor dice cuánto y qué tiene que hacer', function () {
    igual(
        'DEBE Q25.00: Diego Pérez (Promoción 52) — paga antes de jugar',
        mensaje_aviso_deudor('Diego Pérez', 'Promoción 52', 'Q25.00')
    );
});

prueba('sin equipo o sin motivo el renglón no queda cojo', function () {
    igual('NO JUEGA: Kerwin', mensaje_aviso_suspendido('Kerwin', '', ''));
    igual('DEBE Q25.00: Diego — paga antes de jugar', mensaje_aviso_deudor('Diego', '', 'Q25.00'));
});
