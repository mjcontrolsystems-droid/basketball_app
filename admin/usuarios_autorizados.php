<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/usuarios.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/correo.php';

auth_requerir();
$usuarioSesion = usuarios_obtener_por_id((int) $_SESSION['usuario_id']);
if (!es_superadmin($usuarioSesion)) {
    http_response_code(403);
    exit('No tienes permiso para ver esta página.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (($_POST['accion'] ?? '') === 'agregar') {
        $email = trim((string) ($_POST['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            redirigir_con_mensaje(url('admin/usuarios_autorizados.php'), 'error', 'Ingresa un correo válido.');
        }
        $limite = max(0, (int) ($_POST['limite_torneos'] ?? LIMITE_TORNEOS_POR_DEFECTO));
        correos_autorizados_agregar($email, $limite);
        // Aviso automático: la persona se entera sola de que ya puede crear su cuenta,
        // sin que el super-admin tenga que escribirle por aparte.
        $avisado = correo_avisar_autorizado($email, $limite);
        $sufijo = $avisado ? ' Se le envió un correo de aviso.' : (correo_configurado() ? ' (No se pudo enviar el correo de aviso.)' : '');
        redirigir_con_mensaje(url('admin/usuarios_autorizados.php'), 'success', "Correo agregado con {$limite} copa(s) o liga(s) autorizada(s).{$sufijo}");
    }

    if (($_POST['accion'] ?? '') === 'actualizar_limite') {
        $limite = max(0, (int) ($_POST['limite_torneos'] ?? 0));
        $id = (int) $_POST['id'];
        correos_autorizados_actualizar_limite($id, $limite);
        // Solo se avisa si sube el cupo de alguien que ya tiene cuenta (subir el cupo es
        // la consecuencia de un pago; bajar a 0 no amerita un correo automático).
        $fila = null;
        foreach (correos_autorizados_listar() as $c) {
            if ((int) $c['id'] === $id) { $fila = $c; break; }
        }
        $avisado = false;
        if ($fila !== null && $limite > 0 && usuarios_obtener_por_email((string) $fila['email']) !== null) {
            $avisado = correo_avisar_cupo((string) $fila['email'], $limite);
        }
        redirigir_con_mensaje(url('admin/usuarios_autorizados.php'), 'success', 'Cupo actualizado.' . ($avisado ? ' Se le envió un correo de aviso.' : ''));
    }

    if (($_POST['accion'] ?? '') === 'eliminar') {
        correos_autorizados_eliminar((int) $_POST['id']);
        redirigir_con_mensaje(url('admin/usuarios_autorizados.php'), 'success', 'Correo quitado de la lista.');
    }
}

$correos = correos_autorizados_listar();

// Cuántas copas lleva creadas cada correo, para mostrar "usadas / autorizadas" y que el
// super-admin sepa a quién le queda cupo sin tener que entrar a la cuenta de cada uno.
$usadasPorCorreo = [];
foreach ($correos as $c) {
    $cuenta = usuarios_obtener_por_email((string) $c['email']);
    $usadasPorCorreo[$c['id']] = $cuenta ? torneos_contar_por_usuario((int) $cuenta['id']) : 0;
}

$seccion_activa = 'usuarios_autorizados';
$titulo_pagina = 'Correos autorizados';
require __DIR__ . '/includes/admin_layout_top.php';
?>

<div class="mb-4">
    <h3 class="mb-1">Correos autorizados y cupos</h3>
    <p class="text-muted small mb-0">El registro público está cerrado: solo los correos de esta lista pueden crear una cuenta con "Continuar con Google". Además, aquí defines cuántas copas o ligas puede tener creadas cada organizador — el cobro es por torneo, así que sube el cupo conforme te vayan pagando.</p>
</div>

<form method="post" class="card-suave p-3 mb-4" style="max-width:620px;">
    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="accion" value="agregar">
    <div class="row g-2 align-items-end">
        <div class="col-12 col-sm">
            <label class="form-label small fw-semibold mb-1">Correo a autorizar</label>
            <input type="email" name="email" class="form-control" placeholder="correo@ejemplo.com" required>
        </div>
        <div class="col-6 col-sm-auto">
            <label class="form-label small fw-semibold mb-1">Cupo</label>
            <input type="number" name="limite_torneos" class="form-control" value="<?= LIMITE_TORNEOS_POR_DEFECTO ?>" min="0" max="999" style="width:90px;">
        </div>
        <div class="col-6 col-sm-auto">
            <button type="submit" class="btn btn-degradado rounded-pill px-3 w-100"><i class="bi bi-plus-lg me-1"></i>Agregar</button>
        </div>
    </div>
    <div class="form-text">Por defecto se autoriza 1 copa o liga. Puedes cambiarlo después en la tabla.</div>
</form>

<?php if (empty($correos)): ?>
    <p class="text-muted">Todavía no has agregado ningún correo a la lista.</p>
<?php else: ?>
<div class="table-responsive card-suave p-0" style="max-width:720px;">
    <table class="table align-middle mb-0">
        <thead>
            <tr>
                <th>Correo</th>
                <th class="text-center">Usadas</th>
                <th style="min-width:190px;">Cupo autorizado</th>
                <th style="width:60px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($correos as $c): ?>
            <?php
                $limite = (int) ($c['limite_torneos'] ?? LIMITE_TORNEOS_POR_DEFECTO);
                $usadas = $usadasPorCorreo[$c['id']] ?? 0;
                $sinCupo = $usadas >= $limite;
            ?>
            <tr>
                <td data-label="Correo"><?= e($c['email']) ?></td>
                <td class="text-center" data-label="Usadas">
                    <span class="badge rounded-pill <?= $sinCupo ? 'text-bg-warning' : 'text-bg-light border' ?>"><?= $usadas ?> / <?= $limite ?></span>
                </td>
                <td data-label="Cupo autorizado">
                    <form method="post" class="d-flex align-items-center gap-1 mb-0">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="actualizar_limite">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <input type="number" name="limite_torneos" class="form-control form-control-sm" value="<?= $limite ?>" min="0" max="999" style="width:80px;">
                        <button type="submit" class="btn btn-sm btn-outline-success" title="Guardar cupo"><i class="bi bi-check-lg"></i></button>
                    </form>
                </td>
                <td class="text-end" data-label="">
                    <form method="post" data-confirm="¿Quitar a <?= e($c['email']) ?> de la lista?">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<p class="text-muted small mt-2 mb-0">Los correos super-admin no tienen límite de copas. Un cupo en 0 impide crear cualquier copa nueva.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/admin_layout_bottom.php'; ?>
