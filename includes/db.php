<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Único singleton que queda: hay un solo organizador para todas las copas.
const TABLAS_SINGLETON = ['organizador'];

const COLUMNAS_POR_TABLA = [
    'equipos' => ['id', 'torneo_id', 'nombre', 'ciudad', 'sede', 'entrenador', 'fundacion', 'color_primario', 'color_secundario', 'logo', 'descripcion'],
    'partidos' => ['id', 'torneo_id', 'jornada', 'equipo_local', 'equipo_visitante', 'fecha', 'hora', 'cancha', 'estado', 'marcador_local', 'marcador_visitante', 'fase', 'arbitro', 'observaciones', 'cronometro_estado', 'cronometro_inicio', 'cronometro_segundos', 'cronometro_periodo'],
    'patrocinadores' => ['id', 'torneo_id', 'nombre', 'nivel', 'url', 'logo', 'orden'],
    'comentarios' => ['id', 'torneo_id', 'mensaje', 'fecha', 'leido'],
    'jugadores' => ['id', 'torneo_id', 'equipo_id', 'dorsal', 'nombre', 'activo'],
    'partido_eventos' => ['id', 'torneo_id', 'partido_id', 'tipo', 'equipo_id', 'jugador_id', 'jugador_entra_id', 'minuto', 'tipo_gol', 'asistencia_jugador_id', 'motivo', 'periodo'],
];

const COLUMNAS_ENTERAS_POR_TABLA = [
    'equipos' => ['id', 'torneo_id'],
    'partidos' => ['id', 'torneo_id', 'jornada', 'equipo_local', 'equipo_visitante', 'marcador_local', 'marcador_visitante', 'cronometro_segundos', 'cronometro_periodo'],
    'patrocinadores' => ['id', 'torneo_id', 'orden'],
    'comentarios' => ['id', 'torneo_id'],
    'jugadores' => ['id', 'torneo_id', 'equipo_id'],
    'partido_eventos' => ['id', 'torneo_id', 'partido_id', 'equipo_id', 'jugador_id', 'jugador_entra_id', 'minuto', 'asistencia_jugador_id', 'periodo'],
];

// Columnas boolean reales (no INTEGER 0/1 como comentarios.leido): con prepares emulados,
// Postgres no castea implícitamente un entero a boolean, así que estas se mandan como texto
// 'true'/'false', igual que ya hace torneos_guardar() con sus columnas booleanas.
const COLUMNAS_BOOLEANAS_POR_TABLA = [
    'jugadores' => ['activo'],
];

// 'modo' (copa/liga) ya NO está aquí a propósito: hasta ahora era el interruptor que
// activaba jugadores/eventos/PDF solo en "modo liga"; ahora esas funciones están
// disponibles siempre, así que la columna quedó sin uso (se deja en la tabla sin tocar,
// no hace falta migrarla) y torneos_guardar() ya no la lee ni la escribe.
const COLUMNAS_TORNEO = [
    'slug', 'nombre', 'subtitulo', 'temporada', 'descripcion', 'sede_principal', 'logo',
    'color_primario', 'color_secundario', 'color_acento', 'fecha_inicio', 'fecha_fin', 'formato',
    'instagram', 'hero_frase', 'deporte', 'num_equipos', 'fases_playoff', 'permite_empates',
    'puntos_victoria', 'puntos_empate', 'puntos_derrota', 'es_predeterminado', 'activo',
    'genero',
];

/**
 * Conexión PDO a PostgreSQL, reutilizada durante toda la petición.
 * Lee la cadena de conexión de la variable de entorno DATABASE_URL
 * (formato: postgresql://usuario:password@host:puerto/basedatos?sslmode=require)
 */
function db_conexion(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $url = getenv('DATABASE_URL');
    if ($url === false || $url === '') {
        throw new RuntimeException('Falta configurar la variable de entorno DATABASE_URL con la conexión a PostgreSQL.');
    }

    $partes = parse_url($url);
    if ($partes === false || !isset($partes['host'])) {
        throw new RuntimeException('DATABASE_URL no tiene un formato válido.');
    }
    parse_str($partes['query'] ?? '', $opciones);

    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
        $partes['host'],
        $partes['port'] ?? 5432,
        ltrim($partes['path'] ?? '', '/'),
        $opciones['sslmode'] ?? 'require'
    );

    $pdo = new PDO($dsn, $partes['user'] ?? '', $partes['pass'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Neon usa un pooler (PgBouncer) en modo "transaction": los prepared statements
        // nativos de Postgres no sobreviven bien ahí, sobre todo si el PDOStatement se
        // destruye a medio camino de una transacción abierta (ej. una consulta dentro de
        // una función anidada). Emular los prepares del lado del cliente evita ese problema.
        PDO::ATTR_EMULATE_PREPARES => true,
    ]);

    return $pdo;
}

/**
 * PDO pgsql devuelve todas las columnas como texto; esto restaura los tipos nativos
 * (int, bool, null) que el resto de la aplicación espera, igual que hacía json_decode.
 */
function db_normalizar_fila(string $tabla, array $fila): array
{
    foreach (COLUMNAS_ENTERAS_POR_TABLA[$tabla] ?? [] as $col) {
        if (array_key_exists($col, $fila) && $fila[$col] !== null) {
            $fila[$col] = (int) $fila[$col];
        }
    }

    if ($tabla === 'comentarios' && array_key_exists('leido', $fila)) {
        $fila['leido'] = (bool) (int) $fila['leido'];
    }

    if ($tabla === 'jugadores' && array_key_exists('activo', $fila)) {
        $fila['activo'] = is_string($fila['activo']) ? ($fila['activo'] === 't' || $fila['activo'] === '1') : (bool) $fila['activo'];
    }

    return $fila;
}

/**
 * Enlaza un valor PHP a un parámetro con el tipo PDO adecuado según su tipo nativo.
 */
function db_bind(PDOStatement $stmt, string $marcador, mixed $valor): void
{
    if ($valor === null) {
        $stmt->bindValue($marcador, null, PDO::PARAM_NULL);
    } elseif (is_bool($valor)) {
        $stmt->bindValue($marcador, $valor ? 1 : 0, PDO::PARAM_INT);
    } elseif (is_int($valor)) {
        $stmt->bindValue($marcador, $valor, PDO::PARAM_INT);
    } else {
        $stmt->bindValue($marcador, (string) $valor, PDO::PARAM_STR);
    }
}

function db_leer(string $tabla, ?int $torneoId = null): array
{
    $pdo = db_conexion();

    if (in_array($tabla, TABLAS_SINGLETON, true)) {
        $stmt = $pdo->query("SELECT * FROM {$tabla} WHERE id = 1");
        $fila = $stmt->fetch();
        return $fila ? db_normalizar_fila($tabla, $fila) : [];
    }

    if ($torneoId === null) {
        throw new InvalidArgumentException("db_leer('{$tabla}') necesita un torneo_id.");
    }

    $stmt = $pdo->prepare("SELECT * FROM {$tabla} WHERE torneo_id = :torneo_id ORDER BY id");
    $stmt->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll();
    return array_map(fn($fila) => db_normalizar_fila($tabla, $fila), $filas);
}

function db_guardar(string $tabla, array $datos, ?int $torneoId = null): bool
{
    $pdo = db_conexion();

    if (in_array($tabla, TABLAS_SINGLETON, true)) {
        return db_guardar_singleton($pdo, $tabla, $datos);
    }

    if ($torneoId === null) {
        throw new InvalidArgumentException("db_guardar('{$tabla}') necesita un torneo_id.");
    }

    return db_guardar_coleccion($pdo, $tabla, $datos, $torneoId);
}

function db_guardar_singleton(PDO $pdo, string $tabla, array $datos): bool
{
    $datos['id'] = 1;
    $columnas = array_keys($datos);
    $marcadores = array_map(fn($c) => ":{$c}", $columnas);
    $actualizaciones = array_map(fn($c) => "{$c} = EXCLUDED.{$c}", array_filter($columnas, fn($c) => $c !== 'id'));

    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s) ON CONFLICT (id) DO UPDATE SET %s',
        $tabla,
        implode(', ', $columnas),
        implode(', ', $marcadores),
        implode(', ', $actualizaciones)
    );

    $stmt = $pdo->prepare($sql);
    foreach ($datos as $col => $valor) {
        db_bind($stmt, ":{$col}", $valor);
    }
    return $stmt->execute();
}

function db_guardar_coleccion(PDO $pdo, string $tabla, array $registros, int $torneoId): bool
{
    if (!isset(COLUMNAS_POR_TABLA[$tabla])) {
        throw new InvalidArgumentException("Tabla desconocida: {$tabla}");
    }

    $columnas = COLUMNAS_POR_TABLA[$tabla];
    $marcadores = array_map(fn($c) => ":{$c}", $columnas);
    $sql = sprintf(
        'INSERT INTO %s (%s) VALUES (%s)',
        $tabla,
        implode(', ', $columnas),
        implode(', ', $marcadores)
    );

    $pdo->beginTransaction();
    try {
        $stmtBorrar = $pdo->prepare("DELETE FROM {$tabla} WHERE torneo_id = :torneo_id");
        $stmtBorrar->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
        $stmtBorrar->execute();

        $stmt = $pdo->prepare($sql);
        foreach ($registros as $registro) {
            foreach ($columnas as $col) {
                // torneo_id siempre viene del parámetro, no del array, para que ningún llamado
                // pueda "escaparse" a otra copa por accidente.
                $valor = $col === 'torneo_id' ? $torneoId : ($registro[$col] ?? null);
                if (in_array($col, COLUMNAS_BOOLEANAS_POR_TABLA[$tabla] ?? [], true)) {
                    $valor = !empty($valor) ? 'true' : 'false';
                }
                db_bind($stmt, ":{$col}", $valor);
            }
            $stmt->execute();
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return true;
}

/**
 * partido_eventos NO usa db_leer()/db_guardar_coleccion(): esas funciones borran y reescriben
 * TODA la tabla del torneo, lo que aquí sería reescribir los eventos de TODOS los partidos de
 * la temporada cada vez que se agrega un solo gol o tarjeta a UNO de ellos (riesgo de choque si
 * hay dos partidos editándose a la vez, y un costo innecesario). En su lugar, estas dos funciones
 * acotan el DELETE+INSERT a un partido puntual, forzando torneo_id y partido_id siempre desde los
 * parámetros (nunca del array), mismo principio de seguridad que db_guardar_coleccion().
 */
function db_leer_eventos_partido(int $torneoId, int $partidoId): array
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT * FROM partido_eventos WHERE torneo_id = :torneo_id AND partido_id = :partido_id ORDER BY id');
    $stmt->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
    $stmt->bindValue(':partido_id', $partidoId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll();
    return array_map(fn($fila) => db_normalizar_fila('partido_eventos', $fila), $filas);
}

function db_guardar_eventos_partido(int $torneoId, int $partidoId, array $eventos): bool
{
    $pdo = db_conexion();
    $columnas = COLUMNAS_POR_TABLA['partido_eventos'];
    $marcadores = array_map(fn($c) => ":{$c}", $columnas);
    $sql = sprintf(
        'INSERT INTO partido_eventos (%s) VALUES (%s)',
        implode(', ', $columnas),
        implode(', ', $marcadores)
    );

    $pdo->beginTransaction();
    try {
        $stmtBorrar = $pdo->prepare('DELETE FROM partido_eventos WHERE torneo_id = :torneo_id AND partido_id = :partido_id');
        $stmtBorrar->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
        $stmtBorrar->bindValue(':partido_id', $partidoId, PDO::PARAM_INT);
        $stmtBorrar->execute();

        $stmt = $pdo->prepare($sql);
        foreach ($eventos as $evento) {
            foreach ($columnas as $col) {
                $valor = match ($col) {
                    'torneo_id' => $torneoId,
                    'partido_id' => $partidoId,
                    default => $evento[$col] ?? null,
                };
                db_bind($stmt, ":{$col}", $valor);
            }
            $stmt->execute();
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return true;
}

/**
 * El id es una PK global compartida por todas las copas (aunque cada copa solo
 * vea su propio subconjunto de filas vía torneo_id), así que el siguiente id
 * debe calcularse contra TODA la tabla, no solo los registros de la copa actual.
 */
function db_siguiente_id_global(string $tabla): int
{
    if (!isset(COLUMNAS_POR_TABLA[$tabla])) {
        throw new InvalidArgumentException("Tabla desconocida: {$tabla}");
    }
    $pdo = db_conexion();
    $stmt = $pdo->query("SELECT COALESCE(MAX(id), 0) + 1 FROM {$tabla}");
    return (int) $stmt->fetchColumn();
}

function db_buscar_por_id(array $registros, int $id): ?array
{
    foreach ($registros as $r) {
        if ((int) ($r['id'] ?? 0) === $id) {
            return $r;
        }
    }
    return null;
}

// ---------------------------------------------------------------------------
// Tabla `torneos` (las copas en sí): no sigue el patrón genérico de arriba
// porque tiene id SERIAL propio y no está "dentro" de ningún torneo.
// ---------------------------------------------------------------------------

function db_parsear_array_pg(?string $valor): array
{
    if ($valor === null) {
        return [];
    }
    $valor = trim($valor, '{}');
    return $valor === '' ? [] : explode(',', $valor);
}

function db_normalizar_torneo(array $fila): array
{
    foreach (['id', 'usuario_id', 'num_equipos', 'puntos_victoria', 'puntos_empate', 'puntos_derrota'] as $col) {
        if (array_key_exists($col, $fila) && $fila[$col] !== null) {
            $fila[$col] = (int) $fila[$col];
        }
    }
    foreach (['permite_empates', 'es_predeterminado', 'activo'] as $col) {
        if (array_key_exists($col, $fila)) {
            $fila[$col] = (bool) (
                is_string($fila[$col]) ? ($fila[$col] === 't' || $fila[$col] === '1') : $fila[$col]
            );
        }
    }
    if (array_key_exists('fases_playoff', $fila)) {
        $fila['fases_playoff'] = db_parsear_array_pg($fila['fases_playoff']);
    }
    return $fila;
}

/**
 * Lista copas. Si se pasa $usuarioId, solo devuelve las copas de ese usuario (uso normal
 * del panel admin, "Mis Copas"); sin él, lista todas (páginas públicas).
 */
function torneos_listar(bool $soloActivos = true, ?int $usuarioId = null): array
{
    $pdo = db_conexion();
    $condiciones = [];
    if ($soloActivos) {
        $condiciones[] = 'activo = true';
    }
    if ($usuarioId !== null) {
        $condiciones[] = 'usuario_id = :usuario_id';
    }
    $sql = 'SELECT * FROM torneos' . ($condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '') . ' ORDER BY es_predeterminado DESC, creado_en ASC';
    $stmt = $pdo->prepare($sql);
    if ($usuarioId !== null) {
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    }
    $stmt->execute();
    return array_map('db_normalizar_torneo', $stmt->fetchAll());
}

function torneos_obtener_por_slug(string $slug): ?array
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT * FROM torneos WHERE slug = :slug AND activo = true');
    $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
    $stmt->execute();
    $fila = $stmt->fetch();
    return $fila ? db_normalizar_torneo($fila) : null;
}

function torneos_obtener_predeterminado(): ?array
{
    $pdo = db_conexion();
    $fila = $pdo->query('SELECT * FROM torneos WHERE es_predeterminado = true LIMIT 1')->fetch();
    return $fila ? db_normalizar_torneo($fila) : null;
}

/**
 * Si se pasa $usuarioId, solo devuelve la copa si además pertenece a ese usuario —
 * es el filtro que evita que un usuario entre/edite/borre la copa de otro adivinando su id.
 * Sin él (páginas públicas, resolución por slug/código), devuelve cualquier copa por id.
 */
function torneos_obtener_por_id(int $id, ?int $usuarioId = null): ?array
{
    $pdo = db_conexion();
    $sql = 'SELECT * FROM torneos WHERE id = :id' . ($usuarioId !== null ? ' AND usuario_id = :usuario_id' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    if ($usuarioId !== null) {
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $fila = $stmt->fetch();
    return $fila ? db_normalizar_torneo($fila) : null;
}

/**
 * Alfabeto sin caracteres ambiguos (sin 0/O, 1/I/L) para que el código sea fácil de
 * leer/dictar en voz alta, tipo código de sala de juego.
 */
const TORNEO_CODIGO_ALFABETO = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

function torneos_generar_codigo_unico(int $largo = 6): string
{
    do {
        $codigo = '';
        for ($i = 0; $i < $largo; $i++) {
            $codigo .= TORNEO_CODIGO_ALFABETO[random_int(0, strlen(TORNEO_CODIGO_ALFABETO) - 1)];
        }
    } while (torneos_obtener_por_codigo($codigo) !== null);
    return $codigo;
}

function torneos_obtener_por_codigo(string $codigo): ?array
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT * FROM torneos WHERE codigo = :codigo AND activo = true');
    $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
    $stmt->execute();
    $fila = $stmt->fetch();
    return $fila ? db_normalizar_torneo($fila) : null;
}

function torneos_regenerar_codigo(int $id, int $usuarioId): string
{
    if (torneos_obtener_por_id($id, $usuarioId) === null) {
        throw new RuntimeException('Copa o liga no encontrada.');
    }
    $nuevo = torneos_generar_codigo_unico();
    $pdo = db_conexion();
    $stmt = $pdo->prepare('UPDATE torneos SET codigo = :codigo WHERE id = :id');
    $stmt->bindValue(':codigo', $nuevo, PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $nuevo;
}

/**
 * Crea o actualiza una copa (según traiga o no 'id'). Devuelve el id.
 * Si se marca es_predeterminado, se le quita esa marca a cualquier otra copa
 * (solo una puede responder en las URLs sin prefijo).
 *
 * $usuarioIdCreador se usa SOLO al crear (nunca al actualizar): 'usuario_id' y 'codigo'
 * no forman parte de COLUMNAS_TORNEO a propósito, así que editar una copa jamás los toca
 * — es imposible "robar" una copa ajena o cambiarle el código manipulando el formulario.
 */
function torneos_guardar(array $datos, ?int $usuarioIdCreador = null): int
{
    $pdo = db_conexion();

    // Con prepared statements emulados (necesario por el pooler de Neon, ver db_conexion()),
    // Postgres ya no acepta 0/1 como boolean de forma implícita como sí hacía con prepares
    // nativos: hay que mandar el texto 'true'/'false' para estas 3 columnas.
    $columnasBooleanas = ['permite_empates', 'es_predeterminado', 'activo'];

    $valores = [];
    foreach (COLUMNAS_TORNEO as $c) {
        $v = $datos[$c] ?? null;
        if ($c === 'fases_playoff') {
            $v = '{' . implode(',', (array) $v) . '}';
        } elseif (in_array($c, $columnasBooleanas, true)) {
            $v = !empty($v) ? 'true' : 'false';
        }
        $valores[$c] = $v;
    }

    $pdo->beginTransaction();
    try {
        if (!empty($datos['es_predeterminado'])) {
            $pdo->exec('UPDATE torneos SET es_predeterminado = false');
        }

        if (!empty($datos['id'])) {
            $id = (int) $datos['id'];
            $sets = implode(', ', array_map(fn($c) => "{$c} = :{$c}", COLUMNAS_TORNEO));
            $stmt = $pdo->prepare("UPDATE torneos SET {$sets} WHERE id = :id");
            foreach ($valores as $c => $v) {
                db_bind($stmt, ":{$c}", $v);
            }
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $valores['usuario_id'] = $usuarioIdCreador;
            $valores['codigo'] = torneos_generar_codigo_unico();
            $cols = array_merge(COLUMNAS_TORNEO, ['usuario_id', 'codigo']);
            $marcadores = implode(', ', array_map(fn($c) => ":{$c}", $cols));
            $stmt = $pdo->prepare('INSERT INTO torneos (' . implode(', ', $cols) . ") VALUES ({$marcadores}) RETURNING id");
            foreach ($valores as $c => $v) {
                db_bind($stmt, ":{$c}", $v);
            }
            $stmt->execute();
            $id = (int) $stmt->fetchColumn();
        }

        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Elimina una copa y (por ON DELETE CASCADE) todos sus equipos, partidos,
 * patrocinadores y comentarios. No se permite borrar la copa predeterminada.
 * $usuarioId exige además que la copa pertenezca a ese usuario.
 */
function torneos_eliminar(int $id, ?int $usuarioId = null): void
{
    $torneo = torneos_obtener_por_id($id, $usuarioId);
    if ($torneo === null) {
        throw new RuntimeException('Copa o liga no encontrada.');
    }
    if ($torneo['es_predeterminado']) {
        throw new RuntimeException('No se puede eliminar la copa o liga predeterminada.');
    }
    $pdo = db_conexion();
    $stmt = $pdo->prepare('DELETE FROM torneos WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}
