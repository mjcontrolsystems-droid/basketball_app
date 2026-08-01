<?php
declare(strict_types=1);

/**
 * Pruebas automatizadas de la lógica crítica de la plataforma: el cálculo de la tabla
 * de posiciones y el marcador derivado de eventos. Son las dos piezas donde un error
 * pasa desapercibido más tiempo y hace más daño (clasificaciones incorrectas).
 *
 * No necesitan base de datos ni servidor: solo PHP.
 *
 * Uso:  php scripts/tests.php
 * Sale con código 0 si todo pasa, 1 si algo falla (útil para CI).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos (CLI).');
}

require_once __DIR__ . '/../includes/tabla.php';
require_once __DIR__ . '/../includes/liga.php';

$fallos = 0;
$pruebas = 0;

function check(string $desc, mixed $obtenido, mixed $esperado): void
{
    global $fallos, $pruebas;
    $pruebas++;
    $ok = $obtenido === $esperado;
    if (!$ok) {
        $fallos++;
    }
    printf("%s | %s\n", $ok ? '  OK  ' : ' FALLA', $desc);
    if (!$ok) {
        printf("        obtenido: %s\n        esperado: %s\n", var_export($obtenido, true), var_export($esperado, true));
    }
}

// ---------------------------------------------------------------------------
echo "== calcular_tabla: fútbol (3-1-0, con empates) ==\n";

$equipos = [
    ['id' => 1, 'nombre' => 'Leones', 'ciudad' => ''],
    ['id' => 2, 'nombre' => 'Tigres', 'ciudad' => ''],
    ['id' => 3, 'nombre' => 'Pumas', 'ciudad' => ''],
];
$reglasFutbol = ['permite_empates' => true, 'puntos_victoria' => 3, 'puntos_empate' => 1, 'puntos_derrota' => 0];
$partidos = [
    // Leones 2-0 Tigres | Tigres 1-1 Pumas | Pumas 0-1 Leones
    ['id' => 10, 'fase' => 'grupos', 'estado' => 'jugado', 'fecha' => '2026-07-01', 'hora' => '10:00', 'equipo_local' => 1, 'equipo_visitante' => 2, 'marcador_local' => 2, 'marcador_visitante' => 0],
    ['id' => 11, 'fase' => 'grupos', 'estado' => 'jugado', 'fecha' => '2026-07-08', 'hora' => '10:00', 'equipo_local' => 2, 'equipo_visitante' => 3, 'marcador_local' => 1, 'marcador_visitante' => 1],
    ['id' => 12, 'fase' => 'grupos', 'estado' => 'jugado', 'fecha' => '2026-07-15', 'hora' => '10:00', 'equipo_local' => 3, 'equipo_visitante' => 1, 'marcador_local' => 0, 'marcador_visitante' => 1],
    // Programado: no debe contar
    ['id' => 13, 'fase' => 'grupos', 'estado' => 'programado', 'fecha' => '2026-07-22', 'hora' => '10:00', 'equipo_local' => 1, 'equipo_visitante' => 3, 'marcador_local' => null, 'marcador_visitante' => null],
    // Playoff: no debe contar para la tabla
    ['id' => 14, 'fase' => 'final', 'estado' => 'jugado', 'fecha' => '2026-08-01', 'hora' => '10:00', 'equipo_local' => 1, 'equipo_visitante' => 2, 'marcador_local' => 0, 'marcador_visitante' => 5],
];

$tabla = calcular_tabla($equipos, $partidos, $reglasFutbol);
$porNombre = [];
foreach ($tabla as $fila) {
    $porNombre[$fila['equipo']['nombre']] = $fila;
}

check('Leones lidera con 6 pts (2 victorias)', $porNombre['Leones']['pts'], 6);
check('Leones posición 1', $porNombre['Leones']['posicion'], 1);
check('Tigres y Pumas con 1 pt (empate entre sí)', [$porNombre['Tigres']['pts'], $porNombre['Pumas']['pts']], [1, 1]);
check('Leones PJ=2 (el programado y la final no cuentan)', $porNombre['Leones']['pj'], 2);
check('Leones DIF=+3 (3 a favor, 0 en contra)', $porNombre['Leones']['dif'], 3);
check('Tigres registra el empate (PE=1)', $porNombre['Tigres']['pe'], 1);
check('Desempate por DIF: Pumas (-1) arriba de Tigres (-2)', [$porNombre['Pumas']['posicion'], $porNombre['Tigres']['posicion']], [2, 3]);

// ---------------------------------------------------------------------------
echo "\n== calcular_tabla: basketball (2-0-1, sin empates) ==\n";

$reglasBasket = ['permite_empates' => false, 'puntos_victoria' => 2, 'puntos_empate' => 0, 'puntos_derrota' => 1];
$partidosBasket = [
    ['id' => 20, 'fase' => 'grupos', 'estado' => 'jugado', 'fecha' => '2026-07-01', 'hora' => '10:00', 'equipo_local' => 1, 'equipo_visitante' => 2, 'marcador_local' => 60, 'marcador_visitante' => 55],
    ['id' => 21, 'fase' => 'grupos', 'estado' => 'jugado', 'fecha' => '2026-07-08', 'hora' => '10:00', 'equipo_local' => 2, 'equipo_visitante' => 1, 'marcador_local' => 70, 'marcador_visitante' => 40],
];
$tablaB = calcular_tabla($equipos, $partidosBasket, $reglasBasket);
$porNombreB = [];
foreach ($tablaB as $fila) {
    $porNombreB[$fila['equipo']['nombre']] = $fila;
}
check('Basketball: 1 victoria + 1 derrota = 3 pts (2+1)', $porNombreB['Leones']['pts'], 3);
check('Basketball: Tigres también 3 pts, desempata DIF', $porNombreB['Tigres']['posicion'], 1);
check('Pumas sin jugar: 0 pts y última posición', [$porNombreB['Pumas']['pts'], $porNombreB['Pumas']['posicion']], [0, 3]);

// ---------------------------------------------------------------------------
echo "\n== marcador_desde_eventos: fútbol ==\n";

$evGol = fn(int $equipo, string $tipo = 'jugada') => ['tipo' => 'gol', 'equipo_id' => $equipo, 'tipo_gol' => $tipo];

check('2 goles local, 1 visitante', marcador_desde_eventos([$evGol(1), $evGol(1), $evGol(2)], 1, 2, 'futbol'), [2, 1]);
check('autogol del local suma al visitante', marcador_desde_eventos([$evGol(1, 'autogol')], 1, 2, 'futbol'), [0, 1]);
check('autogol del visitante suma al local', marcador_desde_eventos([$evGol(2, 'autogol')], 1, 2, 'futbol'), [1, 0]);
check('tarjetas y cambios no suman', marcador_desde_eventos([
    ['tipo' => 'amarilla', 'equipo_id' => 1],
    ['tipo' => 'cambio', 'equipo_id' => 2],
], 1, 2, 'futbol'), [0, 0]);
check('sin eventos: 0-0', marcador_desde_eventos([], 1, 2, 'futbol'), [0, 0]);

// ---------------------------------------------------------------------------
echo "\n== marcador_desde_eventos: basketball (1/2/3 puntos) ==\n";

check('libre+doble+triple local = 6', marcador_desde_eventos([
    $evGol(1, 'libre'), $evGol(1, 'doble'), $evGol(1, 'triple'),
], 1, 2, 'basketball'), [6, 0]);
check('triple visitante = 0-3', marcador_desde_eventos([$evGol(2, 'triple')], 1, 2, 'basketball'), [0, 3]);
check('tipo desconocido vale 1 (no revienta)', marcador_desde_eventos([$evGol(1, 'rarisimo')], 1, 2, 'basketball'), [1, 0]);

// ---------------------------------------------------------------------------
echo "\n== faltas_por_jugador: expulsión por acumulación ==\n";

$faltas = [];
for ($i = 0; $i < 5; $i++) {
    $faltas[] = ['tipo' => 'amarilla', 'jugador_id' => 7, 'equipo_id' => 1];
}
$faltas[] = ['tipo' => 'amarilla', 'jugador_id' => 8, 'equipo_id' => 1];
$conteo = faltas_por_jugador($faltas);
check('jugador 7 acumula 5 faltas (límite FIBA)', $conteo[7], LIMITE_FALTAS_EXPULSION);
check('jugador 8 solo 1', $conteo[8], 1);

// ---------------------------------------------------------------------------
echo "\n== Reglas por deporte: expulsión y duración de periodos ==\n";

check('fútbol: expulsión a las 2 amarillas (IFAB)', limite_faltas_expulsion('futbol'), 2);
check('basketball: expulsión a las 5 faltas (FIBA)', limite_faltas_expulsion('basketball'), 5);

check('fútbol 11 reglamentario: 45 min', torneo_duracion_periodo_min(['deporte' => 'futbol', 'modalidad' => 'futbol11']), 45);
check('fútbol 7 reglamentario: 25 min', torneo_duracion_periodo_min(['deporte' => 'futbol', 'modalidad' => 'futbol7']), 25);
check('fútbol sala reglamentario: 20 min', torneo_duracion_periodo_min(['deporte' => 'futbol', 'modalidad' => 'futbol5']), 20);
check('basketball FIBA reglamentario: 10 min', torneo_duracion_periodo_min(['deporte' => 'basketball', 'modalidad' => 'fiba']), 10);
check('basketball NBA: 12 min', torneo_duracion_periodo_min(['deporte' => 'basketball', 'modalidad' => 'nba']), 12);
check('duración personalizada gana a la reglamentaria', torneo_duracion_periodo_min(['deporte' => 'basketball', 'modalidad' => 'fiba', 'duracion_periodo_min' => 15]), 15);
check('copa vieja sin modalidad: default del deporte', torneo_duracion_periodo_min(['deporte' => 'futbol']), 45);
check('modalidad inválida cae al default', torneo_duracion_periodo_min(['deporte' => 'basketball', 'modalidad' => 'rarisima']), 10);

check('periodos fútbol: 2 tiempos', partido_periodo_maximo('futbol'), 2);
check('periodos basketball: 4 cuartos', partido_periodo_maximo('basketball'), 4);

// ---------------------------------------------------------------------------
echo "\n============================\n";
printf("%d pruebas, %d fallos\n", $pruebas, $fallos);
echo $fallos === 0 ? "TODAS LAS PRUEBAS PASARON\n" : "HAY FALLOS - revisar antes de desplegar\n";
exit($fallos === 0 ? 0 : 1);
