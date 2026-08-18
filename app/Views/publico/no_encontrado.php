<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Página no encontrada</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="<?= url('assets/img/logo.png') ?>" type="image/png">
</head>
<body style="background:linear-gradient(135deg,#171028,#241a3a);color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;text-align:center;font-family:'Inter',sans-serif;">
    <div class="p-4" style="max-width:460px;">
        <div class="d-flex justify-content-center"><?= icono_multideporte(72) ?></div>
        <p class="mt-4 mb-1 fw-bold" style="font-family:'Poppins',sans-serif;font-size:4rem;line-height:1;background:linear-gradient(135deg,#7b2ff7,#ff6b35);-webkit-background-clip:text;background-clip:text;color:transparent;">404</p>
        <h1 class="fs-4 mb-3" style="font-family:'Poppins',sans-serif;">Esta página no existe</h1>
        <p class="mb-4" style="color:rgba(255,255,255,.65);">Puede que el enlace esté mal escrito o que la página ya no esté disponible. Si buscas una copa o liga, entra con su código.</p>
        <a href="<?= url('/') ?>" class="btn btn-light rounded-pill px-4 fw-semibold">Ir al inicio</a>
    </div>
</body>
</html>
