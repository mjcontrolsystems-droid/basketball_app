<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
    <h3 class="mb-0"><i class="bi bi-person-plus me-2"></i>Colaboradores</h3>
</div>
<p class="text-muted mb-4">Gente que te ayuda a administrar <strong><?= e($torneo['nombre']) ?></strong>. No aparecen en el sitio público y no pueden ver tus otras copas.</p>

<?php // El enlace recién copiado. Se muestra grande porque es lo que la persona vino a
      // hacer: sacarlo de aquí y pegarlo en el chat de la liga. ?>
<?php if ($enlaceInvitacion !== null): ?>
<div class="card-suave p-4 mb-4" style="border:2px solid var(--color-primario,#7b2ff7);">
    <div class="d-flex align-items-start gap-3">
        <i class="bi bi-link-45deg fs-3" style="color:var(--color-primario,#7b2ff7);"></i>
        <div class="flex-grow-1" style="min-width:0;">
            <h6 class="mb-1">Enlace de invitación para <?= e($enlaceInvitacion['email']) ?></h6>
            <p class="small text-muted mb-2">
                Pásaselo por WhatsApp. Al abrirlo entra con su Google y queda dentro.
                Sirve <strong>una sola vez</strong> y solo funciona con ese correo.
            </p>
            <div class="input-group">
                <?php // Sin onfocus inline: la política de seguridad del sitio bloquea el
                      // JavaScript escrito dentro del HTML. Todo va en app.js. ?>
                <input type="text" class="form-control font-monospace" style="font-size:.8rem;" readonly
                       data-seleccionar-al-tocar
                       value="<?= e($enlaceInvitacion['url']) ?>">
                <button type="button" class="btn btn-degradado btn-copiar-url" data-url="<?= e($enlaceInvitacion['url']) ?>">
                    <i class="bi bi-clipboard"></i>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php // Si no hay correo configurado hay que decirlo aquí y no dejar que lo descubra
      // cuando el envío falle: el botón del enlace es la salida y tiene que verse. ?>
<?php if (!$correoListo): ?>
<div class="alert alert-warning d-flex gap-3 align-items-start">
    <i class="bi bi-envelope-slash fs-5"></i>
    <div>
        <strong>El envío de correos no está configurado.</strong>
        Las invitaciones no van a salir por correo, pero puedes invitar igual:
        usa el botón <i class="bi bi-link-45deg"></i> de cada persona para copiar su enlace y pasárselo por WhatsApp.
    </div>
</div>
<?php endif; ?>

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
                    <input class="form-check-input" type="radio" name="nivel" id="nivel<?= e($clave) ?>" value="<?= e($clave) ?>"
                           <?= $clave === 'mesa' ? 'checked' : '' ?>
                           <?= colaborador_nivel_por_equipo($clave) ? 'data-muestra="#bloqueEquipoCapitan"' : '' ?>>
                    <label class="form-check-label" for="nivel<?= e($clave) ?>">
                        <strong><?= e($nivel['nombre']) ?></strong>
                        <span class="d-block small text-muted"><?= e($nivel['resumen']) ?></span>
                    </label>
                </div>
                <?php endforeach; ?>
            </div>

            <?php // El capitán es el único nivel que necesita saber DE QUÉ equipo: su
                  // acceso no abarca la copa sino un equipo, y todo el candado cuelga de
                  // este dato. El bloque aparece solo al elegir ese nivel. ?>
            <div class="mb-3" id="bloqueEquipoCapitan" style="display:none;">
                <label class="form-label small fw-semibold" for="equipoCapitan">¿De qué equipo es capitán?</label>
                <select name="equipo_id" id="equipoCapitan" class="form-select">
                    <option value="">Elige el equipo...</option>
                    <?php foreach ($equipos as $eq): ?>
                    <option value="<?= (int) $eq['id'] ?>"><?= e($eq['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">Solo va a poder ver y editar ese equipo. Nada del resto de la liga.</div>
            </div>

            <?php // Lo que NUNCA puede un colaborador. Se dice explícito para que el
                  // organizador invite tranquilo y no se quede con la duda. ?>
            <div class="alert alert-light border small mb-3">
                <strong>Ninguno puede:</strong> generar o borrar el calendario, cambiar el formato y las reglas,
                cerrar el sitio público, invitar a otros, ni ver tus demás copas. El capitán, además, no toca
                resultados, ni sanciones, ni ningún equipo que no sea el suyo.
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
                                <?php // Aceptó = entró por el enlace del correo. Sin eso, la
                                      // invitación está mandada pero nadie la ha usado. ?>
                                <?php if (empty($c['aceptado_en'])): ?>
                                    <span class="badge rounded-pill text-bg-warning mt-1">Invitación enviada</span>
                                <?php else: ?>
                                    <span class="badge rounded-pill text-bg-success mt-1">Aceptada</span>
                                <?php endif; ?>
                                <?php // De qué equipo es capitán: sin esto, la lista no
                                      // distingue a los 16 capitanes entre sí. ?>
                                <?php if (!empty($c['equipo_nombre'])): ?>
                                    <div class="small text-muted mt-1"><i class="bi bi-shield me-1"></i><?= e($c['equipo_nombre']) ?></div>
                                <?php endif; ?>
                            </td>
                            <?php // Se cambia en el sitio: quitar y volver a invitar para
                                  // subirle el nivel a alguien era un rodeo absurdo. ?>
                            <td>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="nivel">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <select name="nivel" class="form-select form-select-sm" data-enviar-al-cambiar style="min-width:120px;">
                                        <?php foreach (COLABORADOR_NIVELES as $clave => $n): ?>
                                        <option value="<?= e($clave) ?>" <?= $c['nivel'] === $clave ? 'selected' : '' ?>><?= e($n['nombre']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </form>
                            </td>
                            <td class="text-end text-nowrap">
                                <?php if (empty($c['aceptado_en'])): ?>
                                <?php // Copiar el enlace: la vía que no depende del correo. ?>
                                <form method="post" class="d-inline">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="enlace">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-primary rounded-pill" title="Obtener enlace para pasar por WhatsApp"><i class="bi bi-link-45deg"></i></button>
                                </form>
                                <?php if ($correoListo): ?>
                                <form method="post" class="d-inline"
                                      data-confirm="&iquest;Reenviar la invitaci&oacute;n a <?= e($c['email']) ?>? El enlace anterior dejar&aacute; de servir.">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="reenviar">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary rounded-pill" title="Reenviar invitación por correo"><i class="bi bi-envelope-arrow-up"></i></button>
                                </form>
                                <?php endif; ?>
                                <?php endif; ?>
                                <form method="post" class="d-inline"
                                      data-confirm="&iquest;Quitarle el acceso a <?= e($c['email']) ?>? Dejar&aacute; de ver esta copa de inmediato. Lo que ya captur&oacute; no se borra.">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="accion" value="quitar">
                                    <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill" title="Quitar acceso"><i class="bi bi-x-lg"></i></button>
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
