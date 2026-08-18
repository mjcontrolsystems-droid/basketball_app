<?php
declare(strict_types=1);


// La copa que resolvió el front controller a partir del slug de la URL.
$torneo = copa_actual();
$organizador = torneo_organizador($torneo) ?? ['nombre' => 'Organizador', 'cargo' => '', 'email' => '', 'telefono' => '', 'bio' => '', 'foto' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot: los bots suelen rellenar este campo oculto; los humanos lo dejan vacío
    if (!empty($_POST['sitio_web'])) {
        redirigir_con_mensaje(url_copa('organizador.php'), 'success', '¡Gracias por tu comentario!');
    }

    $ultimoEnvio = $_SESSION['ultimo_comentario'] ?? 0;
    if (time() - $ultimoEnvio < 20) {
        redirigir_con_mensaje(url_copa('organizador.php'), 'error', 'Espera unos segundos antes de enviar otro comentario.');
    }

    $mensaje = trim((string) ($_POST['mensaje'] ?? ''));

    if (mb_strlen($mensaje) < 5) {
        redirigir_con_mensaje(url_copa('organizador.php'), 'error', 'Tu comentario es muy corto. Cuéntanos un poco más.');
    } elseif (mb_strlen($mensaje) > 800) {
        redirigir_con_mensaje(url_copa('organizador.php'), 'error', 'Tu comentario es demasiado largo (máximo 800 caracteres).');
    } elseif (contiene_lenguaje_inapropiado($mensaje)) {
        redirigir_con_mensaje(url_copa('organizador.php'), 'error', 'Tu comentario contiene lenguaje inapropiado. Por favor reformúlalo con respeto.');
    } else {
        $comentarios = comentarios_listar($torneo['id']);
        $comentarios[] = [
            'id' => comentario_nuevo_id(),
            'mensaje' => $mensaje,
            'fecha' => date('Y-m-d H:i'),
            'leido' => false,
        ];
        comentarios_guardar_todos($comentarios, $torneo['id']);
        $_SESSION['ultimo_comentario'] = time();
        redirigir_con_mensaje(url_copa('organizador.php'), 'success', '¡Gracias! Tu comentario anónimo fue enviado a la organización.');
    }
}

$titulo_pagina = 'Organizador — ' . $torneo['nombre'];
$pagina_activa = 'organizador';

vista_publica('publico/organizador', compact(
    'organizador',
    'pagina_activa',
    'titulo_pagina',
    'torneo'
));
