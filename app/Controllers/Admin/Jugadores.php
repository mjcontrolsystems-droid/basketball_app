<?php
declare(strict_types=1);

auth_requerir();
$torneo = admin_requerir_torneo_activo();

$equipoId = (int) ($_GET['equipo_id'] ?? $_POST['equipo_id'] ?? 0);
$equipos = equipos_listar($torneo['id']);
$equipo = db_buscar_por_id($equipos, $equipoId);
if ($equipo === null) {
    http_response_code(404);
    exit('Equipo no encontrado.');
}

$jugadoresTodos = jugadores_listar($torneo['id']);
$jugadores = array_values(array_filter($jugadoresTodos, fn($j) => (int) $j['equipo_id'] === $equipoId));

$accion = $_GET['accion'] ?? 'lista';
$idEditar = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$jugadorEditar = $idEditar ? db_buscar_por_id($jugadores, $idEditar) : null;

$urlLista = url('admin/jugadores.php?equipo_id=' . $equipoId);
$etJugador = forma_genero($torneo['genero'] ?? null, 'Jugador', 'Jugadora');
$etJugadores = forma_genero($torneo['genero'] ?? null, 'Jugadores', 'Jugadoras');
$etActivo = forma_genero($torneo['genero'] ?? null, 'Activo', 'Activa');
$etInactivo = forma_genero($torneo['genero'] ?? null, 'Inactivo', 'Inactiva');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (($_POST['accion'] ?? '') === 'eliminar') {
        $id = (int) $_POST['id'];

        $eventos = eventos_de_torneo($torneo['id']);
        $referenciado = false;
        foreach ($eventos as $ev) {
            if ((int) ($ev['jugador_id'] ?? 0) === $id || (int) ($ev['jugador_entra_id'] ?? 0) === $id || (int) ($ev['asistencia_jugador_id'] ?? 0) === $id) {
                $referenciado = true;
                break;
            }
        }

        if ($referenciado) {
            $etJugadorRef = forma_genero($torneo['genero'] ?? null, 'jugador', 'jugadora');
            redirigir_con_mensaje($urlLista, 'error', "Este {$etJugadorRef} ya aparece en la ficha de algún partido y no se puede eliminar. Puedes desactivarlo en su lugar.");
        }

        $jugadoresTodos = array_values(array_filter($jugadoresTodos, fn($j) => $j['id'] !== $id));
        jugadores_guardar_todos($jugadoresTodos, $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', forma_genero($torneo['genero'] ?? null, 'Jugador eliminado.', 'Jugadora eliminada.'));
    }

    if (($_POST['accion'] ?? '') === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $dorsal = trim((string) $_POST['dorsal']);
        $nombre = trim((string) $_POST['nombre']);
        $activo = isset($_POST['activo']);

        $etJugadorMin = mb_strtolower($etJugador);

        if ($nombre === '') {
            redirigir_con_mensaje($urlLista, 'error', "El nombre del {$etJugadorMin} es obligatorio.");
        }
        if ($dorsal === '') {
            redirigir_con_mensaje($urlLista, 'error', 'El dorsal es obligatorio.');
        }

        foreach ($jugadores as $j) {
            if ($j['dorsal'] === $dorsal && $j['id'] !== $id) {
                redirigir_con_mensaje($urlLista, 'error', "Ya existe un {$etJugadorMin} con el dorsal \"{$dorsal}\" en este equipo.");
            }
        }

        // Posición habitual: es solo el valor SUGERIDO que se propone al armar la
        // alineación de cada encuentro (ahí se puede cambiar partido por partido).
        $posicion = (string) ($_POST['posicion'] ?? '');
        if (!isset(posiciones_catalogo($torneo['deporte'] ?? null)[$posicion])) {
            $posicion = '';
        }

        $datos = [
            'equipo_id' => $equipoId,
            'dorsal' => $dorsal,
            'nombre' => $nombre,
            'activo' => $activo,
            'posicion' => $posicion,
        ];

        if ($id > 0) {
            foreach ($jugadoresTodos as &$j) {
                if ($j['id'] === $id) {
                    $j = array_merge($j, $datos, ['id' => $id]);
                }
            }
            unset($j);
            $mensaje = forma_genero($torneo['genero'] ?? null, 'Jugador actualizado correctamente.', 'Jugadora actualizada correctamente.');
        } else {
            $datos['id'] = jugador_nuevo_id();
            $jugadoresTodos[] = $datos;
            $mensaje = forma_genero($torneo['genero'] ?? null, 'Jugador agregado correctamente.', 'Jugadora agregada correctamente.');
        }

        jugadores_guardar_todos($jugadoresTodos, $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', $mensaje);
    }
}

$seccion_activa = 'equipos';
$titulo_pagina = $etJugadores . ' · ' . $equipo['nombre'];

vista_admin('admin/jugadores', compact(
    'accion',
    'equipo',
    'equipoId',
    'etActivo',
    'etInactivo',
    'etJugador',
    'etJugadores',
    'jugadorEditar',
    'jugadores',
    'seccion_activa',
    'titulo_pagina',
    'torneo',
    'urlLista'
));
