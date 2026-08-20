<div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1">Hola, <?= e(explode(' ', $organizador['nombre'])[0]) ?> 👋</h3>
        <p class="text-muted mb-0">Este es el resumen de <?= e($torneo['nombre']) ?> — Temporada <?= e($torneo['temporada']) ?>.</p>
    </div>
    <a href="<?= url('admin/partidos.php?accion=nuevo') ?>" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Programar encuentro</a>
</div>

<?php // ---------- Sitio público abierto o en mantenimiento ---------- ?>
<?php $enMantenimiento = !empty($torneo['en_mantenimiento']); ?>
<div class="card-suave p-4 mb-4 <?= $enMantenimiento ? 'border border-warning border-2' : '' ?>">
    <form method="post" data-confirm="<?= $enMantenimiento
        ? 'Se va a reabrir el sitio público. ¿Continuamos?'
        : 'El sitio público va a quedar cerrado y los visitantes verán el aviso de mantenimiento. Tú vas a poder seguir entrando. ¿Continuamos?' ?>">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="mantenimiento">

        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div style="min-width:0;">
                <h5 class="mb-1">
                    <i class="bi <?= $enMantenimiento ? 'bi-cone-striped text-warning' : 'bi-globe2 text-success' ?> me-1"></i>
                    <?= $enMantenimiento ? 'Sitio público cerrado' : 'Sitio público abierto' ?>
                </h5>
                <p class="small text-muted mb-0">
                    <?= $enMantenimiento
                        ? 'Los visitantes ven el aviso de mantenimiento. Tú sigues entrando normal con tu sesión abierta.'
                        : 'Cualquiera con el enlace puede ver la tabla, el calendario y los equipos. Ciérralo mientras reacomodas el calendario, para que nadie lo vea a medias.' ?>
                </p>
            </div>
            <?php if ($enMantenimiento): ?>
            <button type="submit" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-unlock me-1"></i>Reabrir el sitio</button>
            <?php else: ?>
            <input type="hidden" name="cerrar" value="1">
            <button type="submit" class="btn btn-outline-warning rounded-pill px-3"><i class="bi bi-cone-striped me-1"></i>Cerrar por mantenimiento</button>
            <?php endif; ?>
        </div>

        <?php // El mensaje se puede editar aunque el sitio esté abierto, para dejarlo listo. ?>
        <div class="mt-3">
            <label class="form-label small fw-semibold" for="mensajeMantenimiento">Mensaje que verán los visitantes</label>
            <textarea name="mensaje_mantenimiento" id="mensajeMantenimiento" class="form-control" rows="2" maxlength="300"
                      placeholder="Estamos actualizando el calendario. Vuelve en un rato — gracias por tu comprensión."><?= e($torneo['mensaje_mantenimiento'] ?? '') ?></textarea>
            <div class="form-text">Si lo dejas vacío se usa el mensaje de arriba. Se guarda al pulsar el botón.</div>
        </div>
    </form>
</div>

<?php // ---------- Cierre de temporada ----------
      // Aparece cuando ya hay un campeón identificable. El podio NO se publica solo: la
      // app lo detecta y el organizador decide cuándo sacarlo, para que un marcador mal
      // cargado no corone al equipo equivocado delante de toda la liga. ?>
<?php if (!empty($podio)): ?>
<div class="card-suave p-4 mb-4 <?= !empty($podioPublicado) ? 'border border-success border-2' : '' ?>">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div>
            <h5 class="mb-1"><i class="bi bi-trophy-fill text-warning me-1"></i>
                <?= !empty($podioPublicado) ? 'Podio publicado' : ($temporadaTerminada ? '¡Terminó la temporada!' : 'Ya hay un campeón') ?>
            </h5>
            <p class="small text-muted mb-0">
                <?php if (!empty($podioPublicado)): ?>
                    Se está mostrando en la portada de la copa. Si corriges un resultado, el podio se actualiza solo.
                <?php elseif (!empty($temporadaTerminada)): ?>
                    Se jugaron todos los encuentros. Revisa el podio y publícalo cuando quieras.
                <?php else: ?>
                    El partido decisivo ya se jugó, pero todavía quedan encuentros pendientes. Puedes publicarlo igual si ya es definitivo.
                <?php endif; ?>
            </p>
        </div>
        <form method="post" data-confirm="<?= !empty($podioPublicado) ? '¿Quitar el podio de la portada pública?' : '¿Publicar el podio en la portada de la copa? Lo va a ver todo el mundo.' ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="accion" value="publicar_podio">
            <?php if (empty($podioPublicado)): ?>
            <input type="hidden" name="publicar" value="1">
            <button type="submit" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-megaphone me-1"></i>Publicar podio</button>
            <?php else: ?>
            <button type="submit" class="btn btn-outline-secondary rounded-pill px-3"><i class="bi bi-eye-slash me-1"></i>Quitar de la portada</button>
            <?php endif; ?>
        </form>
    </div>

    <div class="row g-3">
        <?php foreach (['campeon' => 1, 'subcampeon' => 2, 'tercero' => 3] as $clave => $puesto): ?>
        <?php if (empty($podio[$clave])) { continue; } ?>
        <div class="col-md-4">
            <div class="stat-tile d-flex align-items-center gap-3 h-100">
                <span class="fs-3"><?= $puesto === 1 ? '🥇' : ($puesto === 2 ? '🥈' : '🥉') ?></span>
                <?= logo_equipo($podio[$clave], 44) ?>
                <div style="min-width:0;">
                    <div class="small text-muted"><?= e(podio_titulo($puesto, $torneo['genero'] ?? null)) ?></div>
                    <div class="fw-semibold text-truncate"><?= e($podio[$clave]['nombre']) ?></div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <p class="small text-muted mt-3 mb-0">
        <i class="bi bi-info-circle me-1"></i>
        <?= ($podio['origen'] ?? '') === 'final'
            ? 'El campeón sale de la final, no de la tabla.'
            : 'En formato liga el campeón es quien termina primero en la tabla.' ?>
        <?php if (!empty($podioPublicado)): ?>
        <a href="<?= e(url_copa('podio_imagen.php')) ?>" target="_blank" class="ms-1"><i class="bi bi-image me-1"></i>Imagen para compartir</a>
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg">
        <div class="stat-tile"><div class="text-muted small mb-1"><i class="bi bi-people me-1"></i>Equipos</div><div class="fs-3 fw-bold"><?= count($equipos) ?></div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="stat-tile"><div class="text-muted small mb-1"><i class="bi bi-check2-circle me-1"></i>Jugados</div><div class="fs-3 fw-bold"><?= count($jugados) ?></div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="stat-tile"><div class="text-muted small mb-1"><i class="bi bi-clock-history me-1"></i>Por jugar</div><div class="fs-3 fw-bold"><?= count($programados) ?></div></div>
    </div>
    <div class="col-6 col-lg">
        <div class="stat-tile"><div class="text-muted small mb-1"><i class="bi bi-award me-1"></i>Patrocinadores</div><div class="fs-3 fw-bold"><?= count($patrocinadores) ?></div></div>
    </div>
    <div class="col-6 col-lg">
        <a href="<?= url('admin/comentarios.php') ?>" class="text-decoration-none text-dark">
            <div class="stat-tile"><div class="text-muted small mb-1"><i class="bi bi-chat-heart me-1"></i>Comentarios nuevos</div><div class="fs-3 fw-bold"><?= $comentariosNoLeidos ?></div></div>
        </a>
    </div>
</div>

<?php // Aviso de morosos: lo primero que debe ver el organizador antes de una jornada,
      // porque decide quién puede jugar. Solo aparece si la liga cobra multas. ?>
<?php if (torneo_cobra_multas($torneo)): ?>
<?php $resumenSanciones = sanciones_resumen($torneo['id']); ?>
<?php if ($resumenSanciones['cantidad_pendiente'] > 0): ?>
<div class="alert alert-warning rounded-4 border-0 shadow-sm d-flex align-items-start gap-2 mb-4">
    <i class="bi bi-exclamation-triangle-fill mt-1"></i>
    <div class="flex-grow-1">
        <div class="fw-semibold"><?= (int) $resumenSanciones['cantidad_pendiente'] ?> multa<?= $resumenSanciones['cantidad_pendiente'] === 1 ? '' : 's' ?> sin cobrar — <?= e(sancion_monto_texto($torneo, $resumenSanciones['pendiente'])) ?></div>
        <div class="small">Esos jugadores <?= torneo_bloquea_morosos($torneo) ? 'no pueden ser alineados' : 'tienen deuda pendiente' ?> hasta ponerse al día.</div>
        <div class="mt-2 d-flex gap-2 flex-wrap">
            <a href="<?= url('admin/sanciones.php') ?>" class="btn btn-sm btn-degradado rounded-pill px-3"><i class="bi bi-cash-coin me-1"></i>Ver y cobrar</a>
            <a href="<?= e(url_copa('solvencia.php')) ?>" target="_blank" class="btn btn-sm btn-outline-secondary rounded-pill px-3"><i class="bi bi-printer me-1"></i>Hoja para la cancha</a>
        </div>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<?php $visitas = visitas_resumen($torneo['id']); ?>
<div class="card-suave p-3 mb-4">
    <div class="d-flex flex-wrap align-items-center gap-3 gap-md-4">
        <span class="small fw-semibold text-muted text-uppercase"><i class="bi bi-eye me-1"></i>Visitas al sitio público</span>
        <span class="small">Hoy: <strong class="fs-6"><?= $visitas['hoy'] ?></strong></span>
        <span class="small">Últimos 7 días: <strong class="fs-6"><?= $visitas['semana'] ?></strong></span>
        <span class="small">Total: <strong class="fs-6"><?= $visitas['total'] ?></strong></span>
        <span class="small text-muted ms-auto d-none d-md-inline">Se cuenta una visita por persona por día.</span>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card-suave p-4">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0">Tabla de posiciones</h5>
                <a href="<?= url('admin/partidos.php') ?>" class="small">Capturar resultados <i class="bi bi-arrow-right"></i></a>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr class="small text-muted"><th>#</th><th>Equipo</th><th class="text-center">PJ</th><th class="text-center">PG-PP</th><th class="text-center">PTS</th></tr></thead>
                    <tbody>
                        <?php foreach (array_slice($tabla, 0, 8) as $fila): ?>
                        <tr>
                            <td class="fw-bold"><?= $fila['posicion'] ?></td>
                            <td class="d-flex align-items-center gap-2"><?= logo_equipo($fila['equipo'], 28) ?><?= e($fila['equipo']['nombre']) ?></td>
                            <td class="text-center"><?= $fila['pj'] ?></td>
                            <td class="text-center"><?= $fila['pg'] ?>-<?= $fila['pp'] ?></td>
                            <td class="text-center fw-bold"><?= $fila['pts'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card-suave p-4 mb-4">
            <h5 class="mb-3">Próximo encuentro</h5>
            <?php if ($proximo): $local = $equiposPorId[$proximo['equipo_local']]; $visit = $equiposPorId[$proximo['equipo_visitante']]; ?>
                <p class="small text-muted mb-3"><i class="bi bi-calendar3 me-1"></i><?= formatear_fecha_larga($proximo['fecha']) ?> · <?= e($proximo['hora']) ?> · <?= e($proximo['cancha']) ?></p>
                <div class="d-flex align-items-center justify-content-between">
                    <div class="equipo-col"><?= logo_equipo($local, 48) ?><span class="nombre"><?= e($local['nombre']) ?></span></div>
                    <span class="fw-bold text-muted">VS</span>
                    <div class="equipo-col"><?= logo_equipo($visit, 48) ?><span class="nombre"><?= e($visit['nombre']) ?></span></div>
                </div>
            <?php else: ?>
                <p class="text-muted mb-0">No hay encuentros programados.</p>
            <?php endif; ?>
        </div>
        <div class="card-suave p-4">
            <h5 class="mb-3">Accesos rápidos</h5>
            <div class="d-grid gap-2">
                <a href="<?= url('admin/equipos.php?accion=nuevo') ?>" class="btn btn-outline-secondary rounded-pill text-start"><i class="bi bi-person-plus me-2"></i>Nuevo equipo</a>
                <a href="<?= url('admin/partidos.php?accion=nuevo') ?>" class="btn btn-outline-secondary rounded-pill text-start"><i class="bi bi-calendar-plus me-2"></i>Nuevo encuentro</a>
                <a href="<?= url('admin/patrocinadores.php?accion=nuevo') ?>" class="btn btn-outline-secondary rounded-pill text-start"><i class="bi bi-award me-2"></i>Nuevo patrocinador</a>
            </div>
        </div>
    </div>
</div>
