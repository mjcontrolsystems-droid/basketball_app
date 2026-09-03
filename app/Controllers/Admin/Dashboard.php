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

// --- Dashboard del capitán ---
//
// Al capitán no le sirve el panel de la copa: no puede tocar casi nada de lo que hay ahí.
// Lo que necesita al entrar son tres cosas, que son justo las que hoy pregunta por
// WhatsApp: cuándo y contra quién juega, quién no puede jugar (suspendido o debiendo), y
// dónde imprime su nómina. Se le pinta su propia pantalla y se corta aquí.
$equipoCapitan = equipo_del_capitan($torneo);
if ($equipoCapitan !== null && isset($equiposPorId[$equipoCapitan])) {
    $miEquipo = $equiposPorId[$equipoCapitan];

    $jugadoresTodos = jugadores_listar($torneo['id']);
    $jugadoresPorId = jugadores_por_id($jugadoresTodos);
    $miPlantilla = array_values(array_filter($jugadoresTodos, fn($j) => (int) $j['equipo_id'] === $equipoCapitan));
    usort($miPlantilla, fn($a, $b) => (int) $a['dorsal'] <=> (int) $b['dorsal']);
    $misActivos = array_values(array_filter($miPlantilla, fn($j) => !empty($j['activo'])));

    $misPartidos = array_values(array_filter(
        $partidos,
        fn($p) => (int) $p['equipo_local'] === $equipoCapitan || (int) $p['equipo_visitante'] === $equipoCapitan
    ));
    usort($misPartidos, fn($a, $b) => strcmp((string) $a['fecha'] . $a['hora'], (string) $b['fecha'] . $b['hora']));

    $proximoMio = null;
    foreach ($misPartidos as $p) {
        if (($p['estado'] ?? '') !== 'jugado') {
            $proximoMio = $p;
            break;
        }
    }
    $ultimosMios = array_values(array_filter($misPartidos, fn($p) => ($p['estado'] ?? '') === 'jugado'));
    $ultimosMios = array_slice(array_reverse($ultimosMios), 0, 3);

    // Quién no puede entrar a la cancha el próximo partido. Es exactamente el mismo
    // cálculo que hace la nómina del árbitro, para que las dos digan lo mismo.
    $misSuspendidos = [];
    if ($proximoMio !== null && torneo_aplica_suspensiones($torneo)) {
        $misSuspendidos = disciplina_suspendidos_para_partido($torneo['id'], $proximoMio, $torneo, $partidos, $jugadoresPorId);
    }
    $misDeudores = [];
    if (torneo_cobra_multas($torneo)) {
        $deudaVigente = $proximoMio !== null
            ? sanciones_deuda_vigente_para_jornada($torneo['id'], $partidos, (int) ($proximoMio['jornada'] ?? 0))
            : sanciones_deuda_por_jugador($torneo['id']);
        // Solo los suyos: la deuda del resto de la liga no es asunto del capitán.
        $mios = array_flip(array_map(fn($j) => (int) $j['id'], $miPlantilla));
        $misDeudores = array_intersect_key($deudaVigente, $mios);
    }

    $miFila = null;
    foreach ($tabla as $fila) {
        if ((int) $fila['equipo']['id'] === $equipoCapitan) {
            $miFila = $fila;
            break;
        }
    }

    // Lo que su equipo le debe a la liga. De solo lectura: el capitán consulta, el
    // organizador cobra. Es la otra pregunta que llega por WhatsApp cada semana.
    $multasAlEquipo = torneo_multas_al_equipo($torneo) && torneo_cobra_multas($torneo);
    $miCuenta = null;
    $misMovimientos = [];
    if (torneo_lleva_cuentas($torneo) || $multasAlEquipo) {
        $movimientosCopa = movimientos_listar($torneo['id']);
        $deudaJugadores = $multasAlEquipo ? sanciones_deuda_por_jugador($torneo['id']) : [];
        foreach (cuentas_saldos($equipos, $movimientosCopa, $deudaJugadores, $jugadoresTodos, $multasAlEquipo) as $fila) {
            if ((int) $fila['equipo']['id'] === $equipoCapitan) {
                $miCuenta = $fila;
            }
        }
        $misMovimientos = array_slice(
            array_values(array_filter($movimientosCopa, fn($m) => (int) $m['equipo_id'] === $equipoCapitan)),
            0,
            8
        );
    }

    $titulo_pagina = $miEquipo['nombre'];

    vista_admin('admin/dashboard_capitan', compact(
        'equiposPorId',
        'jugadoresPorId',
        'miCuenta',
        'miEquipo',
        'miFila',
        'misMovimientos',
        'miPlantilla',
        'misActivos',
        'misDeudores',
        'misPartidos',
        'misSuspendidos',
        'proximoMio',
        'seccion_activa',
        'titulo_pagina',
        'torneo',
        'ultimosMios'
    ));
    return;
}

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
