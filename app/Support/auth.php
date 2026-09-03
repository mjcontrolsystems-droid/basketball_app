<?php
declare(strict_types=1);

require_once __DIR__ . '/bd.php';
require_once __DIR__ . '/../Models/Usuario.php';

const LOGIN_MAX_INTENTOS = 5;
const LOGIN_VENTANA_SEGUNDOS = 60;

const REGISTRO_MAX_INTENTOS = 5;
const REGISTRO_VENTANA_SEGUNDOS = 300;

const CODIGO_MAX_INTENTOS = 20;
const CODIGO_VENTANA_SEGUNDOS = 300;

function auth_check(): bool
{
    return !empty($_SESSION['usuario_autenticado']);
}

/**
 * IP real del visitante detrás del proxy de Render.
 *
 * X-Forwarded-For es una lista que cada proxy en el camino va completando:
 * "ip_dicha_por_el_cliente, ip_vista_por_el_siguiente_proxy, ...". El primer valor
 * lo puede inventar cualquiera con curl -H, así que NO sirve para bloquear fuerza
 * bruta. El único valor confiable es el ÚLTIMO: el que Render agregó al recibir
 * la conexión real, que el cliente no puede falsificar.
 */
function obtener_ip_cliente(): string
{
    $reenviada = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($reenviada !== '') {
        $partes = array_map('trim', explode(',', $reenviada));
        $ip = end($partes);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * true si esta IP ya alcanzó el máximo de intentos de login permitidos en la última ventana de tiempo.
 */
function auth_ip_bloqueada(string $ip): bool
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM intentos_login WHERE ip = :ip AND intentado_en > now() - make_interval(secs => :segundos)'
    );
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->bindValue(':segundos', LOGIN_VENTANA_SEGUNDOS, PDO::PARAM_INT);
    $stmt->execute();
    return (int) $stmt->fetchColumn() >= LOGIN_MAX_INTENTOS;
}

/**
 * Registra un intento de login (exitoso o no) para efectos del límite de intentos,
 * y aprovecha para limpiar registros viejos.
 */
function auth_registrar_intento(string $ip): void
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('INSERT INTO intentos_login (ip) VALUES (:ip)');
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->execute();

    $pdo->exec("DELETE FROM intentos_login WHERE intentado_en < now() - interval '1 hour'");
}

function auth_intentar_login(string $usuario, string $password): bool
{
    $cuenta = usuarios_obtener_por_usuario($usuario);
    if ($cuenta === null) {
        return false;
    }

    if (!password_verify($password, (string) $cuenta['password_hash'])) {
        return false;
    }

    auth_iniciar_sesion_usuario($cuenta);
    return true;
}

/**
 * Marca la sesión como autenticada para la cuenta dada. La usan tanto el login normal
 * como el registro (para dejar al usuario logueado apenas crea su cuenta).
 */
function auth_iniciar_sesion_usuario(array $usuario): void
{
    session_regenerate_id(true);
    $_SESSION['usuario_autenticado'] = true;
    $_SESSION['usuario_id'] = (int) $usuario['id'];
    $_SESSION['organizador_usuario'] = $usuario['usuario'];
    // Trazabilidad de accesos en la bitácora (nunca falla la sesión por esto).
    bitacora_registrar('login', 'Sesión iniciada (' . ($usuario['email'] ?? $usuario['usuario']) . ')');
}

function auth_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function auth_requerir(): void
{
    if (!auth_check()) {
        header('Location: ' . url('login.php'));
        exit;
    }
    // Migraciones idempotentes del esquema, una vez por sesión. Se dispara aquí y no solo
    // en admin_layout_top.php porque las páginas del panel procesan sus POST ANTES de
    // pintar el layout: una escritura que use una columna recién agregada (p. ej.
    // partidos.cronometro_extra_min) tiene que encontrarla ya creada.
    db_migrar_automatico();
}

/**
 * Qué puede hacer cada quien dentro de una copa.
 *
 * La tabla PERMISOS_POR_NIVEL vive junto a la definición de los niveles, en
 * Models/Colaborador.php: son las dos mitades de lo mismo (qué niveles hay y qué alcanza
 * cada uno), y tenerlas separadas hacía fácil agregar un nivel y olvidar sus permisos.
 */

/**
 * Nivel de la persona logueada en la copa activa: 'dueno', 'mesa', 'asistente' o null.
 *
 * Se resuelve una vez por petición y se guarda en una estática: lo consultan el menú, las
 * vistas y cada controlador, y no tiene sentido ir a la base cada vez.
 */
function nivel_en_copa(?array $torneo = null): ?string
{
    return acceso_en_copa($torneo)['nivel'];
}

/**
 * El acceso completo en la copa activa: nivel y, para el capitán, su equipo.
 *
 * @return array{nivel: ?string, equipo_id: ?int}
 */
function acceso_en_copa(?array $torneo = null): array
{
    static $cache = [];

    $sinAcceso = ['nivel' => null, 'equipo_id' => null];

    $torneo = $torneo ?? copa_actual();
    $torneoId = (int) ($torneo['id'] ?? 0);
    $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
    if ($torneoId <= 0 || $usuarioId <= 0) {
        return $sinAcceso;
    }

    $clave = $torneoId . ':' . $usuarioId;
    if (array_key_exists($clave, $cache)) {
        return $cache[$clave];
    }

    if ((int) ($torneo['usuario_id'] ?? 0) === $usuarioId) {
        return $cache[$clave] = ['nivel' => 'dueno', 'equipo_id' => null];
    }

    $usuario = usuarios_obtener_por_id($usuarioId);
    // El superadmin entra a todo como dueño: es quien da soporte cuando algo se rompe.
    if (es_superadmin($usuario)) {
        return $cache[$clave] = ['nivel' => 'dueno', 'equipo_id' => null];
    }

    return $cache[$clave] = colaborador_acceso_de($torneoId, $usuarioId, (string) ($usuario['email'] ?? ''))
        ?? $sinAcceso;
}

/**
 * El equipo del capitán logueado, o null si no es capitán.
 *
 * Todo el candado de este nivel cuelga de aquí. Devolver null para alguien que SÍ es
 * capitán abriría la copa entera, así que un capitán sin equipo asignado (dato roto) se
 * trata como si no tuviera acceso: ver capitan_puede_con_equipo().
 */
function equipo_del_capitan(?array $torneo = null): ?int
{
    $acceso = acceso_en_copa($torneo);

    return $acceso['nivel'] === 'capitan' ? $acceso['equipo_id'] : null;
}

function es_capitan(?array $torneo = null): bool
{
    return nivel_en_copa($torneo) === 'capitan';
}

/**
 * ¿Puede la persona logueada trabajar sobre ESTE equipo?
 *
 * Para el dueño y los colaboradores de copa, todos. Para un capitán, solo el suyo. Es la
 * pregunta que hay que hacer antes de guardar o borrar cualquier cosa que cuelgue de un
 * equipo — un jugador, el escudo, los datos —, porque ocultar el botón no impide que
 * alguien mande el formulario con otro id.
 */
function capitan_puede_con_equipo(int $equipoId, ?array $torneo = null): bool
{
    $acceso = acceso_en_copa($torneo);

    return acceso_alcanza_equipo($acceso['nivel'], $acceso['equipo_id'], $equipoId);
}

/**
 * Corta la petición si el equipo no es el suyo. Va junto a requerir_permiso(), antes de
 * procesar el POST.
 */
function requerir_equipo_propio(int $equipoId, ?array $torneo = null): void
{
    if (capitan_puede_con_equipo($equipoId, $torneo)) {
        return;
    }

    redirigir_con_mensaje(
        url('admin/index.php'),
        'error',
        'Solo puedes trabajar sobre tu propio equipo.'
    );
}

function es_dueno_de_copa(?array $torneo = null): bool
{
    return nivel_en_copa($torneo) === 'dueno';
}

/**
 * ¿Puede la persona logueada hacer esta acción en la copa activa?
 */
function puede(string $accion, ?array $torneo = null): bool
{
    $nivel = nivel_en_copa($torneo);
    if ($nivel === null) {
        return false;
    }
    if ($nivel === 'dueno') {
        return true;
    }

    return in_array($accion, PERMISOS_POR_NIVEL[$nivel] ?? [], true);
}

/**
 * Corta la petición si no tiene permiso. Va al principio del controlador, ANTES de
 * procesar cualquier POST: ocultar el botón del menú no sirve de nada si la URL sigue
 * respondiendo a quien la escriba a mano.
 */
function requerir_permiso(string $accion, ?array $torneo = null): void
{
    if (puede($accion, $torneo)) {
        return;
    }

    redirigir_con_mensaje(
        url('admin/index.php'),
        'error',
        'No tienes permiso para entrar ahí. Si crees que deberías, pídeselo a quien organiza la copa.'
    );
}

/**
 * Exige que haya una copa activa elegida en la sesión del admin (equipos, partidos,
 * patrocinadores y comentarios viven "dentro" de una copa). Si no hay ninguna, o si la
 * persona no es ni dueña ni colaboradora de esa copa (alguien manipuló torneo_activo_id,
 * o es de otro usuario), manda a elegir/crear una. Devuelve la copa ya resuelta.
 */
function admin_requerir_torneo_activo(): array
{
    $torneoId = $_SESSION['torneo_activo_id'] ?? null;
    $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
    // Se busca sin filtrar por dueño y el permiso se decide después, porque ahora una
    // copa la puede abrir tanto quien la creó como quien fue invitado a ayudar.
    $torneo = $torneoId !== null ? torneos_obtener_por_id((int) $torneoId) : null;

    if ($torneo !== null && nivel_en_copa($torneo) === null) {
        $torneo = null;
    }

    if ($torneo === null) {
        unset($_SESSION['torneo_activo_id']);
        header('Location: ' . url('admin/torneos.php'));
        exit;
    }

    // La copa activa del panel es también el contexto para generar enlaces: así
    // url_copa() sigue apuntando al sitio público de ESTA copa (/slug/partido_vivo.php)
    // desde las pantallas del organizador, que no viven bajo ningún /slug/.
    copa_actual_definir($torneo);

    return $torneo;
}

function registro_ip_bloqueada(string $ip): bool
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM intentos_registro WHERE ip = :ip AND intentado_en > now() - make_interval(secs => :segundos)'
    );
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->bindValue(':segundos', REGISTRO_VENTANA_SEGUNDOS, PDO::PARAM_INT);
    $stmt->execute();
    return (int) $stmt->fetchColumn() >= REGISTRO_MAX_INTENTOS;
}

function registro_registrar_intento(string $ip): void
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('INSERT INTO intentos_registro (ip) VALUES (:ip)');
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->execute();

    $pdo->exec("DELETE FROM intentos_registro WHERE intentado_en < now() - interval '1 hour'");
}

function codigo_ip_bloqueada(string $ip): bool
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM intentos_codigo WHERE ip = :ip AND intentado_en > now() - make_interval(secs => :segundos)'
    );
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->bindValue(':segundos', CODIGO_VENTANA_SEGUNDOS, PDO::PARAM_INT);
    $stmt->execute();
    return (int) $stmt->fetchColumn() >= CODIGO_MAX_INTENTOS;
}

function codigo_registrar_intento(string $ip): void
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('INSERT INTO intentos_codigo (ip) VALUES (:ip)');
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->execute();

    $pdo->exec("DELETE FROM intentos_codigo WHERE intentado_en < now() - interval '1 hour'");
}

/**
 * Token anti-CSRF para formularios del panel: evita que un sitio externo pueda enviar
 * acciones (crear/editar/eliminar) en nombre del organizador ya autenticado.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_validar(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        exit('Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.');
    }
}
