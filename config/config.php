<?php
declare(strict_types=1);

date_default_timezone_set('America/Guatemala');

define('BASE_DIR', dirname(__DIR__));
define('DATA_DIR', BASE_DIR . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR);

// Carga variables de entorno desde .env en local (en Render, DATABASE_URL ya viene como variable de entorno real)
$archivoEnv = BASE_DIR . DIRECTORY_SEPARATOR . '.env';
if (getenv('DATABASE_URL') === false && file_exists($archivoEnv)) {
    foreach (file($archivoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
            continue;
        }
        [$clave, $valor] = explode('=', $linea, 2);
        $valor = trim($valor);

        // Se quitan las comillas que envuelven el valor. Hacen falta en el archivo cuando
        // el valor lleva espacios — MAIL_FROM="MJ Control Systems <avisos@...>" — pero si
        // se dejan pasan a formar parte del texto, y entonces el remitente que se le manda
        // a Resend es literalmente «"MJ Control Systems <...>"» con comillas. Resend lo
        // rechaza y el correo nunca sale, sin que nada avise por qué.
        if (strlen($valor) >= 2
            && (($valor[0] === '"' && $valor[-1] === '"') || ($valor[0] === "'" && $valor[-1] === "'"))
        ) {
            $valor = substr($valor, 1, -1);
        }

        putenv(trim($clave) . '=' . $valor);
    }
}

// Contacto de MJ Control Systems (quien hace la plataforma), no del organizador de cada
// copa. Va en el pie del sitio y en la ficha imprimible del partido. Como constante y no
// suelto en cada plantilla: estaba escrito a mano en la ficha y ya iba camino a
// desincronizarse del resto.
const CONTACTO_PLATAFORMA = 'mjcontrolsystems@gmail.com';
const LEMA_PLATAFORMA = 'Plataformas web inteligentes, control total de tu negocio.';

// Credenciales de Google OAuth ("Continuar con Google"). En Render se configuran como
// variables de entorno reales (panel "Environment"), igual que DATABASE_URL.
define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '');
define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET') ?: '');

// Correos (en minúsculas) de las cuentas super-admin: son las únicas que pueden gestionar
// la lista blanca de correos autorizados a entrar con Google.
//
// Se combinan dos fuentes: (1) una lista base fija en el código (SUPERADMIN_EMAILS_BASE),
// que garantiza que estas cuentas siempre sean super-admin aunque la variable de entorno
// no esté configurada en el servidor; y (2) los correos extra de la variable de entorno
// SUPERADMIN_EMAILS (separados por coma), para poder sumar más sin tocar el código.
const SUPERADMIN_EMAILS_BASE = [
    'sagastumejosue71@gmail.com',
    'mjcontrolsystems@gmail.com',
];
define('SUPERADMIN_EMAILS', array_values(array_unique(array_filter(array_map(
    fn($e) => mb_strtolower(trim($e)),
    array_merge(SUPERADMIN_EMAILS_BASE, explode(',', getenv('SUPERADMIN_EMAILS') ?: ''))
)))));

// Render (y la mayoría de hostings con proxy) terminan el HTTPS antes de llegar a PHP;
// hay que revisar X-Forwarded-Proto además de $_SERVER['HTTPS'] para detectarlo correctamente.
$esHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => $esHttps,
    ]);
    session_start();
}

// Cabeceras de seguridad básicas para todas las respuestas del sitio
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    // object-src y frame-src se declaran explícitamente (aunque heredarían de default-src)
    // porque el visor del reglamento incrusta un PDF propio con <object>: dejarlo implícito
    // hace que algunos navegadores lo bloqueen. Ambos siguen limitados al propio sitio.
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; object-src 'self'; frame-src 'self';");
    if ($esHttps) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Evita filtrar detalles internos (rutas, credenciales de conexión, stack traces) a los visitantes
ini_set('display_errors', '0');

/**
 * Monitoreo de errores sin dependencias: si la variable de entorno ERROR_WEBHOOK_URL
 * está configurada (un webhook de Discord o Slack sirve tal cual), cada error fatal de
 * producción se avisa ahí en el momento — sin esperar a que lo reporte un cliente.
 * Si no está configurada, no hace nada (el error igual queda en el log del servidor).
 */
function notificar_error_webhook(Throwable $e): void
{
    $webhook = getenv('ERROR_WEBHOOK_URL') ?: '';
    if ($webhook === '' || !function_exists('curl_init')) {
        return;
    }
    // Formato compatible con Discord ("content") y Slack ("text") a la vez.
    $mensaje = '⚠️ Error en la plataforma: ' . $e->getMessage()
        . "\nArchivo: " . basename($e->getFile()) . ':' . $e->getLine()
        . "\nURL: " . (($_SERVER['REQUEST_METHOD'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? 'cli'));
    $ch = curl_init($webhook);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 3,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode(['content' => $mensaje, 'text' => $mensaje]),
    ]);
    @curl_exec($ch);
    curl_close($ch);
}

set_exception_handler(function (Throwable $e): void {
    error_log($e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    notificar_error_webhook($e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    // Al organizador que tiene sesión abierta SÍ se le muestra el detalle técnico. Antes
    // solo veía "Ocurrió un error inesperado" y no había forma de saber qué falló sin
    // pedirle los logs del servidor a alguien. Al visitante anónimo no se le muestra nada:
    // la ruta de un archivo o el texto de una consulta son información que no le compete.
    // Y no a cualquiera con sesión: solo al superadmin. El mensaje de un error de base de
    // datos puede traer la fila entera que se intentaba guardar, y desde que existen los
    // colaboradores hay gente con sesión abierta que no tiene por qué ver eso.
    $esAdmin = false;
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['usuario_id'])) {
        try {
            // Va en try porque este es el manejador de errores: si la base es justamente lo
            // que está fallando, consultarla aquí tumbaría también la página de error.
            $quien = usuarios_obtener_por_id((int) $_SESSION['usuario_id']);
            $esAdmin = es_superadmin($quien);
        } catch (Throwable $ignorado) {
            $esAdmin = false;
        }
    }
    $escapar = fn($t) => htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');

    $detalle = '';
    if ($esAdmin) {
        $traza = array_slice(explode("\n", $e->getTraceAsString()), 0, 6);
        $detalle = '<div style="margin-top:1.5rem;text-align:left;background:#fff;border:1px solid #e6e2f0;'
            . 'border-radius:14px;padding:1rem 1.15rem;max-width:760px;">'
            . '<p style="margin:0 0 .5rem;font-weight:600;font-size:.9rem;color:#b93130;">Detalle técnico</p>'
            . '<p style="margin:0 0 .35rem;font-size:.9rem;"><strong>' . $escapar($e->getMessage()) . '</strong></p>'
            . '<p style="margin:0 0 .75rem;font-size:.82rem;color:#6c757d;">'
            . $escapar(basename($e->getFile())) . ' línea ' . (int) $e->getLine() . '</p>'
            . '<pre style="margin:0;font-size:.75rem;color:#6c757d;white-space:pre-wrap;">'
            . $escapar(implode("\n", $traza)) . '</pre>'
            . '<p style="margin:.75rem 0 0;font-size:.8rem;color:#6c757d;">Esto solo lo ve el administrador de la plataforma. Los visitantes ven únicamente el aviso de arriba.</p>'
            . '</div>';
    }

    echo '<!doctype html><html lang="es"><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>Algo salió mal</title>'
        . '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;'
        . 'font-family:Inter,system-ui,-apple-system,sans-serif;background:#f6f4fb;color:#241a3a;padding:2rem;}'
        . '.caja{text-align:center;max-width:760px;}'
        . 'h1{font-size:1.4rem;margin:0 0 .5rem;}p.msg{color:#5f5e6a;margin:0 0 1.5rem;}'
        . 'a.btn{display:inline-block;background:#7b2ff7;color:#fff;text-decoration:none;'
        . 'padding:.6rem 1.4rem;border-radius:999px;font-weight:600;}</style></head><body>'
        . '<div class="caja">'
        . '<div style="font-size:3rem;line-height:1;margin-bottom:.5rem;">⚠️</div>'
        . '<h1>Algo salió mal</h1>'
        . '<p class="msg">No se pudo cargar esta página. Vuelve a intentarlo en un momento.</p>'
        . '<a class="btn" href="' . $escapar($_SERVER['HTTP_REFERER'] ?? '/') . '">Volver</a>'
        . $detalle
        . '</div></body></html>';
    exit;
});

// Ruta base para generar enlaces. Con front controller la raíz pública es siempre el
// directorio donde vive public/index.php, así que ya no hay que deducirla del script en
// curso (antes había que corregir a mano el caso de /admin, porque cada página del panel
// era su propio punto de entrada y "dirname" devolvía /admin).
if (!defined('BASE_URL')) {
    define('BASE_URL', '');
}

// Dominio + esquema completos, para mostrar/copiar enlaces absolutos (ej. la URL de una
// copa en el panel admin) sin depender de la página desde la que se generan.
if (!defined('SITE_ORIGIN')) {
    define('SITE_ORIGIN', ($esHttps ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
}

function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * Igual que url(), pero para assets propios (CSS/JS) agrega ?v=<fecha de modificación
 * del archivo> para que el navegador pida la versión nueva en cuanto el archivo cambia
 * en el servidor, en vez de seguir sirviendo una copia cacheada indefinidamente (el
 * navegador cachea agresivamente un mismo nombre de archivo entre visitas).
 */
function asset_url(string $path): string
{
    // Los assets viven dentro de public/ (el directorio web), pero la URL no lleva ese
    // prefijo porque public/ ES la raíz del sitio.
    $absoluto = BASE_DIR . '/public/' . ltrim($path, '/');
    $version = is_file($absoluto) ? (string) filemtime($absoluto) : '1';
    return url($path) . '?v=' . $version;
}

/**
 * URL pública absoluta (con dominio) de una copa cualquiera, sin depender del $torneo
 * de la petición actual. Útil para mostrar/copiar el enlace de cada copa en el admin.
 */
function url_copa_de(array $torneo, string $path = ''): string
{
    $prefijo = (empty($torneo['es_predeterminado']) && !empty($torneo['slug'])) ? '/' . $torneo['slug'] : '';
    return SITE_ORIGIN . BASE_URL . $prefijo . '/' . ltrim($path, '/');
}

/**
 * Igual que url(), pero antepone el slug de la copa que está resolviendo esta petición
 * (ver copa_actual() en app/Support/vista.php) cuando no es la copa predeterminada, para
 * que los links del sitio se queden dentro de la misma copa (/slug/tabla.php).
 */
function url_copa(string $path = ''): string
{
    $torneo = copa_actual();
    $prefijo = (!empty($torneo) && empty($torneo['es_predeterminado']) && !empty($torneo['slug']))
        ? '/' . $torneo['slug']
        : '';
    return BASE_URL . $prefijo . '/' . ltrim($path, '/');
}
