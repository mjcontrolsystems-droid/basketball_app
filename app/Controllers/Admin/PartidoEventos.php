<?php
declare(strict_types=1);

auth_requerir();
$torneo = admin_requerir_torneo_activo();

$partidoId = (int) ($_GET['partido_id'] ?? $_POST['partido_id'] ?? 0);
$partidos = partidos_listar($torneo['id']);
$partido = db_buscar_por_id($partidos, $partidoId);
if ($partido === null) {
    http_response_code(404);
    exit('Encuentro no encontrado.');
}

$equipos = equipos_listar($torneo['id']);
$equiposPorId = [];
foreach ($equipos as $eq) { $equiposPorId[$eq['id']] = $eq; }
$equipoLocal = $equiposPorId[(int) $partido['equipo_local']] ?? null;
$equipoVisitante = $equiposPorId[(int) $partido['equipo_visitante']] ?? null;
$equiposDelPartido = array_filter([(int) $partido['equipo_local'], (int) $partido['equipo_visitante']]);

$jugadoresTodos = jugadores_listar($torneo['id']);
$jugadoresPorEquipo = jugadores_por_equipo($jugadoresTodos);
$jugadoresPorId = jugadores_por_id($jugadoresTodos);
$etJugador = forma_genero($torneo['genero'] ?? null, 'Jugador', 'Jugadora');
$deporte = $torneo['deporte'] ?? null;
$basketball = es_basketball($deporte);

// Cuántos jugadores pone cada equipo en la cancha según la modalidad de la copa (5, 7 u
// 11): es el tope de titulares que admite la alineación de este encuentro.
$jugadoresEnCancha = torneo_jugadores_en_cancha($torneo);

$urlLista = url('admin/partido_eventos.php?partido_id=' . $partidoId);

// Regla de integridad (aplica a fútbol y basketball por igual): un encuentro marcado como
// JUGADO tiene su resultado en firme — alimenta la tabla de posiciones y pudo haberse
// publicado/compartido. Sus eventos, cronómetro y fecha quedan bloqueados; solo se pueden
// tocar reabriéndolo explícitamente para corrección (botón con confirmación + bitácora).
$resultadoBloqueado = ($partido['estado'] ?? '') === 'jugado';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $accion = (string) ($_POST['accion'] ?? '');

    // Reabrir para corrección: vuelve el encuentro a "programado" (sale de la tabla hasta
    // volver a marcarlo jugado) y desbloquea la ficha. Queda registrado en la bitácora.
    if ($accion === 'reabrir_correccion') {
        foreach ($partidos as &$p) {
            if ((int) $p['id'] === $partidoId) {
                $p['estado'] = 'programado';
            }
        }
        unset($p);
        partidos_guardar_todos($partidos, $torneo['id']);
        bitacora_registrar('partido_reabierto', 'Encuentro #' . $partidoId . ' reabierto para corrección de resultado', $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', 'Encuentro reabierto para corrección. Cuando termines, márcalo como jugado de nuevo para que vuelva a contar en la tabla.');
    }

    // Cualquier otra acción de escritura sobre un encuentro con resultado en firme se
    // rechaza (protege contra pestañas viejas o envíos directos del formulario).
    if ($resultadoBloqueado) {
        redirigir_con_mensaje($urlLista, 'error', 'Este encuentro ya está finalizado y su resultado quedó en firme. Usa "Reabrir para corrección" si hubo un error.');
    }

    // El partido se está jugando/registrando antes de la fecha programada: el modal de
    // confirmación ofrece adelantar la fecha del encuentro a hoy sin salir de la ficha.
    if ($accion === 'actualizar_fecha_hoy') {
        foreach ($partidos as &$p) {
            if ((int) $p['id'] === $partidoId) {
                $p['fecha'] = date('Y-m-d');
            }
        }
        unset($p);
        partidos_guardar_todos($partidos, $torneo['id']);
        bitacora_registrar('fecha_adelantada', 'Encuentro #' . $partidoId . ' adelantado a ' . date('Y-m-d'), $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', 'Fecha del encuentro actualizada a hoy.');
    }

    // Cronómetro del partido: controla qué minuto se sugiere al cargar un evento (ver
    // partido_cronometro_minuto() en app/Support/liga.php y el autocompletado del campo "Min."
    // en assets/js/app.js). 'iniciar' solo aplica desde 'detenido' para no reiniciar por
    // accidente un cronómetro que ya venía corriendo.
    if ($accion === 'cronometro_iniciar') {
        foreach ($partidos as &$p) {
            if ((int) $p['id'] === $partidoId && ($p['cronometro_estado'] ?? 'detenido') === 'detenido') {
                $p['cronometro_estado'] = 'corriendo';
                $p['cronometro_inicio'] = date('c');
                $p['cronometro_segundos'] = 0;
                $p['cronometro_extra_min'] = 0;
            }
        }
        unset($p);
        partidos_guardar_todos($partidos, $torneo['id']);
        $duracion = torneo_duracion_periodo_min($torneo);
        redirigir_con_mensaje($urlLista, 'success', "Cronómetro iniciado: {$duracion} minutos para este " . ($basketball ? 'cuarto' : 'tiempo') . '.');
    }

    // Tiempo añadido / reposición del periodo EN CURSO: en vez de dejar el reloj en 00:00
    // esperando, el árbitro suma los minutos que se van a jugar de más y la cuenta
    // regresiva (aquí y en la transmisión en vivo) los refleja al instante.
    if ($accion === 'cronometro_agregar_extra') {
        $minutosExtraNuevos = max(1, min(30, (int) ($_POST['minutos'] ?? 1)));
        foreach ($partidos as &$p) {
            if ((int) $p['id'] === $partidoId) {
                $p['cronometro_extra_min'] = min(60, partido_minutos_extra($p) + $minutosExtraNuevos);
            }
        }
        unset($p);
        partidos_guardar_todos($partidos, $torneo['id']);
        bitacora_registrar('tiempo_extra', "Encuentro #{$partidoId}: +{$minutosExtraNuevos} min de tiempo extra", $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', "Se agregaron {$minutosExtraNuevos} minutos extra a este periodo.");
    }

    if ($accion === 'cronometro_quitar_extra') {
        foreach ($partidos as &$p) {
            if ((int) $p['id'] === $partidoId) {
                $p['cronometro_extra_min'] = 0;
            }
        }
        unset($p);
        partidos_guardar_todos($partidos, $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', 'Tiempo extra descartado.');
    }

    // Alineación del encuentro: quién arranca de titular, quién queda en banca y en qué
    // posición juega cada uno hoy. El tope de titulares por equipo lo marca la modalidad
    // de la copa (5, 7 u 11 jugadores en cancha).
    if ($accion === 'guardar_alineacion') {
        $titularesEnviados = array_map('intval', (array) ($_POST['titular'] ?? []));
        $posicionesEnviadas = (array) ($_POST['posicion'] ?? []);
        $catalogoPosiciones = posiciones_catalogo($deporte);

        $filas = [];
        $titularesPorEquipo = [];
        foreach ($equiposDelPartido as $equipoIdLado) {
            foreach ($jugadoresPorEquipo[$equipoIdLado] ?? [] as $j) {
                // Los dados de baja en la plantilla no entran a la convocatoria (el
                // formulario los muestra en gris, pero no se guardan ni en la banca).
                if (empty($j['activo'])) {
                    continue;
                }
                $jugadorId = (int) $j['id'];
                $esTitular = in_array($jugadorId, $titularesEnviados, true);
                $posicion = (string) ($posicionesEnviadas[$jugadorId] ?? '');
                if (!isset($catalogoPosiciones[$posicion])) {
                    $posicion = '';
                }
                if ($esTitular) {
                    $titularesPorEquipo[$equipoIdLado] = ($titularesPorEquipo[$equipoIdLado] ?? 0) + 1;
                }
                $filas[] = [
                    'jugador_id' => $jugadorId,
                    'equipo_id' => $equipoIdLado,
                    'titular' => $esTitular,
                    'posicion' => $posicion,
                ];
            }
        }

        foreach ($titularesPorEquipo as $equipoIdLado => $cuantos) {
            if ($cuantos > $jugadoresEnCancha) {
                $nombreEquipo = $equiposPorId[$equipoIdLado]['nombre'] ?? 'ese equipo';
                redirigir_con_mensaje($urlLista, 'error', "{$nombreEquipo} tiene {$cuantos} titulares marcados y esta modalidad juega con {$jugadoresEnCancha}. Pasa a la banca los que sobran.");
            }
        }

        db_guardar_alineacion($torneo['id'], $partidoId, $filas);
        bitacora_registrar('alineacion_guardada', 'Encuentro #' . $partidoId . ': alineación actualizada', $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', 'Alineación guardada.');
    }

    if ($accion === 'cronometro_alternar_pausa') {
        foreach ($partidos as &$p) {
            if ((int) $p['id'] === $partidoId) {
                $estado = $p['cronometro_estado'] ?? 'detenido';
                if ($estado === 'corriendo') {
                    $p['cronometro_segundos'] = partido_cronometro_segundos($p);
                    $p['cronometro_estado'] = 'pausado';
                    $p['cronometro_inicio'] = null;
                } elseif ($estado === 'pausado') {
                    $p['cronometro_estado'] = 'corriendo';
                    $p['cronometro_inicio'] = date('c');
                }
            }
        }
        unset($p);
        partidos_guardar_todos($partidos, $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', 'Cronómetro actualizado.');
    }

    if ($accion === 'cronometro_finalizar') {
        foreach ($partidos as &$p) {
            if ((int) $p['id'] === $partidoId && in_array($p['cronometro_estado'] ?? 'detenido', ['corriendo', 'pausado'], true)) {
                $p['cronometro_segundos'] = partido_cronometro_segundos($p);
                $p['cronometro_estado'] = 'finalizado';
                $p['cronometro_inicio'] = null;
            }
        }
        unset($p);
        partidos_guardar_todos($partidos, $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', 'Cronómetro finalizado.');
    }

    // Avanza de periodo (1er a 2do tiempo en fútbol; cuarto a cuarto en basketball). Cada
    // periodo nuevo lleva su propio cronómetro desde 00:00 (no es continuo entre tiempos/
    // cuartos, a diferencia de una pausa dentro del mismo periodo).
    if ($accion === 'cronometro_siguiente_periodo') {
        $maximo = partido_periodo_maximo($deporte);
        foreach ($partidos as &$p) {
            if ((int) $p['id'] === $partidoId) {
                $periodoActual = (int) ($p['cronometro_periodo'] ?? 1);
                if ($periodoActual < $maximo) {
                    $p['cronometro_periodo'] = $periodoActual + 1;
                    $p['cronometro_segundos'] = 0;
                    // El tiempo extra pertenece al periodo que se cerró: el nuevo arranca
                    // otra vez con la duración limpia configurada en la copa.
                    $p['cronometro_extra_min'] = 0;
                    if (($p['cronometro_estado'] ?? 'detenido') === 'corriendo') {
                        $p['cronometro_inicio'] = date('c');
                    }
                }
            }
        }
        unset($p);
        partidos_guardar_todos($partidos, $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', 'Periodo actualizado y cronómetro reiniciado.');
    }

    if ($accion === 'eliminar_evento') {
        $id = (int) $_POST['id'];
        $eventos = db_leer_eventos_partido($torneo['id'], $partidoId);
        $eventoBorrado = db_buscar_por_id($eventos, $id);
        $eventos = array_values(array_filter($eventos, fn($ev) => (int) $ev['id'] !== $id));
        db_guardar_eventos_partido($torneo['id'], $partidoId, $eventos);
        // Si el evento borrado era un gol, el marcador cambia: se recalcula desde los
        // eventos restantes. Un evento que no era gol (tarjeta/cambio) no toca el marcador.
        if ($eventoBorrado !== null && ($eventoBorrado['tipo'] ?? '') === 'gol') {
            partido_recalcular_marcador($torneo['id'], $partidoId, $partidos, $deporte);
        }
        if ($eventoBorrado !== null) {
            bitacora_registrar('evento_eliminado', 'Encuentro #' . $partidoId . ': ' . evento_descripcion($eventoBorrado, $jugadoresPorId, $deporte), $torneo['id']);
        }
        redirigir_con_mensaje($urlLista, 'success', 'Evento eliminado.');
    }

    if (in_array($accion, ['agregar_gol', 'agregar_tarjeta', 'agregar_cambio'], true)) {
        $equipoId = (int) ($_POST['equipo_id'] ?? 0);
        if (!in_array($equipoId, $equiposDelPartido, true)) {
            redirigir_con_mensaje($urlLista, 'error', 'Selecciona un equipo válido para este encuentro.');
        }
        $rosterEquipo = array_column($jugadoresPorEquipo[$equipoId] ?? [], 'id');
        $minuto = ($_POST['minuto'] ?? '') !== '' ? (int) $_POST['minuto'] : null;

        $evento = [
            'equipo_id' => $equipoId,
            'jugador_id' => null,
            'jugador_entra_id' => null,
            'minuto' => $minuto,
            'tipo_gol' => null,
            'asistencia_jugador_id' => null,
            'motivo' => null,
            // Periodo tomado del cronómetro en este momento (1er/2do tiempo o cuarto 1-4),
            // para poder mostrar "4' Cambio ... · 1er Tiempo" en la ficha y la transmisión.
            'periodo' => (int) ($partido['cronometro_periodo'] ?? 1),
        ];

        if ($accion === 'agregar_gol') {
            $jugadorId = (int) ($_POST['jugador_id'] ?? 0);
            if (!in_array($jugadorId, $rosterEquipo, true)) {
                redirigir_con_mensaje($urlLista, 'error', forma_genero($torneo['genero'] ?? null, 'Selecciona un jugador válido de ese equipo.', 'Selecciona una jugadora válida de ese equipo.'));
            }
            $catalogoTipoGol = tipos_anotacion_catalogo($deporte);
            $tipoGolDefault = $catalogoTipoGol[0];
            $tipoGol = (string) ($_POST['tipo_gol'] ?? $tipoGolDefault);
            $asistenciaId = (int) ($_POST['asistencia_jugador_id'] ?? 0);

            $evento['tipo'] = 'gol';
            $evento['jugador_id'] = $jugadorId;
            $evento['tipo_gol'] = in_array($tipoGol, $catalogoTipoGol, true) ? $tipoGol : $tipoGolDefault;
            $evento['asistencia_jugador_id'] = in_array($asistenciaId, $rosterEquipo, true) ? $asistenciaId : null;
        }

        if ($accion === 'agregar_tarjeta') {
            $jugadorId = (int) ($_POST['jugador_id'] ?? 0);
            if (!in_array($jugadorId, $rosterEquipo, true)) {
                redirigir_con_mensaje($urlLista, 'error', forma_genero($torneo['genero'] ?? null, 'Selecciona un jugador válido de ese equipo.', 'Selecciona una jugadora válida de ese equipo.'));
            }
            $color = (string) ($_POST['color'] ?? 'amarilla') === 'roja' ? 'roja' : 'amarilla';
            $catalogoMotivo = motivos_falta_grave_catalogo($deporte);
            $motivoDefault = $catalogoMotivo[0];
            $motivo = (string) ($_POST['motivo'] ?? $motivoDefault);

            $evento['tipo'] = $color;
            $evento['jugador_id'] = $jugadorId;
            $evento['motivo'] = $color === 'roja' ? (in_array($motivo, $catalogoMotivo, true) ? $motivo : $motivoDefault) : null;
        }

        if ($accion === 'agregar_cambio') {
            $jugadorSaleId = (int) ($_POST['jugador_id'] ?? 0);
            $jugadorEntraId = (int) ($_POST['jugador_entra_id'] ?? 0);
            if (!in_array($jugadorSaleId, $rosterEquipo, true) || !in_array($jugadorEntraId, $rosterEquipo, true)) {
                redirigir_con_mensaje($urlLista, 'error', forma_genero($torneo['genero'] ?? null, 'Selecciona jugadores válidos de ese equipo.', 'Selecciona jugadoras válidas de ese equipo.'));
            }
            if ($jugadorSaleId === $jugadorEntraId) {
                redirigir_con_mensaje($urlLista, 'error', forma_genero($torneo['genero'] ?? null, 'El jugador que entra y el que sale no pueden ser el mismo.', 'La jugadora que entra y la que sale no pueden ser la misma.'));
            }

            $evento['tipo'] = 'cambio';
            $evento['jugador_id'] = $jugadorSaleId;
            $evento['jugador_entra_id'] = $jugadorEntraId;
        }

        $eventos = db_leer_eventos_partido($torneo['id'], $partidoId);
        $evento['id'] = evento_nuevo_id();
        $eventos[] = $evento;
        db_guardar_eventos_partido($torneo['id'], $partidoId, $eventos);
        // Al registrar un gol, el marcador se actualiza automáticamente reflejando
        // todos los goles del partido (fútbol +1; basketball +1/2/3; autogol al rival).
        if ($accion === 'agregar_gol') {
            partido_recalcular_marcador($torneo['id'], $partidoId, $partidos, $deporte);
        }
        bitacora_registrar('evento_agregado', 'Encuentro #' . $partidoId . ': ' . evento_descripcion($evento, $jugadoresPorId, $deporte), $torneo['id']);
        redirigir_con_mensaje($urlLista, 'success', 'Evento agregado.');
    }
}

$eventos = db_leer_eventos_partido($torneo['id'], $partidoId);
usort($eventos, fn($a, $b) => ($a['minuto'] ?? 999) <=> ($b['minuto'] ?? 999));

// Alineación ya guardada de este encuentro. Si todavía no hay ninguna, el formulario se
// pinta con la posición habitual de cada jugador (jugadores.posicion) como sugerencia y
// nadie marcado de titular: el organizador arma la alineación del día en un par de clics.
$alineacion = db_leer_alineacion($torneo['id'], $partidoId);
$alineacionPorJugador = alineacion_por_jugador($alineacion);
$hayAlineacion = !empty($alineacion);
$titularesPorEquipoActual = [];
foreach ($alineacion as $filaAlineacion) {
    if (!empty($filaAlineacion['titular'])) {
        $eqId = (int) $filaAlineacion['equipo_id'];
        $titularesPorEquipoActual[$eqId] = ($titularesPorEquipoActual[$eqId] ?? 0) + 1;
    }
}

// Marcador en vivo calculado directamente desde los goles registrados. Es la fuente de
// verdad del resultado: cada gol que se agrega abajo se refleja aquí y en la tabla.
[$marcadorLocalVivo, $marcadorVisitanteVivo] = marcador_desde_eventos(
    $eventos,
    (int) $partido['equipo_local'],
    (int) $partido['equipo_visitante'],
    $deporte
);

// El encuentro se está registrando antes de su fecha programada: se ofrece (vía modal)
// adelantar la fecha a hoy o seguir registrando eventos sin tocar la fecha.
$hoy = date('Y-m-d');
$fechaEsFutura = ($partido['fecha'] ?? '') > $hoy && ($partido['estado'] ?? '') !== 'jugado';

$seccion_activa = 'partidos';
$titulo_pagina = 'Ficha del partido';

vista_admin('admin/partido_eventos', compact(
    'alineacionPorJugador',
    'basketball',
    'deporte',
    'equipoLocal',
    'equipoVisitante',
    'equiposDelPartido',
    'equiposPorId',
    'etJugador',
    'eventos',
    'fechaEsFutura',
    'hayAlineacion',
    'jugadoresEnCancha',
    'jugadoresPorEquipo',
    'jugadoresPorId',
    'marcadorLocalVivo',
    'marcadorVisitanteVivo',
    'partido',
    'partidoId',
    'resultadoBloqueado',
    'seccion_activa',
    'titularesPorEquipoActual',
    'titulo_pagina',
    'torneo'
));
