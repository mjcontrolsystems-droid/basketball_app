<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/usuarios.php';

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
}

/**
 * Exige que haya una copa activa elegida en la sesión del admin (equipos, partidos,
 * patrocinadores y comentarios viven "dentro" de una copa). Si no hay ninguna, o si la
 * copa activa no pertenece al usuario logueado (alguien manipuló torneo_activo_id, o
 * es de otro usuario), manda a elegir/crear una. Devuelve la copa ya resuelta.
 */
function admin_requerir_torneo_activo(): array
{
    $torneoId = $_SESSION['torneo_activo_id'] ?? null;
    $usuarioId = (int) ($_SESSION['usuario_id'] ?? 0);
    $torneo = $torneoId !== null ? torneos_obtener_por_id((int) $torneoId, $usuarioId) : null;

    if ($torneo === null) {
        unset($_SESSION['torneo_activo_id']);
        header('Location: ' . url('admin/torneos.php'));
        exit;
    }

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
