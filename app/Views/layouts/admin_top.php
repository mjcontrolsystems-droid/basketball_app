<?php
declare(strict_types=1);

// Los datos que usa este layout (usuario, copa activa, flash, contador de comentarios)
// los prepara datos_layout_admin() en app/Support/vista.php, no la plantilla.

function admin_nav_activa(string $clave, string $activa): string
{
    return $clave === $activa ? 'active' : '';
}

function admin_badge_no_leidos(int $cantidad): string
{
    return $cantidad > 0 ? ' <span class="badge rounded-pill text-bg-danger ms-1">' . $cantidad . '</span>' : '';
}

function admin_nav_copa(string $seccion_activa, ?array $torneoActivo): string
{
    ob_start();
    if ($torneoActivo) {
        ?>
        <?php // Cada quien ve solo lo que puede abrir. Esconder el enlace es cortesía, no
              // seguridad: el candado de verdad está en requerir_permiso() dentro de cada
              // controlador, porque el menú no impide escribir la URL a mano. ?>
        <?php if (puede('equipos', $torneoActivo)): ?>
        <a class="nav-link <?= admin_nav_activa('equipos', $seccion_activa) ?>" href="<?= url('admin/equipos.php') ?>"><i class="bi bi-people me-2"></i>Equipos</a>
        <?php endif; ?>
        <a class="nav-link <?= admin_nav_activa('partidos', $seccion_activa) ?>" href="<?= url('admin/partidos.php') ?>"><i class="bi bi-calendar2-week me-2"></i>Encuentros</a>
        <?php // Sanciones solo aparece si la liga cobra multas por tarjeta ?>
        <?php if (torneo_cobra_multas($torneoActivo) && puede('sanciones', $torneoActivo)): ?>
        <a class="nav-link <?= admin_nav_activa('sanciones', $seccion_activa) ?>" href="<?= url('admin/sanciones.php') ?>"><i class="bi bi-cash-coin me-2"></i>Sanciones</a>
        <?php endif; ?>
        <?php if (puede('patrocinadores', $torneoActivo)): ?>
        <a class="nav-link <?= admin_nav_activa('patrocinadores', $seccion_activa) ?>" href="<?= url('admin/patrocinadores.php') ?>"><i class="bi bi-award me-2"></i>Patrocinadores</a>
        <?php endif; ?>
        <?php if (puede('comentarios', $torneoActivo)): ?>
        <a class="nav-link <?= admin_nav_activa('comentarios', $seccion_activa) ?>" href="<?= url('admin/comentarios.php') ?>"><i class="bi bi-chat-heart me-2"></i>Comentarios</a>
        <?php endif; ?>
        <?php if (es_dueno_de_copa($torneoActivo)): ?>
        <a class="nav-link <?= admin_nav_activa('colaboradores', $seccion_activa) ?>" href="<?= url('admin/colaboradores.php') ?>"><i class="bi bi-person-plus me-2"></i>Colaboradores</a>
        <a class="nav-link <?= admin_nav_activa('torneos', $seccion_activa) ?>" href="<?= url('admin/torneos.php?accion=editar&id=' . $torneoActivo['id']) ?>"><i class="bi bi-sliders me-2"></i>Configuración de la copa o liga</a>
        <?php endif; ?>
        <?php
    }
    return (string) ob_get_clean();
}

/**
 * Foto (o iniciales) + nombre del usuario logueado, enlazando a Mi Perfil donde
 * puede cambiar la foto. Se usa igual en el sidebar de escritorio y el de móvil.
 */
function admin_tarjeta_usuario(array $usuario): string
{
    ob_start();
    ?>
    <a href="<?= url('admin/perfil.php') ?>" class="d-flex align-items-center gap-2 text-decoration-none px-2 py-2 mb-1" style="color:rgba(255,255,255,.85);">
        <?php if (!empty($usuario['foto'])): ?>
            <img src="<?= e(url_imagen($usuario['foto'])) ?>" width="34" height="34" class="rounded-circle" style="object-fit:cover;" alt="">
        <?php else: ?>
            <span class="avatar-organizador" style="width:34px;height:34px;font-size:.85rem;"><?= e(iniciales_de($usuario['nombre'] ?: $usuario['usuario'])) ?></span>
        <?php endif; ?>
        <span class="small">
            <span class="d-block fw-semibold text-white"><?= e($usuario['nombre'] !== '' ? $usuario['nombre'] : $usuario['usuario']) ?></span>
            <span class="d-block" style="color:rgba(255,255,255,.55);font-size:.72rem;">Ver mi perfil</span>
        </span>
    </a>
    <?php
    return (string) ob_get_clean();
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($titulo_pagina) ?> — Panel Organizador</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <link href="<?= asset_url('assets/css/style.css') ?>" rel="stylesheet">
    <link rel="icon" href="<?= url('assets/img/logo.png') ?>" type="image/png">
    <?= torneo_variables_css($torneoActivo) ?>
</head>
<body style="background:#f7f5fb;">
<div class="d-flex">
    <aside class="sidebar-admin d-none d-lg-flex flex-column p-3" style="width:270px;flex-shrink:0;">
        <a href="<?= url('admin/index.php') ?>" class="d-flex align-items-center gap-2 text-decoration-none text-white mb-3 px-2 pt-2">
            <?= logo_torneo($torneoActivo, 38) ?>
            <span class="fw-heading fs-6"><?= e($nombreMarca) ?></span>
        </a>
        <a href="<?= url('admin/torneos.php') ?>" class="d-block small text-decoration-none px-2 mb-3" style="color:rgba(255,255,255,.6);">
            <i class="bi bi-arrow-left-right me-1"></i>Cambiar de copa o liga
        </a>
        <nav class="nav flex-column flex-grow-1">
            <a class="nav-link <?= admin_nav_activa('dashboard', $seccion_activa) ?>" href="<?= url('admin/index.php') ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
            <?= admin_nav_copa($seccion_activa, $torneoActivo) ?>
            <hr class="border-secondary opacity-25 my-2">
            <a class="nav-link <?= admin_nav_activa('torneos-lista', $seccion_activa) ?>" href="<?= url('admin/torneos.php') ?>"><i class="bi bi-trophy me-2"></i>Mis Copas y Ligas</a>
            <?php if ($esSuperadmin): ?><a class="nav-link <?= admin_nav_activa('usuarios_autorizados', $seccion_activa) ?>" href="<?= url('admin/usuarios_autorizados.php') ?>"><i class="bi bi-shield-check me-2"></i>Correos autorizados</a><?php endif; ?>
            <a class="nav-link <?= admin_nav_activa('bitacora', $seccion_activa) ?>" href="<?= url('admin/bitacora.php') ?>"><i class="bi bi-journal-text me-2"></i>Actividad</a>
                    <a class="nav-link <?= admin_nav_activa('perfil', $seccion_activa) ?>" href="<?= url('admin/perfil.php') ?>"><i class="bi bi-person-badge me-2"></i>Mi Perfil</a>
        </nav>
        <hr class="border-secondary opacity-25">
        <?= admin_tarjeta_usuario($organizador) ?>
        <?php if ($torneoActivo): ?>
        <a href="<?= url(($torneoActivo['es_predeterminado'] ? '' : $torneoActivo['slug'] . '/') . 'index.php') ?>" class="nav-link" target="_blank"><i class="bi bi-box-arrow-up-right me-2"></i>Ver sitio público</a>
        <?php endif; ?>
        <a href="<?= url('logout.php') ?>" class="nav-link text-danger-emphasis"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a>
    </aside>

    <main class="flex-grow-1 min-vh-100">
        <nav class="navbar navbar-light bg-white border-bottom d-lg-none px-3">
            <button class="btn btn-outline-secondary btn-sm" type="button" data-bs-toggle="offcanvas" data-bs-target="#sidebarMovil"><i class="bi bi-list fs-5"></i></button>
            <span class="fw-heading"><?= e($nombreMarca) ?></span>
            <a href="<?= url('logout.php') ?>" class="btn btn-sm btn-outline-danger"><i class="bi bi-box-arrow-right"></i></a>
        </nav>

        <div class="offcanvas offcanvas-start sidebar-admin" tabindex="-1" id="sidebarMovil">
            <div class="offcanvas-header">
                <span class="text-white fw-heading">Menú</span>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
            </div>
            <div class="offcanvas-body">
                <a href="<?= url('admin/torneos.php') ?>" class="d-block small text-decoration-none mb-3" style="color:rgba(255,255,255,.6);">
                    <i class="bi bi-arrow-left-right me-1"></i>Cambiar de copa o liga
                </a>
                <nav class="nav flex-column">
                    <a class="nav-link <?= admin_nav_activa('dashboard', $seccion_activa) ?>" href="<?= url('admin/index.php') ?>"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
                    <?= admin_nav_copa($seccion_activa, $torneoActivo) ?>
                    <hr class="border-secondary opacity-25 my-2">
                    <a class="nav-link <?= admin_nav_activa('torneos-lista', $seccion_activa) ?>" href="<?= url('admin/torneos.php') ?>"><i class="bi bi-trophy me-2"></i>Mis Copas y Ligas</a>
                    <?php if ($esSuperadmin): ?><a class="nav-link <?= admin_nav_activa('usuarios_autorizados', $seccion_activa) ?>" href="<?= url('admin/usuarios_autorizados.php') ?>"><i class="bi bi-shield-check me-2"></i>Correos autorizados</a><?php endif; ?>
                    <a class="nav-link <?= admin_nav_activa('bitacora', $seccion_activa) ?>" href="<?= url('admin/bitacora.php') ?>"><i class="bi bi-journal-text me-2"></i>Actividad</a>
                    <a class="nav-link <?= admin_nav_activa('perfil', $seccion_activa) ?>" href="<?= url('admin/perfil.php') ?>"><i class="bi bi-person-badge me-2"></i>Mi Perfil</a>
                </nav>
                <hr class="border-secondary opacity-25">
                <?= admin_tarjeta_usuario($organizador) ?>
            </div>
        </div>

        <div class="p-3 p-md-4">
            <?php // El mensaje viaja en data-attributes y lo muestra SweetAlert2 desde
                  // app.js (el CSP no permite JavaScript inline). El <noscript> deja el
                  // aviso visible si el navegador tiene el JS desactivado. ?>
            <?php // Si una migración del esquema falló, el organizador tiene que saberlo:
                  // corren en orden, así que una que falle deja sin crear todas las de
                  // atrás y el síntoma aparece después y en otro lado. ?>
            <?php if (!empty($_SESSION['migraciones_error'])): ?>
            <div class="alert alert-danger rounded-4 border-0 shadow-sm" role="alert">
                <div class="fw-semibold mb-1"><i class="bi bi-database-exclamation me-1"></i>No se pudo actualizar la base de datos</div>
                <div class="small mb-0"><?= e($_SESSION['migraciones_error']) ?></div>
                <div class="small mt-2 mb-0 text-muted">Algunas funciones nuevas pueden fallar hasta resolverlo. Se reintenta en cada visita.</div>
            </div>
            <?php endif; ?>

            <?php if ($flash): ?>
            <div id="datosFlash" class="d-none" data-tipo="<?= e($flash['tipo']) ?>" data-mensaje="<?= e($flash['mensaje']) ?>"></div>
            <noscript>
                <div class="alert alert-<?= $flash['tipo'] === 'error' ? 'danger' : $flash['tipo'] ?> rounded-4 border-0 shadow-sm" role="alert">
                    <?= e($flash['mensaje']) ?>
                </div>
            </noscript>
            <?php endif; ?>
