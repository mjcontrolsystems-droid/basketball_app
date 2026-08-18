<?php
declare(strict_types=1);

auth_requerir();
$torneo = admin_requerir_torneo_activo();

$comentarios = comentarios_listar($torneo['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    $id = (int) ($_POST['id'] ?? 0);

    if (($_POST['accion'] ?? '') === 'marcar_leido') {
        foreach ($comentarios as &$c) {
            if ($c['id'] === $id) {
                $c['leido'] = true;
            }
        }
        unset($c);
        comentarios_guardar_todos($comentarios, $torneo['id']);
        redirigir_con_mensaje(url('admin/comentarios.php'), 'success', 'Comentario marcado como leído.');
    }

    if (($_POST['accion'] ?? '') === 'eliminar') {
        $comentarios = array_values(array_filter($comentarios, fn($c) => $c['id'] !== $id));
        comentarios_guardar_todos($comentarios, $torneo['id']);
        redirigir_con_mensaje(url('admin/comentarios.php'), 'success', 'Comentario eliminado.');
    }
}

usort($comentarios, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));
$noLeidos = count(array_filter($comentarios, fn($c) => empty($c['leido'])));

$seccion_activa = 'comentarios';
$titulo_pagina = 'Comentarios';

vista_admin('admin/comentarios', compact(
    'comentarios',
    'noLeidos',
    'seccion_activa',
    'titulo_pagina'
));
