<?php
declare(strict_types=1);

/**
 * Aceptar una invitación para ayudar a administrar una copa.
 *
 * Es pública a propósito: quien llega aquí normalmente todavía no tiene sesión, y muchas
 * veces ni cuenta. El flujo es:
 *
 *   1. Llega por el enlace del correo, ve de qué copa se trata y con qué rol.
 *   2. Entra con Google. El token queda guardado en la sesión para volver aquí solo.
 *   3. Se compara el correo con el que recibió la invitación. Si coincide, queda dentro.
 *
 * Ese último paso es la verificación: Google ya confirmó que la persona controla ese
 * correo, y el token confirma que la invitación es la que se envió a ese correo.
 */

$token = trim((string) ($_GET['t'] ?? ''));
$invitacion = $token !== '' ? colaborador_por_token($token) : null;

$titulo_pagina = 'Invitación para colaborar';
$flash = obtener_flash();
$estado = 'invalida';
$copa = null;
$nivel = '';
$emailInvitado = '';
$emailSesion = '';

if ($invitacion !== null) {
    $emailInvitado = (string) $invitacion['email'];
    $nivel = (string) $invitacion['nivel'];
    $copa = [
        'nombre' => (string) $invitacion['torneo_nombre'],
        'slug' => (string) $invitacion['torneo_slug'],
        'logo' => (string) ($invitacion['torneo_logo'] ?? ''),
    ];

    if (!auth_check()) {
        // Se guarda el token, NO una URL: así el regreso después de Google lo arma la
        // propia app y nadie puede colar un destino externo en el enlace.
        $_SESSION['invitacion_pendiente'] = $token;
        $estado = 'necesita_entrar';
    } else {
        $usuario = usuarios_obtener_por_id((int) $_SESSION['usuario_id']);
        $emailSesion = colaborador_normalizar_email((string) ($usuario['email'] ?? ''));

        if ($emailSesion !== colaborador_normalizar_email($emailInvitado)) {
            // Entró con otra cuenta de Google. Pasa seguido en celulares con dos cuentas.
            $estado = 'otro_correo';
        } else {
            colaborador_aceptar((int) $invitacion['id'], (int) $usuario['id']);
            unset($_SESSION['invitacion_pendiente']);
            $_SESSION['torneo_activo_id'] = (int) $invitacion['torneo_id'];

            bitacora_registrar(
                'colaborador_acepto',
                "{$emailInvitado} aceptó la invitación a " . $copa['nombre'],
                (int) $invitacion['torneo_id']
            );

            redirigir_con_mensaje(
                url('admin/index.php'),
                'success',
                '¡Listo! Ya estás dentro de ' . $copa['nombre'] . ' como ' . mb_strtolower(colaborador_nivel_nombre($nivel)) . '.'
            );
        }
    }
}

vista('publico/invitacion', compact(
    'copa',
    'emailInvitado',
    'emailSesion',
    'estado',
    'flash',
    'nivel',
    'titulo_pagina',
    'token'
));
