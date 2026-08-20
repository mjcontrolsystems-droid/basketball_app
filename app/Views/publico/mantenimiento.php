<?php
/**
 * Aviso de mantenimiento del sitio público de una copa.
 *
 * Es una página completa y no una plantilla dentro del layout: si el sitio está cerrado,
 * mostrar el menú con enlaces a la tabla y al calendario sería una invitación a entrar a
 * páginas que igual van a rebotar aquí.
 */
$mensaje = trim((string) ($torneo['mensaje_mantenimiento'] ?? ''));
if ($mensaje === '') {
    $mensaje = 'Estamos actualizando el calendario. Vuelve en un rato — gracias por tu comprensión.';
}
?><!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($torneo['nombre'] ?? 'Copa') ?> — En mantenimiento</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            font-family: Inter, system-ui, sans-serif;
            color: #fff;
            text-align: center;
            background: linear-gradient(135deg,
                <?= e(color_hex_valido($torneo['color_primario'] ?? null, '#241a3a')) ?>,
                <?= e(color_hex_valido($torneo['color_secundario'] ?? null, '#7b2ff7')) ?>);
        }
        .caja { max-width: 540px; }
        h1 { font-family: Poppins, sans-serif; font-size: 1.6rem; margin: 1.25rem 0 .75rem; }
        p { font-size: 1.05rem; line-height: 1.6; color: rgba(255, 255, 255, .88); margin: 0 0 1.5rem; }
        .marca { font-size: .8rem; color: rgba(255, 255, 255, .55); margin: 2rem 0 0; }
        .icono { font-size: 3rem; opacity: .9; }
        .logo img, .logo svg { width: 86px; height: 86px; border-radius: 20px; background: rgba(255,255,255,.12); padding: 8px; }
        .redes a { color: rgba(255,255,255,.85); text-decoration: none; margin: 0 .6rem; font-size: .95rem; }
    </style>
</head>
<body>
    <div class="caja">
        <div class="logo"><?= logo_torneo($torneo, 86) ?></div>
        <div class="icono mt-3"><i class="bi bi-cone-striped"></i></div>
        <h1><?= e($torneo['nombre'] ?? 'Esta copa') ?> está en mantenimiento</h1>
        <p><?= e($mensaje) ?></p>

        <?php // Si la copa tiene redes, se dejan a la vista: es por donde la gente va a
              // preguntar mientras el sitio esté cerrado. ?>
        <?php $redesMant = redes_del_torneo($torneo ?? []); ?>
        <?php if (!empty($redesMant)): ?>
        <div class="redes">
            <?php foreach ($redesMant as $red): ?>
            <a href="<?= $red['url'] ?>" target="_blank" rel="noopener"><i class="bi <?= e($red['icono']) ?> me-1"></i><?= e($red['texto']) ?></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <p class="marca">MJ Control System</p>
    </div>
</body>
</html>
