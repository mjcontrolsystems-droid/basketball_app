-- Esquema PostgreSQL para la plataforma multi-torneo (varias copas, varios deportes, un solo admin)
-- Los IDs de las tablas "por torneo" son asignados por la aplicación (no autoincrementales),
-- salvo torneos e imagenes que sí son SERIAL.

-- Cada fila es una copa independiente (Copa Estrellas, Papifútbol Masculino, etc.)
CREATE TABLE IF NOT EXISTS torneos (
    id SERIAL PRIMARY KEY,
    slug TEXT UNIQUE NOT NULL,
    nombre TEXT NOT NULL DEFAULT '',
    subtitulo TEXT NOT NULL DEFAULT '',
    temporada TEXT NOT NULL DEFAULT '',
    descripcion TEXT NOT NULL DEFAULT '',
    sede_principal TEXT NOT NULL DEFAULT '',
    logo TEXT NOT NULL DEFAULT '',
    color_primario TEXT NOT NULL DEFAULT '#8b2fd9',
    color_secundario TEXT NOT NULL DEFAULT '#ff6b35',
    color_acento TEXT NOT NULL DEFAULT '#ffc93c',
    fecha_inicio TEXT NOT NULL DEFAULT '',
    fecha_fin TEXT NOT NULL DEFAULT '',
    formato TEXT NOT NULL DEFAULT '',
    instagram TEXT NOT NULL DEFAULT '',
    hero_frase TEXT NOT NULL DEFAULT '',
    -- 'basketball' | 'futbol': define los valores por defecto de empates/puntos al crear la copa
    deporte TEXT NOT NULL DEFAULT 'basketball',
    -- informativo: se muestra en el sitio, no genera automáticamente el cuadro de playoffs
    num_equipos INTEGER NOT NULL DEFAULT 8,
    -- catálogo fijo de fases posibles: dieciseisavos, octavos, cuartos, semifinal, final
    fases_playoff TEXT[] NOT NULL DEFAULT ARRAY['cuartos','semifinal','final'],
    permite_empates BOOLEAN NOT NULL DEFAULT FALSE,
    puntos_victoria INTEGER NOT NULL DEFAULT 2,
    puntos_empate INTEGER NOT NULL DEFAULT 0,
    puntos_derrota INTEGER NOT NULL DEFAULT 1,
    -- solo una copa debe tener esto en TRUE: es la que responde en las URLs sin prefijo /slug/
    es_predeterminado BOOLEAN NOT NULL DEFAULT FALSE,
    activo BOOLEAN NOT NULL DEFAULT TRUE,
    creado_en TIMESTAMP NOT NULL DEFAULT now()
);
-- Dueño de la copa (multi-usuario) y código corto para compartirla, aparte de la URL y el QR.
-- Aditivo/nullable aquí porque ya hay copas en producción; scripts/migrar_usuarios.php las
-- respalda con un usuario_id/codigo y luego pone estas columnas NOT NULL.
ALTER TABLE torneos ADD COLUMN IF NOT EXISTS usuario_id INTEGER;
ALTER TABLE torneos ADD COLUMN IF NOT EXISTS codigo TEXT UNIQUE;
-- FORMATO de la competencia:
--   'copa' = campeonato: fase de grupos + el cuadro de eliminación directa habilitado
--            en fases_playoff (comportamiento clásico).
--   'liga' = temporada regular y nada más: todo se decide en la tabla de puntos, no hay
--            fases de eliminación directa en ningún lado.
-- Ver torneo_es_liga()/torneo_fases_playoff() en app/Support/liga.php. Con DEFAULT, las
-- copas ya existentes quedan en 'copa' automáticamente, sin necesitar backfill.
-- (Esta columna existía antes con otro significado —activar plantilla/ficha solo en
-- "modo liga"—; esas funciones hoy están disponibles siempre, así que se reutilizó.)
ALTER TABLE torneos ADD COLUMN IF NOT EXISTS modo TEXT NOT NULL DEFAULT 'copa';
-- 'femenino' | 'masculino' | 'mixto' (no aplica / no se distingue). Ajusta "entrenador/a",
-- "jugador/a", etc. en todo el sitio sin tener que hardcodear un género fijo. DEFAULT
-- 'mixto' deja el lenguaje neutro-masculino genérico que ya usaba el sitio, así que las
-- copas existentes no cambian de texto hasta que el organizador elija explícitamente.
ALTER TABLE torneos ADD COLUMN IF NOT EXISTS genero TEXT NOT NULL DEFAULT 'mixto';

CREATE TABLE IF NOT EXISTS equipos (
    id INTEGER PRIMARY KEY,
    torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
    nombre TEXT NOT NULL,
    ciudad TEXT NOT NULL DEFAULT '',
    sede TEXT NOT NULL DEFAULT '',
    entrenador TEXT NOT NULL DEFAULT '',
    fundacion TEXT NOT NULL DEFAULT '',
    color_primario TEXT NOT NULL DEFAULT '#7b2ff7',
    color_secundario TEXT NOT NULL DEFAULT '#ff6b35',
    logo TEXT NOT NULL DEFAULT '',
    descripcion TEXT NOT NULL DEFAULT ''
);
-- Migración aditiva para la tabla que ya existía en producción (sin torneo_id todavía)
ALTER TABLE equipos ADD COLUMN IF NOT EXISTS torneo_id INTEGER REFERENCES torneos(id) ON DELETE CASCADE;

-- Solo se usa en modo 'liga': plantilla de jugadores por equipo (dorsal + nombre), reutilizada
-- en todos los partidos de la temporada. Sin FK a equipos(id) a propósito, mismo criterio que
-- ya usa este esquema (p.ej. partidos.equipo_local tampoco referencia equipos.id): la
-- integridad se valida en PHP, no en SQL.
CREATE TABLE IF NOT EXISTS jugadores (
    id INTEGER PRIMARY KEY,
    torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
    equipo_id INTEGER NOT NULL,
    dorsal TEXT NOT NULL,
    nombre TEXT NOT NULL,
    activo BOOLEAN NOT NULL DEFAULT TRUE
);
-- Posición habitual del jugador (portero/defensa/medio/delantero en fútbol;
-- base/escolta/alero/ala_pivot/pivot en basketball; vacío = sin definir). Ver
-- POSICIONES_POR_DEPORTE en app/Support/liga.php. Solo es el valor SUGERIDO: la posición
-- real de cada encuentro se guarda en partido_alineacion.
ALTER TABLE jugadores ADD COLUMN IF NOT EXISTS posicion TEXT NOT NULL DEFAULT '';

CREATE TABLE IF NOT EXISTS partidos (
    id INTEGER PRIMARY KEY,
    torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
    jornada INTEGER NOT NULL,
    equipo_local INTEGER NOT NULL,
    equipo_visitante INTEGER NOT NULL,
    fecha TEXT NOT NULL,
    hora TEXT NOT NULL,
    cancha TEXT NOT NULL DEFAULT '',
    estado TEXT NOT NULL DEFAULT 'programado',
    marcador_local INTEGER,
    marcador_visitante INTEGER,
    -- 'grupos' = fase regular (tabla de posiciones); las demás son las fases de playoff de la copa
    fase TEXT NOT NULL DEFAULT 'grupos'
);
ALTER TABLE partidos ADD COLUMN IF NOT EXISTS fase TEXT NOT NULL DEFAULT 'grupos';
ALTER TABLE partidos ADD COLUMN IF NOT EXISTS torneo_id INTEGER REFERENCES torneos(id) ON DELETE CASCADE;
ALTER TABLE partidos ADD COLUMN IF NOT EXISTS arbitro TEXT NOT NULL DEFAULT '';
ALTER TABLE partidos ADD COLUMN IF NOT EXISTS observaciones TEXT NOT NULL DEFAULT '';
-- Cronómetro del partido (ficha de eventos): 'detenido' | 'corriendo' | 'pausado' | 'finalizado'.
-- cronometro_inicio es el momento (con huso horario) en que arrancó el tramo actual, solo
-- mientras está 'corriendo' (NULL en cualquier otro estado); cronometro_segundos son los
-- segundos ya acumulados de tramos anteriores. El minuto en vivo se calcula sumando ambos
-- (ver partido_cronometro_segundos() en app/Support/liga.php).
ALTER TABLE partidos ADD COLUMN IF NOT EXISTS cronometro_estado TEXT NOT NULL DEFAULT 'detenido';
ALTER TABLE partidos ADD COLUMN IF NOT EXISTS cronometro_inicio TIMESTAMPTZ;
ALTER TABLE partidos ADD COLUMN IF NOT EXISTS cronometro_segundos INTEGER NOT NULL DEFAULT 0;
-- Periodo actual del partido (1er/2do tiempo en fútbol, cuarto 1-4 en basketball). Avanzar
-- de periodo pone cronometro_segundos y cronometro_extra_min en 0, o sea que el reloj
-- vuelve a la duración completa configurada para la copa (cada tiempo/cuarto lleva su
-- propio conteo), ver la acción cronometro_siguiente_periodo en admin/partido_eventos.php
-- y partido_periodo_maximo()/partido_periodo_etiqueta() en app/Support/liga.php.
ALTER TABLE partidos ADD COLUMN IF NOT EXISTS cronometro_periodo INTEGER NOT NULL DEFAULT 1;
-- Minutos extra (tiempo añadido / reposición) que el árbitro sumó al periodo EN CURSO.
-- El cronómetro cuenta hacia atrás desde (duración configurada de la copa + estos
-- minutos); se reinicia a 0 al pasar de periodo, igual que cronometro_segundos.
-- Ver partido_duracion_periodo_segundos() en app/Support/liga.php.
ALTER TABLE partidos ADD COLUMN IF NOT EXISTS cronometro_extra_min INTEGER NOT NULL DEFAULT 0;

-- Alineación de cada encuentro: quién sale de titular, quién de banca y en qué posición
-- juega ese día. Cuántos titulares admite cada equipo lo define la modalidad de la copa
-- (5, 7 u 11 jugadores en cancha, ver torneo_jugadores_en_cancha() en app/Support/liga.php).
-- Sin FK a partidos(id)/jugadores(id) a propósito, mismo criterio que el resto del
-- esquema: la integridad se valida en PHP. partidos.id es una PK global, así que
-- (partido_id, jugador_id) identifica la fila sin ambigüedad entre copas.
CREATE TABLE IF NOT EXISTS partido_alineacion (
    torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
    partido_id INTEGER NOT NULL,
    jugador_id INTEGER NOT NULL,
    equipo_id INTEGER NOT NULL,
    titular BOOLEAN NOT NULL DEFAULT FALSE,
    posicion TEXT NOT NULL DEFAULT '',
    PRIMARY KEY (partido_id, jugador_id)
);

-- Solo se usa en modo 'liga': ficha del partido (goles, tarjetas, cambios), cargada por el admin
-- después de jugado. tipo = 'gol' | 'amarilla' | 'roja' | 'cambio'. jugador_entra_id solo aplica
-- a 'cambio'; tipo_gol y asistencia_jugador_id solo a 'gol'; motivo solo a las tarjetas. Sin FK a
-- partidos(id)/jugadores(id) a propósito, mismo criterio que el resto del esquema.
CREATE TABLE IF NOT EXISTS partido_eventos (
    id INTEGER PRIMARY KEY,
    torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
    partido_id INTEGER NOT NULL,
    tipo TEXT NOT NULL,
    equipo_id INTEGER NOT NULL,
    jugador_id INTEGER,
    jugador_entra_id INTEGER,
    minuto INTEGER,
    tipo_gol TEXT,
    asistencia_jugador_id INTEGER,
    motivo TEXT
);
-- Periodo en que se cargó el evento (1er/2do tiempo o cuarto 1-4, tomado del cronómetro
-- del partido en ese momento). Los eventos ya existentes antes de esta columna quedan en 1.
ALTER TABLE partido_eventos ADD COLUMN IF NOT EXISTS periodo INTEGER NOT NULL DEFAULT 1;

CREATE TABLE IF NOT EXISTS patrocinadores (
    id INTEGER PRIMARY KEY,
    torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
    nombre TEXT NOT NULL,
    nivel TEXT NOT NULL DEFAULT 'plata',
    url TEXT NOT NULL DEFAULT '',
    logo TEXT NOT NULL DEFAULT '',
    orden INTEGER NOT NULL DEFAULT 0
);
ALTER TABLE patrocinadores ADD COLUMN IF NOT EXISTS torneo_id INTEGER REFERENCES torneos(id) ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS comentarios (
    id INTEGER PRIMARY KEY,
    torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
    mensaje TEXT NOT NULL,
    fecha TEXT NOT NULL,
    leido INTEGER NOT NULL DEFAULT 0
);
ALTER TABLE comentarios ADD COLUMN IF NOT EXISTS torneo_id INTEGER REFERENCES torneos(id) ON DELETE CASCADE;

-- Almacena las imágenes subidas (escudos, logos, foto de perfil) como datos binarios, compartida
-- entre todas las copas. Se usa en vez de archivos en disco porque el plan gratuito de Render no
-- tiene disco persistente: cualquier archivo escrito en assets/img/ se perdería en el próximo
-- reinicio o despliegue.
CREATE TABLE IF NOT EXISTS imagenes (
    id SERIAL PRIMARY KEY,
    mime TEXT NOT NULL,
    datos BYTEA NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT now()
);

-- Registra cada intento de login (correcto o incorrecto) por IP, para limitar fuerza bruta.
-- Global: hay un solo admin para todas las copas.
CREATE TABLE IF NOT EXISTS intentos_login (
    id SERIAL PRIMARY KEY,
    ip TEXT NOT NULL,
    intentado_en TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_intentos_login_ip_fecha ON intentos_login (ip, intentado_en);

-- Singleton: un solo organizador para todas las copas. Reemplazada por 'usuarios' (multi-usuario);
-- se deja intacta como red de seguridad, sin usarse ya en el código.
CREATE TABLE IF NOT EXISTS organizador (
    id INTEGER PRIMARY KEY DEFAULT 1,
    usuario TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    nombre TEXT NOT NULL DEFAULT '',
    cargo TEXT NOT NULL DEFAULT '',
    email TEXT NOT NULL DEFAULT '',
    telefono TEXT NOT NULL DEFAULT '',
    foto TEXT NOT NULL DEFAULT '',
    bio TEXT NOT NULL DEFAULT '',
    CONSTRAINT organizador_singleton CHECK (id = 1)
);

-- Cada organizador registrado tiene su propia cuenta y sus propias copas (torneos.usuario_id).
CREATE TABLE IF NOT EXISTS usuarios (
    id SERIAL PRIMARY KEY,
    usuario TEXT UNIQUE NOT NULL,
    email TEXT UNIQUE NOT NULL,
    -- NULL para cuentas creadas solo con "Continuar con Google" (sin contraseña propia)
    password_hash TEXT,
    nombre TEXT NOT NULL DEFAULT '',
    cargo TEXT NOT NULL DEFAULT '',
    telefono TEXT NOT NULL DEFAULT '',
    foto TEXT NOT NULL DEFAULT '',
    bio TEXT NOT NULL DEFAULT '',
    creado_en TIMESTAMP NOT NULL DEFAULT now()
);
-- Identificador estable de Google ("sub"), para iniciar sesión con Google sin depender
-- del correo (que en teoría podría cambiar de dueño en Google en casos raros).
ALTER TABLE usuarios ADD COLUMN IF NOT EXISTS google_id TEXT UNIQUE;
ALTER TABLE usuarios ALTER COLUMN password_hash DROP NOT NULL;

-- Lista blanca de correos autorizados a crear una cuenta nueva con "Continuar con Google".
-- El registro público (usuario/contraseña) está cerrado; solo el/los super-admin (definidos
-- en la variable de entorno SUPERADMIN_EMAILS) pueden agregar/quitar correos de esta lista.
-- No bloquea a cuentas que ya existían antes de cerrar el registro público.
CREATE TABLE IF NOT EXISTS correos_autorizados (
    id SERIAL PRIMARY KEY,
    email TEXT UNIQUE NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT now()
);
-- Cuántas copas o ligas puede tener creadas ese correo (modelo de cobro por torneo: el
-- super-admin autoriza cupos conforme el organizador va pagando). DEFAULT 1 para que un
-- correo recién autorizado pueda crear su primer torneo sin configurar nada extra.
-- Los super-admin (SUPERADMIN_EMAILS) no tienen límite, ver usuario_limite_torneos().
ALTER TABLE correos_autorizados ADD COLUMN IF NOT EXISTS limite_torneos INTEGER NOT NULL DEFAULT 1;

-- Rate-limit de registro de cuentas nuevas, mismo patrón que intentos_login pero en su propia
-- tabla para no arriesgar el límite de login que ya funciona en producción.
CREATE TABLE IF NOT EXISTS intentos_registro (
    id SERIAL PRIMARY KEY,
    ip TEXT NOT NULL,
    intentado_en TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_intentos_registro_ip_fecha ON intentos_registro (ip, intentado_en);

-- Modalidad del deporte (futbol11/futbol7/futbol5 o fiba/nba) y duración personalizada
-- de cada tiempo/cuarto en minutos (NULL = usar la reglamentaria de la modalidad).
-- Ver MODALIDADES_POR_DEPORTE en app/Support/liga.php.
ALTER TABLE torneos ADD COLUMN IF NOT EXISTS modalidad TEXT NOT NULL DEFAULT '';
ALTER TABLE torneos ADD COLUMN IF NOT EXISTS duracion_periodo_min INTEGER;

-- Vueltas de la temporada regular: 1 = todos contra todos una vez; 2 = ida y vuelta
-- (cada par se enfrenta dos veces, invirtiendo la localía). Es lo que usa el generador
-- automático de calendario para saber cuántas jornadas armar; ver app/Support/fixture.php.
-- DEFAULT 1 para que las copas existentes no cambien de comportamiento.
ALTER TABLE torneos ADD COLUMN IF NOT EXISTS vueltas INTEGER NOT NULL DEFAULT 1;

-- Bitácora de acciones del panel (quién hizo qué y cuándo). Sin FK a torneos a propósito:
-- la entrada es historial y debe sobrevivir aunque la copa se borre después.
CREATE TABLE IF NOT EXISTS bitacora (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER,
    torneo_id INTEGER,
    accion TEXT NOT NULL,
    detalle TEXT NOT NULL DEFAULT '',
    creado_en TIMESTAMP NOT NULL DEFAULT now()
);

-- Tokens de "olvidé mi contraseña". Se guarda el hash SHA-256 del token (nunca el token
-- en claro), vence en 1 hora y se borra al usarse. Un token vigente por cuenta.
CREATE TABLE IF NOT EXISTS password_resets (
    id SERIAL PRIMARY KEY,
    usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
    token_hash TEXT UNIQUE NOT NULL,
    expira_en TIMESTAMPTZ NOT NULL,
    creado_en TIMESTAMP NOT NULL DEFAULT now()
);

-- Visitas al sitio público de cada copa, agregadas por día (no se guarda IP ni nada
-- identificable: solo un contador). Alimenta el dashboard del organizador para que
-- pueda ver cuánta gente sigue su torneo.
CREATE TABLE IF NOT EXISTS visitas_diarias (
    torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
    fecha DATE NOT NULL,
    visitas INTEGER NOT NULL DEFAULT 0,
    PRIMARY KEY (torneo_id, fecha)
);

-- Rate-limit de búsquedas por código de copa (evita fuerza bruta/scraping del formulario).
CREATE TABLE IF NOT EXISTS intentos_codigo (
    id SERIAL PRIMARY KEY,
    ip TEXT NOT NULL,
    intentado_en TIMESTAMP NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_intentos_codigo_ip_fecha ON intentos_codigo (ip, intentado_en);
