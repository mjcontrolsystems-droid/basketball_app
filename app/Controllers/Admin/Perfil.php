<?php
declare(strict_types=1);

auth_requerir();

$organizador = usuarios_obtener_por_id((int) $_SESSION['usuario_id']);
$errores = [];
// Las cuentas que entraron con "Continuar con Google" no tienen password_hash propio,
// así que no tiene sentido (ni es seguro) dejarles cambiar una contraseña que no usan.
$esCuentaGoogle = !empty($organizador['google_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (($_POST['accion'] ?? '') === 'datos') {
        try {
            $fotoSubida = manejar_subida_imagen('foto', 'organizador');
        } catch (RuntimeException $e) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', $e->getMessage());
        }
        $organizador['nombre'] = trim((string) $_POST['nombre']);
        $organizador['cargo'] = trim((string) $_POST['cargo']);
        // Solo se aceptan los dos valores del catálogo; cualquier otra cosa queda como
        // "no indicado" (el sitio usará entonces la forma masculina genérica).
        $generoElegido = (string) ($_POST['genero'] ?? '');
        $organizador['genero'] = in_array($generoElegido, ['masculino', 'femenino'], true) ? $generoElegido : '';
        $organizador['email'] = trim((string) $_POST['email']);
        $organizador['telefono'] = trim((string) $_POST['telefono']);
        $organizador['bio'] = trim((string) $_POST['bio']);
        if ($fotoSubida) {
            eliminar_imagen($organizador['foto'] ?? null);
            $organizador['foto'] = $fotoSubida;
        }

        if ($organizador['nombre'] === '' || $organizador['email'] === '') {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'Nombre y correo son obligatorios.');
        }

        $otroConEseCorreo = usuarios_obtener_por_email($organizador['email']);
        if ($otroConEseCorreo && $otroConEseCorreo['id'] !== $organizador['id']) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'Ese correo ya está en uso por otra cuenta.');
        }

        // El rol de super-admin se define por correo (SUPERADMIN_EMAILS), así que dejar
        // que cualquiera escriba aquí uno de esos correos equivaldría a dejarle otorgarse
        // el rol (y el control de la lista blanca y los cupos) a sí mismo. Solo se permite
        // si la cuenta YA es super-admin (está corrigiendo mayúsculas o similar).
        $emailAnterior = usuarios_obtener_por_id((int) $organizador['id'])['email'] ?? '';
        $nuevoEsSuperadmin = in_array(mb_strtolower($organizador['email']), SUPERADMIN_EMAILS, true);
        $yaEraSuperadmin = in_array(mb_strtolower($emailAnterior), SUPERADMIN_EMAILS, true);
        if ($nuevoEsSuperadmin && !$yaEraSuperadmin) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'Ese correo está reservado y no puede asignarse a esta cuenta.');
        }

        usuarios_guardar($organizador);
        redirigir_con_mensaje(url('admin/perfil.php'), 'success', 'Perfil actualizado correctamente.');
    }

    if (($_POST['accion'] ?? '') === 'password') {
        if ($esCuentaGoogle) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'Tu cuenta entra con Google y no usa contraseña.');
        }
        $actual = (string) $_POST['password_actual'];
        $nueva = (string) $_POST['password_nueva'];
        $confirmar = (string) $_POST['password_confirmar'];

        if (!password_verify($actual, $organizador['password_hash'])) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'La contraseña actual no es correcta.');
        } elseif (mb_strlen($nueva) < 8) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'La nueva contraseña debe tener al menos 8 caracteres.');
        } elseif ($nueva !== $confirmar) {
            redirigir_con_mensaje(url('admin/perfil.php'), 'error', 'La confirmación no coincide con la nueva contraseña.');
        } else {
            $organizador['password_hash'] = password_hash($nueva, PASSWORD_DEFAULT);
            usuarios_guardar($organizador);
            redirigir_con_mensaje(url('admin/perfil.php'), 'success', 'Contraseña actualizada correctamente.');
        }
    }
}

$seccion_activa = 'perfil';
$titulo_pagina = 'Mi Perfil';

vista_admin('admin/perfil', compact(
    'esCuentaGoogle',
    'organizador',
    'seccion_activa',
    'titulo_pagina'
));
