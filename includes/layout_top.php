<?php
declare(strict_types=1);
/**
 * Requiere que la página que lo incluye ya haya definido (opcional):
 * $titulo_pagina, $pagina_activa, $torneo (resuelto por torneo_actual.php).
 * $torneo puede venir null/sin definir SOLO en torneos.php (el listado de todas
 * las copas no pertenece a ninguna copa en particular), así que el navbar y el
 * footer deben poder mostrarse también sin una copa activa.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/usuarios.php';
require_once __DIR__ . '/helpers.php';

$torneo = $torneo ?? null;
$pagina_activa = $pagina_activa ?? '';
$titulo_pagina = $titulo_pagina ?? ($torneo ? $torneo['nombre'] . ' — ' . $torneo['subtitulo'] : 'Plataforma de Copas y Ligas');
$flash = obtener_flash();
$usuarioActual = auth_check() ? usuarios_obtener_por_id((int) $_SESSION['usuario_id']) : null;

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
    <meta name="description" content="<?= e($torneo['descripcion'] ?? '') ?>">
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
        <a class="navbar-brand d-flex align-items-center gap-2" href="<?= $torneo ? url_copa('index.php') : url('/') ?>">
            <?php if ($torneo): ?>
                <span class="badge-pill-icon"><?= icono_deporte($torneo['deporte'], 22) ?></span>
            <?php else: ?>
                <?= icono_multideporte(40) ?>
            <?php endif; ?>
            <span><?= e($torneo['nombre'] ?? 'Plataforma de Copas y Ligas') ?></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navPrincipal">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navPrincipal">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <?php if ($torneo): ?>
                <li class="nav-item"><a class="nav-link <?= nav_activa('inicio', $pagina_activa) ?>" href="<?= url_copa('index.php') ?>">Inicio</a></li>
                <li class="nav-item"><a class="nav-link <?= nav_activa('tabla', $pagina_activa) ?>" href="<?= url_copa('tabla.php') ?>">Tabla de Posiciones</a></li>
                <li class="nav-item"><a class="nav-link <?= nav_activa('calendario', $pagina_activa) ?>" href="<?= url_copa('calendario.php') ?>">Calendario</a></li>
                <li class="nav-item"><a class="nav-link <?= nav_activa('equipos', $pagina_activa) ?>" href="<?= url_copa('equipos.php') ?>">Equipos</a></li>
                <li class="nav-item"><a class="nav-link <?= nav_activa('patrocinadores', $pagina_activa) ?>" href="<?= url_copa('patrocinadores.php') ?>">Patrocinadores</a></li>
                <li class="nav-item"><a class="nav-link <?= nav_activa('organizador', $pagina_activa) ?>" href="<?= url_copa('organizador.php') ?>">Organizador</a></li>
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

<?php if ($flash): ?>
<div class="container position-fixed top-0 start-50 translate-middle-x pt-5 mt-5" style="z-index:2000;max-width:520px;">
    <div class="alert alert-<?= $flash['tipo'] === 'error' ? 'danger' : $flash['tipo'] ?> shadow-lg rounded-4 border-0 alert-dismissible fade show" data-autoclose role="alert">
        <?= e($flash['mensaje']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>
<?php endif; ?>
