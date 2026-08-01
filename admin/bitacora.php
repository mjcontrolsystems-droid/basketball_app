<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/usuarios.php';
require_once __DIR__ . '/../includes/helpers.php';

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
    'correo_desautorizado' => ['Correo quitado', 'bi-person-dash', 'danger'],
    'cupo_actualizado' => ['Cupo actualizado', 'bi-ticket-perforated', 'secondary'],
];

$seccion_activa = 'bitacora';
$titulo_pagina = 'Actividad';
require __DIR__ . '/includes/admin_layout_top.php';
?>

<div class="mb-4">
    <h3 class="mb-1">Actividad de la plataforma</h3>
    <p class="text-muted small mb-0"><?= $esSuper ? 'Registro de las acciones de todos los organizadores (vista de super-admin).' : 'Registro de tus acciones en el panel: resultados, correcciones, cambios de encuentros y más.' ?></p>
</div>

<?php if (empty($entradas)): ?>
<div class="card-suave p-4 text-center text-muted">
    <i class="bi bi-journal-text fs-3 d-block mb-2 opacity-50"></i>
    Todavía no hay actividad registrada. Las acciones nuevas del panel irán apareciendo aquí.
</div>
<?php else: ?>
<div class="card-suave p-0">
    <ul class="list-group list-group-flush">
        <?php foreach ($entradas as $en): ?>
        <?php
            [$etiqueta, $icono, $tono] = BITACORA_ETIQUETAS[$en['accion']] ?? [ucfirst(str_replace('_', ' ', (string) $en['accion'])), 'bi-dot', 'secondary'];
            $quien = trim((string) ($en['usuario_nombre'] ?? '')) !== '' ? $en['usuario_nombre'] : ($en['usuario_usuario'] ?? 'Sistema');
            $ts = strtotime((string) $en['creado_en']);
            $cuando = $ts !== false ? date('d/m/Y H:i', $ts) : (string) $en['creado_en'];
        ?>
        <li class="list-group-item d-flex align-items-start gap-3 px-3 py-3">
            <span class="badge rounded-pill text-bg-<?= e($tono) ?> mt-1" style="width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;"><i class="bi <?= e($icono) ?>"></i></span>
            <div class="flex-grow-1" style="min-width:0;">
                <div class="d-flex flex-wrap align-items-baseline gap-2">
                    <span class="fw-semibold"><?= e($etiqueta) ?></span>
                    <?php if (!empty($en['torneo_nombre'])): ?><span class="badge rounded-pill text-bg-light border small"><?= e($en['torneo_nombre']) ?></span><?php endif; ?>
                </div>
                <?php if (trim((string) $en['detalle']) !== ''): ?><div class="small text-muted"><?= e($en['detalle']) ?></div><?php endif; ?>
                <div class="small text-muted mt-1"><i class="bi bi-person me-1"></i><?= e($quien) ?> · <i class="bi bi-clock me-1"></i><?= e($cuando) ?></div>
            </div>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
<p class="small text-muted mt-2 mb-0">Se muestran las últimas <?= count($entradas) ?> acciones.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/admin_layout_bottom.php'; ?>
