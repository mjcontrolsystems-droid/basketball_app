<?php
declare(strict_types=1);

/**
 * Importación de plantillas desde Excel o CSV.
 *
 * Cargar 16 equipos de 12 jugadores a mano son casi doscientas capturas, y el organizador
 * casi siempre ya tiene esa lista en un Excel que le pasó el delegado. El problema es que
 * cada Excel viene distinto: unos traen DPI, otros teléfono, otros el dorsal en una
 * columna llamada "No." y otros en una llamada "Camisa".
 *
 * Por eso la detección NO se fía solo del encabezado. Mira también la FORMA del dato, que
 * es mucho más confiable: un DPI guatemalteco son 13 dígitos y un dorsal tiene 1 o 2, así
 * que una columna llena de números de 13 dígitos no es el dorsal por más que el encabezado
 * diga "No.". Y pase lo que pase, el organizador ve una vista previa y puede corregir cada
 * columna antes de que se cree nada.
 *
 * El DPI nunca se importa: la app no tiene dónde guardarlo y es dato sensible de personas
 * que no tiene por qué terminar en una base de datos de fútbol.
 */

const IMPORTACION_MAX_FILAS = 500;

/**
 * Encabezados que delatan cada columna. Se comparan sin tildes y en minúsculas.
 */
// El apellido va aparte del nombre a propósito: muchos Excel traen "Nombres" y
// "Apellidos" en columnas distintas, y quedarse solo con la primera dejaría a todos los
// jugadores registrados con el nombre de pila. Cuando existen las dos, se juntan.
const IMPORTACION_PISTAS = [
    'nombre' => ['nombre', 'nombres', 'jugador', 'jugadora', 'nombre completo', 'nombre y apellido', 'nombre del jugador'],
    'apellido' => ['apellido', 'apellidos', 'apellido paterno', 'apellidos del jugador'],
    // "uniforme" y "camisola" salieron de nóminas reales de papifútbol.
    'dorsal' => ['dorsal', 'numero', 'num', 'no', 'n', '#', 'camisa', 'camiseta', 'camisola',
        'playera', 'uniforme', 'numero de camisa', 'numero de uniforme', 'numero de camisola'],
    'posicion' => ['posicion', 'puesto', 'pos'],
];

/**
 * Columnas que se ignoran siempre, aunque el dato encaje: son datos personales que la app
 * no guarda ni necesita.
 */
// "antiguedad" es el número de socio del club: se parece muchísimo a un dorsal (a veces
// hasta coincide) pero no lo es, y aparece en casi todas las nóminas reales. "talla"
// también engaña porque a veces trae números.
const IMPORTACION_IGNORAR = [
    'dpi', 'cui', 'cedula', 'identificacion', 'identidad', 'nit', 'pasaporte',
    'telefono', 'celular', 'movil', 'direccion', 'correo', 'email', 'e-mail',
    'fecha de nacimiento', 'nacimiento', 'edad', 'tipo de sangre', 'sangre',
    'antiguedad', 'numero de antiguedad', 'no. de antiguedad', 'talla',
];

/**
 * Quita tildes y pasa a minúsculas, para comparar encabezados sin depender de cómo los
 * escribió cada quien ("Posición", "POSICION", "posicion").
 */
function importacion_normalizar(string $texto): string
{
    $texto = mb_strtolower(trim($texto));
    $de = ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'];
    $a = ['a', 'e', 'i', 'o', 'u', 'u', 'n'];

    return str_replace($de, $a, $texto);
}

/**
 * Lee un archivo subido y lo devuelve como tabla de filas.
 *
 * @return array<int, array<int, string>>
 * @throws RuntimeException si el archivo no se puede leer.
 */
function importacion_leer_archivo(string $rutaTemporal, string $nombreOriginal): array
{
    $extension = mb_strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));

    if (in_array($extension, ['csv', 'txt'], true)) {
        return importacion_leer_csv($rutaTemporal);
    }
    if ($extension === 'xlsx') {
        return importacion_leer_xlsx($rutaTemporal);
    }
    if ($extension === 'xls') {
        throw new RuntimeException('El formato .xls es muy antiguo y no se puede leer. Abre el archivo en Excel y guárdalo como .xlsx o como CSV.');
    }

    throw new RuntimeException('Solo se pueden importar archivos .xlsx o .csv. El que subiste es .' . $extension . '.');
}

/**
 * @return array<int, array<int, string>>
 */
function importacion_leer_csv(string $ruta): array
{
    $manejador = @fopen($ruta, 'r');
    if ($manejador === false) {
        throw new RuntimeException('No se pudo abrir el archivo.');
    }

    // Excel en español guarda los CSV separados por punto y coma, no por coma. Se detecta
    // con la primera línea en vez de asumir uno de los dos.
    $primera = fgets($manejador) ?: '';
    $separador = substr_count($primera, ';') > substr_count($primera, ',') ? ';' : ',';
    rewind($manejador);

    // Excel escribe los CSV en Windows-1252, no en UTF-8: sin esto los apellidos con
    // tilde o con ñ llegan rotos.
    $filas = [];
    while (($fila = fgetcsv($manejador, 0, $separador)) !== false) {
        if (count($filas) >= IMPORTACION_MAX_FILAS) {
            break;
        }
        $filas[] = array_map(function ($celda) {
            $celda = (string) $celda;
            if (!mb_check_encoding($celda, 'UTF-8')) {
                $celda = mb_convert_encoding($celda, 'UTF-8', 'Windows-1252');
            }
            // El BOM que Excel pone al inicio se colaría en el primer encabezado.
            return trim(str_replace("\u{FEFF}", '', $celda));
        }, $fila);
    }
    fclose($manejador);

    return $filas;
}

/**
 * Lee la primera hoja de un .xlsx.
 *
 * Un Excel moderno es un ZIP con XML adentro: la hoja está en xl/worksheets/sheet1.xml y
 * los textos, para no repetirlos, viven aparte en xl/sharedStrings.xml y en la hoja solo
 * aparece su número. Por eso hay que leer los dos archivos.
 *
 * @return array<int, array<int, string>>
 */
function importacion_leer_xlsx(string $ruta): array
{
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('El servidor no puede abrir archivos .xlsx en este momento. Guarda el Excel como CSV y súbelo así.');
    }

    $zip = new ZipArchive();
    if ($zip->open($ruta) !== true) {
        throw new RuntimeException('El archivo no se pudo abrir: puede estar dañado o no ser un Excel.');
    }

    // Textos compartidos.
    $textos = [];
    $xmlTextos = $zip->getFromName('xl/sharedStrings.xml');
    if ($xmlTextos !== false) {
        $doc = @simplexml_load_string($xmlTextos);
        if ($doc !== false) {
            foreach ($doc->si as $si) {
                // Una celda con formato mezclado parte el texto en varios <t>; hay que
                // pegarlos o se pierde media palabra.
                $textos[] = trim(implode('', array_map('strval', $si->xpath('.//t') ?: [])));
            }
        }
    }

    $xmlHoja = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($xmlHoja === false) {
        throw new RuntimeException('El Excel no tiene una hoja legible.');
    }

    $doc = @simplexml_load_string($xmlHoja);
    if ($doc === false) {
        throw new RuntimeException('No se pudo leer el contenido del Excel.');
    }

    $filas = [];
    foreach ($doc->sheetData->row as $fila) {
        if (count($filas) >= IMPORTACION_MAX_FILAS) {
            break;
        }
        $celdas = [];
        foreach ($fila->c as $celda) {
            // La referencia ("C7") dice en qué columna va: las celdas vacías simplemente
            // no aparecen en el XML, así que sin esto las columnas se correrían.
            $ref = (string) ($celda['r'] ?? '');
            $indice = importacion_indice_columna($ref);
            $tipo = (string) ($celda['t'] ?? '');

            if ($tipo === 's') {
                $valor = $textos[(int) $celda->v] ?? '';
            } elseif ($tipo === 'inlineStr') {
                $valor = trim(implode('', array_map('strval', $celda->xpath('.//t') ?: [])));
            } else {
                $valor = trim((string) $celda->v);
            }

            $celdas[$indice] = $valor;
        }
        if (empty($celdas)) {
            $filas[] = [];
            continue;
        }
        // Se rellenan los huecos para que todas las filas tengan la misma forma.
        $maximo = max(array_keys($celdas));
        $ordenada = [];
        for ($i = 0; $i <= $maximo; $i++) {
            $ordenada[] = $celdas[$i] ?? '';
        }
        $filas[] = $ordenada;
    }

    return $filas;
}

/**
 * Convierte la referencia de una celda ("A1", "AB12") en el número de columna (0, 27).
 */
function importacion_indice_columna(string $referencia): int
{
    if (!preg_match('/^([A-Z]+)/i', $referencia, $m)) {
        return 0;
    }
    $letras = strtoupper($m[1]);
    $indice = 0;
    for ($i = 0; $i < strlen($letras); $i++) {
        $indice = $indice * 26 + (ord($letras[$i]) - 64);
    }

    return $indice - 1;
}

/**
 * Decide qué fila es el encabezado y qué columna es cada cosa.
 *
 * @param array<int, array<int, string>> $filas
 * @return array{fila_encabezado: int, encabezados: array<int, string>, mapa: array<string, int|null>, motivos: array<int, string>}
 */
function importacion_detectar(array $filas): array
{
    // El encabezado es la primera fila con al menos dos celdas con texto. Muchos archivos
    // traen antes el nombre del equipo o el escudo en una fila suelta.
    $filaEncabezado = 0;
    foreach ($filas as $i => $fila) {
        $llenas = count(array_filter($fila, fn($c) => trim((string) $c) !== ''));
        if ($llenas >= 2) {
            $filaEncabezado = $i;
            break;
        }
    }

    $encabezados = array_map('strval', $filas[$filaEncabezado] ?? []);
    $datos = array_slice($filas, $filaEncabezado + 1);

    $mapa = ['nombre' => null, 'apellido' => null, 'dorsal' => null, 'posicion' => null];
    $motivos = [];
    $ignoradas = [];

    // --- Paso 1: por el encabezado ---
    foreach ($encabezados as $col => $titulo) {
        $limpio = importacion_normalizar($titulo);
        if ($limpio === '') {
            continue;
        }
        foreach (IMPORTACION_IGNORAR as $prohibido) {
            if (str_contains($limpio, $prohibido)) {
                $ignoradas[$col] = $titulo;
                continue 2;
            }
        }
        foreach (IMPORTACION_PISTAS as $campo => $pistas) {
            if ($mapa[$campo] !== null) {
                continue;
            }
            if (in_array($limpio, $pistas, true)) {
                $mapa[$campo] = $col;
                $motivos[] = "\"{$titulo}\" se usará como {$campo}.";
                continue 2;
            }
        }
    }

    // --- Paso 2: por la forma del dato ---
    // Es el paso que salva los archivos con encabezados raros o sin encabezado.
    $perfil = [];
    foreach ($encabezados as $col => $_) {
        $perfil[$col] = importacion_perfil_columna($datos, $col);
    }

    // Un dorsal detectado por encabezado pero que en realidad trae DPIs se descarta: el
    // encabezado miente más seguido que los datos.
    if ($mapa['dorsal'] !== null && ($perfil[$mapa['dorsal']]['parece_dpi'] ?? false)) {
        $motivos[] = 'La columna "' . $encabezados[$mapa['dorsal']] . '" trae números de 13 dígitos (parecen DPI), así que no se usó como dorsal.';
        $ignoradas[$mapa['dorsal']] = $encabezados[$mapa['dorsal']];
        $mapa['dorsal'] = null;
    }

    if ($mapa['dorsal'] === null) {
        foreach ($perfil as $col => $p) {
            if (isset($ignoradas[$col]) || in_array($col, $mapa, true)) {
                continue;
            }
            if ($p['parece_dorsal']) {
                $mapa['dorsal'] = $col;
                $motivos[] = 'La columna ' . importacion_letra_columna($col) . ' se tomó como dorsal porque trae números de 1 o 2 cifras.';
                break;
            }
        }
    }

    if ($mapa['nombre'] === null) {
        $mejor = null;
        foreach ($perfil as $col => $p) {
            if (isset($ignoradas[$col]) || in_array($col, $mapa, true)) {
                continue;
            }
            if ($p['parece_nombre'] && ($mejor === null || $p['largo_promedio'] > $perfil[$mejor]['largo_promedio'])) {
                $mejor = $col;
            }
        }
        if ($mejor !== null) {
            $mapa['nombre'] = $mejor;
            $motivos[] = 'La columna ' . importacion_letra_columna($mejor) . ' se tomó como nombre porque trae texto con espacios.';
        }
    }

    foreach ($ignoradas as $titulo) {
        $motivos[] = "\"{$titulo}\" se ignora: la app no guarda ese dato.";
    }

    return [
        'fila_encabezado' => $filaEncabezado,
        'encabezados' => $encabezados,
        'mapa' => $mapa,
        'motivos' => $motivos,
    ];
}

/**
 * Qué pinta tienen los datos de una columna.
 *
 * @return array{parece_dorsal: bool, parece_dpi: bool, parece_nombre: bool, largo_promedio: float}
 */
function importacion_perfil_columna(array $datos, int $col): array
{
    $valores = [];
    foreach ($datos as $fila) {
        $v = trim((string) ($fila[$col] ?? ''));
        if ($v !== '') {
            $valores[] = $v;
        }
    }
    if (empty($valores)) {
        return ['parece_dorsal' => false, 'parece_dpi' => false, 'parece_nombre' => false, 'largo_promedio' => 0.0];
    }

    $muestra = array_slice($valores, 0, 30);
    $dorsales = 0;
    $dpis = 0;
    $textos = 0;
    $largo = 0;

    foreach ($muestra as $v) {
        $largo += mb_strlen($v);
        $soloDigitos = preg_replace('/\D/', '', $v);
        // Hasta 3 cifras: en el papifútbol de ex alumnos los dorsales van con el número de
        // promoción y hay camisolas 105, 139 o 175. Con el tope en 2 no se detectaban.
        if (ctype_digit($v) && mb_strlen($v) <= 3 && (int) $v >= 0 && (int) $v <= 999) {
            $dorsales++;
        }
        // El DPI guatemalteco tiene 13 dígitos; también se descartan teléfonos de 8.
        if (mb_strlen((string) $soloDigitos) >= 8 && preg_match('/^[\d\s\-]+$/', $v)) {
            $dpis++;
        }
        if (preg_match('/\p{L}/u', $v)) {
            $textos++;
        }
    }

    $total = count($muestra);

    return [
        'parece_dorsal' => $dorsales / $total >= 0.7 && !importacion_es_correlativo($muestra),
        'parece_dpi' => $dpis / $total >= 0.5,
        'parece_nombre' => $textos / $total >= 0.7,
        'largo_promedio' => $largo / $total,
    ];
}

/**
 * ¿Esta columna es el "No." correlativo de la lista y no un dorsal?
 *
 * Casi todas las nóminas traen una primera columna numerada 1, 2, 3... que tiene la misma
 * pinta que un dorsal. Sin este control, la app importaba a los jugadores con el número de
 * renglón como camisola — un error silencioso y muy molesto de corregir después.
 *
 * Se pide que arranque en 1 y que suba de uno en uno: un equipo cuyos dorsales sean
 * justamente 1, 2, 3... en ese orden es rarísimo, y aun así queda la vista previa.
 */
function importacion_es_correlativo(array $valores): bool
{
    if (count($valores) < 3) {
        return false;
    }
    foreach ($valores as $i => $v) {
        if (!ctype_digit(trim($v)) || (int) $v !== $i + 1) {
            return false;
        }
    }

    return true;
}

function importacion_letra_columna(int $indice): string
{
    $letra = '';
    $n = $indice + 1;
    while ($n > 0) {
        $resto = ($n - 1) % 26;
        $letra = chr(65 + $resto) . $letra;
        $n = intdiv($n - 1, 26);
    }

    return $letra;
}

/**
 * Convierte las filas en jugadores listos para crear, según el mapa de columnas.
 *
 * @param array<string, int|null> $mapa
 * @param array<int, array> $jugadoresActuales Plantilla que ya tiene el equipo.
 * @return array{jugadores: array, omitidos: array<int, string>}
 */
function importacion_preparar_jugadores(array $filas, int $filaEncabezado, array $mapa, array $jugadoresActuales = []): array
{
    $dorsalesTomados = [];
    foreach ($jugadoresActuales as $j) {
        $dorsalesTomados[trim((string) ($j['dorsal'] ?? ''))] = true;
    }

    $nombresTomados = [];
    foreach ($jugadoresActuales as $j) {
        $nombresTomados[importacion_normalizar((string) ($j['nombre'] ?? ''))] = true;
    }

    $jugadores = [];
    $omitidos = [];

    foreach (array_slice($filas, $filaEncabezado + 1) as $i => $fila) {
        $numeroFila = $filaEncabezado + $i + 2; // como se ve en Excel, empezando en 1
        $nombre = $mapa['nombre'] !== null ? trim((string) ($fila[$mapa['nombre']] ?? '')) : '';
        // Nombre y apellido en columnas separadas se juntan: quedarse solo con la primera
        // dejaría a todos los jugadores registrados con el nombre de pila.
        // Se compara con null y no con empty(): la columna 0 es válida y empty(0) es true.
        if (($mapa['apellido'] ?? null) !== null) {
            $apellido = trim((string) ($fila[$mapa['apellido']] ?? ''));
            $nombre = trim($nombre . ' ' . $apellido);
        }
        $dorsal = $mapa['dorsal'] !== null ? trim((string) ($fila[$mapa['dorsal']] ?? '')) : '';
        $posicion = $mapa['posicion'] !== null ? trim((string) ($fila[$mapa['posicion']] ?? '')) : '';

        // Excel guarda los números como "10.0"; se limpia para que el dorsal quede "10".
        if ($dorsal !== '' && is_numeric($dorsal)) {
            $dorsal = (string) (int) (float) $dorsal;
        }

        // Listas escritas a mano en una sola columna: "10 Mario Estrada", "7.- Carlos".
        // Si no hay columna de dorsal, el número pegado adelante ES el dorsal, y dejarlo
        // dentro del nombre haría que el jugador se llamara "10 Mario Estrada".
        // Los separadores se aceptan de a varios: la gente escribe "7.-", "9 -", "10)".
        if ($dorsal === '' && preg_match('/^\s*(\d{1,2})\s*[\.\-–)]*\s+(\p{L}.*)$/u', $nombre, $m)) {
            $dorsal = $m[1];
            $nombre = trim($m[2]);
        }

        if ($nombre === '' && $dorsal === '') {
            continue; // fila vacía, ni se menciona
        }
        if ($nombre === '') {
            $omitidos[] = "Fila {$numeroFila}: tiene dorsal {$dorsal} pero no tiene nombre.";
            continue;
        }
        // Una fila de totales o un encabezado repetido a media hoja.
        if (!preg_match('/\p{L}/u', $nombre)) {
            $omitidos[] = "Fila {$numeroFila}: \"{$nombre}\" no parece un nombre.";
            continue;
        }

        $clave = importacion_normalizar($nombre);
        if (isset($nombresTomados[$clave])) {
            $omitidos[] = "Fila {$numeroFila}: {$nombre} ya está en la plantilla.";
            continue;
        }

        if ($dorsal !== '' && isset($dorsalesTomados[$dorsal])) {
            $omitidos[] = "Fila {$numeroFila}: el dorsal {$dorsal} ya está ocupado, {$nombre} entra sin dorsal.";
            $dorsal = '';
        }

        if ($dorsal !== '') {
            $dorsalesTomados[$dorsal] = true;
        }
        $nombresTomados[$clave] = true;

        $jugadores[] = [
            'nombre' => mb_substr($nombre, 0, 120),
            'dorsal' => mb_substr($dorsal, 0, 3),
            'posicion' => importacion_posicion_valida($posicion),
        ];
    }

    return ['jugadores' => $jugadores, 'omitidos' => $omitidos];
}

/**
 * Arma todo lo que la vista previa necesita mostrar.
 *
 * Se usa tanto en la primera lectura (con las columnas detectadas) como al re-leer con las
 * columnas que eligió el organizador, para que las dos pasen exactamente por el mismo
 * camino y la previa no pueda mentir sobre lo que se va a crear.
 *
 * A diferencia del primer intento, aquí NO se corta con un error cuando no sale ningún
 * jugador: se devuelve la previa igual, con los selectores de columna, que es justo lo que
 * el organizador necesita para arreglarlo.
 *
 * @return array{archivo:string, encabezados:array, columnas:array, mapa:array, fila_encabezado:int, motivos:array, jugadores:array, omitidos:array}
 */
function importacion_armar_previa(array $filas, int $filaEncabezado, array $mapa, array $jugadoresActuales, string $archivo, array $motivos = []): array
{
    $propuesta = importacion_preparar_jugadores($filas, $filaEncabezado, $mapa, $jugadoresActuales);
    $encabezados = array_map('strval', $filas[$filaEncabezado] ?? []);

    // Cuántas columnas tiene el archivo de verdad: la fila de encabezado puede ser más
    // corta que las de datos, y entonces faltarían opciones en los selectores.
    $anchoMaximo = 0;
    foreach ($filas as $fila) {
        $anchoMaximo = max($anchoMaximo, count($fila));
    }

    // Cada columna con su nombre y una muestra del contenido, para que el organizador
    // reconozca cuál es cuál aunque el encabezado no diga nada útil.
    $columnas = [];
    for ($c = 0; $c < $anchoMaximo; $c++) {
        $muestra = [];
        foreach (array_slice($filas, $filaEncabezado + 1, 3) as $fila) {
            $v = trim((string) ($fila[$c] ?? ''));
            if ($v !== '') {
                $muestra[] = mb_substr($v, 0, 22);
            }
        }
        $titulo = trim((string) ($encabezados[$c] ?? ''));
        $columnas[$c] = [
            'etiqueta' => importacion_letra_columna($c) . ($titulo !== '' ? ' · ' . $titulo : ''),
            'muestra' => implode(', ', $muestra),
        ];
    }

    return [
        'archivo' => $archivo,
        'encabezados' => $encabezados,
        'columnas' => $columnas,
        'mapa' => $mapa,
        'fila_encabezado' => $filaEncabezado,
        'motivos' => $motivos,
        'jugadores' => $propuesta['jugadores'],
        'omitidos' => $propuesta['omitidos'],
    ];
}

/**
 * Traduce lo que diga el Excel a una posición del catálogo, o cadena vacía si no coincide.
 * Se acepta tanto el nombre completo como la abreviatura ("POR", "Portero", "Arquero").
 */
function importacion_posicion_valida(string $texto): string
{
    $limpio = importacion_normalizar($texto);
    if ($limpio === '') {
        return '';
    }

    $sinonimos = [
        'portero' => 'portero', 'por' => 'portero', 'arquero' => 'portero', 'guardameta' => 'portero',
        'defensa' => 'defensa', 'def' => 'defensa', 'defensor' => 'defensa', 'zaguero' => 'defensa',
        'medio' => 'medio', 'med' => 'medio', 'mediocampista' => 'medio', 'volante' => 'medio', 'centrocampista' => 'medio',
        'delantero' => 'delantero', 'del' => 'delantero', 'atacante' => 'delantero', 'punta' => 'delantero',
        'base' => 'base', 'b' => 'base', 'armador' => 'base',
        'escolta' => 'escolta', 'e' => 'escolta',
        'alero' => 'alero', 'a' => 'alero',
        'ala-pivot' => 'ala_pivot', 'ala pivot' => 'ala_pivot', 'ap' => 'ala_pivot',
        'pivot' => 'pivot', 'p' => 'pivot', 'centro' => 'pivot',
    ];

    return $sinonimos[$limpio] ?? '';
}
