<?php
declare(strict_types=1);

/**
 * FRONT CONTROLLER — único punto de entrada del sitio.
 *
 * El servidor manda aquí todo lo que no sea un archivo real de public/ (ver .htaccess y
 * apache-vhost.conf). Este archivo:
 *   1. arranca la aplicación (config + soporte + modelos),
 *   2. separa el slug de la copa del resto de la ruta  (/liga-municipal/tabla.php),
 *   3. resuelve la copa cuando la ruta la necesita,
 *   4. y delega en el controlador correspondiente (app/Controllers/...).
 *
 * Nada más de la aplicación es accesible por web: los controladores, las vistas, los
 * modelos, el esquema y el .env viven FUERA de este directorio.
 */

require_once dirname(__DIR__) . '/config/bootstrap.php';

$rutas = require RAIZ_APP . '/config/rutas.php';

// --- 1. Ruta pedida, relativa a la raíz del sitio ---
$ruta = trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');

// --- 2. ¿La ruta empieza con el slug de una copa? ---
// Formatos posibles:  ""  |  "slug"  |  "slug/archivo.php"  |  "archivo.php"  |  "admin/x.php"
$slugCopa = null;
if ($ruta === '') {
    // Raíz del sitio: la portada de la plataforma, que no pertenece a ninguna copa.
    $ruta = 'portada.php';
} elseif (preg_match('#^([a-z0-9-]+)$#', $ruta, $m) && !isset($rutas[$ruta])) {
    // "/liga-municipal" -> portada de esa copa
    $slugCopa = $m[1];
    $ruta = 'index.php';
} elseif (preg_match('#^([a-z0-9-]+)/(.+)$#', $ruta, $m) && $m[1] !== 'admin') {
    // "/liga-municipal/tabla.php" -> tabla.php dentro de esa copa
    $slugCopa = $m[1];
    $ruta = $m[2];
}

// --- 3. ¿Existe esa ruta? ---
if (!isset($rutas[$ruta])) {
    http_response_code(404);
    require RAIZ_APP . '/app/Controllers/Publico/NoEncontrado.php';
    exit;
}
$destino = $rutas[$ruta];

// --- 4. Resolver la copa cuando la página vive dentro de una ---
if ($destino['copa']) {
    if ($slugCopa === null) {
        // Una página de copa pedida sin slug (ej. /tabla.php a secas) no tiene contexto:
        // no hay copa "por defecto", así que se manda al inicio a elegir una.
        header('Location: ' . url('/'));
        exit;
    }
    $torneo = torneos_obtener_por_slug($slugCopa);
    if ($torneo === null) {
        http_response_code(404);
        require RAIZ_APP . '/app/Controllers/Publico/CopaNoEncontrada.php';
        exit;
    }
    copa_actual_definir($torneo);
    copa_registrar_visita($torneo);
}

require RAIZ_APP . '/app/Controllers/' . $destino['controlador'] . '.php';

/**
 * Estadística de visitas del sitio público de la copa: se suma una por sesión de
 * navegador y por día (no por página vista, para no inflar el número recargando). Solo
 * un contador agregado — no se guarda IP ni nada identificable del visitante. El propio
 * organizador logueado no cuenta como visita de su copa.
 */
function copa_registrar_visita(array $torneo): void
{
    $clave = 'visita_' . (int) $torneo['id'] . '_' . date('Y-m-d');
    if (!empty($_SESSION[$clave]) || !empty($_SESSION['usuario_autenticado'])) {
        return;
    }
    $_SESSION[$clave] = true;
    try {
        visitas_registrar((int) $torneo['id']);
    } catch (Throwable $e) {
        // La estadística nunca debe tumbar el sitio público (p. ej. si la tabla
        // visitas_diarias todavía no existe porque no se corrió la migración).
        error_log('visitas_registrar: ' . $e->getMessage());
    }
}
