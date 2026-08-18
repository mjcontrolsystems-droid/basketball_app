<?php
declare(strict_types=1);

/**
 * Router para el servidor embebido de PHP en desarrollo local:
 *
 *     php -S 127.0.0.1:8000 -t public router.php
 *
 * Cuando se le pasa un router, el servidor embebido manda TODAS las peticiones aquí,
 * incluidas las de archivos reales: devolver false es la forma de decirle "este lo
 * sirves tú". Todo lo demás va al front controller, igual que hace Apache en
 * producción (ver apache-vhost.conf).
 */

$uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ($uri !== '/' && is_file(__DIR__ . '/public' . $uri)) {
    return false; // assets, favicon: los sirve el servidor embebido
}

require __DIR__ . '/public/index.php';
