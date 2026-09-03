<?php
declare(strict_types=1);

/**
 * Corredor de pruebas de la lógica crítica de la liga.
 *
 * Qué es esto y por qué existe
 * ----------------------------
 * Hay cuatro cálculos en esta app que, si se equivocan, NO se ven en pantalla: se
 * descubren cuando un capitán reclama. La tabla de posiciones, las suspensiones, la
 * vigencia de las multas y el motor de calendario. Ya pasó dos veces esta temporada:
 * la final se generó una semana después de la fecha de cierre, y la plantilla del
 * reporte por equipo salió vacía sin que nada avisara.
 *
 * Estas pruebas fijan por escrito lo que cada regla DEBE hacer. Se corren en segundos,
 * antes de subir un cambio, y si una falla el error está en lo que se acaba de tocar.
 *
 * Cómo se corre
 * -------------
 *   Doble clic en Probar.bat            (Windows)
 *   php herramientas/pruebas/correr.php (consola)
 *
 * Devuelve código de salida 1 si algo falla, para poder engancharlo a un hook de git
 * o a un despliegue más adelante.
 *
 * NO toca la base de datos ni levanta sesión: solo carga los archivos de reglas puras y
 * les pasa datos inventados. Es seguro correrlo en cualquier momento, incluso a mitad de
 * temporada, porque no puede escribir nada en ningún lado.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Las pruebas solo se corren desde la consola.\n");
}

define('RAIZ_APP', dirname(__DIR__, 2));

// A propósito NO se carga config/config.php: ese archivo abre sesión, manda cabeceras y
// prepara la conexión. Aquí solo hacen falta los archivos de reglas, que son funciones
// puras (entran arrays, salen arrays) y no tocan nada de fuera.
foreach ([
    '/app/Support/fixture.php',
    '/app/Support/tabla.php',
    '/app/Support/liga.php',
    '/app/Support/disciplina.php',
    '/app/Support/calendario.php',
    '/app/Models/Sancion.php',
] as $archivo) {
    require_once RAIZ_APP . $archivo;
}

final class PruebaFallida extends RuntimeException
{
}

$GLOBALS['__pruebas'] = ['ok' => 0, 'fallos' => [], 'grupo' => ''];

/**
 * Encabezado para agrupar pruebas del mismo tema en la salida.
 */
function grupo(string $nombre): void
{
    $GLOBALS['__pruebas']['grupo'] = $nombre;
    echo PHP_EOL . '  ' . $nombre . PHP_EOL;
}

/**
 * Un caso. Si el cuerpo lanza, se anota el fallo y se sigue con los demás: interesa ver
 * TODO lo que está roto de una sola corrida, no el primer error y parar.
 */
function prueba(string $nombre, callable $caso): void
{
    try {
        $caso();
        $GLOBALS['__pruebas']['ok']++;
        echo '    [ok]    ' . $nombre . PHP_EOL;
    } catch (Throwable $e) {
        $GLOBALS['__pruebas']['fallos'][] = [
            'grupo' => $GLOBALS['__pruebas']['grupo'],
            'nombre' => $nombre,
            'detalle' => $e->getMessage(),
            'donde' => basename($e->getFile()) . ':' . $e->getLine(),
        ];
        echo '    [FALLA] ' . $nombre . PHP_EOL;
        echo '            ' . $e->getMessage() . PHP_EOL;
    }
}

function describir($valor): string
{
    if (is_array($valor)) {
        return str_replace(["\n", '  '], ['', ''], var_export($valor, true));
    }
    if (is_bool($valor)) {
        return $valor ? 'true' : 'false';
    }
    if ($valor === null) {
        return 'null';
    }
    return (string) $valor;
}

/**
 * Igualdad estricta, salvo entre números decimales, donde se compara con tolerancia:
 * un promedio de goles calculado con divisiones no da nunca el mismo bit dos veces.
 */
function igual($esperado, $real, string $que = ''): void
{
    $iguales = (is_float($esperado) || is_float($real))
        ? abs((float) $esperado - (float) $real) < 0.000001
        : $esperado === $real;

    if (!$iguales) {
        throw new PruebaFallida(
            ($que !== '' ? $que . ': ' : '')
            . 'esperaba ' . describir($esperado) . ' y salió ' . describir($real)
        );
    }
}

function cierto($condicion, string $que = ''): void
{
    if ($condicion !== true) {
        throw new PruebaFallida(($que !== '' ? $que : 'la condición') . ' debía cumplirse y no se cumplió');
    }
}

function falso($condicion, string $que = ''): void
{
    if ($condicion !== false) {
        throw new PruebaFallida(($que !== '' ? $que : 'la condición') . ' NO debía cumplirse y se cumplió');
    }
}

// ---------- Corrida ----------

echo PHP_EOL . 'Pruebas de la lógica de la liga' . PHP_EOL;
echo str_repeat('-', 52) . PHP_EOL;

$casos = glob(__DIR__ . '/casos/*.php') ?: [];
sort($casos);
foreach ($casos as $caso) {
    require $caso;
}

$ok = $GLOBALS['__pruebas']['ok'];
$fallos = $GLOBALS['__pruebas']['fallos'];

echo PHP_EOL . str_repeat('-', 52) . PHP_EOL;
if (empty($fallos)) {
    echo "TODO BIEN — {$ok} pruebas pasaron." . PHP_EOL . PHP_EOL;
    exit(0);
}

echo 'FALLARON ' . count($fallos) . ' de ' . ($ok + count($fallos)) . ' pruebas:' . PHP_EOL . PHP_EOL;
foreach ($fallos as $f) {
    echo '  * ' . $f['grupo'] . ' — ' . $f['nombre'] . PHP_EOL;
    echo '    ' . $f['detalle'] . PHP_EOL;
    echo '    (' . $f['donde'] . ')' . PHP_EOL . PHP_EOL;
}
exit(1);
