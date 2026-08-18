<?php
declare(strict_types=1);


// La copa que resolvió el front controller a partir del slug de la URL.
$torneo = copa_actual();
$id = (int) ($_GET['id'] ?? 0);
$partidos = partidos_listar($torneo['id']);
$partido = db_buscar_por_id($partidos, $id);

$equipos = equipos_listar($torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) {
    $equiposPorId[$eq['id']] = $eq;
}

$local = $partido ? ($equiposPorId[$partido['equipo_local']] ?? null) : null;
$visit = $partido ? ($equiposPorId[$partido['equipo_visitante']] ?? null) : null;

// La ficha de partido está disponible para cualquier copa o liga; si el partido o
// alguno de los equipos no existe (o no pertenece a esta copa), es un 404.
if (!$partido || !$local || !$visit) {
    vista_404_copa('Partido no encontrado', url_copa('calendario.php'), 'Volver al calendario');
}

$jugado = $partido['estado'] === 'jugado';
$deporte = $torneo['deporte'] ?? null;
$basketball = es_basketball($deporte);

$jugadoresTodos = jugadores_listar($torneo['id']);
$jugadoresPorId = jugadores_por_id($jugadoresTodos);

// Alineación del encuentro (titulares y banca de cada equipo, con la posición de cada uno)
$alineacion = db_leer_alineacion($torneo['id'], $id);

$eventos = db_leer_eventos_partido($torneo['id'], $id);
$goles = array_values(array_filter($eventos, fn($e) => $e['tipo'] === 'gol'));
$amarillas = array_values(array_filter($eventos, fn($e) => $e['tipo'] === 'amarilla'));
$rojas = array_values(array_filter($eventos, fn($e) => $e['tipo'] === 'roja'));
$cambios = array_values(array_filter($eventos, fn($e) => $e['tipo'] === 'cambio'));

// El admin puede ir cargando goles/tarjetas/cambios desde antes de marcar el partido como
// "jugado" (se captura el marcador al final). La ficha/descarga debe estar disponible en
// cuanto haya algo que mostrar, no solo cuando el estado ya es "jugado" — si no, el botón
// "Descargar PDF" de la pantalla de Eventos manda a una página vacía sin nada para imprimir.
$hayFicha = $jugado || !empty($eventos) || !empty($partido['observaciones']) || !empty($alineacion);

// Para dejar constancia de quién descargó la ficha (control interno): solo aplica si
// quien la pide tiene sesión de organizador iniciada; un visitante público no queda registrado.
$usuarioImprime = null;
if (!empty($_SESSION['usuario_autenticado']) && !empty($_SESSION['usuario_id'])) {
    $usuarioImprime = usuarios_obtener_por_id((int) $_SESSION['usuario_id']);
}

// Para la ficha imprimible (ver .solo-impresion más abajo): "-" cuando el dato no aplica,
// para que se vea como un formulario lleno a mano, no como una página web recortada.
function ficha_valor(?string $valor): string
{
    $valor = trim((string) $valor);
    return $valor === '' ? '—' : e($valor);
}

$titulo_pagina = $local['nombre'] . ' vs ' . $visit['nombre'] . ' — ' . $torneo['nombre'];
$pagina_activa = 'calendario';

vista_publica('publico/partido', compact(
    'alineacion',
    'amarillas',
    'basketball',
    'cambios',
    'deporte',
    'equiposPorId',
    'eventos',
    'goles',
    'hayFicha',
    'id',
    'jugado',
    'jugadoresPorId',
    'local',
    'pagina_activa',
    'partido',
    'rojas',
    'titulo_pagina',
    'torneo',
    'usuarioImprime',
    'visit'
));
