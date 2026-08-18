<?php
declare(strict_types=1);

auth_requerir();
$usuarioSesion = usuarios_obtener_por_id((int) $_SESSION['usuario_id']);
if (!es_superadmin($usuarioSesion)) {
    http_response_code(403);
    exit('No tienes permiso para ver esta página.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (($_POST['accion'] ?? '') === 'agregar') {
        $email = trim((string) ($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirigir_con_mensaje(url('admin/usuarios_autorizados.php'), 'error', 'Ingresa un correo válido.');
        }
        $limite = max(0, (int) ($_POST['limite_torneos'] ?? LIMITE_TORNEOS_POR_DEFECTO));
        correos_autorizados_agregar($email, $limite);
        // Aviso automático: la persona se entera sola de que ya puede crear su cuenta,
        // sin que el super-admin tenga que escribirle por aparte.
        $avisado = correo_avisar_autorizado($email, $limite);
        bitacora_registrar('correo_autorizado', "{$email} autorizado con cupo de {$limite}");
        $sufijo = $avisado ? ' Se le envió un correo de aviso.' : (correo_configurado() ? ' (No se pudo enviar el correo de aviso.)' : '');
        redirigir_con_mensaje(url('admin/usuarios_autorizados.php'), 'success', "Correo agregado con {$limite} copa(s) o liga(s) autorizada(s).{$sufijo}");
    }

    if (($_POST['accion'] ?? '') === 'actualizar_limite') {
        $limite = max(0, (int) ($_POST['limite_torneos'] ?? 0));
        $id = (int) $_POST['id'];
        correos_autorizados_actualizar_limite($id, $limite);
        // Solo se avisa si sube el cupo de alguien que ya tiene cuenta (subir el cupo es
        // la consecuencia de un pago; bajar a 0 no amerita un correo automático).
        $fila = null;
        foreach (correos_autorizados_listar() as $c) {
            if ((int) $c['id'] === $id) { $fila = $c; break; }
        }
        $avisado = false;
        if ($fila !== null && $limite > 0 && usuarios_obtener_por_email((string) $fila['email']) !== null) {
            $avisado = correo_avisar_cupo((string) $fila['email'], $limite);
        }
        bitacora_registrar('cupo_actualizado', ($fila['email'] ?? "id {$id}") . " ahora con cupo de {$limite}");
        redirigir_con_mensaje(url('admin/usuarios_autorizados.php'), 'success', 'Cupo actualizado.' . ($avisado ? ' Se le envió un correo de aviso.' : ''));
    }

    if (($_POST['accion'] ?? '') === 'eliminar') {
        $idQuitar = (int) $_POST['id'];
        $emailQuitar = '';
        foreach (correos_autorizados_listar() as $c) {
            if ((int) $c['id'] === $idQuitar) { $emailQuitar = (string) $c['email']; break; }
        }
        correos_autorizados_eliminar($idQuitar);
        bitacora_registrar('correo_desautorizado', $emailQuitar !== '' ? $emailQuitar : "id {$idQuitar}");
        redirigir_con_mensaje(url('admin/usuarios_autorizados.php'), 'success', 'Correo quitado de la lista.');
    }
}

$correos = correos_autorizados_listar();

// Cuántas copas lleva creadas cada correo, para mostrar "usadas / autorizadas" y que el
// super-admin sepa a quién le queda cupo sin tener que entrar a la cuenta de cada uno.
$usadasPorCorreo = [];
foreach ($correos as $c) {
    $cuenta = usuarios_obtener_por_email((string) $c['email']);
    $usadasPorCorreo[$c['id']] = $cuenta ? torneos_contar_por_usuario((int) $cuenta['id']) : 0;
}

$seccion_activa = 'usuarios_autorizados';
$titulo_pagina = 'Correos autorizados';

vista_admin('admin/usuarios_autorizados', compact(
    'correos',
    'limite',
    'seccion_activa',
    'titulo_pagina',
    'usadasPorCorreo'
));
