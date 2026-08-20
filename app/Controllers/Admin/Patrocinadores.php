<?php
declare(strict_types=1);

auth_requerir();
$torneo = admin_requerir_torneo_activo();
requerir_permiso('patrocinadores');

$patrocinadores = patrocinadores_listar($torneo['id']);
usort($patrocinadores, fn($a, $b) => ($a['orden'] ?? 0) <=> ($b['orden'] ?? 0));

$accion = $_GET['accion'] ?? 'lista';
$idEditar = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$itemEditar = $idEditar ? db_buscar_por_id($patrocinadores, $idEditar) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (($_POST['accion'] ?? '') === 'eliminar') {
        $id = (int) $_POST['id'];
        $itemAEliminar = db_buscar_por_id($patrocinadores, $id);
        $patrocinadores = array_values(array_filter($patrocinadores, fn($p) => $p['id'] !== $id));
        patrocinadores_guardar_todos($patrocinadores, $torneo['id']);
        if ($itemAEliminar) {
            eliminar_imagen($itemAEliminar['logo'] ?? null);
        }
        redirigir_con_mensaje(url('admin/patrocinadores.php'), 'success', 'Patrocinador eliminado.');
    }

    if (($_POST['accion'] ?? '') === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $datos = [
            'nombre' => trim((string) $_POST['nombre']),
            'nivel' => (string) $_POST['nivel'],
            'url' => trim((string) $_POST['url']),
            'orden' => (int) $_POST['orden'],
        ];

        if ($datos['nombre'] === '') {
            redirigir_con_mensaje(url('admin/patrocinadores.php'), 'error', 'El nombre del patrocinador es obligatorio.');
        }

        try {
            $logoSubido = manejar_subida_imagen('logo', 'patrocinadores');
        } catch (RuntimeException $e) {
            redirigir_con_mensaje(url('admin/patrocinadores.php'), 'error', $e->getMessage());
        }

        $quitarLogo = !empty($_POST['quitar_logo']);

        if ($id > 0) {
            foreach ($patrocinadores as &$p) {
                if ($p['id'] === $id) {
                    $datos['logo'] = resolver_archivo_guardado($logoSubido, (string) ($p['logo'] ?? ''), $quitarLogo);
                    $p = array_merge($p, $datos, ['id' => $id]);
                }
            }
            unset($p);
            $mensaje = 'Patrocinador actualizado.';
        } else {
            $datos['id'] = patrocinador_nuevo_id();
            $datos['logo'] = $logoSubido ?? '';
            $patrocinadores[] = $datos;
            $mensaje = 'Patrocinador agregado.';
        }

        patrocinadores_guardar_todos($patrocinadores, $torneo['id']);
        redirigir_con_mensaje(url('admin/patrocinadores.php'), 'success', $mensaje);
    }
}

$seccion_activa = 'patrocinadores';
$titulo_pagina = 'Patrocinadores';

vista_admin('admin/patrocinadores', compact(
    'accion',
    'itemEditar',
    'patrocinadores',
    'seccion_activa',
    'titulo_pagina'
));
