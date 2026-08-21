<?php
declare(strict_types=1);

/**
 * Quién más puede administrar esta copa, y hasta dónde.
 *
 * Solo el dueño entra aquí: dar permisos es la acción que permite dar todas las demás.
 */

auth_requerir();
$torneo = admin_requerir_torneo_activo();
requerir_permiso('colaboradores');

$urlLista = url('admin/colaboradores.php');
$usuarioId = (int) $_SESSION['usuario_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (($_POST['accion'] ?? '') === 'invitar') {
        $email = colaborador_normalizar_email((string) ($_POST['email'] ?? ''));
        $nivel = (string) ($_POST['nivel'] ?? 'mesa');

        try {
            colaboradores_guardar($torneo['id'], $email, $nivel, $usuarioId);
        } catch (RuntimeException $e) {
            redirigir_con_mensaje($urlLista, 'error', $e->getMessage());
        }

        // Sin esto la invitación sería un callejón sin salida: el alta de cuentas es por
        // lista blanca, así que invitar a alguien implica autorizar su correo. Se le da
        // cupo 0 de copas propias — viene a ayudar en esta, no a crear las suyas.
        $yaAutorizado = correo_autorizado($email);
        if (!$yaAutorizado) {
            correos_autorizados_agregar($email, 0);
        }

        bitacora_registrar(
            'colaborador_invitado',
            "{$email} agregado como " . mb_strtolower(colaborador_nivel_nombre($nivel)) . " en " . $torneo['nombre'],
            $torneo['id']
        );

        $enviado = colaborador_enviar_invitacion($torneo, $email, $usuarioId);

        redirigir_con_mensaje(
            $urlLista,
            $enviado ? 'success' : 'warning',
            $enviado
                ? "Invitación enviada a {$email}. Cuando la acepte va a entrar como " . mb_strtolower(colaborador_nivel_nombre($nivel)) . '.'
                : "{$email} quedó agregado, pero el correo no salió. " . correo_ultimo_error()
                    . ' Mientras tanto puede entrar igual con su Google usando ese mismo correo.'
        );
    }

    if (($_POST['accion'] ?? '') === 'reenviar') {
        $id = (int) ($_POST['id'] ?? 0);

        $destino = null;
        foreach (colaboradores_listar($torneo['id']) as $c) {
            if ($c['id'] === $id) {
                $destino = $c;
            }
        }
        if ($destino === null) {
            redirigir_con_mensaje($urlLista, 'error', 'Ese colaborador ya no está en la lista.');
        }

        $enviado = colaborador_enviar_invitacion($torneo, $destino['email'], $usuarioId);
        redirigir_con_mensaje(
            $urlLista,
            $enviado ? 'success' : 'error',
            $enviado
                ? "Invitación reenviada a {$destino['email']}. El enlace anterior dejó de servir."
                : 'No se pudo enviar el correo. ' . correo_ultimo_error()
        );
    }

    if (($_POST['accion'] ?? '') === 'quitar') {
        $id = (int) ($_POST['id'] ?? 0);

        $quitado = null;
        foreach (colaboradores_listar($torneo['id']) as $c) {
            if ($c['id'] === $id) {
                $quitado = $c;
            }
        }
        if ($quitado === null) {
            redirigir_con_mensaje($urlLista, 'error', 'Ese colaborador ya no está en la lista.');
        }

        colaboradores_eliminar($id, $torneo['id']);
        bitacora_registrar('colaborador_quitado', "{$quitado['email']} ya no colabora en " . $torneo['nombre'], $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', "{$quitado['email']} ya no tiene acceso a esta copa.");
    }
}

$colaboradores = colaboradores_listar($torneo['id']);
$seccion_activa = 'colaboradores';
$titulo_pagina = 'Colaboradores';
$flash = obtener_flash();

vista_admin('admin/colaboradores', compact(
    'colaboradores',
    'flash',
    'seccion_activa',
    'titulo_pagina',
    'torneo'
));
