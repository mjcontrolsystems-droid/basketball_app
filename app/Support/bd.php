<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';

// Único singleton que queda: hay un solo organizador para todas las copas.
const TABLAS_SINGLETON = ['organizador'];

const COLUMNAS_POR_TABLA = [
    'equipos' => ['id', 'torneo_id', 'nombre', 'ciudad', 'sede', 'entrenador', 'fundacion', 'color_primario', 'color_secundario', 'logo', 'descripcion', 'grupo', 'cabeza_serie', 'siglas'],
    'partidos' => ['id', 'torneo_id', 'jornada', 'equipo_local', 'equipo_visitante', 'fecha', 'hora', 'cancha', 'estado', 'marcador_local', 'marcador_visitante', 'fase', 'arbitro', 'observaciones', 'por_default', 'cronometro_estado', 'cronometro_inicio', 'cronometro_segundos', 'cronometro_periodo', 'cronometro_extra_min'],
    'patrocinadores' => ['id', 'torneo_id', 'nombre', 'nivel', 'url', 'logo', 'orden'],
    'comentarios' => ['id', 'torneo_id', 'mensaje', 'fecha', 'leido'],
    'jugadores' => ['id', 'torneo_id', 'equipo_id', 'dorsal', 'nombre', 'activo', 'posicion'],
    'partido_eventos' => ['id', 'torneo_id', 'partido_id', 'tipo', 'equipo_id', 'jugador_id', 'jugador_entra_id', 'minuto', 'tipo_gol', 'asistencia_jugador_id', 'motivo', 'periodo'],
];

const COLUMNAS_ENTERAS_POR_TABLA = [
    'equipos' => ['id', 'torneo_id'],
    'partidos' => ['id', 'torneo_id', 'jornada', 'equipo_local', 'equipo_visitante', 'marcador_local', 'marcador_visitante', 'cronometro_segundos', 'cronometro_periodo', 'cronometro_extra_min'],
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
    'equipos' => ['cabeza_serie'],
    'partidos' => ['por_default'],
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

    // Red contra ids repetidos ANTES de tocar la base.
    //
    // Los ids se asignan en PHP con "SELECT MAX(id)+1", así que quien cree varios
    // registros de un golpe tiene que pedir el id una vez e ir incrementándolo; pedirlo
    // dentro del bucle devuelve el mismo número para todos, porque hasta el final no se
    // guarda nada. Cuando eso pasaba, Postgres tiraba un "duplicate key" críptico apuntando
    // a bd.php y no al bucle que lo causó. Este chequeo dice qué tabla y qué id.
    $vistos = [];
    foreach ($registros as $r) {
        $id = (int) ($r['id'] ?? 0);
        if ($id > 0 && isset($vistos[$id])) {
            throw new RuntimeException(
                "Se intentó guardar dos registros de '{$tabla}' con el mismo id ({$id}). "
                . 'Al crear varios de una vez hay que pedir el id una sola vez e ir sumándole 1 a cada uno.'
            );
        }
        $vistos[$id] = true;
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

/**
 * Migraciones automáticas: crea las tablas/columnas nuevas si aún no existen. Todas las
 * instrucciones son idempotentes (IF NOT EXISTS), así que correrlas de más no daña nada.
 *
 * Se ejecuta una vez por sesión del panel admin (ver admin_layout_top.php): evita el
 * paso manual de "corre el script de migración en el servidor", que en un hosting sin
 * acceso a terminal (como el plan gratuito de Render) es difícil de hacer.
 *
 * La marca de "ya corrieron" lleva el HASH DE ESTE ARCHIVO, no un nombre fijo. El nombre
 * fijo causó un bug feo: al agregar columnas nuevas, quien ya tenía la sesión abierta se
 * saltaba las migraciones, la columna nunca se creaba y el UPDATE fallaba en silencio —
 * el botón de guardar parecía no hacer nada. Con el hash, cualquier cambio en este archivo
 * invalida la marca y las migraciones vuelven a correr solas. Correrlas de más es gratis
 * porque son idempotentes.
 */
function db_migrar_automatico(): void
{
    $clave = 'migraciones_' . substr((string) @md5_file(__FILE__), 0, 12);
    if (!empty($_SESSION[$clave])) {
        return;
    }
    try {
        $pdo = db_conexion();
        $pdo->exec('ALTER TABLE correos_autorizados ADD COLUMN IF NOT EXISTS limite_torneos INTEGER NOT NULL DEFAULT 1');
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS password_resets (
                id SERIAL PRIMARY KEY,
                usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
                token_hash TEXT UNIQUE NOT NULL,
                expira_en TIMESTAMPTZ NOT NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT now()
            )'
        );
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS visitas_diarias (
                torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
                fecha DATE NOT NULL,
                visitas INTEGER NOT NULL DEFAULT 0,
                PRIMARY KEY (torneo_id, fecha)
            )'
        );
        // Bitácora de acciones del panel (sin FK a torneos: la entrada debe sobrevivir
        // aunque la copa se borre después — es historial, no dato vivo).
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS bitacora (
                id SERIAL PRIMARY KEY,
                usuario_id INTEGER,
                torneo_id INTEGER,
                accion TEXT NOT NULL,
                detalle TEXT NOT NULL DEFAULT \'\',
                creado_en TIMESTAMP NOT NULL DEFAULT now()
            )'
        );
        // Modalidad del deporte (fútbol 11/7/5, basketball FIBA) y duración de cada
        // tiempo/cuarto en minutos (personalizable por copa; NULL = usar el estándar).
        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS modalidad TEXT NOT NULL DEFAULT ''");
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS duracion_periodo_min INTEGER');
        // Formato de la competencia: 'liga' (solo tabla de puntos) o 'copa' (campeonato
        // con eliminación directa). El DEFAULT deja en 'copa' todo lo que ya existía.
        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS modo TEXT NOT NULL DEFAULT 'copa'");
        // Vueltas de la temporada regular: 1 = solo ida, 2 = ida y vuelta. Alimenta el
        // generador automático de calendario (ver app/Support/fixture.php).
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS vueltas INTEGER NOT NULL DEFAULT 1');
        // Minutos extra (tiempo añadido) del periodo que se está jugando.
        $pdo->exec('ALTER TABLE partidos ADD COLUMN IF NOT EXISTS cronometro_extra_min INTEGER NOT NULL DEFAULT 0');
        // Posición habitual del jugador en su plantilla (portero/defensa/... o base/pívot).
        $pdo->exec("ALTER TABLE jugadores ADD COLUMN IF NOT EXISTS posicion TEXT NOT NULL DEFAULT ''");
        // Alineación de cada encuentro: titulares, banca y posición de cada jugador.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS partido_alineacion (
                torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
                partido_id INTEGER NOT NULL,
                jugador_id INTEGER NOT NULL,
                equipo_id INTEGER NOT NULL,
                titular BOOLEAN NOT NULL DEFAULT FALSE,
                posicion TEXT NOT NULL DEFAULT \'\',
                PRIMARY KEY (partido_id, jugador_id)
            )'
        );
        // Género de la PERSONA organizadora (no el de la copa): decide si el sitio
        // público dice "El Organizador" o "La Organizadora". '' = no indicado, en cuyo
        // caso se usa la forma masculina genérica, igual criterio que el resto del sitio.
        $pdo->exec("ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS genero TEXT NOT NULL DEFAULT ''");

        // --- Sanciones disciplinarias (multas por tarjeta) ---
        // Tarifas y reglas por copa. En 0 la liga no cobra multas y toda la función
        // queda oculta, así que esto no altera a las copas que ya existen.
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS multa_amarilla NUMERIC(10,2) NOT NULL DEFAULT 0');
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS multa_roja NUMERIC(10,2) NOT NULL DEFAULT 0');
        // true = el moroso NO se puede alinear; false = solo se advierte.
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS sancion_bloquea BOOLEAN NOT NULL DEFAULT TRUE');
        // Partidos de suspensión que arrastra una roja (0 = la liga no suspende por fechas).
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS partidos_suspension_roja INTEGER NOT NULL DEFAULT 0');
        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS moneda TEXT NOT NULL DEFAULT 'Q'");

        // Una sanción por evento de tarjeta (UNIQUE en evento_id): así reprocesar la
        // ficha de un partido no duplica multas. Guarda el monto vigente al crearla.
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS sanciones (
                id SERIAL PRIMARY KEY,
                torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
                evento_id INTEGER NOT NULL UNIQUE,
                partido_id INTEGER NOT NULL,
                jugador_id INTEGER NOT NULL,
                equipo_id INTEGER NOT NULL,
                tipo TEXT NOT NULL,
                monto NUMERIC(10,2) NOT NULL DEFAULT 0,
                estado TEXT NOT NULL DEFAULT \'pendiente\',
                nota TEXT NOT NULL DEFAULT \'\',
                cobrada_en TIMESTAMPTZ,
                cobrada_por INTEGER,
                creada_en TIMESTAMP NOT NULL DEFAULT now()
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sanciones_torneo_estado ON sanciones (torneo_id, estado)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sanciones_jugador ON sanciones (jugador_id, estado)');

        // Reglamento del campeonato en PDF: guarda el id del archivo en la tabla binaria
        // `imagenes` (que almacena cualquier tipo, no solo imágenes) y el nombre original
        // para mostrarlo y para el archivo descargado.
        // Suspensiones por partidos: cada cuántas amarillas se suspende y cuántos partidos
        // cubre. En 0 la liga no aplica esa regla (partidos_suspension_roja ya existía).
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS amarillas_para_suspension INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS partidos_suspension_amarillas INTEGER NOT NULL DEFAULT 1');

        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS reglamento TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS reglamento_nombre TEXT NOT NULL DEFAULT ''");

        // Fase de grupos estilo mundial: 16 equipos en 4 grupos de 4, todos contra todos
        // dentro del grupo y los mejores cruzan a eliminación directa. 0 grupos significa
        // que la competencia no usa este formato, que es como venían todas las anteriores.
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS num_grupos INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS clasifican_por_grupo INTEGER NOT NULL DEFAULT 2');
        // El grupo se guarda como letra ('A', 'B'...) y no como número, porque es como lo
        // dice todo el mundo en la cancha y así se imprime en el calendario.
        $pdo->exec("ALTER TABLE equipos ADD COLUMN IF NOT EXISTS grupo TEXT NOT NULL DEFAULT ''");
        $pdo->exec('ALTER TABLE equipos ADD COLUMN IF NOT EXISTS cabeza_serie BOOLEAN NOT NULL DEFAULT FALSE');

        // Aviso al público de la copa: un mensaje emergente que el organizador publica y
        // quita cuando quiere — condolencias por un fallecimiento, un cumpleaños, un
        // recordatorio. Aparece una vez por visita al entrar al sitio.
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS aviso_activo BOOLEAN NOT NULL DEFAULT FALSE');
        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS aviso_tipo TEXT NOT NULL DEFAULT 'informativo'");
        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS aviso_titulo TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS aviso_mensaje TEXT NOT NULL DEFAULT ''");

        // Partido ganado por default (W.O.): el marcador es reglamentario (3-0 en fútbol,
        // 20-0 en basketball), no sale de goles, y el encuentro queda excluido de las
        // estadísticas individuales — nadie anotó y la portería no fue "vencida" jugando.
        $pdo->exec('ALTER TABLE partidos ADD COLUMN IF NOT EXISTS por_default BOOLEAN NOT NULL DEFAULT FALSE');

        // Colaboradores: gente que ayuda a administrar una copa sin ser su dueño. Se
        // guardan por correo porque casi siempre se invita a quien todavía no tiene
        // cuenta; usuario_id se llena cuando entra por primera vez (ver Colaborador.php).
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS colaboradores (
                id SERIAL PRIMARY KEY,
                torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
                email TEXT NOT NULL,
                nivel TEXT NOT NULL DEFAULT \'mesa\',
                usuario_id INTEGER REFERENCES usuarios(id) ON DELETE SET NULL,
                creado_en TIMESTAMP NOT NULL DEFAULT NOW(),
                UNIQUE (torneo_id, email)
            )'
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS colaboradores_usuario_idx ON colaboradores (usuario_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS colaboradores_email_idx ON colaboradores (email)');

        // Invitación por correo: el token va en el enlace que se le manda a la persona, y
        // aceptado_en marca cuándo entró por ese enlace. El token es NULL cuando ya se usó,
        // así que un correo reenviado a alguien que ya aceptó no revive un enlace viejo.
        $pdo->exec('ALTER TABLE colaboradores ADD COLUMN IF NOT EXISTS token TEXT');
        $pdo->exec('ALTER TABLE colaboradores ADD COLUMN IF NOT EXISTS aceptado_en TIMESTAMP');
        $pdo->exec('CREATE INDEX IF NOT EXISTS colaboradores_token_idx ON colaboradores (token)');

        // Qué se pinta en el escudo del equipo que no subió logo. Vacío = lo decide la app
        // (el número del nombre, o las iniciales). Sirve para los casos que ninguna regla
        // automática acierta: un apodo, una sigla del club, una letra.
        $pdo->exec("ALTER TABLE equipos ADD COLUMN IF NOT EXISTS siglas TEXT NOT NULL DEFAULT ''");

        // Podio de cierre. Solo se guarda si está publicado: quiénes son los tres se
        // recalcula en vivo, así que corregir el marcador de la final cambia al campeón.
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS podio_publicado BOOLEAN NOT NULL DEFAULT FALSE');

        // Redes de la copa. Instagram ya existía; el resto son las que de verdad usan las
        // ligas aquí — el grupo de WhatsApp suele ser el canal principal con los delegados.
        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS facebook TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS tiktok TEXT NOT NULL DEFAULT ''");
        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS whatsapp TEXT NOT NULL DEFAULT ''");

        // Modo mantenimiento: cierra el sitio público mientras se reacomoda el calendario,
        // sin tener que borrar nada ni desactivar la copa.
        $pdo->exec('ALTER TABLE torneos ADD COLUMN IF NOT EXISTS en_mantenimiento BOOLEAN NOT NULL DEFAULT FALSE');
        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS mensaje_mantenimiento TEXT NOT NULL DEFAULT ''");

        // Cómo juega esta copa: días, cupos, hora de inicio, intervalo, canchas y fechas
        // que no se juegan. Antes esto vivía solo en el formulario del generador y se
        // perdía al salir, así que al armar cuartos y semis la app ya no sabía que se
        // juega sábado y domingo ni a qué hora, y los partidos nacían todos juntos y sin
        // hora. Guardado aquí, el resto del torneo se programa con el mismo criterio.
        $pdo->exec("ALTER TABLE torneos ADD COLUMN IF NOT EXISTS calendario_config TEXT NOT NULL DEFAULT ''");

        // Se limpian las marcas de versiones anteriores para que la sesión no acumule una
        // clave por cada cambio del archivo a lo largo de la vida del proyecto.
        foreach (array_keys($_SESSION) as $k) {
            if (str_starts_with((string) $k, 'migraciones_')) {
                unset($_SESSION[$k]);
            }
        }
        $_SESSION[$clave] = true;
        unset($_SESSION['migraciones_error']);
    } catch (Throwable $e) {
        // No se bloquea el panel: puede que lo que fallara no haga falta para lo que el
        // organizador está haciendo ahora. Pero SÍ se deja constancia visible.
        //
        // Antes esto solo iba al log del servidor, y como las migraciones corren en orden,
        // una que falle deja sin crear todas las de atrás. El síntoma llega después y en
        // otro lado ("columna no existe" al guardar algo), sin ninguna pista de la causa
        // real. Guardarlo en la sesión permite mostrarlo en el panel.
        error_log('db_migrar_automatico: ' . $e->getMessage());
        $_SESSION['migraciones_error'] = $e->getMessage();
    }
}

// ---------------------------------------------------------------------------
// Bitácora de acciones del panel (logs): quién hizo qué y cuándo. Da trazabilidad a
// las acciones sensibles (resultados, reaperturas, cupos, accesos) — imprescindible
// cuando un resultado se disputa o hay que auditar un cambio.
// ---------------------------------------------------------------------------
