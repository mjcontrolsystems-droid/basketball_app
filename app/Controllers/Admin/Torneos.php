<?php
declare(strict_types=1);

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
    // Vueltas de la temporada regular: solo ida (1) o ida y vuelta (2).
    $vueltas = ((int) ($_POST['vueltas'] ?? 1)) === 2 ? 2 : 1;

    // Formato de la competencia: una LIGA solo lleva el control de puntos (tabla de
    // posiciones), así que se guarda sin ninguna fase de eliminación directa aunque el
    // formulario venga con casillas marcadas de cuando era campeonato.
    $modoEnviado = (string) ($_POST['modo'] ?? FORMATO_CAMPEONATO);
    $modo = array_key_exists($modoEnviado, FORMATOS_TORNEO_LABEL) ? $modoEnviado : FORMATO_CAMPEONATO;
    $fasesElegidas = $modo === FORMATO_LIGA
        ? []
        : array_values(array_intersect((array) ($_POST['fases_playoff'] ?? []), FASES_PLAYOFF_CATALOGO));

    // Los grupos solo tienen sentido en el formato de grupos: si la competencia es liga o
    // liga con fase final se guardan en 0 aunque el formulario traiga valores de antes.
    $numGrupos = $modo === FORMATO_GRUPOS ? max(2, min(26, (int) ($_POST['num_grupos'] ?? 4))) : 0;
    $clasificanPorGrupo = $modo === FORMATO_GRUPOS ? max(1, min(8, (int) ($_POST['clasifican_por_grupo'] ?? 2))) : 2;

    // No se bloquea el guardado: el organizador puede estar armando la copa por partes y
    // todavía no tener claro cuántos pasan. Se avisa y ya.
    $avisoCuadro = $modo === FORMATO_GRUPOS ? grupos_aviso_cuadro($numGrupos, $clasificanPorGrupo) : '';

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
            $reglamentoSubido = manejar_subida_pdf('reglamento');
        } catch (RuntimeException $e) {
            redirigir_con_mensaje(url('admin/torneos.php' . ($id ? "?accion=editar&id={$id}" : '?accion=nuevo')), 'error', $e->getMessage());
        }

        // Reglamento y logo: se sube uno nuevo, se conserva el actual, o se quita con su
        // casilla. Al reemplazarlo o quitarlo se borra el archivo viejo de la base.
        $reglamentoActual = (string) ($torneoEditar['reglamento'] ?? '');
        $reglamentoFinal = resolver_archivo_guardado($reglamentoSubido, $reglamentoActual, !empty($_POST['quitar_reglamento']));
        $reglamentoNombreFinal = match (true) {
            $reglamentoFinal === '' => '',
            $reglamentoSubido !== null => mb_substr(basename((string) ($_FILES['reglamento']['name'] ?? 'reglamento.pdf')), 0, 120),
            default => (string) ($torneoEditar['reglamento_nombre'] ?? ''),
        };

        $logoFinal = resolver_archivo_guardado($logoSubido, (string) ($torneoEditar['logo'] ?? ''), !empty($_POST['quitar_logo']));

        $datos = [
            'id' => $id ?: null,
            'slug' => $slug,
            'nombre' => $nombre,
            'subtitulo' => trim((string) $_POST['subtitulo']),
            'temporada' => trim((string) $_POST['temporada']),
            'descripcion' => trim((string) $_POST['descripcion']),
            'sede_principal' => trim((string) $_POST['sede_principal']),
            'logo' => $logoFinal,
            'reglamento' => $reglamentoFinal,
            'reglamento_nombre' => $reglamentoNombreFinal,
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
            'modo' => $modo,
            'vueltas' => $vueltas,
            'modalidad' => $modalidad,
            'duracion_periodo_min' => $duracionPeriodo,
            'num_equipos' => max(2, (int) $_POST['num_equipos']),
            'fases_playoff' => $fasesElegidas,
            'num_grupos' => $numGrupos,
            'clasifican_por_grupo' => $clasificanPorGrupo,
            // El podio se publica desde el dashboard, no desde este formulario: se
            // conserva tal cual estaba para que editar la copa no lo baje sin querer.
            'podio_publicado' => !empty($torneoEditar['podio_publicado']),
            'permite_empates' => isset($_POST['permite_empates']),
            'puntos_victoria' => (int) $_POST['puntos_victoria'],
            'puntos_empate' => (int) $_POST['puntos_empate'],
            'puntos_derrota' => (int) $_POST['puntos_derrota'],
            // Disciplina: tarifas por tarjeta y reglas de bloqueo. Se aceptan decimales
            // (hay ligas que cobran 12.50) y nunca negativos.
            'multa_amarilla' => max(0, (float) ($_POST['multa_amarilla'] ?? 0)),
            'multa_roja' => max(0, (float) ($_POST['multa_roja'] ?? 0)),
            'sancion_bloquea' => isset($_POST['sancion_bloquea']),
            'partidos_suspension_roja' => max(0, min(10, (int) ($_POST['partidos_suspension_roja'] ?? 0))),
            'amarillas_para_suspension' => max(0, min(20, (int) ($_POST['amarillas_para_suspension'] ?? 0))),
            'partidos_suspension_amarillas' => max(0, min(10, (int) ($_POST['partidos_suspension_amarillas'] ?? 1))),
            'moneda' => trim((string) ($_POST['moneda'] ?? 'Q')) !== '' ? mb_substr(trim((string) $_POST['moneda']), 0, 4) : 'Q',
            'es_predeterminado' => !empty($torneoEditar['es_predeterminado']),
            'activo' => true,
        ];

        $idGuardado = torneos_guardar($datos, $usuarioId);
        bitacora_registrar($id ? 'torneo_editado' : 'torneo_creado', '"' . $nombre . '" (' . $deporte . ')', $idGuardado);

        if (empty($_SESSION['torneo_activo_id'])) {
            $_SESSION['torneo_activo_id'] = $idGuardado;
        }

        $mensaje = $id ? 'Copa o liga actualizada correctamente.' : '¡Copa o liga creada! Ya puedes cargar sus equipos y encuentros.';
        if (!empty($avisoCuadro)) {
            redirigir_con_mensaje(url('admin/torneos.php'), 'error', $mensaje . ' Ojo: ' . $avisoCuadro);
        }
        redirigir_con_mensaje(url('admin/torneos.php'), 'success', $mensaje);
    } else {
        $torneoEditar = array_merge($_POST, ['id' => $id, 'fases_playoff' => $fasesElegidas, 'modo' => $modo, 'vueltas' => $vueltas]);
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
// Las copas creadas antes de que existiera el selector de formato quedan como campeonato
// (mismo comportamiento que tenían), no como liga.
$modoPorDefecto = ($torneoEditar['modo'] ?? FORMATO_CAMPEONATO) === FORMATO_LIGA ? FORMATO_LIGA : FORMATO_CAMPEONATO;
$vueltasPorDefecto = torneo_vueltas($torneoEditar ?? []);
$torneos = torneos_listar(false, $usuarioId);

$seccion_activa = 'torneos';
$titulo_pagina = 'Mis Copas y Ligas';

vista_admin('admin/torneos', compact(
    'accion',
    'deportePorDefecto',
    'errores',
    'generoPorDefecto',
    'limiteTorneos',
    'modoPorDefecto',
    'puedeCrearTorneo',
    'seccion_activa',
    'titulo_pagina',
    'torneoEditar',
    'torneos',
    'torneosCreados',
    'vueltasPorDefecto'
));
