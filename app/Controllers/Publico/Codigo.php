<?php
declare(strict_types=1);

// Búsqueda pública por código corto: no necesita CSRF (es una consulta GET + redirección,
// igual criterio que admin/torneos.php?accion=entrar), pero sí límite de intentos para
// evitar que alguien intente adivinar códigos por fuerza bruta.
$ip = obtener_ip_cliente();
if (codigo_ip_bloqueada($ip)) {
    redirigir_con_mensaje(url('/'), 'error', 'Demasiados intentos. Espera unos minutos antes de volver a intentar.');
}
codigo_registrar_intento($ip);

$codigo = strtoupper(trim((string) ($_GET['c'] ?? '')));

if (!preg_match('/^[' . preg_quote(TORNEO_CODIGO_ALFABETO, '/') . ']{6}$/', $codigo)) {
    redirigir_con_mensaje(url('/'), 'error', 'Ese código no tiene un formato válido.');
}

$torneo = torneos_obtener_por_codigo($codigo);
if ($torneo === null) {
    redirigir_con_mensaje(url('/'), 'error', 'No encontramos ninguna copa ni liga con ese código.');
}

header('Location: ' . url_copa_de($torneo));
exit;
