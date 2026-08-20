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

// Vista previa de una importación. Solo tiene contenido justo después de subir el archivo.
$previaImport = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    // --- Paso 1 de la importación: leer el archivo y proponer qué se va a crear ---
    // No se guarda nada todavía. El archivo se lee, se detectan las columnas y se arma una
    // tabla editable: el organizador corrige lo que haga falta y recién ahí confirma.
    if (($_POST['accion'] ?? '') === 'importar_previa') {
        if (empty($_FILES['archivo']['tmp_name']) || !is_uploaded_file($_FILES['archivo']['tmp_name'])) {
            redirigir_con_mensaje($urlLista, 'error', 'No se recibió ningún archivo. Puede que pese más de lo que acepta el servidor.');
        }

        try {
            $filas = importacion_leer_archivo($_FILES['archivo']['tmp_name'], (string) $_FILES['archivo']['name']);
        } catch (RuntimeException $e) {
            redirigir_con_mensaje($urlLista, 'error', $e->getMessage());
        }

        if (count($filas) < 2) {
            redirigir_con_mensaje($urlLista, 'error', 'El archivo está vacío o solo tiene el encabezado.');
        }

        $deteccion = importacion_detectar($filas);
        $propuesta = importacion_preparar_jugadores($filas, $deteccion['fila_encabezado'], $deteccion['mapa'], $jugadores);

        if (empty($propuesta['jugadores'])) {
            $detalle = $propuesta['omitidos'] ? ' ' . implode(' ', array_slice($propuesta['omitidos'], 0, 3)) : '';
            redirigir_con_mensaje($urlLista, 'error', 'No se encontró ningún ' . mb_strtolower($etJugador) . ' nuevo en el archivo.' . $detalle);
        }

        $previaImport = [
            'archivo' => (string) $_FILES['archivo']['name'],
            'encabezados' => $deteccion['encabezados'],
            'motivos' => $deteccion['motivos'],
            'jugadores' => $propuesta['jugadores'],
            'omitidos' => $propuesta['omitidos'],
        ];
        $accion = 'importar';
    }

    // --- Paso 2: crear lo que el organizador aprobó ---
    // Se guarda lo que viene del formulario de la vista previa, no lo que decía el archivo:
    // así cualquier corrección que haya hecho ahí es la que manda.
    if (($_POST['accion'] ?? '') === 'importar_confirmar') {
        $nombres = (array) ($_POST['imp_nombre'] ?? []);
        $dorsales = (array) ($_POST['imp_dorsal'] ?? []);
        $posiciones = (array) ($_POST['imp_posicion'] ?? []);
        $incluidos = array_map('intval', (array) ($_POST['imp_incluir'] ?? []));

        $dorsalesTomados = [];
        foreach ($jugadores as $j) {
            $dorsalesTomados[trim((string) ($j['dorsal'] ?? ''))] = true;
        }

        $creados = 0;
        foreach ($nombres as $i => $nombreFila) {
            if (!in_array((int) $i, $incluidos, true)) {
                continue; // el organizador lo desmarcó en la vista previa
            }
            $nombreFila = trim((string) $nombreFila);
            if ($nombreFila === '') {
                continue;
            }
            $dorsal = trim((string) ($dorsales[$i] ?? ''));
            // Última red contra el dorsal repetido: entre la vista previa y el guardado
            // pudo haberse cargado otro jugador desde otra pestaña.
            if ($dorsal !== '' && isset($dorsalesTomados[$dorsal])) {
                $dorsal = '';
            }
            if ($dorsal !== '') {
                $dorsalesTomados[$dorsal] = true;
            }

            $jugadoresTodos[] = [
                'id' => jugador_nuevo_id(),
                'equipo_id' => $equipoId,
                'dorsal' => mb_substr($dorsal, 0, 3),
                'nombre' => mb_substr($nombreFila, 0, 120),
                'posicion' => (string) ($posiciones[$i] ?? ''),
                'activo' => true,
            ];
            $creados++;
        }

        if ($creados === 0) {
            redirigir_con_mensaje($urlLista, 'error', 'No se marcó ningún ' . mb_strtolower($etJugador) . ' para importar.');
        }

        jugadores_guardar_todos($jugadoresTodos, $torneo['id']);
        bitacora_registrar('jugadores_importados', "{$creados} " . mb_strtolower($etJugadores) . " importados a {$equipo['nombre']}", $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', "Se importaron {$creados} " . mb_strtolower($etJugadores) . " a {$equipo['nombre']}.");
    }

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
    'previaImport',
    'seccion_activa',
    'titulo_pagina',
    'torneo',
    'urlLista'
));
