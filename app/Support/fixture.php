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

/**
 * Días de holgura para considerar que dos encuentros son de la misma jornada. Una jornada
 * de fin de semana se juega sábado y domingo y a veces arrastra un pendiente al lunes, así
 * que 2 días cubre el fin de semana completo.
 *
 * No más: con 3 días, una liga que juega miércoles y sábado metería ambas fechas en la
 * misma jornada. La distancia se mide siempre contra la fecha que ABRE cada jornada, no
 * contra el último encuentro, para que la ventana no se vaya corriendo día a día.
 */
const JORNADA_VENTANA_DIAS = 2;

/**
 * Qué jornada le toca a un encuentro según su fecha.
 *
 * El organizador ya no la escribe a mano (podía poner "jornada 30" en un torneo de 5) —
 * se deduce: si la fecha cae dentro de la ventana de una jornada que ya existe, es esa
 * jornada; si es una fecha nueva, se abre la siguiente. La fecha manda y no el orden de
 * captura, así que cargar los encuentros salteados o corregir una fecha vieja sigue
 * dando el número correcto.
 *
 * Se respeta la jornada por fecha aunque quede "apretada": si el organizador programó dos
 * partidos del mismo equipo el mismo día sabrá por qué lo hizo, y forzar otra jornada solo
 * escondería el problema. Lo que sí se evita es inventar números fuera de secuencia.
 *
 * @param array $partidos   Encuentros existentes del torneo.
 * @param string $fecha     Fecha del encuentro que se está guardando (YYYY-MM-DD).
 * @param int $idIgnorar    Al editar, el id del propio encuentro (no debe compararse consigo mismo).
 */
function jornada_por_fecha(array $partidos, string $fecha, int $idIgnorar = 0): int
{
    $marca = strtotime($fecha);
    if ($marca === false) {
        // Sin fecha usable no hay nada que deducir: va a la jornada que sigue.
        return jornada_siguiente($partidos, $idIgnorar);
    }

    // Fecha de referencia de cada jornada ya existente. Se toma la más temprana porque es
    // la que abre la jornada; las demás caen dentro de su ventana.
    $inicioDeJornada = [];
    foreach ($partidos as $p) {
        if ((int) ($p['id'] ?? 0) === $idIgnorar) {
            continue;
        }
        // Los partidos de eliminación directa no llevan jornada de temporada regular.
        if (($p['fase'] ?? 'grupos') !== 'grupos') {
            continue;
        }
        $numero = (int) ($p['jornada'] ?? 0);
        $suMarca = strtotime((string) ($p['fecha'] ?? ''));
        if ($numero < 1 || $suMarca === false) {
            continue;
        }
        if (!isset($inicioDeJornada[$numero]) || $suMarca < $inicioDeJornada[$numero]) {
            $inicioDeJornada[$numero] = $suMarca;
        }
    }

    if (empty($inicioDeJornada)) {
        return 1;
    }

    $ventana = JORNADA_VENTANA_DIAS * 86400;
    foreach ($inicioDeJornada as $numero => $inicio) {
        if (abs($marca - $inicio) <= $ventana) {
            return $numero;
        }
    }

    // Fecha que no encaja en ninguna jornada existente. Si es posterior a todas, abre la
    // siguiente; si es anterior a todas, también va al final: renumerar las jornadas ya
    // publicadas rompería calendarios impresos y enlaces compartidos.
    return max(array_keys($inicioDeJornada)) + 1;
}

/**
 * La jornada siguiente a la última registrada (1 si todavía no hay ninguna).
 */
function jornada_siguiente(array $partidos, int $idIgnorar = 0): int
{
    $maxima = 0;
    foreach ($partidos as $p) {
        if ((int) ($p['id'] ?? 0) === $idIgnorar || ($p['fase'] ?? 'grupos') !== 'grupos') {
            continue;
        }
        $maxima = max($maxima, (int) ($p['jornada'] ?? 0));
    }

    return $maxima + 1;
}

/**
 * Tope aceptable al corregir la jornada a mano: una más que la última existente. Impide
 * saltos absurdos (jornada 30 con 4 jornadas jugadas) sin estorbar una reprogramación real.
 */
function jornada_maxima_permitida(array $partidos, int $idIgnorar = 0): int
{
    return jornada_siguiente($partidos, $idIgnorar);
}
