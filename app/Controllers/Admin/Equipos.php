<?php
declare(strict_types=1);

auth_requerir();
$torneo = admin_requerir_torneo_activo();

$equipos = equipos_listar($torneo['id']);
$accion = $_GET['accion'] ?? 'lista';
$idEditar = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$equipoEditar = $idEditar ? db_buscar_por_id($equipos, $idEditar) : null;

// Jugadores mínimos que debe traer un equipo nuevo: los que juegan en cancha según la
// modalidad de la copa (11 en fútbol 11, 7 en fútbol 7, 5 en sala y basketball).
$minimoPlantilla = torneo_jugadores_en_cancha($torneo);
// Al fallar la validación se vuelve al formulario de alta, no a la lista.
$urlFormularioNuevo = url('admin/equipos.php?accion=nuevo');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (($_POST['accion'] ?? '') === 'eliminar') {
        $id = (int) $_POST['id'];
        $equipoAEliminar = db_buscar_por_id($equipos, $id);
        $equipos = array_values(array_filter($equipos, fn($e) => $e['id'] !== $id));
        equipos_guardar_todos($equipos, $torneo['id']);

        // Elimina también los encuentros que involucraban a este equipo, para no dejar referencias huérfanas
        $partidos = partidos_listar($torneo['id']);
        $partidosAEliminar = array_values(array_filter($partidos, fn($p) => (int) $p['equipo_local'] === $id || (int) $p['equipo_visitante'] === $id));
        $partidos = array_values(array_filter($partidos, fn($p) => (int) $p['equipo_local'] !== $id && (int) $p['equipo_visitante'] !== $id));
        partidos_guardar_todos($partidos, $torneo['id']);

        // Limpia también la plantilla del equipo y la ficha (goles/tarjetas/cambios) de los
        // partidos que jugaba, para no dejar referencias huérfanas.
        $jugadores = jugadores_listar($torneo['id']);
        $jugadores = array_values(array_filter($jugadores, fn($j) => (int) $j['equipo_id'] !== $id));
        jugadores_guardar_todos($jugadores, $torneo['id']);

        foreach ($partidosAEliminar as $p) {
            db_guardar_eventos_partido($torneo['id'], (int) $p['id'], []);
        }

        if ($equipoAEliminar) {
            eliminar_imagen($equipoAEliminar['logo'] ?? null);
        }
        redirigir_con_mensaje(url('admin/equipos.php'), 'success', 'Equipo y sus encuentros asociados fueron eliminados.');
    }

    if (($_POST['accion'] ?? '') === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $datos = [
            'nombre' => trim((string) $_POST['nombre']),
            'ciudad' => trim((string) $_POST['ciudad']),
            'sede' => trim((string) $_POST['sede']),
            'entrenador' => trim((string) $_POST['entrenador']),
            'fundacion' => trim((string) $_POST['fundacion']),
            'color_primario' => (string) $_POST['color_primario'],
            'color_secundario' => (string) $_POST['color_secundario'],
            'descripcion' => trim((string) $_POST['descripcion']),
        ];

        if ($datos['nombre'] === '') {
            redirigir_con_mensaje(url('admin/equipos.php'), 'error', 'El nombre del equipo es obligatorio.');
        }

        // --- Plantilla inicial (solo al CREAR) ---
        // Un equipo sin jugadores no puede alinearse ni generar estadísticas, así que se
        // exige la plantilla mínima desde el alta. Al editar no se piden aquí: la plantilla
        // ya se administra en su propia pantalla.
        $minimoJugadores = torneo_jugadores_en_cancha($torneo);
        $plantillaInicial = [];

        if ($id === 0) {
            $dorsales = (array) ($_POST['jug_dorsal'] ?? []);
            $nombres = (array) ($_POST['jug_nombre'] ?? []);
            $posiciones = (array) ($_POST['jug_posicion'] ?? []);
            $dorsalesUsados = [];

            foreach ($nombres as $i => $nombreJugador) {
                $nombreJugador = trim((string) $nombreJugador);
                $dorsal = trim((string) ($dorsales[$i] ?? ''));
                // Una fila vacía simplemente se ignora: el organizador puede llenar
                // solo las que necesite de las que se muestran.
                if ($nombreJugador === '' && $dorsal === '') {
                    continue;
                }
                if ($nombreJugador === '' || $dorsal === '') {
                    redirigir_con_mensaje($urlFormularioNuevo, 'error', 'Cada jugador necesita dorsal y nombre. Revisa la fila ' . ($i + 1) . '.');
                }
                if (isset($dorsalesUsados[$dorsal])) {
                    redirigir_con_mensaje($urlFormularioNuevo, 'error', "El dorsal {$dorsal} está repetido en la plantilla.");
                }
                $dorsalesUsados[$dorsal] = true;

                $plantillaInicial[] = [
                    'dorsal' => $dorsal,
                    'nombre' => $nombreJugador,
                    'posicion' => (string) ($posiciones[$i] ?? ''),
                ];
            }

            if (count($plantillaInicial) < $minimoJugadores) {
                $faltan = $minimoJugadores - count($plantillaInicial);
                redirigir_con_mensaje(
                    $urlFormularioNuevo,
                    'error',
                    "Esta modalidad juega con {$minimoJugadores} en cancha, así que el equipo necesita al menos {$minimoJugadores} jugadores. Te faltan {$faltan}."
                );
            }
        }

        try {
            $logoSubido = manejar_subida_imagen('logo', 'equipos');
        } catch (RuntimeException $e) {
            redirigir_con_mensaje(url('admin/equipos.php'), 'error', $e->getMessage());
        }

        if ($id > 0) {
            foreach ($equipos as &$e) {
                if ($e['id'] === $id) {
                    if ($logoSubido) {
                        eliminar_imagen($e['logo'] ?? null);
                        $datos['logo'] = $logoSubido;
                    } else {
                        $datos['logo'] = $e['logo'] ?? '';
                    }
                    $e = array_merge($e, $datos, ['id' => $id]);
                }
            }
            unset($e);
            $mensaje = 'Equipo actualizado correctamente.';
        } else {
            $datos['id'] = equipo_nuevo_id();
            $datos['logo'] = $logoSubido ?? '';
            $equipos[] = $datos;
            $mensaje = 'Equipo creado con ' . count($plantillaInicial) . ' jugadores.';
        }

        equipos_guardar_todos($equipos, $torneo['id']);

        // La plantilla se guarda DESPUÉS del equipo porque cada jugador necesita el id
        // del equipo, que solo existe una vez creado.
        if ($id === 0 && !empty($plantillaInicial)) {
            $jugadores = jugadores_listar($torneo['id']);
            foreach ($plantillaInicial as $nuevo) {
                $jugadores[] = [
                    'id' => jugador_nuevo_id(),
                    'equipo_id' => $datos['id'],
                    'dorsal' => $nuevo['dorsal'],
                    'nombre' => $nuevo['nombre'],
                    'posicion' => $nuevo['posicion'],
                    'activo' => true,
                ];
            }
            jugadores_guardar_todos($jugadores, $torneo['id']);
        }

        redirigir_con_mensaje(url('admin/equipos.php'), 'success', $mensaje);
    }
}

$seccion_activa = 'equipos';
$titulo_pagina = 'Equipos';

vista_admin('admin/equipos', compact(
    'accion',
    'equipoEditar',
    'equipos',
    'minimoPlantilla',
    'seccion_activa',
    'titulo_pagina',
    'torneo'
));
