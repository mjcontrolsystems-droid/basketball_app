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
