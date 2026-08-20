<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
    <h3 class="mb-0"><i class="bi bi-person-plus me-2"></i>Colaboradores</h3>
</div>
<p class="text-muted mb-4">Gente que te ayuda a administrar <strong><?= e($torneo['nombre']) ?></strong>. No aparecen en el sitio público y no pueden ver tus otras copas.</p>

<div class="row g-4">
    <div class="col-lg-5">
        <form method="post" class="card-suave p-4">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="invitar">

            <h5 class="mb-3">Invitar a alguien</h5>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Correo de Google</label>
                <input type="email" name="email" class="form-control" placeholder="persona@gmail.com" required>
                <div class="form-text">Tiene que ser el correo con el que entra a Google: así es como accede.</div>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-semibold">Qué podrá hacer</label>
                <?php foreach (COLABORADOR_NIVELES as $clave => $nivel): ?>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="radio" name="nivel" id="nivel<?= e($clave) ?>" value="<?= e($clave) ?>" <?= $clave === 'mesa' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="nivel<?= e($clave) ?>">
                        <strong><?= e($nivel['nombre']) ?></strong>
                        <span class="d-block small text-muted"><?= e($nivel['resumen']) ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <?php // Lo que NUNCA puede un colaborador. Se dice explícito para que el
                  // organizador invite tranquilo y no se quede con la duda. ?>
            <div class="alert alert-light border small mb-3">
                <strong>Ninguno de los dos puede:</strong> generar o borrar el calendario, cambiar el formato y las reglas,
                cerrar el sitio público, invitar a otros, ni ver tus demás copas.
            </div>

            <button type="submit" class="btn btn-degradado rounded-pill w-100">
                <i class="bi bi-send me-2"></i>Dar acceso
            </button>
        </form>
    </div>

    <div class="col-lg-7">
        <div class="card-suave p-4">
            <h5 class="mb-3">Con acceso ahora</h5>

            <?php if (empty($colaboradores)): ?>
                <p class="text-muted mb-0">Todavía no has invitado a nadie. Por ahora solo tú administras esta copa.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Persona</th>
                            <th>Nivel</th>
                            <th class="text-end">Quitar</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($colaboradores as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold"><?= e($c['nombre'] ?? $c['email']) ?></div>
                                <?php if ($c['nombre'] !== null): ?>
                                    <div class="small text-muted"><?= e($c['email']) ?></div>
                                <?php endif; ?>
                                <?php // Sin usuario_id todavía no ha entrado ni una vez. ?>
                                <?php if ($c['usuario_id'] === null): ?>
                                    <span class="badge rounded-pill text-bg-warning mt-1">Aún no ha entrado</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge rounded-pill text-bg-secondary"><?= e(colaborador_nivel_nombre($c['nivel'])) ?></span></td>
                            <td class="text-end">
                                <form method="post" class="d-inline"
                                      data-confirm="&iquest;Quitarle el acceso a <?= e($c['email']) ?>? Dejar&aacute; de ver esta copa de inmediato. Lo que ya captur&oacute; no se borra.">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="quitar">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill"><i class="bi bi-x-lg"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <hr class="my-4">
            <p class="small text-muted mb-0">
                <i class="bi bi-info-circle me-1"></i>
                Todo lo que hagan queda registrado con su nombre en <a href="<?= url('admin/bitacora.php') ?>">Actividad</a>.
                Si alguien cambia un marcador, vas a ver quién y cuándo.
            </p>
        </div>
    </div>
</div>
