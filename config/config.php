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
        putenv(trim($clave) . '=' . trim($valor));
    }
}

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
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net; font-src 'self' https://fonts.gstatic.com https://cdn.jsdelivr.net; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com;");
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
    }
    echo 'Ocurrió un error inesperado. Por favor intenta de nuevo más tarde.';
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
