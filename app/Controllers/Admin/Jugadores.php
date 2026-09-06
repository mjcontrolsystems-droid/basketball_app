<?php
declare(strict_types=1);

auth_requerir();
$torneo = admin_requerir_torneo_activo();
requerir_permiso('jugadores');

$equipoId = (int) ($_GET['equipo_id'] ?? $_POST['equipo_id'] ?? 0);
$equipos = equipos_listar($torneo['id']);
$equipo = db_buscar_por_id($equipos, $equipoId);
if ($equipo === null) {
    http_response_code(404);
    exit('Equipo no encontrado.');
}

// Un capitán solo entra a la plantilla de su equipo. Va aquí arriba, antes de procesar
// cualquier POST: esconder el enlace no sirve de nada si la URL con otro equipo_id sigue
// respondiendo a quien la escriba a mano.
requerir_equipo_propio($equipoId, $torneo);

$jugadoresTodos = jugadores_listar($torneo['id']);
$jugadores = array_values(array_filter($jugadoresTodos, fn($j) => (int) $j['equipo_id'] === $equipoId));

$accion = $_GET['accion'] ?? 'lista';
$idEditar = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$jugadorEditar = $idEditar ? db_buscar_por_id($jugadores, $idEditar) : null;

// --- Inscripciones cerradas ---
//
// Al capitán se le congela la plantilla; al organizador nunca. El corte se hace en el
// servidor y no escondiendo botones: una vez que alguien tuvo acceso a esta pantalla,
// conoce las URLs de memoria.
$plantillaCongelada = es_capitan($torneo) && !torneo_inscripciones_abiertas($torneo);
if ($plantillaCongelada) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        redirigir_con_mensaje(
            url('admin/jugadores.php?equipo_id=' . $equipoId),
            'error',
            'El registro de jugadores está cerrado. Si necesitas un cambio en tu plantilla, pídeselo a quien organiza la liga.'
        );
    }
    if ($accion !== 'lista') {
        $accion = 'lista';
    }
}

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

        // Las filas quedan en la sesión para poder RE-LEER el archivo con otras columnas
        // si la detección se equivocó, sin obligar a subirlo de nuevo. Sin esto, corregir
        // una columna mal elegida significaría reescribir a mano los doce nombres.
        $_SESSION['importacion_jugadores'] = [
            'equipo_id' => $equipoId,
            'archivo' => (string) $_FILES['archivo']['name'],
            'filas' => $filas,
        ];

        $previaImport = importacion_armar_previa($filas, $deteccion['fila_encabezado'], $deteccion['mapa'], $jugadores, (string) $_FILES['archivo']['name'], $deteccion['motivos']);
        $accion = 'importar';
    }

    // Re-leer el mismo archivo con las columnas que eligió el organizador.
    if (($_POST['accion'] ?? '') === 'importar_remapear') {
        $guardado = $_SESSION['importacion_jugadores'] ?? null;
        if (!is_array($guardado) || (int) ($guardado['equipo_id'] ?? 0) !== $equipoId) {
            redirigir_con_mensaje($urlLista, 'error', 'Se perdió el archivo que estabas importando. Vuelve a subirlo.');
        }

        $mapa = [];
        foreach (['nombre', 'apellido', 'dorsal', 'posicion'] as $campo) {
            $valor = $_POST['col_' . $campo] ?? '';
            // Cadena vacía = "no usar esta columna". El 0 es una columna válida, así que
            // se compara contra '' y no con empty().
            $mapa[$campo] = $valor === '' ? null : (int) $valor;
        }
        // -1 significa "el archivo no trae encabezado": los datos empiezan en la fila 1.
        $filaEncabezado = max(-1, (int) ($_POST['fila_encabezado'] ?? 0));

        $previaImport = importacion_armar_previa($guardado['filas'], $filaEncabezado, $mapa, $jugadores, (string) $guardado['archivo'], ['Columnas elegidas por ti.']);
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
        // El id se pide UNA vez y se incrementa en memoria: jugador_nuevo_id() hace
        // "SELECT MAX(id)+1" y dentro del bucle devolvería el mismo número para todos,
        // porque hasta el final no se guarda nada.
        $siguienteId = jugador_nuevo_id();

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
                'id' => $siguienteId++,
                'equipo_id' => $equipoId,
                'dorsal' => mb_substr($dorsal, 0, 3),
                'nombre' => mb_substr($nombreFila, 0, 120),
                'posicion' => (string) ($posiciones[$i] ?? ''),
                'activo' => true,
                // Explícita aunque venga vacía: la columna es NOT NULL y el guardado
                // escribe todas las columnas, así que omitirla intentaría meter NULL.
                'foto' => '',
            ];
            $creados++;
        }

        if ($creados === 0) {
            redirigir_con_mensaje($urlLista, 'error', 'No se marcó ningún ' . mb_strtolower($etJugador) . ' para importar.');
        }

        jugadores_guardar_todos($jugadoresTodos, $torneo['id']);
        // El archivo ya cumplió: no tiene por qué seguir ocupando la sesión.
        unset($_SESSION['importacion_jugadores']);
        bitacora_registrar('jugadores_importados', "{$creados} " . mb_strtolower($etJugadores) . " importados a {$equipo['nombre']}", $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', "Se importaron {$creados} " . mb_strtolower($etJugadores) . " a {$equipo['nombre']}.");
    }

    if (($_POST['accion'] ?? '') === 'eliminar') {
        $id = (int) $_POST['id'];

        // El id tiene que ser de ESTA plantilla. Sin esta comprobación, mandar el id de un
        // jugador de otro equipo junto con el equipo_id propio lo borraba igual, porque la
        // búsqueda era sobre la lista completa de la copa.
        if (db_buscar_por_id($jugadores, $id) === null) {
            redirigir_con_mensaje($urlLista, 'error', 'Ese ' . mb_strtolower($etJugador) . ' no es de este equipo.');
        }

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

        // La foto se borra con el jugador: si no, la imagen queda huérfana en la base
        // ocupando espacio para siempre (Neon en plan gratis tiene 0.5 GB).
        $aEliminar = db_buscar_por_id($jugadores, $id);
        $jugadoresTodos = array_values(array_filter($jugadoresTodos, fn($j) => $j['id'] !== $id));
        jugadores_guardar_todos($jugadoresTodos, $torneo['id']);
        eliminar_imagen(!empty($aEliminar['foto']) ? (string) $aEliminar['foto'] : null);
        redirigir_con_mensaje($urlLista, 'success', forma_genero($torneo['genero'] ?? null, 'Jugador eliminado.', 'Jugadora eliminada.'));
    }

    if (($_POST['accion'] ?? '') === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $dorsal = trim((string) $_POST['dorsal']);
        $nombre = trim((string) $_POST['nombre']);
        $activo = isset($_POST['activo']);

        $etJugadorMin = mb_strtolower($etJugador);

        // Al editar, el jugador tiene que ser de esta plantilla. Si no, guardar con el id
        // de otro equipo lo MOVERÍA a este (los datos llevan equipo_id): un capitán podría
        // quedarse con el goleador del rival mandando su id en el formulario.
        if ($id > 0 && db_buscar_por_id($jugadores, $id) === null) {
            redirigir_con_mensaje($urlLista, 'error', "Ese {$etJugadorMin} no es de este equipo.");
        }

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

        // Foto del jugador. Mismo camino que el escudo del equipo: la imagen se guarda en
        // la base (no en el disco, que Render borra en cada despliegue) y aquí solo queda
        // su referencia.
        try {
            $fotoSubida = manejar_subida_imagen('foto', 'jugadores', FOTO_JUGADOR_LADO_MAXIMO);
        } catch (RuntimeException $e) {
            redirigir_con_mensaje($urlLista, 'error', $e->getMessage());
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
                    $datos['foto'] = resolver_archivo_guardado($fotoSubida, (string) ($j['foto'] ?? ''), !empty($_POST['quitar_foto']));
                    $j = array_merge($j, $datos, ['id' => $id]);
                }
            }
            unset($j);
            $mensaje = forma_genero($torneo['genero'] ?? null, 'Jugador actualizado correctamente.', 'Jugadora actualizada correctamente.');
        } else {
            $datos['id'] = jugador_nuevo_id();
            $datos['foto'] = $fotoSubida ?? '';
            $jugadoresTodos[] = $datos;
            $mensaje = forma_genero($torneo['genero'] ?? null, 'Jugador agregado correctamente.', 'Jugadora agregada correctamente.');
        }

        jugadores_guardar_todos($jugadoresTodos, $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', $mensaje);
    }
}

// Para el capitán, "Mi plantilla" es una entrada propia del menú y no una subpantalla de
// Equipos: si las dos comparten sección, las dos quedan resaltadas a la vez.
$seccion_activa = es_capitan($torneo) ? 'plantilla' : 'equipos';
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
    'plantillaCongelada',
    'previaImport',
    'seccion_activa',
    'titulo_pagina',
    'torneo',
    'urlLista'
));
