<?php
declare(strict_types=1);

auth_requerir();
$torneo = admin_requerir_torneo_activo();

$seccion_activa = 'dashboard';
$titulo_pagina = 'Dashboard';

$equipos = equipos_listar($torneo['id']);
$partidos = partidos_listar($torneo['id']);

// Publicar o bajar el podio de cierre. El podio en sí no se guarda: se recalcula en vivo,
// así que corregir el marcador de la final cambia al campeón sin tener que republicar.
// Abrir o cerrar el sitio público. Sirve para reacomodar el calendario sin que la gente
// vea a medias los cambios — publicar un calendario y estarlo corrigiendo en vivo genera
// más reclamos que tenerlo cerrado un rato.
// Cerrar el sitio y publicar el podio son decisiones de quien organiza, no de quien
// ayuda: una apaga la copa para todo el público y la otra la da por terminada.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_POST['accion'] ?? '', ['mantenimiento', 'publicar_podio', 'aviso'], true)) {
    requerir_permiso('configuracion');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'mantenimiento') {
    csrf_validar();
    $cerrar = !empty($_POST['cerrar']);
    $mensaje = trim((string) ($_POST['mensaje_mantenimiento'] ?? ''));

    torneos_guardar(array_merge($torneo, [
        'en_mantenimiento' => $cerrar,
        'mensaje_mantenimiento' => mb_substr($mensaje, 0, 300),
    ]));
    bitacora_registrar($cerrar ? 'sitio_cerrado' : 'sitio_abierto', $cerrar ? 'Sitio público puesto en mantenimiento' : 'Sitio público reabierto', $torneo['id']);
    redirigir_con_mensaje(
        url('admin/index.php'),
        'success',
        $cerrar
            ? 'Sitio público cerrado. Los visitantes ven el aviso de mantenimiento; tú puedes seguir entrando con tu sesión.'
            : '¡Sitio público reabierto! Ya se puede ver de nuevo.'
    );
}

// Aviso al público: condolencias, cumpleaños, un recordatorio. Se publica y se quita
// desde aquí; el sitio lo muestra como mensaje emergente una vez por visita.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'aviso') {
    csrf_validar();
    $activar = !empty($_POST['activar']);
    $tipo = (string) ($_POST['aviso_tipo'] ?? 'informativo');
    if (!in_array($tipo, ['luto', 'celebracion', 'informativo'], true)) {
        $tipo = 'informativo';
    }
    $titulo = mb_substr(trim((string) ($_POST['aviso_titulo'] ?? '')), 0, 120);
    $mensaje = mb_substr(trim((string) ($_POST['aviso_mensaje'] ?? '')), 0, 600);

    if ($activar && ($titulo === '' || $mensaje === '')) {
        redirigir_con_mensaje(url('admin/index.php'), 'error', 'El aviso necesita un título y un mensaje.');
    }

    torneos_guardar(array_merge($torneo, [
        'aviso_activo' => $activar,
        'aviso_tipo' => $tipo,
        'aviso_titulo' => $titulo,
        'aviso_mensaje' => $mensaje,
    ]));
    bitacora_registrar($activar ? 'aviso_publicado' : 'aviso_retirado', $activar ? "Aviso al público: {$titulo}" : 'Aviso al público retirado', $torneo['id']);
    redirigir_con_mensaje(
        url('admin/index.php'),
        'success',
        $activar
            ? 'Aviso publicado. Cada visitante lo verá una vez al entrar al sitio.'
            : 'Aviso retirado del sitio.'
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'publicar_podio') {
    csrf_validar();
    $publicar = !empty($_POST['publicar']);

    if ($publicar && podio_calcular($torneo, $equipos, $partidos) === null) {
        redirigir_con_mensaje(url('admin/index.php'), 'error', 'Todavía no se puede determinar el campeón. Revisa que el partido decisivo esté jugado y sin empatar.');
    }

    torneos_guardar(array_merge($torneo, ['podio_publicado' => $publicar]));
    bitacora_registrar($publicar ? 'podio_publicado' : 'podio_ocultado', $publicar ? 'Podio de cierre publicado en el sitio' : 'Podio de cierre retirado del sitio', $torneo['id']);
    redirigir_con_mensaje(
        url('admin/index.php'),
        'success',
        $publicar
            ? '¡Podio publicado! Ya se ve en la portada de la copa.'
            : 'Podio retirado de la portada.'
    );
}
$patrocinadores = patrocinadores_listar($torneo['id']);
$tabla = calcular_tabla($equipos, $partidos, $torneo);
$lider = $tabla[0] ?? null;

$jugados = array_filter($partidos, fn($p) => $p['estado'] === 'jugado');
$programados = array_filter($partidos, fn($p) => $p['estado'] === 'programado');
$proximo = proximos_partidos($partidos, 1)[0] ?? null;

// Los últimos jugados, del más reciente hacia atrás: cada uno enlaza a su ficha de
// eventos para revisar o corregir sin dar vueltas por el menú.
$ultimosJugados = array_values($jugados);
usort($ultimosJugados, fn($a, $b) => strcmp((string) $b['fecha'] . $b['hora'], (string) $a['fecha'] . $a['hora']));
$ultimosJugados = array_slice($ultimosJugados, 0, 5);
$equiposPorId = [];
foreach ($equipos as $eq) { $equiposPorId[$eq['id']] = $eq; }

// Cierre de temporada: el podio que la app detecta y si ya está publicado.
$podio = podio_calcular($torneo, $equipos, $partidos);
$temporadaTerminada = podio_temporada_terminada($partidos);
$podioPublicado = torneo_podio_publicado($torneo);

vista_admin('admin/dashboard', compact(
    'equipos',
    'equiposPorId',
    'jugados',
    'patrocinadores',
    'podio',
    'podioPublicado',
    'programados',
    'proximo',
    'seccion_activa',
    'tabla',
    'temporadaTerminada',
    'titulo_pagina',
    'torneo',
    'ultimosJugados'
));
