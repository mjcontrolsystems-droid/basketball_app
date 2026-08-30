<?php
declare(strict_types=1);

auth_requerir();
$usuarioSesion = usuarios_obtener_por_id((int) $_SESSION['usuario_id']);
$esSuper = es_superadmin($usuarioSesion);

// El super-admin ve la actividad de TODA la plataforma; un organizador normal solo la
// suya (sus acciones sobre sus copas), que es lo que necesita para auditarse a sí mismo.
$entradas = bitacora_listar($esSuper ? null : (int) $usuarioSesion['id'], 300);

// Etiqueta e icono legibles por tipo de acción (la columna 'accion' guarda la clave técnica).
const BITACORA_ETIQUETAS = [
    'login' => ['Inicio de sesión', 'bi-box-arrow-in-right', 'secondary'],
    'torneo_creado' => ['Copa/liga creada', 'bi-trophy', 'success'],
    'torneo_editado' => ['Copa/liga editada', 'bi-trophy', 'secondary'],
    'torneo_eliminado' => ['Copa/liga eliminada', 'bi-trash', 'danger'],
    'codigo_regenerado' => ['Código regenerado', 'bi-arrow-repeat', 'secondary'],
    'partido_creado' => ['Encuentro programado', 'bi-calendar-plus', 'secondary'],
    'partido_editado' => ['Encuentro editado', 'bi-calendar2-week', 'secondary'],
    'partido_eliminado' => ['Encuentro eliminado', 'bi-trash', 'danger'],
    'partido_jugado' => ['Resultado en firme', 'bi-lock-fill', 'success'],
    'partido_reabierto' => ['Reabierto para corrección', 'bi-unlock', 'warning'],
    'evento_agregado' => ['Evento registrado', 'bi-clipboard-plus', 'secondary'],
    'evento_eliminado' => ['Evento eliminado', 'bi-clipboard-x', 'warning'],
    'fecha_adelantada' => ['Fecha adelantada a hoy', 'bi-calendar-check', 'secondary'],
    'correo_autorizado' => ['Correo autorizado', 'bi-person-plus', 'success'],
    'colaborador_invitado' => ['Colaborador agregado', 'bi-person-plus', 'success'],
    'colaborador_quitado' => ['Colaborador quitado', 'bi-person-dash', 'danger'],
    'colaborador_acepto' => ['Invitación aceptada', 'bi-person-check', 'success'],
    'colaborador_nivel' => ['Nivel de colaborador cambiado', 'bi-person-gear', 'secondary'],
    'partido_default' => ['Triunfo por default (W.O.)', 'bi-flag', 'warning'],
    'correo_desautorizado' => ['Correo quitado', 'bi-person-dash', 'danger'],
    'cupo_actualizado' => ['Cupo actualizado', 'bi-ticket-perforated', 'secondary'],
];

$seccion_activa = 'bitacora';
$titulo_pagina = 'Actividad';

vista_admin('admin/bitacora', compact(
    'entradas',
    'esSuper',
    'seccion_activa',
    'titulo_pagina'
));
