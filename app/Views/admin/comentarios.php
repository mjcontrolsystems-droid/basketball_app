<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <h3 class="mb-0">Comentarios anónimos (<?= count($comentarios) ?>)</h3>
    <?php if ($noLeidos > 0): ?>
        <span class="badge rounded-pill text-bg-danger px-3 py-2"><?= $noLeidos ?> sin leer</span>
    <?php endif; ?>
</div>

<p class="text-muted small mb-4"><i class="bi bi-shield-check me-1"></i>Estos mensajes son enviados de forma anónima por visitantes del sitio (sin nombre ni correo) desde la página del Organizador, y ya pasaron por un filtro de lenguaje inapropiado.</p>

<?php if (empty($comentarios)): ?>
    <p class="text-muted">Aún no has recibido comentarios.</p>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($comentarios as $c): ?>
    <div class="card-suave p-3 <?= empty($c['leido']) ? 'border-start border-4 border-primary' : '' ?>">
        <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
            <span class="small text-muted"><i class="bi bi-clock me-1"></i><?= e($c['fecha']) ?></span>
            <?php if (empty($c['leido'])): ?>
                <span class="badge rounded-pill text-bg-primary">Nuevo</span>
            <?php endif; ?>
        </div>
        <p class="mb-3"><?= nl2br(e($c['mensaje'])) ?></p>
        <div class="d-flex gap-2">
            <?php if (empty($c['leido'])): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="marcar_leido">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary"><i class="bi bi-check2 me-1"></i>Marcar como leído</button>
            </form>
            <?php endif; ?>
            <form method="post" data-confirm="¿Eliminar este comentario?">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="accion" value="eliminar">
                <input type="hidden" name="id" value="<?= $c['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash me-1"></i>Eliminar</button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
