<?php
declare(strict_types=1);

/**
 * Generación automática del calendario de temporada regular (todos contra todos).
 *
 * Programar a mano una liga de 10 equipos son 45 encuentros a una vuelta y 90 a ida y
 * vuelta, uno por uno: es el trabajo más tedioso de arrancar una temporada y donde más
 * fácil se cuela un error (un cruce repetido, un equipo que juega dos veces la misma
 * jornada, alguien que nunca juega de local).
 *
 * El algoritmo es el "método del círculo" (round-robin de Berger), el mismo que usan las
 * ligas reales: se fija un equipo y los demás rotan a su alrededor; en cada ronda se
 * enfrenta el equipo de la posición i con el de la posición opuesta. Garantiza que:
 *   - cada par de equipos se enfrenta EXACTAMENTE una vez por vuelta;
 *   - ningún equipo juega dos veces en la misma jornada;
 *   - con número impar de equipos, cada jornada uno descansa (no se le inventa rival).
 */

/**
 * Arma el fixture completo de una temporada regular.
 *
 * @param array<int> $equipoIds Ids de los equipos que participan.
 * @param int $vueltas 1 = solo ida; 2 = ida y vuelta (la vuelta invierte la localía).
 * @return array<int, array<int, array{0:int,1:int}>> Lista de jornadas; cada jornada es una
 *   lista de pares [equipoLocalId, equipoVisitanteId]. La jornada 1 es el índice 0.
 */
function generar_fixture_round_robin(array $equipoIds, int $vueltas = 1): array
{
    $equipos = array_values(array_unique(array_map('intval', $equipoIds)));
    if (count($equipos) < 2) {
        return [];
    }

    $vueltas = max(1, $vueltas);

    // Con número impar de equipos se agrega un rival "fantasma" (null): quien queda
    // emparejado con él simplemente descansa esa jornada.
    $rueda = $equipos;
    if (count($rueda) % 2 !== 0) {
        $rueda[] = null;
    }

    $n = count($rueda);
    $rondasPorVuelta = $n - 1;
    $mitad = intdiv($n, 2);

    $jornadas = [];
    for ($vuelta = 0; $vuelta < $vueltas; $vuelta++) {
        $rotacion = $rueda;
        for ($ronda = 0; $ronda < $rondasPorVuelta; $ronda++) {
            $partidosJornada = [];
            for ($i = 0; $i < $mitad; $i++) {
                $a = $rotacion[$i];
                $b = $rotacion[$n - 1 - $i];
                if ($a === null || $b === null) {
                    continue; // ese equipo descansa esta jornada
                }

                // El equipo fijo (posición 0) siempre caería de local sin este ajuste, y
                // su rival de turno nunca jugaría en casa. Alternar la localía de ESE
                // cruce en rondas impares reparte los partidos de local de forma pareja.
                [$local, $visitante] = ($i === 0 && $ronda % 2 === 1) ? [$b, $a] : [$a, $b];

                // Vuelta de regreso: se repite el mismo cruce con la localía invertida.
                if ($vuelta % 2 === 1) {
                    [$local, $visitante] = [$visitante, $local];
                }

                $partidosJornada[] = [$local, $visitante];
            }
            $jornadas[] = $partidosJornada;

            // Rotación del círculo: el primero queda fijo y el resto gira una posición.
            $fijo = array_shift($rotacion);
            $ultimo = array_pop($rotacion);
            array_unshift($rotacion, $ultimo);
            array_unshift($rotacion, $fijo);
        }
    }

    return $jornadas;
}

/**
 * Cuántas jornadas y cuántos encuentros saldrían con estos equipos, para poder avisarlo
 * ANTES de generar (y no dejar al organizador con 90 partidos que no esperaba).
 *
 * @return array{jornadas:int, partidos:int, descansa:bool}
 */
function fixture_resumen(int $cantidadEquipos, int $vueltas = 1): array
{
    if ($cantidadEquipos < 2) {
        return ['jornadas' => 0, 'partidos' => 0, 'descansa' => false];
    }
    $vueltas = max(1, $vueltas);
    $impar = $cantidadEquipos % 2 !== 0;
    $n = $impar ? $cantidadEquipos + 1 : $cantidadEquipos;

    return [
        'jornadas' => ($n - 1) * $vueltas,
        'partidos' => (int) (($cantidadEquipos * ($cantidadEquipos - 1) / 2) * $vueltas),
        'descansa' => $impar,
    ];
}

/**
 * Vueltas configuradas para la competencia (1 = solo ida, 2 = ida y vuelta). Las copas
 * creadas antes de que existiera el campo quedan en 1, que es como venían funcionando.
 */
function torneo_vueltas(array $torneo): int
{
    return ((int) ($torneo['vueltas'] ?? 1)) === 2 ? 2 : 1;
}

function torneo_vueltas_label(array $torneo): string
{
    return torneo_vueltas($torneo) === 2 ? 'Ida y vuelta' : 'Una vuelta';
}
