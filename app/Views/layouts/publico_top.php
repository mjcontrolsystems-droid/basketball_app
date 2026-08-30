<?php
declare(strict_types=1);
/**
 * Layout del sitio público: navbar + avisos flash. Lo cierra publico_bottom.php.
 *
 * Datos opcionales que le puede pasar el controlador: $titulo_pagina, $pagina_activa y
 * $torneo. $torneo puede venir null en las páginas que no pertenecen a ninguna copa
 * (portada, 404), así que el navbar y el footer tienen que poder pintarse sin copa.
 */

function nav_activa(string $clave, string $activa): string
{
    return $clave === $activa ? 'active' : '';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo_pagina) ?></title>
    <meta name="description" content="<?= e($torneo['descripcion'] ?? 'Plataforma de copas y ligas deportivas: tabla de posiciones, calendario y resultados en vivo.') ?>">

    <?php
    // Open Graph + Twitter Card: al compartir el enlace por WhatsApp, Instagram o
    // redes, se muestra una tarjeta con el nombre de la copa, descripción y logo en
    // vez de un link pelón — la primera impresión del torneo empieza en el chat.
    $ogImagen = !empty($torneo['logo']) ? SITE_ORIGIN . url_imagen((string) $torneo['logo']) : SITE_ORIGIN . url('assets/img/logo.png');
    ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($torneo['nombre'] ?? 'Plataforma de Copas y Ligas') ?>">
    <meta property="og:title" content="<?= e($titulo_pagina) ?>">
    <meta property="og:description" content="<?= e($torneo['descripcion'] ?? 'Tabla de posiciones, calendario y resultados.') ?>">
    <meta property="og:image" content="<?= e($ogImagen) ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= e($titulo_pagina) ?>">
    <meta name="twitter:description" content="<?= e($torneo['descripcion'] ?? 'Tabla de posiciones, calendario y resultados.') ?>">
    <meta name="twitter:image" content="<?= e($ogImagen) ?>">

    <?php // La barra del navegador móvil toma el color de la copa (detalle de app nativa) ?>
    <meta name="theme-color" content="<?= e(color_hex_valido($torneo['color_primario'] ?? null, '#7b2ff7')) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= asset_url('assets/css/style.css') ?>" rel="stylesheet">
    <link rel="icon" href="<?= url('assets/img/logo.png') ?>" type="image/png">
    <?= torneo_variables_css($torneo) ?>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark fixed-top navbar-copa">
    <div class="container">
        <?php // El logo que subió el organizador manda: solo si la copa no tiene logo
              // propio se cae al balón/ícono del deporte (ver logo_torneo() en helpers). ?>
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $torneo ? url_copa('index.php') : url('/') ?>">
            <?= logo_torneo($torneo, 42) ?>
            <span><?= e($torneo['nombre'] ?? 'Plataforma de Copas y Ligas') ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPrincipal">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navPrincipal">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <?php if ($torneo): ?>
                <li class="nav-item"><a class="nav-link <?= nav_activa('inicio', $pagina_activa) ?>" href="<?= url_copa('index.php') ?>"><i class="bi bi-house-door me-1"></i>Inicio</a></li>
                <li class="nav-item"><a class="nav-link <?= nav_activa('tabla', $pagina_activa) ?>" href="<?= url_copa('tabla.php') ?>"><i class="bi bi-trophy me-1"></i>Tabla de Posiciones</a></li>
                <li class="nav-item"><a class="nav-link <?= nav_activa('calendario', $pagina_activa) ?>" href="<?= url_copa('calendario.php') ?>"><i class="bi bi-calendar2-week me-1"></i>Calendario</a></li>
                <li class="nav-item"><a class="nav-link <?= nav_activa('equipos', $pagina_activa) ?>" href="<?= url_copa('equipos.php') ?>"><i class="bi bi-people me-1"></i>Equipos</a></li>
                <li class="nav-item"><a class="nav-link <?= nav_activa('patrocinadores', $pagina_activa) ?>" href="<?= url_copa('patrocinadores.php') ?>"><i class="bi bi-award me-1"></i>Patrocinadores</a></li>
                <?php // Solvencia: solo si la copa cobra multas o aplica suspensiones. La
                      // hoja existía pero no había forma de llegarle desde el sitio: el
                      // capitán tenía que pedirle el enlace al organizador cada semana. ?>
                <?php if (torneo_cobra_multas($torneo ?? []) || torneo_aplica_suspensiones($torneo ?? [])): ?>
                <li class="nav-item"><a class="nav-link <?= nav_activa('solvencia', $pagina_activa) ?>" href="<?= url_copa('solvencia.php') ?>"><i class="bi bi-clipboard-check me-1"></i>Solvencia</a></li>
                <?php endif; ?>
                <?php // El reglamento solo aparece en el menú si la copa lo publicó ?>
                <?php if (torneo_tiene_reglamento($torneo)): ?>
                <li class="nav-item"><a class="nav-link <?= nav_activa('reglamento', $pagina_activa) ?>" href="<?= url_copa('reglamento.php') ?>"><i class="bi bi-journal-text me-1"></i>Reglamento</a></li>
                <?php endif; ?>
                <li class="nav-item"><a class="nav-link <?= nav_activa('organizador', $pagina_activa) ?>" href="<?= url_copa('organizador.php') ?>"><i class="bi bi-person-badge me-1"></i>Organizador</a></li>
                <?php endif; ?>
                <li class="nav-item ms-lg-2">
                    <button type="button" class="btn btn-outline-luz btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalCompartir">
                        <i class="bi bi-share-fill me-1"></i>Compartir
                    </button>
                </li>
                <li class="nav-item ms-lg-2">
                    <button type="button" class="btn btn-outline-luz btn-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#modalCodigo">
                        <i class="bi bi-key-fill me-1"></i>Tengo un código
                    </button>
                </li>
                <li class="nav-item ms-lg-2">
                    <?php if ($usuarioActual): ?>
                    <div class="dropdown">
                        <button class="btn btn-outline-luz btn-sm rounded-pill px-2 d-flex align-items-center gap-2 dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <?php if (!empty($usuarioActual['foto'])): ?>
                                <img src="<?= e(url_imagen($usuarioActual['foto'])) ?>" width="26" height="26" class="rounded-circle" style="object-fit:cover;" alt="">
                            <?php else: ?>
                                <span class="avatar-organizador" style="width:26px;height:26px;font-size:.72rem;"><?= e(iniciales_de($usuarioActual['nombre'] ?: $usuarioActual['usuario'])) ?></span>
                            <?php endif; ?>
                            <span class="d-none d-lg-inline"><?= e($usuarioActual['nombre'] !== '' ? explode(' ', $usuarioActual['nombre'])[0] : $usuarioActual['usuario']) ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="<?= url('admin/index.php') ?>"><i class="bi bi-speedometer2 me-2"></i>Panel</a></li>
                            <li><a class="dropdown-item" href="<?= url('admin/perfil.php') ?>"><i class="bi bi-person-circle me-2"></i>Mi perfil</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="<?= url('logout.php') ?>"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
                        </ul>
                    </div>
                    <?php else: ?>
                        <a class="btn btn-degradado btn-sm rounded-pill px-3" href="<?= url('login.php') ?>"><i class="bi bi-person-circle me-1"></i>Acceder</a>
                    <?php endif; ?>
                </li>
            </ul>
        </div>
    </div>
</nav>

<?php // Aviso de que el sitio está cerrado al público.
      //
      // Solo lo ve quien tiene permiso para entrar en mantenimiento — el organizador y los
      // superadmins. Sin esta franja no había forma de notar que estaba cerrado: uno abre
      // el sitio, lo ve normal, y concluye que el botón no sirvió. Pasó exactamente eso. ?>
<?php if (torneo_en_mantenimiento($torneo ?? null)): ?>
<div class="w-100 text-center py-2 px-3" style="background:#ffc93c;color:#3d2c00;font-weight:600;font-size:.9rem;position:relative;z-index:1080;">
    <i class="bi bi-cone-striped me-1"></i>
    Este sitio está <strong>cerrado al público</strong>. Lo estás viendo porque tienes tu sesión abierta.
    <a href="<?= url('admin/index.php') ?>" class="ms-2 text-decoration-underline" style="color:#3d2c00;">Reabrirlo</a>
</div>
<?php endif; ?>

<?php // Aviso al público de la copa (condolencias, cumpleaños, recordatorio). Lo pinta
      // app.js como emergente UNA vez por visita: el hash cambia si el organizador edita
      // el texto, y así el mensaje nuevo vuelve a mostrarse aunque ya se haya visto otro. ?>
<?php if (!empty($torneo['aviso_activo']) && trim((string) ($torneo['aviso_mensaje'] ?? '')) !== ''): ?>
<div id="avisoCopa" class="d-none"
     data-tipo="<?= e($torneo['aviso_tipo'] ?? 'informativo') ?>"
     data-titulo="<?= e($torneo['aviso_titulo'] ?? '') ?>"
     data-mensaje="<?= e($torneo['aviso_mensaje'] ?? '') ?>"
     data-hash="<?= e(substr(md5(($torneo['aviso_titulo'] ?? '') . '|' . ($torneo['aviso_mensaje'] ?? '')), 0, 10)) ?>"></div>
<?php endif; ?>

<?php // Igual que en el panel: el mensaje lo pinta SweetAlert2 desde app.js. ?>
<?php if ($flash): ?>
<div id="datosFlash" class="d-none" data-tipo="<?= e($flash['tipo']) ?>" data-mensaje="<?= e($flash['mensaje']) ?>"></div>
<noscript>
    <div class="container pt-4">
        <div class="alert alert-<?= $flash['tipo'] === 'error' ? 'danger' : $flash['tipo'] ?> shadow-lg rounded-4 border-0" role="alert">
            <?= e($flash['mensaje']) ?>
        </div>
    </div>
</noscript>
<?php endif; ?>
