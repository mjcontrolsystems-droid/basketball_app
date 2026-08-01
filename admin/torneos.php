<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/upload.php';
require_once __DIR__ . '/../includes/tabla.php';
require_once __DIR__ . '/../includes/usuarios.php';
require_once __DIR__ . '/../includes/liga.php';

auth_requerir();
$usuarioId = (int) $_SESSION['usuario_id'];

// Cupo de copas/ligas del organizador (modelo de cobro por torneo). Los super-admin no
// tienen límite; el resto depende de lo que se les haya autorizado en su correo.
$usuarioSesion = usuarios_obtener_por_id($usuarioId);
$limiteTorneos = usuario_limite_torneos($usuarioSesion);
$torneosCreados = torneos_contar_por_usuario($usuarioId);
$puedeCrearTorneo = $limiteTorneos === null || $torneosCreados < $limiteTorneos;

$accion = $_GET['accion'] ?? 'lista';

// Sin cupo disponible no se abre siquiera el formulario de "Nueva copa o liga": se avisa
// desde el listado en vez de dejar llenar todo el formulario para fallar al guardar.
if ($accion === 'nuevo' && !$puedeCrearTorneo) {
    redirigir_con_mensaje(url('admin/torneos.php'), 'error', mensaje_limite_torneos($limiteTorneos));
}

// Cambiar de copa activa (no necesita CSRF: es solo un cambio de contexto, no una escritura de datos)
if ($accion === 'entrar' && isset($_GET['id'])) {
    $id = (int) $_GET['id'];
    if (torneos_obtener_por_id($id, $usuarioId) !== null) {
        $_SESSION['torneo_activo_id'] = $id;
    }
    header('Location: ' . url('admin/index.php'));
    exit;
}

$idEditar = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$torneoEditar = $idEditar ? torneos_obtener_por_id($idEditar, $usuarioId) : null;
$errores = [];

function torneos_slugificar(string $texto): string
{
    $texto = mb_strtolower(trim($texto), 'UTF-8');
    $mapa = ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'ü' => 'u'];
    $texto = strtr($texto, $mapa);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?? '';
    return trim($texto, '-');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'guardar') {
    csrf_validar();

    $id = (int) ($_POST['id'] ?? 0);
    // Si el id no es 0 debe pertenecer a este usuario, si no cualquiera podría guardar
    // cambios sobre una copa ajena con solo adivinar/probar su id.
    if ($id > 0 && torneos_obtener_por_id($id, $usuarioId) === null) {
        http_response_code(403);
        exit('No tienes permiso para editar esta copa o liga.');
    }
    // Segunda barrera del cupo: el bloqueo de arriba solo cubre abrir el formulario, así
    // que un POST directo (o una pestaña abierta desde antes de que se agotara el cupo)
    // todavía podría crear una copa de más. Editar una existente no consume cupo nuevo.
    if ($id === 0 && !$puedeCrearTorneo) {
        redirigir_con_mensaje(url('admin/torneos.php'), 'error', mensaje_limite_torneos($limiteTorneos));
    }
    $nombre = trim((string) $_POST['nombre']);
    $slug = torneos_slugificar((string) ($_POST['slug'] ?: $nombre));
    $deporte = (string) $_POST['deporte'] === 'futbol' ? 'futbol' : 'basketball';
    $genero = in_array($_POST['genero'] ?? '', ['femenino', 'masculino'], true) ? $_POST['genero'] : 'mixto';

    // Modalidad válida para el deporte elegido (fútbol 11/7/5 o basketball FIBA/NBA);
    // si no coincide, se usa la modalidad por defecto del deporte. La duración
    // personalizada es opcional (vacía = usar la reglamentaria de la modalidad).
    $modalidad = (string) ($_POST['modalidad'] ?? '');
    if (!isset(MODALIDADES_POR_DEPORTE[$deporte][$modalidad])) {
        $modalidad = MODALIDAD_POR_DEFECTO[$deporte];
    }
    $duracionPeriodo = ($_POST['duracion_periodo_min'] ?? '') !== '' ? max(1, min(90, (int) $_POST['duracion_periodo_min'])) : null;

    $fasesElegidas = array_values(array_intersect((array) ($_POST['fases_playoff'] ?? []), FASES_PLAYOFF_CATALOGO));

    if ($nombre === '') {
        $errores[] = 'El nombre de la copa o liga es obligatorio.';
    }
    if ($slug === '') {
        $errores[] = 'La URL (slug) no puede quedar vacía. Usa letras, números y guiones.';
    } else {
        $existente = torneos_obtener_por_slug($slug);
        if ($existente && $existente['id'] !== $id) {
            $errores[] = "Ya existe otra copa o liga con la URL \"{$slug}\". Elige una distinta.";
        }
    }

    if (empty($errores)) {
        try {
            $logoSubido = manejar_subida_imagen('logo');
        } catch (RuntimeException $e) {
            redirigir_con_mensaje(url('admin/torneos.php' . ($id ? "?accion=editar&id={$id}" : '?accion=nuevo')), 'error', $e->getMessage());
        }

        $datos = [
            'id' => $id ?: null,
            'slug' => $slug,
            'nombre' => $nombre,
            'subtitulo' => trim((string) $_POST['subtitulo']),
            'temporada' => trim((string) $_POST['temporada']),
            'descripcion' => trim((string) $_POST['descripcion']),
            'sede_principal' => trim((string) $_POST['sede_principal']),
            'logo' => $logoSubido ?: ($torneoEditar['logo'] ?? ''),
            'color_primario' => (string) $_POST['color_primario'],
            'color_secundario' => (string) $_POST['color_secundario'],
            'color_acento' => (string) $_POST['color_acento'],
            'fecha_inicio' => (string) $_POST['fecha_inicio'],
            'fecha_fin' => (string) $_POST['fecha_fin'],
            'formato' => trim((string) $_POST['formato']),
            'instagram' => trim((string) $_POST['instagram']),
            'hero_frase' => trim((string) $_POST['hero_frase']),
            'deporte' => $deporte,
            'genero' => $genero,
            'modalidad' => $modalidad,
            'duracion_periodo_min' => $duracionPeriodo,
            'num_equipos' => max(2, (int) $_POST['num_equipos']),
            'fases_playoff' => $fasesElegidas,
            'permite_empates' => isset($_POST['permite_empates']),
            'puntos_victoria' => (int) $_POST['puntos_victoria'],
            'puntos_empate' => (int) $_POST['puntos_empate'],
            'puntos_derrota' => (int) $_POST['puntos_derrota'],
            'es_predeterminado' => !empty($torneoEditar['es_predeterminado']),
            'activo' => true,
        ];

        $idGuardado = torneos_guardar($datos, $usuarioId);
        bitacora_registrar($id ? 'torneo_editado' : 'torneo_creado', '"' . $nombre . '" (' . $deporte . ')', $idGuardado);

        if (empty($_SESSION['torneo_activo_id'])) {
            $_SESSION['torneo_activo_id'] = $idGuardado;
        }

        redirigir_con_mensaje(url('admin/torneos.php'), 'success', $id ? 'Copa o liga actualizada correctamente.' : '¡Copa o liga creada! Ya puedes cargar sus equipos y encuentros.');
    } else {
        $torneoEditar = array_merge($_POST, ['id' => $id, 'fases_playoff' => $fasesElegidas]);
        $accion = $id ? 'editar' : 'nuevo';
    }
}

if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['accion'] ?? '') === 'eliminar') {
    csrf_validar();
    $id = (int) $_POST['id'];
    try {
        $torneoBorrar = torneos_obtener_por_id($id, $usuarioId);
        torneos_eliminar($id, $usuarioId);
        bitacora_registrar('torneo_eliminado', '"' . ($torneoBorrar['nombre'] ?? "#{$id}") . '" eliminada con todos sus datos');
        if (($_SESSION['torneo_activo_id'] ?? null) === $id) {
            unset($_SESSION['torneo_activo_id']);
        }
        redirigir_con_mensaje(url('admin/torneos.php'), 'success', 'Copa o liga eliminada.');
    } catch (RuntimeException $e) {
        redirigir_con_mensaje(url('admin/torneos.php'), 'error', $e->getMessage());
    }
}

if (($_SERVER['REQUEST_METHOD'] === 'POST') && ($_POST['accion'] ?? '') === 'regenerar_codigo') {
    csrf_validar();
    $id = (int) $_POST['id'];
    try {
        torneos_regenerar_codigo($id, $usuarioId);
        bitacora_registrar('codigo_regenerado', 'Código de acceso regenerado', $id);
        redirigir_con_mensaje(url('admin/torneos.php'), 'success', 'Código regenerado.');
    } catch (RuntimeException $e) {
        redirigir_con_mensaje(url('admin/torneos.php'), 'error', $e->getMessage());
    }
}

$deportePorDefecto = $torneoEditar['deporte'] ?? 'basketball';
$generoPorDefecto = $torneoEditar['genero'] ?? 'mixto';
$torneos = torneos_listar(false, $usuarioId);

$seccion_activa = 'torneos';
$titulo_pagina = 'Mis Copas y Ligas';
require __DIR__ . '/includes/admin_layout_top.php';
?>

<?php if ($accion === 'nuevo' || $accion === 'editar'): ?>
    <div class="d-flex align-items-center gap-2 mb-4">
        <a href="<?= url('admin/torneos.php') ?>" class="btn btn-sm btn-outline-secondary rounded-circle"><i class="bi bi-arrow-left"></i></a>
        <h3 class="mb-0"><?= $accion === 'editar' ? 'Editar copa o liga' : 'Nueva copa o liga' ?></h3>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="alert alert-danger rounded-3">
        <ul class="mb-0 small">
            <?php foreach ($errores as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="card-suave p-4" style="max-width:860px;">
        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="accion" value="guardar">
        <input type="hidden" name="id" value="<?= $torneoEditar['id'] ?? 0 ?>">

        <h6 class="text-uppercase small fw-bold text-muted mb-3">Datos básicos</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-7">
                <label class="form-label small fw-semibold">Nombre de la copa o liga</label>
                <input type="text" name="nombre" id="campoNombre" class="form-control" value="<?= e($torneoEditar['nombre'] ?? '') ?>" required placeholder="Ej. Papifútbol Masculino 2026">
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold">URL de la copa o liga</label>
                <div class="input-group">
                    <span class="input-group-text small">/</span>
                    <input type="text" name="slug" id="campoSlug" class="form-control" value="<?= e($torneoEditar['slug'] ?? '') ?>" placeholder="se genera automático" data-predeterminado="<?= !empty($torneoEditar['es_predeterminado']) ? '1' : '0' ?>" data-origen="<?= e(SITE_ORIGIN . BASE_URL) ?>">
                </div>
                <div class="form-text">Solo letras, números y guiones. Tu copa o liga quedará en: <strong id="previewUrlCopa"><?= !empty($torneoEditar['es_predeterminado']) ? e(SITE_ORIGIN . BASE_URL . '/') : e(SITE_ORIGIN . BASE_URL . '/' . ($torneoEditar['slug'] ?? '') . '/') ?></strong></div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Deporte</label>
                <select name="deporte" id="selectDeporte" class="form-select">
                    <option value="basketball" <?= $deportePorDefecto === 'basketball' ? 'selected' : '' ?>>Basketball</option>
                    <option value="futbol" <?= $deportePorDefecto === 'futbol' ? 'selected' : '' ?>>Fútbol</option>
                </select>
                <div class="form-text">Define los valores iniciales de empates y puntos abajo, y el catálogo de eventos (goles/puntos, tarjetas/faltas) de cada partido.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Género</label>
                <select name="genero" class="form-select">
                    <option value="mixto" <?= $generoPorDefecto === 'mixto' ? 'selected' : '' ?>>Mixto / no aplica</option>
                    <option value="femenino" <?= $generoPorDefecto === 'femenino' ? 'selected' : '' ?>>Femenino</option>
                    <option value="masculino" <?= $generoPorDefecto === 'masculino' ? 'selected' : '' ?>>Masculino</option>
                </select>
                <div class="form-text">Ajusta "entrenador/a", "jugador/a", etc. en todo el sitio.</div>
            </div>

            <?php
            // Modalidad + duración de periodo: definen cuántos minutos dura cada tiempo/
            // cuarto (cronómetro y transmisión en vivo). El JS de app.js sugiere la
            // duración reglamentaria al elegir la modalidad; el organizador puede
            // sobreescribirla (muchas ligas amateur juegan tiempos más cortos).
            $modalidadActual = (string) ($torneoEditar['modalidad'] ?? '');
            $duracionActual = (int) ($torneoEditar['duracion_periodo_min'] ?? 0);
            ?>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Modalidad</label>
                <select name="modalidad" id="selectModalidad" class="form-select">
                    <?php foreach (MODALIDADES_POR_DEPORTE as $dep => $catalogo): foreach ($catalogo as $clave => $m): ?>
                    <option value="<?= e($clave) ?>" data-deporte="<?= e($dep) ?>" data-duracion="<?= (int) $m['duracion_min'] ?>" <?= $modalidadActual === $clave ? 'selected' : '' ?>><?= e($m['label']) ?></option>
                    <?php endforeach; endforeach; ?>
                </select>
                <div class="form-text">Fútbol: 11, 7 o sala/5. Basketball: FIBA o estilo NBA. Define los tiempos reglamentarios.</div>
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Minutos por tiempo/cuarto</label>
                <input type="number" name="duracion_periodo_min" id="campoDuracionPeriodo" class="form-control" min="1" max="90" value="<?= $duracionActual > 0 ? $duracionActual : '' ?>" placeholder="Reglamentario de la modalidad">
                <div class="form-text">Déjalo vacío para usar el reglamentario. Cámbialo si tu liga juega tiempos distintos (ej. cuartos de 15 min).</div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Subtítulo</label>
                <input type="text" name="subtitulo" class="form-control" placeholder="Ej. Liga Municipal de Verano" value="<?= e($torneoEditar['subtitulo'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Temporada</label>
                <input type="text" name="temporada" class="form-control" placeholder="Ej. 2026" value="<?= e($torneoEditar['temporada'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Descripción</label>
                <textarea name="descripcion" class="form-control" rows="2" placeholder="Ej. Ocho equipos, una vuelta de temporada regular y el sueño de levantar la copa."><?= e($torneoEditar['descripcion'] ?? '') ?></textarea>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Frase del hero (portada)</label>
                <input type="text" name="hero_frase" class="form-control" placeholder="Ej. Pasión, esfuerzo y comunidad en cada jornada" value="<?= e($torneoEditar['hero_frase'] ?? '') ?>">
            </div>
        </div>

        <h6 class="text-uppercase small fw-bold text-muted mb-3">Formato de la competencia</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Número de equipos</label>
                <input type="number" min="2" name="num_equipos" class="form-control" value="<?= e((string) ($torneoEditar['num_equipos'] ?? 8)) ?>">
                <div class="form-text">Informativo, se muestra en el sitio.</div>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">¿Permite empates?</label>
                <div class="form-check form-switch mt-2">
                    <input class="form-check-input" type="checkbox" role="switch" id="checkEmpates" name="permite_empates" <?= !empty($torneoEditar['permite_empates']) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="checkEmpates">Sí, esta copa o liga admite empates</label>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Puntos por resultado</label>
                <div class="row g-2">
                    <div class="col-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Victoria</span>
                            <input type="number" min="0" name="puntos_victoria" id="campoPtsVictoria" class="form-control" value="<?= e((string) ($torneoEditar['puntos_victoria'] ?? 2)) ?>">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Empate</span>
                            <input type="number" min="0" name="puntos_empate" id="campoPtsEmpate" class="form-control" value="<?= e((string) ($torneoEditar['puntos_empate'] ?? 0)) ?>">
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">Derrota</span>
                            <input type="number" min="0" name="puntos_derrota" id="campoPtsDerrota" class="form-control" value="<?= e((string) ($torneoEditar['puntos_derrota'] ?? 1)) ?>">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold d-block">Fases de eliminación directa</label>
                <?php $fasesGuardadas = $torneoEditar['fases_playoff'] ?? ['cuartos', 'semifinal', 'final']; ?>
                <?php foreach (FASES_PLAYOFF_CATALOGO as $f): ?>
                <div class="form-check form-check-inline">
                    <input class="form-check-input" type="checkbox" name="fases_playoff[]" value="<?= e($f) ?>" id="fase-<?= e($f) ?>" <?= in_array($f, $fasesGuardadas, true) ? 'checked' : '' ?>>
                    <label class="form-check-label small" for="fase-<?= e($f) ?>"><?= e(FASES_LABEL[$f]) ?></label>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <h6 class="text-uppercase small fw-bold text-muted mb-3">Fechas, sede y estilo</h6>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Fecha de inicio</label>
                <input type="date" name="fecha_inicio" class="form-control" value="<?= e($torneoEditar['fecha_inicio'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Fecha de fin</label>
                <input type="date" name="fecha_fin" class="form-control" value="<?= e($torneoEditar['fecha_fin'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Formato</label>
                <input type="text" name="formato" class="form-control" placeholder="Ej. Fase de grupos + eliminación directa" value="<?= e($torneoEditar['formato'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Sede principal</label>
                <input type="text" name="sede_principal" class="form-control" placeholder="Ej. Polideportivo Municipal, Ciudad Capital" value="<?= e($torneoEditar['sede_principal'] ?? '') ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label small fw-semibold">Instagram (opcional)</label>
                <input type="url" name="instagram" class="form-control" placeholder="https://instagram.com/tu_copa" value="<?= e($torneoEditar['instagram'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Color primario</label>
                <input type="color" name="color_primario" class="form-control form-control-color w-100" value="<?= e($torneoEditar['color_primario'] ?? '#7b2ff7') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Color secundario</label>
                <input type="color" name="color_secundario" class="form-control form-control-color w-100" value="<?= e($torneoEditar['color_secundario'] ?? '#ff6b35') ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Color acento</label>
                <input type="color" name="color_acento" class="form-control form-control-color w-100" value="<?= e($torneoEditar['color_acento'] ?? '#ffc93c') ?>">
            </div>
            <div class="col-12">
                <label class="form-label small fw-semibold">Logo de la copa o liga (opcional)</label>
                <input type="file" name="logo" class="form-control" accept=".png,.jpg,.jpeg,.webp">
            </div>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn btn-degradado rounded-pill px-4">Guardar</button>
            <a href="<?= url('admin/torneos.php') ?>" class="btn btn-outline-secondary rounded-pill px-4">Cancelar</a>
        </div>
    </form>

<?php else: ?>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4">
        <div>
            <h3 class="mb-0">Mis Copas y Ligas (<?= count($torneos) ?>)</h3>
            <?php if ($limiteTorneos !== null): ?>
            <p class="text-muted small mb-0 mt-1">
                <i class="bi bi-ticket-perforated me-1"></i>Usas <?= $torneosCreados ?> de <?= $limiteTorneos ?> <?= $limiteTorneos === 1 ? 'copa o liga autorizada' : 'copas o ligas autorizadas' ?>.
            </p>
            <?php endif; ?>
        </div>
        <?php if ($puedeCrearTorneo): ?>
        <a href="<?= url('admin/torneos.php?accion=nuevo') ?>" class="btn btn-degradado rounded-pill px-3"><i class="bi bi-plus-lg me-1"></i>Nueva copa o liga</a>
        <?php else: ?>
        <button type="button" class="btn btn-degradado rounded-pill px-3 disabled" disabled title="<?= e(mensaje_limite_torneos($limiteTorneos)) ?>"><i class="bi bi-lock me-1"></i>Nueva copa o liga</button>
        <?php endif; ?>
    </div>

    <?php if (!$puedeCrearTorneo): ?>
    <div class="alert alert-warning rounded-4 border-0 shadow-sm d-flex align-items-start gap-2">
        <i class="bi bi-exclamation-triangle-fill mt-1"></i>
        <div>
            <div class="fw-semibold">Sin cupo disponible</div>
            <div class="small"><?= e(mensaje_limite_torneos($limiteTorneos)) ?></div>
        </div>
    </div>
    <?php endif; ?>

    <div class="row row-cols-1 row-cols-md-2 row-cols-xl-3 g-3">
        <?php foreach ($torneos as $t): ?>
        <div class="col">
            <div class="card-suave p-3 h-100 d-flex flex-column <?= ($_SESSION['torneo_activo_id'] ?? null) === $t['id'] ? 'border border-2' : '' ?>" style="<?= ($_SESSION['torneo_activo_id'] ?? null) === $t['id'] ? 'border-color:var(--color-primario) !important;' : '' ?>">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="badge rounded-pill text-bg-light border small"><?= $t['deporte'] === 'futbol' ? '⚽ Fútbol' : '🏀 Basketball' ?></span>
                    <?php if (($t['genero'] ?? 'mixto') !== 'mixto'): ?><span class="badge rounded-pill text-bg-light border small"><?= $t['genero'] === 'femenino' ? 'Femenino' : 'Masculino' ?></span><?php endif; ?>
                    <?php if (!$t['activo']): ?><span class="badge rounded-pill text-bg-secondary small">Inactiva</span><?php endif; ?>
                    <?php if ($t['es_predeterminado']): ?><span class="badge rounded-pill text-bg-warning small">Predeterminada</span><?php endif; ?>
                </div>
                <div class="fw-semibold mb-1"><?= e($t['nombre']) ?></div>
                <?php // min-width:0 en el <code> es lo que permite que la URL larga se
                      // trunque con "..." dentro de la tarjeta: sin él, un flex item no
                      // encoge por debajo de su contenido y la URL empujaba el ancho de
                      // toda la página en el teléfono (se veía cortada y con scroll lateral). ?>
                <?php // Los estilos de truncado van INLINE a propósito: son la garantía de que
                      // la URL larga jamás ensanche la tarjeta en el teléfono, aunque el
                      // navegador tenga una hoja de estilos vieja en caché. ?>
                <div class="d-flex align-items-center gap-1 mb-1" style="min-width:0;max-width:100%;">
                    <code class="small flex-grow-1" style="display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= e(url_copa_de($t)) ?></code>
                    <button type="button" class="btn btn-sm btn-link p-0 ms-1 flex-shrink-0 btn-copiar-url" data-url="<?= e(url_copa_de($t)) ?>" title="Copiar enlace"><i class="bi bi-clipboard"></i></button>
                </div>
                <div class="d-flex align-items-center gap-1 mb-3">
                    <span class="small text-muted">Código:</span>
                    <code class="small fw-bold"><?= e($t['codigo']) ?></code>
                    <button type="button" class="btn btn-sm btn-link p-0 ms-1 btn-copiar-url" data-url="<?= e($t['codigo']) ?>" title="Copiar código"><i class="bi bi-clipboard"></i></button>
                    <form method="post" data-confirm="¿Generar un código nuevo para \"<?= e($t['nombre']) ?>\"? El código anterior dejará de funcionar." class="d-inline">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="regenerar_codigo">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-link p-0 ms-1 text-muted" title="Generar código nuevo"><i class="bi bi-arrow-repeat"></i></button>
                    </form>
                </div>
                <div class="d-flex gap-2 mt-auto flex-wrap">
                    <a href="<?= url('admin/torneos.php?accion=entrar&id=' . $t['id']) ?>" class="btn btn-sm btn-degradado rounded-pill flex-grow-1">Entrar</a>
                    <a href="<?= e(url_copa_de($t)) ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="bi bi-box-arrow-up-right"></i></a>
                    <a href="<?= url('admin/torneos.php?accion=editar&id=' . $t['id']) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil"></i></a>
                    <?php if (!$t['es_predeterminado']): ?>
                    <form method="post" data-confirm="¿Eliminar \"<?= e($t['nombre']) ?>\"? Se borrarán todos sus equipos, partidos y patrocinadores.">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="accion" value="eliminar">
                        <input type="hidden" name="id" value="<?= $t['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

<?php endif; ?>

<?php require __DIR__ . '/includes/admin_layout_bottom.php'; ?>
