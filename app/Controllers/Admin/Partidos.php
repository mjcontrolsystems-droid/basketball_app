<?php
declare(strict_types=1);

auth_requerir();
$torneo = admin_requerir_torneo_activo();

$equipos = equipos_listar($torneo['id']);
$partidos = partidos_listar($torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) { $equiposPorId[$eq['id']] = $eq; }

$accion = $_GET['accion'] ?? 'lista';
$idEditar = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$partidoEditar = $idEditar ? db_buscar_por_id($partidos, $idEditar) : null;
$errores = [];

// En formato liga no hay eliminación directa: la única "fase" posible es la temporada
// regular, así que ni el formulario ni las pestañas ofrecen otra cosa.
$esLiga = torneo_es_liga($torneo);
$fasesTorneo = torneo_fases_playoff($torneo);
$fasesValidas = array_merge(['grupos'], $fasesTorneo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();

    if (($_POST['accion'] ?? '') === 'eliminar') {
        $id = (int) $_POST['id'];
        $partidos = array_values(array_filter($partidos, fn($p) => $p['id'] !== $id));
        partidos_guardar_todos($partidos, $torneo['id']);
        db_guardar_eventos_partido($torneo['id'], $id, []);
        bitacora_registrar('partido_eliminado', 'Encuentro #' . $id . ' eliminado con todos sus eventos', $torneo['id']);
        redirigir_con_mensaje(url('admin/partidos.php'), 'success', 'Encuentro eliminado correctamente.');
    }

    // Interruptor rápido en la tarjeta del encuentro: alternar jugado/programado sin
    // abrir el formulario completo. Al marcarlo como jugado el marcador se toma de los
    // goles registrados en Eventos (queda 0-0 si no hay goles), no se captura a mano.
    if (($_POST['accion'] ?? '') === 'alternar_jugado') {
        $id = (int) $_POST['id'];
        $partidoActual = db_buscar_por_id($partidos, $id);
        if ($partidoActual === null) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', 'Encuentro no encontrado.');
        }

        if ($partidoActual['estado'] === 'jugado') {
            foreach ($partidos as &$p) {
                if ($p['id'] === $id) {
                    $p['estado'] = 'programado';
                }
            }
            unset($p);
            partidos_guardar_todos($partidos, $torneo['id']);
            bitacora_registrar('partido_reabierto', 'Encuentro #' . $id . ' reabierto para corrección de resultado', $torneo['id']);
            redirigir_con_mensaje(url('admin/partidos.php'), 'success', 'Encuentro reabierto para corrección. Márcalo como jugado de nuevo cuando termines.');
        }

        // El marcador se toma de los goles registrados en Eventos (fuente de verdad). Si
        // no hay goles queda 0-0, salvo que la copa no permita empates. Se conserva un
        // marcador histórico si ya estaba capturado y todavía no hay goles que lo sustituyan.
        [$mLocal, $mVisit] = marcador_jugado_desde_eventos($torneo['id'], $partidoActual, $torneo['deporte'] ?? null);
        if ($mLocal === $mVisit && empty($torneo['permite_empates'])) {
            redirigir_con_mensaje(url('admin/partido_eventos.php?partido_id=' . $id), 'error', 'Esta copa no permite empates: registra los ' . mb_strtolower(etiqueta_anotaciones($torneo['deporte'] ?? null)) . ' en Eventos para definir un ganador antes de marcar el encuentro como jugado.');
        }

        foreach ($partidos as &$p) {
            if ($p['id'] === $id) {
                $p['estado'] = 'jugado';
                $p['marcador_local'] = $mLocal;
                $p['marcador_visitante'] = $mVisit;
            }
        }
        unset($p);
        partidos_guardar_todos($partidos, $torneo['id']);
        bitacora_registrar('partido_jugado', "Encuentro #{$id} en firme con marcador {$mLocal}-{$mVisit}", $torneo['id']);
        redirigir_con_mensaje(url('admin/partidos.php'), 'success', 'Encuentro marcado como jugado. El resultado queda en firme.');
    }

    // Generador automático del calendario de temporada regular (todos contra todos, una o
    // dos vueltas). Sustituye el trabajo de programar los encuentros uno por uno: con 10
    // equipos son 45 partidos a una vuelta y 90 a ida y vuelta.
    if (($_POST['accion'] ?? '') === 'generar_fixture') {
        $urlGenerar = url('admin/partidos.php?accion=generar');
        $equipoIds = array_map(fn($eq) => (int) $eq['id'], $equipos);

        if (count($equipoIds) < 2) {
            redirigir_con_mensaje($urlGenerar, 'error', 'Necesitas al menos 2 equipos cargados para generar el calendario.');
        }

        $vueltasGenerar = ((int) ($_POST['vueltas'] ?? 1)) === 2 ? 2 : 1;
        $fechaInicio = (string) ($_POST['fecha_inicio'] ?? '');
        $horaPorDefecto = (string) ($_POST['hora'] ?? '');
        $diasEntreJornadas = max(0, min(60, (int) ($_POST['dias_entre_jornadas'] ?? 7)));
        $canchaPorDefecto = trim((string) ($_POST['cancha'] ?? ''));
        $reemplazar = !empty($_POST['reemplazar']);

        $tsInicio = strtotime($fechaInicio);
        if ($fechaInicio === '' || $tsInicio === false) {
            redirigir_con_mensaje($urlGenerar, 'error', 'Indica la fecha de la primera jornada.');
        }
        if ($horaPorDefecto === '') {
            redirigir_con_mensaje($urlGenerar, 'error', 'Indica la hora por defecto de los encuentros.');
        }

        // Los encuentros de temporada regular que ya existen. Los de eliminación directa
        // no se tocan nunca: el generador solo arma la fase de grupos/liga.
        $regularesExistentes = array_values(array_filter($partidos, fn($p) => ($p['fase'] ?? 'grupos') === 'grupos'));

        if (!empty($regularesExistentes) && !$reemplazar) {
            redirigir_con_mensaje($urlGenerar, 'error', 'Ya hay ' . count($regularesExistentes) . ' encuentros de temporada regular programados. Marca la casilla de reemplazar si quieres rehacer el calendario desde cero.');
        }

        if ($reemplazar) {
            // Red de seguridad: un encuentro ya jugado o con ficha cargada es historia del
            // torneo. Antes de borrar nada se exige que el organizador lo resuelva a mano,
            // en vez de destruir resultados en firme con un clic.
            $jugados = array_values(array_filter($regularesExistentes, fn($p) => ($p['estado'] ?? '') === 'jugado'));
            if (!empty($jugados)) {
                redirigir_con_mensaje($urlGenerar, 'error', 'No se puede rehacer el calendario: hay ' . count($jugados) . ' encuentro(s) ya marcados como jugados. Reábrelos o elimínalos primero si de verdad quieres empezar de nuevo.');
            }
            $conFicha = [];
            foreach ($regularesExistentes as $p) {
                if (!empty(db_leer_eventos_partido($torneo['id'], (int) $p['id']))) {
                    $conFicha[] = (int) $p['id'];
                }
            }
            if (!empty($conFicha)) {
                redirigir_con_mensaje($urlGenerar, 'error', 'No se puede rehacer el calendario: hay ' . count($conFicha) . ' encuentro(s) con eventos ya cargados en su ficha. Bórralos uno por uno si quieres empezar de nuevo.');
            }
        }

        $jornadasGeneradas = generar_fixture_round_robin($equipoIds, $vueltasGenerar);
        if (empty($jornadasGeneradas)) {
            redirigir_con_mensaje($urlGenerar, 'error', 'No se pudo generar el calendario con los equipos cargados.');
        }

        if ($reemplazar) {
            // No hace falta limpiar fichas: la validación de arriba ya garantizó que
            // ninguno de los encuentros que se van tiene eventos cargados.
            $partidos = array_values(array_filter($partidos, fn($p) => ($p['fase'] ?? 'grupos') !== 'grupos'));
        }

        $siguienteId = partido_nuevo_id();
        $totalCreados = 0;
        foreach ($jornadasGeneradas as $indiceJornada => $cruces) {
            // Se avanza en días con strtotime (no sumando segundos) para que un cambio de
            // horario de verano no corra las fechas un día.
            $diasDesplazados = $indiceJornada * $diasEntreJornadas;
            $tsJornada = strtotime("+{$diasDesplazados} days", $tsInicio);
            $fechaJornada = date('Y-m-d', $tsJornada !== false ? $tsJornada : $tsInicio);
            foreach ($cruces as [$localId, $visitanteId]) {
                $partidos[] = [
                    'id' => $siguienteId++,
                    'jornada' => $indiceJornada + 1,
                    'equipo_local' => $localId,
                    'equipo_visitante' => $visitanteId,
                    'fecha' => $fechaJornada,
                    'hora' => $horaPorDefecto,
                    'cancha' => $canchaPorDefecto,
                    'estado' => 'programado',
                    'marcador_local' => null,
                    'marcador_visitante' => null,
                    'fase' => 'grupos',
                    'arbitro' => '',
                    'observaciones' => '',
                    // Columnas NOT NULL del cronómetro: un encuentro nuevo las necesita
                    // explícitas (mismo motivo que al programar uno a mano).
                    'cronometro_estado' => 'detenido',
                    'cronometro_inicio' => null,
                    'cronometro_segundos' => 0,
                    'cronometro_periodo' => 1,
                    'cronometro_extra_min' => 0,
                ];
                $totalCreados++;
            }
        }

        partidos_guardar_todos($partidos, $torneo['id']);
        $textoVueltas = $vueltasGenerar === 2 ? 'ida y vuelta' : 'una vuelta';
        bitacora_registrar(
            'calendario_generado',
            "Calendario de temporada regular generado: {$totalCreados} encuentros en " . count($jornadasGeneradas) . " jornadas ({$textoVueltas}, " . count($equipoIds) . ' equipos)',
            $torneo['id']
        );
        redirigir_con_mensaje(url('admin/partidos.php'), 'success', "Calendario generado: {$totalCreados} encuentros en " . count($jornadasGeneradas) . ' jornadas. Ajusta fechas, horas o canchas encuentro por encuentro si hace falta.');
    }

    if (($_POST['accion'] ?? '') === 'guardar') {
        $id = (int) ($_POST['id'] ?? 0);
        $local = (int) $_POST['equipo_local'];
        $visitante = (int) $_POST['equipo_visitante'];
        $estado = (string) $_POST['estado'];
        $fase = (string) ($_POST['fase'] ?? 'grupos');

        if (!in_array($fase, $fasesValidas, true)) {
            $fase = 'grupos';
        }

        if ($local === $visitante) {
            $errores[] = 'El equipo local y el visitante no pueden ser el mismo.';
        }
        if (!isset($equiposPorId[$local]) || !isset($equiposPorId[$visitante])) {
            $errores[] = 'Selecciona equipos válidos.';
        }

        // El marcador ya no se captura en este formulario: se calcula desde los goles
        // registrados en Eventos. Para un encuentro existente se deriva de sus goles
        // (conservando un marcador histórico si aún no hay goles); uno nuevo empieza 0-0.
        $partidoExistente = $id > 0 ? db_buscar_por_id($partidos, $id) : null;
        if ($partidoExistente !== null) {
            [$marcadorLocal, $marcadorVisitante] = marcador_jugado_desde_eventos($torneo['id'], $partidoExistente, $torneo['deporte'] ?? null);
        } else {
            $marcadorLocal = 0;
            $marcadorVisitante = 0;
        }

        if ($estado === 'jugado' && $marcadorLocal === $marcadorVisitante && empty($torneo['permite_empates'])) {
            $txtAnot = mb_strtolower(etiqueta_anotaciones($torneo['deporte'] ?? null));
            if ($partidoExistente !== null) {
                $errores[] = "Esta copa no permite empates: registra los {$txtAnot} en la ficha de Eventos para definir un ganador antes de marcar el encuentro como jugado.";
            } else {
                $errores[] = "Esta copa no permite empates. Guarda el encuentro como \"Programado\", registra los {$txtAnot} en Eventos y luego márcalo como jugado.";
            }
        }

        // --- Jornada ---
        // Se deduce de la fecha en lugar de escribirse a mano: los encuentros del mismo
        // fin de semana caen en la misma jornada y una fecha nueva abre la siguiente. Solo
        // si el organizador marca "ajustar manualmente" se toma lo que él escribió, y aun
        // así con tope, para que no salga una jornada 30 en un torneo de cinco.
        $fechaPartido = (string) ($_POST['fecha'] ?? '');
        $jornadaAutomatica = jornada_por_fecha($partidos, $fechaPartido, $id);
        $jornadaManual = !empty($_POST['jornada_manual']);
        $jornada = $jornadaAutomatica;

        if ($jornadaManual) {
            $jornada = (int) ($_POST['jornada'] ?? 0);
            $tope = jornada_maxima_permitida($partidos, $id);
            if ($jornada < 1) {
                $errores[] = 'La jornada debe ser 1 o mayor.';
            } elseif ($jornada > $tope) {
                $errores[] = "La jornada más alta que puedes usar ahora es la {$tope}. Para llegar a la {$jornada} tendrías que programar antes las jornadas intermedias.";
            }
        }

        if (empty($errores)) {
            $datos = [
                'jornada' => $jornada,
                'equipo_local' => $local,
                'equipo_visitante' => $visitante,
                'fecha' => (string) $_POST['fecha'],
                'hora' => (string) $_POST['hora'],
                'cancha' => trim((string) $_POST['cancha']),
                'estado' => $estado,
                // Marcador derivado de los goles. Un encuentro jugado siempre lleva marcador
                // (0-0 incluido); uno programado solo lo muestra si ya tiene goles cargados.
                'marcador_local' => ($estado === 'jugado' || $marcadorLocal !== 0 || $marcadorVisitante !== 0) ? $marcadorLocal : null,
                'marcador_visitante' => ($estado === 'jugado' || $marcadorLocal !== 0 || $marcadorVisitante !== 0) ? $marcadorVisitante : null,
                'fase' => $fase,
                'arbitro' => trim((string) ($_POST['arbitro'] ?? '')),
                'observaciones' => trim((string) ($_POST['observaciones'] ?? '')),
            ];

            if ($id > 0) {
                foreach ($partidos as &$p) {
                    if ($p['id'] === $id) {
                        $p = array_merge($p, $datos, ['id' => $id]);
                    }
                }
                unset($p);
                $mensaje = 'Encuentro actualizado correctamente.';
            } else {
                $datos['id'] = partido_nuevo_id();
                // Estado inicial del cronómetro: estas columnas son NOT NULL en la base,
                // así que un encuentro NUEVO debe traerlas explícitas — sin esto, el
                // INSERT fallaba con "error inesperado" al programar cualquier encuentro.
                // En una edición no se tocan (array_merge conserva las del partido).
                $datos['cronometro_estado'] = 'detenido';
                $datos['cronometro_inicio'] = null;
                $datos['cronometro_segundos'] = 0;
                $datos['cronometro_periodo'] = 1;
                $datos['cronometro_extra_min'] = 0;
                $partidos[] = $datos;
                $mensaje = 'Encuentro programado correctamente.';
            }

            partidos_guardar_todos($partidos, $torneo['id']);
            bitacora_registrar($id > 0 ? 'partido_editado' : 'partido_creado', 'Encuentro ' . ($id > 0 ? "#{$id}" : "#{$datos['id']}") . ' — ' . ($equiposPorId[$local]['nombre'] ?? '?') . ' vs ' . ($equiposPorId[$visitante]['nombre'] ?? '?') . ' (' . $datos['fecha'] . ')', $torneo['id']);
            redirigir_con_mensaje(url('admin/partidos.php'), 'success', $mensaje);
        } else {
            $partidoEditar = array_merge($_POST, ['id' => $id]);
            $accion = $id > 0 ? 'editar' : 'nuevo';
        }
    }
}

$jornadas = partidos_por_jornada($partidos);
$playoffsPorFase = partidos_playoffs_por_fase($partidos, $fasesTorneo);
// Para el formulario: qué jornada saldría y hasta cuál se puede corregir a mano. La
// automática real se recalcula al guardar con la fecha que se haya elegido; esta es solo
// la que corresponde hoy, para mostrarla como referencia.
$idFormulario = (int) ($partidoEditar['id'] ?? 0);
$jornadaSugerida = $idFormulario > 0 && isset($partidoEditar['jornada'])
    ? (int) $partidoEditar['jornada']
    : jornada_por_fecha($partidos, (string) ($partidoEditar['fecha'] ?? date('Y-m-d')), $idFormulario);
$jornadaTope = jornada_maxima_permitida($partidos, $idFormulario);
$jornadaManualMarcada = !empty($partidoEditar['jornada_manual']);
$faseSeleccionada = $partidoEditar['fase'] ?? ($_GET['fase'] ?? 'grupos');
if (!in_array($faseSeleccionada, $fasesValidas, true)) {
    $faseSeleccionada = 'grupos';
}

$seccion_activa = 'partidos';
$titulo_pagina = 'Encuentros';

vista_admin('admin/partidos', compact(
    'accion',
    'equipos',
    'equiposPorId',
    'errores',
    'esLiga',
    'faseSeleccionada',
    'fasesTorneo',
    'fasesValidas',
    'jornadaManualMarcada',
    'jornadas',
    'jornadaSugerida',
    'jornadaTope',
    'partidoEditar',
    'partidos',
    'playoffsPorFase',
    'seccion_activa',
    'titulo_pagina',
    'torneo'
));
