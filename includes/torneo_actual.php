<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

/**
 * Resuelve qué copa corresponde a esta petición.
 * - Si la URL trae ?copa=slug (via la reescritura de /slug/...), se busca esa copa.
 * - Si no hay slug (acceso a la raíz del sitio o a un archivo sin prefijo), no hay
 *   ninguna copa "por defecto": se manda al visitante al listado de todas las copas
 *   para que elija una. Cada copa, incluida Copa Estrellas, vive solo bajo su propio
 *   /slug/ — ninguna es especial.
 *
 * Deja la copa resuelta en $torneo (mismo nombre de variable que ya usaban todas las
 * plantillas existentes), para no tener que renombrar cada referencia a $torneo['...'].
 */
$slugSolicitado = $_GET['copa'] ?? null;

if ($slugSolicitado === null) {
    header('Location: ' . url('torneos.php'));
    exit;
}

$torneo = torneos_obtener_por_slug($slugSolicitado);

if ($torneo === null) {
    http_response_code(404);
    ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Copa o liga no encontrada</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#120a24;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;">
    <div class="p-4">
        <div style="font-size:3rem;">🏆</div>
        <h1 class="mb-3">Copa o liga no encontrada</h1>
        <p class="mb-4" style="color:rgba(255,255,255,.7);">Esta copa o liga no existe o ya no está activa.</p>
        <a href="/" class="btn btn-light rounded-pill px-4">Ir al inicio</a>
    </div>
</body>
</html>
    <?php
    exit;
}

// Estadística de visitas del sitio público de la copa: se suma una visita por sesión de
// navegador por día (no por página vista, para no inflar el número recargando). Solo un
// contador agregado por día — no se guarda IP ni ningún dato identificable del visitante.
// El propio organizador logueado no cuenta como visita de su copa.
$visitaClave = 'visita_' . (int) $torneo['id'] . '_' . date('Y-m-d');
if (empty($_SESSION[$visitaClave]) && empty($_SESSION['usuario_autenticado'])) {
    $_SESSION[$visitaClave] = true;
    try {
        visitas_registrar((int) $torneo['id']);
    } catch (Throwable $e) {
        // La estadística nunca debe tumbar el sitio público (p. ej. si la tabla
        // visitas_diarias todavía no existe porque no se corrió la migración).
        error_log('visitas_registrar: ' . $e->getMessage());
    }
}
