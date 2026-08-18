<!-- HERO -->
<header class="hero-copa">
    <div class="container">
        <div class="row align-items-center gy-5">
            <div class="col-lg-7">
                <p class="kicker mb-3"><i class="bi bi-stars me-1"></i>Temporada <?= e($torneo['temporada']) ?></p>
                <h1 class="text-white mb-3"><?= e($torneo['nombre']) ?> <span class="text-degradado d-block d-sm-inline"><?= e($torneo['subtitulo']) ?></span></h1>
                <p class="fs-5 mb-4" style="color:rgba(255,255,255,.8);max-width:560px;"><?= e($torneo['hero_frase']) ?>. <?= e($torneo['descripcion']) ?></p>
                <div class="d-flex flex-wrap gap-3 mb-5">
                    <a href="<?= url_copa('tabla.php') ?>" class="btn btn-degradado btn-lg rounded-pill px-4">Ver tabla de posiciones</a>
                    <a href="<?= url_copa('calendario.php') ?>" class="btn btn-outline-luz btn-lg rounded-pill px-4">Calendario completo</a>
                </div>
                <div class="row row-cols-2 row-cols-sm-4 g-3">
                    <div class="col"><div class="hero-stat"><span class="stat-icono"><i class="bi bi-people-fill"></i></span><div class="valor"><?= count($equipos) ?></div><div class="etiqueta">Equipos</div></div></div>
                    <div class="col"><div class="hero-stat"><span class="stat-icono"><?= icono_balon_img($deporte, 20) ?></span><div class="valor"><?= count($partidos) ?></div><div class="etiqueta">Partidos</div></div></div>
                    <div class="col"><div class="hero-stat"><span class="stat-icono"><i class="bi bi-check2-circle"></i></span><div class="valor"><?= $totalJugados ?></div><div class="etiqueta">Jugados</div></div></div>
                    <div class="col"><div class="hero-stat"><span class="stat-icono"><i class="bi bi-calendar2-week"></i></span><div class="valor"><?= $jornadaActual ?></div><div class="etiqueta">Jornadas</div></div></div>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="balones-3d">
                    <?php
                    // Si la copa/liga subió su logo, es ÉL el que preside el hero (flotando
                    // igual que el balón); el balón del deporte queda solo como respaldo
                    // para las copas que todavía no cargaron logo.
                    if (!empty($torneo['logo'])):
                        $heroImg = url_imagen((string) $torneo['logo']);
                        $heroClase = 'logo-hero-solo';
                    else:
                        $heroImg = url('assets/img/' . (($torneo['deporte'] ?? 'basketball') === 'futbol' ? 'balon-futbol.png' : 'balon-basketball.png'));
                        $heroClase = 'balon-real';
                    endif;
                    ?>
                    <img src="<?= e($heroImg) ?>" alt="<?= e($torneo['nombre']) ?>" class="<?= $heroClase ?> balon-flotante-1 balon-hero-solo">
                </div>
            </div>
        </div>
    </div>
</header>

<!-- TABLA PREVIEW -->
<section class="seccion" id="tabla">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 seccion-titulo">
            <div>
                <p class="eyebrow mb-1">Clasificación</p>
                <h2 class="mb-0">Tabla de posiciones</h2>
            </div>
            <a href="<?= url_copa('tabla.php') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3 mt-3 mt-sm-0">Ver tabla completa <i class="bi bi-arrow-right ms-1"></i></a>
        </div>
        <div class="table-responsive">
            <table class="table tabla-posiciones align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Equipo</th>
                        <th class="text-center">PJ</th>
                        <th class="text-center">PG</th>
                        <th class="text-center">PP</th>
                        <th class="text-center">DIF</th>
                        <th class="text-center">PTS</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top5 as $fila): ?>
                    <tr class="fila-clicable <?= (!$esLiga && $fila['posicion'] <= 4) ? 'zona-playoff' : '' ?>" data-href="<?= e(url_copa('equipo.php?id=' . $fila['equipo']['id'])) ?>">
                        <td data-label="#">
                            <span class="pos-num <?= $fila['posicion'] === 1 ? 'oro' : ($fila['posicion'] === 2 ? 'plata' : ($fila['posicion'] === 3 ? 'bronce' : '')) ?>"><?= $fila['posicion'] ?></span>
                        </td>
                        <td class="td-equipo" data-label="Equipo">
                            <a href="<?= url_copa('equipo.php?id=' . $fila['equipo']['id']) ?>" class="d-flex align-items-center gap-2 text-decoration-none text-dark">
                                <?= logo_equipo($fila['equipo'], 34) ?>
                                <span class="fw-semibold"><?= e($fila['equipo']['nombre']) ?></span>
                            </a>
                        </td>
                        <td class="text-center" data-label="PJ"><?= $fila['pj'] ?></td>
                        <td class="text-center" data-label="PG"><?= $fila['pg'] ?></td>
                        <td class="text-center" data-label="PP"><?= $fila['pp'] ?></td>
                        <td class="text-center fw-semibold <?= $fila['dif'] >= 0 ? 'text-success' : 'text-danger' ?>" data-label="DIF"><?= $fila['dif'] >= 0 ? '+' : '' ?><?= $fila['dif'] ?></td>
                        <td class="text-center fw-bold" data-label="PTS"><?= $fila['pts'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (!$esLiga): ?>
        <p class="small text-muted mt-3 mb-0"><span class="d-inline-block" style="width:10px;height:10px;background:var(--color-acento);border-radius:2px;"></span> Zona de Playoffs (Top 4)</p>
        <?php else: ?>
        <p class="small text-muted mt-3 mb-0"><i class="bi bi-info-circle me-1"></i>Liga a puntos: el campeón es quien termine la temporada en el primer lugar.</p>
        <?php endif; ?>
    </div>
</section>

<!-- GOLEADORES (solo si hay eventos cargados) -->
<?php if (!empty($topGoleadores)): ?>
<section class="seccion pt-0" id="goleadores">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end mb-4 seccion-titulo">
            <div>
                <p class="eyebrow mb-1"><?= e(forma_genero($torneo['genero'] ?? null, 'Máximos anotadores', 'Máximas anotadoras')) ?></p>
                <h2 class="mb-0">Tabla de <?= e($basketball ? 'anotación' : forma_genero($torneo['genero'] ?? null, 'goleadores', 'goleadoras')) ?></h2>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table tabla-posiciones align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th><?= e(forma_genero($torneo['genero'] ?? null, 'Jugador', 'Jugadora')) ?></th>
                        <th>Equipo</th>
                        <th class="text-center"><?= e(etiqueta_anotaciones($deporte)) ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topGoleadores as $i => $g): ?>
                    <tr>
                        <td data-label="#">
                            <span class="pos-num <?= $i === 0 ? 'oro' : ($i === 1 ? 'plata' : ($i === 2 ? 'bronce' : '')) ?>"><?= $i + 1 ?></span>
                        </td>
                        <td class="td-equipo" data-label="<?= e(forma_genero($torneo['genero'] ?? null, 'Jugador', 'Jugadora')) ?>">
                            <span class="fw-semibold">#<?= e($g['jugador']['dorsal']) ?> <?= e($g['jugador']['nombre']) ?></span>
                        </td>
                        <td data-label="Equipo"><span class="small text-muted"><?= e($g['equipo']['nombre'] ?? '') ?></span></td>
                        <td class="text-center fw-bold" data-label="<?= e(etiqueta_anotaciones($deporte)) ?>"><?= $g['goles'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- PARTIDOS -->
<section class="seccion bg-white bg-opacity-50" style="background:#f4f0fb;">
    <div class="container">
        <div class="row gy-5">
            <div class="col-lg-6">
                <div class="seccion-titulo mb-4">
                    <p class="eyebrow mb-1">Agenda</p>
                    <h2 class="mb-0">Próximos partidos</h2>
                </div>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($proximos as $p): $local = $equiposPorId[$p['equipo_local']]; $visit = $equiposPorId[$p['equipo_visitante']]; ?>
                    <div class="partido-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge-jornada">Jornada <?= $p['jornada'] ?></span>
                            <span class="small text-muted"><i class="bi bi-calendar3 me-1"></i><?= formatear_fecha_larga($p['fecha']) ?> · <?= e($p['hora']) ?></span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="equipo-col"><?= logo_equipo($local, 52) ?><span class="nombre"><?= e($local['nombre']) ?></span></div>
                            <div class="fw-bold text-muted">VS</div>
                            <div class="equipo-col"><?= logo_equipo($visit, 52) ?><span class="nombre"><?= e($visit['nombre']) ?></span></div>
                        </div>
                        <p class="text-center small text-muted mt-2 mb-0"><i class="bi bi-geo-alt me-1"></i><?= e($p['cancha']) ?></p>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($proximos)): ?>
                        <p class="text-muted">No hay partidos programados por el momento.</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="seccion-titulo mb-4">
                    <p class="eyebrow mb-1">Resultados</p>
                    <h2 class="mb-0">Últimos marcadores</h2>
                </div>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($resultados as $p): $local = $equiposPorId[$p['equipo_local']]; $visit = $equiposPorId[$p['equipo_visitante']]; $ganoLocal = $p['marcador_local'] > $p['marcador_visitante']; ?>
                    <div class="partido-card">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="badge-jornada">Jornada <?= $p['jornada'] ?></span>
                            <span class="badge badge-estado-jugado rounded-pill px-3 py-2"><i class="bi bi-check-circle me-1"></i>Finalizado</span>
                        </div>
                        <div class="d-flex align-items-center justify-content-between">
                            <div class="equipo-col">
                                <?= logo_equipo($local, 52) ?>
                                <span class="nombre <?= $ganoLocal ? 'text-success' : '' ?>"><?= e($local['nombre']) ?></span>
                            </div>
                            <div class="marcador"><?= $p['marcador_local'] ?> - <?= $p['marcador_visitante'] ?></div>
                            <div class="equipo-col">
                                <?= logo_equipo($visit, 52) ?>
                                <span class="nombre <?= !$ganoLocal ? 'text-success' : '' ?>"><?= e($visit['nombre']) ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- EQUIPOS -->
<section class="seccion" id="equipos">
    <div class="container">
        <div class="seccion-titulo mb-4">
            <p class="eyebrow mb-1">La liga</p>
            <h2 class="mb-0">Equipos de la temporada</h2>
        </div>
        <div class="row row-cols-2 row-cols-md-4 g-3">
            <?php foreach ($equipos as $eq): ?>
            <div class="col">
                <a href="<?= url_copa('equipo.php?id=' . $eq['id']) ?>" class="equipo-tile">
                    <?= logo_equipo($eq, 68) ?>
                    <div class="nombre"><?= e($eq['nombre']) ?></div>
                    <div class="ciudad"><?= e($eq['ciudad']) ?></div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- PATROCINADORES -->
<?php vista('parciales/seccion_patrocinadores', compact('torneo', 'patrocOficiales', 'patrocOro', 'patrocPlata')); ?>
