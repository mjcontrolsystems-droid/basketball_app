<?php
declare(strict_types=1);

auth_requerir();
$torneo = admin_requerir_torneo_activo();

// Esta pantalla mezcla tres niveles de riesgo, así que el permiso no es uno solo:
//   - ver la lista y entrar a un partido lo puede hacer hasta la mesa;
//   - crear, editar o borrar un encuentro suelto es de asistente para arriba;
//   - todo lo que rehace el calendario o arma el cuadro final es solo del dueño,
//     porque de un botón puede salir un torneo entero distinto al que ya se anunció.
requerir_permiso('partido_capturar');

const PARTIDOS_ACCIONES_DE_DUENO = ['generar_fixture', 'borrar_desde_jornada', 'correr_calendario', 'armar_cruces'];
const PARTIDOS_ACCIONES_DE_ASISTENTE = ['guardar', 'eliminar', 'alternar_jugado'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accionPost = (string) ($_POST['accion'] ?? '');
    if (in_array($accionPost, PARTIDOS_ACCIONES_DE_DUENO, true)) {
        requerir_permiso('calendario');
    } elseif (in_array($accionPost, PARTIDOS_ACCIONES_DE_ASISTENTE, true)) {
        requerir_permiso('partidos_editar');
    }
}
if (in_array($_GET['accion'] ?? '', ['generar', 'nuevo', 'editar'], true)) {
    requerir_permiso(($_GET['accion'] ?? '') === 'generar' ? 'calendario' : 'partidos_editar');
}

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

    // Armar los cruces de eliminación con los clasificados de cada grupo. Se cruza el
    // primero de un grupo con el segundo de otro, y los dos clasificados de un mismo grupo
    // caen en mitades opuestas del cuadro: así no se reencuentran hasta la final.
    if (($_POST['accion'] ?? '') === 'armar_cruces') {
        $paso = eliminacion_siguiente_paso($torneo, $partidos, $equiposPorId);

        if ($paso['fase'] === null) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', $paso['motivo'] ?: 'No hay ninguna ronda por armar.');
        }
        // "Faltan partidos por jugar" se puede saltar a conciencia (el organizador ya sabe
        // que las posiciones pueden cambiar). Un EMPATE en eliminación no: sin ganador no
        // hay a quién poner en la ronda siguiente, así que ahí no hay bypass que valga.
        $hayEmpateSinDefinir = str_contains($paso['motivo'], 'empatados');
        if (!$paso['listo'] && (empty($_POST['aun_faltan']) || $hayEmpateSinDefinir)) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', $paso['motivo']);
        }

        $avisos = [];
        if ($paso['origen'] === null) {
            // Primera ronda del cuadro: los clasificados salen de la tabla. En el formato
            // de grupos hay una tabla por grupo y el cruce es 1° de uno contra 2° de otro;
            // en liga con fase final hay una sola tabla y se usa la siembra clásica.
            $eventosTorneo = eventos_de_torneo($torneo['id']);
            if (torneo_tiene_grupos($torneo)) {
                $resultado = grupos_cruces_eliminacion(
                    grupos_tablas($equipos, $partidos, $torneo, $eventosTorneo),
                    torneo_clasifican_por_grupo($torneo)
                );
            } else {
                $resultado = eliminacion_cruces_desde_tabla(
                    calcular_tabla($equipos, $partidos, $torneo, $eventosTorneo),
                    (int) ($_POST['clasifican'] ?? 4)
                );
            }
            $faseDestino = $resultado['fase'];
            $cruces = $resultado['cruces'];
            $avisos = $resultado['avisos'];
        } else {
            // Rondas siguientes: salen de la ronda anterior. El tercer lugar es el único
            // que se arma con los PERDEDORES.
            $resultadoRonda = eliminacion_resultado_ronda($partidos, $paso['origen'], $equiposPorId);
            $faseDestino = $paso['fase'];
            $labelOrigen = mb_strtolower(FASES_LABEL[$paso['origen']] ?? $paso['origen']);

            if ($faseDestino === 'tercer_lugar') {
                $cruces = eliminacion_emparejar($resultadoRonda['perdedores'], 'Perdedores de ' . $labelOrigen);
            } else {
                $cruces = eliminacion_emparejar($resultadoRonda['ganadores'], 'Ganadores de ' . $labelOrigen);
            }
        }

        if (empty($cruces)) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', 'No se pudieron armar los cruces. ' . (implode(' ', $avisos) ?: 'Revisa que haya suficientes equipos con partidos jugados.'));
        }

        // Si ya existen encuentros de esa fase se detiene: rearmarlos borraría fichas.
        $yaExisten = array_values(array_filter($partidos, fn($p) => ($p['fase'] ?? '') === $faseDestino));
        if (!empty($yaExisten)) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', 'Ya hay ' . count($yaExisten) . ' encuentros de ' . mb_strtolower(FASES_LABEL[$faseDestino] ?? $faseDestino) . '. Bórralos primero si quieres rearmar esa ronda.');
        }

        // Arrancan el fin de semana siguiente al último encuentro que ya existe.
        $ultima = '';
        foreach ($partidos as $p) {
            $ultima = max($ultima, (string) ($p['fecha'] ?? ''));
        }
        $tsCruces = strtotime('+1 day', (int) (strtotime($ultima ?: date('Y-m-d')) ?: time()));
        $fechaCruces = date('Y-m-d', $tsCruces !== false ? $tsCruces : time());

        // Se reparten con el mismo criterio que la temporada regular: por días de juego,
        // por parejas (los dos ganadores se cruzan después, así que descansan lo mismo) y
        // con el turno para quien menos veces le ha tocado. Antes nacían todos el mismo
        // día, sin hora y sin cancha, y había que acomodarlos a mano.
        $ubicados = calendario_ubicar_cruces(
            $cruces,
            $partidos,
            calendario_config_del_torneo($torneo, $partidos),
            $fechaCruces
        );

        // La jornada sigue la numeración de la copa en vez de volver a 1.
        $jornadaCruces = 1;
        foreach ($partidos as $p) {
            $jornadaCruces = max($jornadaCruces, (int) ($p['jornada'] ?? 0) + 1);
        }

        $siguienteId = partido_nuevo_id();
        foreach ($ubicados as $u) {
            $fila = eliminacion_fila_partido(
                $siguienteId++,
                $u['cruce']['local'],
                $u['cruce']['visitante'],
                $faseDestino,
                $u['fecha'],
                $u['cruce']['etiqueta']
            );
            $fila['hora'] = $u['hora'];
            $fila['cancha'] = $u['cancha'];
            $fila['jornada'] = $jornadaCruces;
            $partidos[] = $fila;
        }

        partidos_guardar_todos($partidos, $torneo['id']);
        $nombreFase = FASES_LABEL[$faseDestino] ?? $faseDestino;
        bitacora_registrar('cruces_armados', count($cruces) . ' encuentros de ' . $nombreFase . ' armados' . ($paso['origen'] ? ' desde ' . $paso['origen'] : ' desde la tabla'), $torneo['id']);

        // Se listan las fechas de verdad: con varios días de juego una ronda se reparte
        // entre ellos, y decir una sola fecha sería mentira.
        $fechasUsadas = array_values(array_unique(array_map(fn($u) => $u['fecha'], $ubicados)));
        sort($fechasUsadas);
        $conHora = !empty($ubicados) && ($ubicados[0]['hora'] ?? '') !== '';

        $aviso = count($ubicados) . ' encuentros de ' . mb_strtolower($nombreFase) . ' creados para el '
            . implode(' y el ', array_map('formatear_fecha_corta', $fechasUsadas)) . '.'
            . ($conHora ? ' Ya llevan día, hora y cancha; revísalos por si quieres moverlos.' : ' Ajústales día, hora y cancha.');
        if (!empty($avisos)) {
            $aviso .= ' ' . implode(' ', $avisos);
        }
        redirigir_con_mensaje(url('admin/partidos.php'), 'success', $aviso);
    }

    // Borrar de una jornada en adelante, conservando lo anterior.
    //
    // Hace falta cuando hay que rehacer el calendario pero las primeras jornadas ya se
    // publicaron a los equipos: "reemplazar" borra todo y obligaría a volver a capturar a
    // mano lo que ya estaba avisado. Con esto se borra solo la parte generada y después se
    // vuelve a generar con la opción de continuar.
    if (($_POST['accion'] ?? '') === 'borrar_desde_jornada') {
        $desde = (int) ($_POST['jornada'] ?? 0);
        if ($desde < 1) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', 'Indica desde qué jornada hay que borrar.');
        }

        $aBorrar = array_values(array_filter(
            $partidos,
            fn($p) => ($p['fase'] ?? 'grupos') === 'grupos' && (int) ($p['jornada'] ?? 0) >= $desde
        ));

        // Un encuentro jugado o con ficha cargada es historia del torneo: no se borra de
        // un clic. Se avisa y el organizador decide qué hacer con él.
        $jugados = array_values(array_filter($aBorrar, fn($p) => ($p['estado'] ?? '') === 'jugado'));
        if (!empty($jugados)) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', 'No se borró nada: hay ' . count($jugados) . ' encuentro(s) ya jugados desde la jornada ' . $desde . '. Reábrelos o bórralos uno por uno si de verdad quieres rehacer esa parte.');
        }
        $conFicha = [];
        foreach ($aBorrar as $p) {
            if (!empty(db_leer_eventos_partido($torneo['id'], (int) $p['id']))) {
                $conFicha[] = (int) $p['id'];
            }
        }
        if (!empty($conFicha)) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', 'No se borró nada: hay ' . count($conFicha) . ' encuentro(s) con eventos cargados en su ficha desde la jornada ' . $desde . '.');
        }

        if (empty($aBorrar)) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', 'No hay encuentros desde la jornada ' . $desde . '.');
        }

        $ids = array_map(fn($p) => (int) $p['id'], $aBorrar);
        $partidos = array_values(array_filter($partidos, fn($p) => !in_array((int) $p['id'], $ids, true)));
        partidos_guardar_todos($partidos, $torneo['id']);
        foreach ($ids as $idBorrado) {
            db_guardar_eventos_partido($torneo['id'], $idBorrado, []);
        }

        bitacora_registrar('calendario_borrado_parcial', count($ids) . ' encuentros borrados desde la jornada ' . $desde, $torneo['id']);
        redirigir_con_mensaje(url('admin/partidos.php'), 'success', 'Se borraron ' . count($ids) . ' encuentros desde la jornada ' . $desde . '. Lo anterior quedó intacto: ahora puedes generar de nuevo con la opción de conservar lo que ya existe.');
    }

    // Correr el calendario a partir de una jornada. Cuando un fin de semana se cae (un
    // feriado que se confirmó tarde, una cancha que no prestaron), no sirve mover ese
    // partido solo: hay que empujar esa jornada y TODAS las siguientes, o se le encima a
    // la que venía atrás. Los encuentros ya jugados no se tocan nunca.
    if (($_POST['accion'] ?? '') === 'correr_calendario') {
        $desdeJornada = (int) ($_POST['jornada'] ?? 0);
        $semanas = (int) ($_POST['semanas'] ?? 1);

        if ($desdeJornada < 1) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', 'Indica desde qué jornada hay que correr el calendario.');
        }
        if ($semanas === 0 || $semanas < -20 || $semanas > 20) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', 'Se puede correr entre 1 y 20 semanas, hacia adelante o hacia atrás.');
        }

        $dias = $semanas * 7;
        $movidos = 0;
        $respetados = 0;

        foreach ($partidos as &$p) {
            if (($p['fase'] ?? 'grupos') !== 'grupos' || (int) ($p['jornada'] ?? 0) < $desdeJornada) {
                continue;
            }
            // Un encuentro ya jugado es historia: cambiarle la fecha falsearía el registro
            // de cuándo se disputó y descuadraría las suspensiones, que se calculan por
            // orden de partidos.
            if (($p['estado'] ?? '') === 'jugado') {
                $respetados++;
                continue;
            }
            $ts = strtotime((string) ($p['fecha'] ?? ''));
            if ($ts === false) {
                continue;
            }
            // En días y no sumando segundos, para que un cambio de horario no corra la fecha.
            $nuevo = strtotime(($dias >= 0 ? '+' : '-') . abs($dias) . ' days', $ts);
            if ($nuevo === false) {
                continue;
            }
            $p['fecha'] = date('Y-m-d', $nuevo);
            $movidos++;
        }
        unset($p);

        if ($movidos === 0) {
            redirigir_con_mensaje(url('admin/partidos.php'), 'error', 'No había encuentros por mover desde la jornada ' . $desdeJornada . '.');
        }

        partidos_guardar_todos($partidos, $torneo['id']);
        $texto = abs($semanas) === 1 ? 'una semana' : abs($semanas) . ' semanas';
        $sentido = $semanas > 0 ? 'adelante' : 'atrás';
        bitacora_registrar('calendario_corrido', "Calendario corrido {$texto} hacia {$sentido} desde la jornada {$desdeJornada}: {$movidos} encuentros", $torneo['id']);

        $aviso = "Se corrieron {$movidos} encuentros {$texto} hacia {$sentido}, desde la jornada {$desdeJornada}.";
        if ($respetados > 0) {
            $aviso .= " {$respetados} ya estaban jugados y no se tocaron.";
        }
        redirigir_con_mensaje(url('admin/partidos.php'), 'success', $aviso);
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
        $soloPrevia = !empty($_POST['solo_previa']);
        // Qué hacer con los encuentros que ya existen. "Continuar" es lo que hace falta
        // cuando la primera jornada ya se publicó a los equipos: rehacer el calendario
        // cambiaría partidos ya avisados. Por eso viene marcado por defecto.
        $queHacer = (string) ($_POST['que_hacer'] ?? 'continuar');
        $reemplazar = $queHacer === 'reemplazar';
        $continuar = $queHacer === 'continuar';

        // --- Días de juego ---
        // El calendario se arma desde la realidad de la cancha: qué días se juega y cuántos
        // partidos caben en cada uno. De ahí salen las jornadas, no al revés.
        $diasConfig = [];
        foreach ((array) ($_POST['dia_activo'] ?? []) as $w) {
            $w = (int) $w;
            if (!array_key_exists($w, CALENDARIO_DIAS)) {
                continue;
            }
            $cupoDia = max(0, min(40, (int) ($_POST['dia_partidos'][$w] ?? 0)));
            if ($cupoDia < 1) {
                continue;
            }
            $diasConfig[] = [
                'dia' => $w,
                'partidos' => $cupoDia,
                'hora' => (string) ($_POST['dia_hora'][$w] ?? '09:00'),
                'intervalo' => max(0, min(480, (int) ($_POST['dia_intervalo'][$w] ?? 90))),
            ];
        }

        $canchas = array_values(array_filter(array_map('trim', explode(',', (string) ($_POST['canchas'] ?? ''))), fn($c) => $c !== ''));

        // Fechas que no se juegan (feriados, fines de semana todavía sin confirmar). Se
        // aceptan separadas por coma o por salto de línea, que es como se pegan de un chat.
        $excluidas = [];
        foreach (preg_split('/[\s,;]+/', (string) ($_POST['fechas_excluidas'] ?? '')) ?: [] as $f) {
            $f = trim($f);
            if ($f === '') {
                continue;
            }
            $ts = strtotime($f);
            if ($ts === false) {
                redirigir_con_mensaje($urlGenerar, 'error', "No entendí la fecha excluida \"{$f}\". Usa el formato 2026-10-31.");
            }
            $excluidas[] = date('Y-m-d', $ts);
        }

        $tsInicio = strtotime($fechaInicio);
        if ($fechaInicio === '' || $tsInicio === false) {
            redirigir_con_mensaje($urlGenerar, 'error', 'Indica la fecha del primer día de juego.');
        }
        if (empty($diasConfig)) {
            redirigir_con_mensaje($urlGenerar, 'error', 'Marca al menos un día de juego e indica cuántos partidos caben ese día.');
        }

        // El primer día de juego tiene que caer en uno de los días marcados: si no, todo el
        // calendario nace corrido y el organizador no entiende por qué.
        $diasMarcados = array_column($diasConfig, 'dia');
        if (!in_array((int) date('w', $tsInicio), $diasMarcados, true)) {
            $nombres = array_map(fn($d) => CALENDARIO_DIAS[$d], $diasMarcados);
            redirigir_con_mensaje($urlGenerar, 'error', 'La fecha de inicio (' . CALENDARIO_DIAS[(int) date('w', $tsInicio)] . ') no es ninguno de los días que marcaste: ' . implode(', ', $nombres) . '.');
        }

        // Los encuentros de temporada regular que ya existen. Los de eliminación directa
        // no se tocan nunca: el generador solo arma la fase de grupos/liga.
        $regularesExistentes = array_values(array_filter($partidos, fn($p) => ($p['fase'] ?? 'grupos') === 'grupos'));

        if (!empty($regularesExistentes) && !$reemplazar && !$continuar && !$soloPrevia) {
            redirigir_con_mensaje($urlGenerar, 'error', 'Ya hay ' . count($regularesExistentes) . ' encuentros de temporada regular programados. Marca la casilla de reemplazar si quieres rehacer el calendario desde cero.');
        }

        if ($reemplazar && !$soloPrevia) {
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

        // La semilla del sorteo viaja en el formulario: así la vista previa que el
        // organizador aprueba es EXACTAMENTE el calendario que se crea después, y no otro
        // sorteo distinto. Si le da a "sortear de nuevo" se genera otra.
        $semilla = (int) ($_POST['semilla'] ?? 0);
        if ($semilla < 1) {
            $semilla = random_int(1, 999999);
        }

        // En el formato de grupos, cada equipo solo juega contra los de su grupo: el
        // fixture no es el todos contra todos general sino la unión de los de cada grupo.
        $rondasPrearmadas = null;
        if (torneo_tiene_grupos($torneo)) {
            $sinGrupo = array_values(array_filter($equipos, fn($e) => trim((string) ($e['grupo'] ?? '')) === ''));
            if (!empty($sinGrupo)) {
                redirigir_con_mensaje($urlGenerar, 'error', 'Hay ' . count($sinGrupo) . ' equipo(s) sin grupo asignado. Sortea los grupos desde la pantalla de Equipos antes de generar el calendario.');
            }
            $rondasPrearmadas = grupos_rondas($equipos, torneo_num_grupos($torneo), $vueltasGenerar);
            if (empty($rondasPrearmadas)) {
                redirigir_con_mensaje($urlGenerar, 'error', 'No se pudo armar la fase de grupos: revisa que cada grupo tenga al menos 2 equipos.');
            }
        }

        // Al continuar, los cruces ya programados se sacan del fixture y la numeración de
        // jornadas arranca donde va, para no repetir partidos ni volver a la jornada 1.
        // El historial además lleva la hora, para seguir repartiendo turnos y localías
        // tomando en cuenta a quién ya le tocó qué en lo que está publicado.
        $yaProgramados = [];
        $historial = [];
        $jornadaInicial = 0;
        if ($continuar) {
            foreach ($regularesExistentes as $p) {
                $yaProgramados[] = [(int) $p['equipo_local'], (int) $p['equipo_visitante']];
                $historial[] = [
                    'local' => (int) $p['equipo_local'],
                    'visitante' => (int) $p['equipo_visitante'],
                    'hora' => (string) ($p['hora'] ?? ''),
                    'jornada' => (int) ($p['jornada'] ?? 0),
                ];
                $jornadaInicial = max($jornadaInicial, (int) ($p['jornada'] ?? 0));
            }
        }

        // --- Que la temporada aterrice en la fecha de la final ---
        //
        // La fecha_fin de la copa es el día de la final. Antes de generar se comprueba
        // con números si los partidos caben en los fines de semana que quedan hasta ahí
        // (descontando los que ocupan los playoffs). Si no caben se corta AQUÍ, diciendo
        // cuántos hacen falta, en vez de generar un calendario que se pasa de la fecha y
        // que el organizador descubriría partido por partido.
        $fechaFinLiga = trim((string) ($torneo['fecha_fin'] ?? ''));
        $bloquesPlayoffs = 0;
        if ($fechaFinLiga !== '') {
            $bloquesPlayoffs = calendario_bloques_playoffs($torneo, $diasConfig, $excluidas);
            $totalPartidos = calendario_contar_partidos($equipoIds, $vueltasGenerar, $rondasPrearmadas, $yaProgramados);
            $cupoFinDeSemana = array_sum(array_map(fn($d) => max(0, (int) ($d['partidos'] ?? 0)), $diasConfig));
            $bloquesHastaFinal = calendario_bloques_hasta(
                $fechaInicio,
                array_map(fn($d) => (int) ($d['dia'] ?? 0), $diasConfig),
                $fechaFinLiga,
                $excluidas
            );
            $jornadasDisponibles = $bloquesHastaFinal - $bloquesPlayoffs;

            if ($totalPartidos > 0 && ($jornadasDisponibles < 1 || $totalPartidos > $jornadasDisponibles * max(1, $cupoFinDeSemana))) {
                $necesarios = $jornadasDisponibles >= 1 ? (int) ceil($totalPartidos / $jornadasDisponibles) : 0;
                redirigir_con_mensaje($urlGenerar, 'error',
                    "No alcanzan los fines de semana: hay {$totalPartidos} partidos por programar y solo "
                    . max(0, $jornadasDisponibles) . " fines de semana antes de los playoffs, que terminan el "
                    . formatear_fecha_corta($fechaFinLiga) . '.'
                    . ($necesarios > 0
                        ? " Harían falta {$necesarios} partidos por fin de semana (ahora caben {$cupoFinDeSemana})."
                        : ' Mueve la fecha de cierre de la liga o la fecha de inicio.')
                );
            }
        }

        $calendarioGenerado = calendario_generar($equipoIds, [
            'vueltas' => $vueltasGenerar,
            'dias' => $diasConfig,
            'canchas' => $canchas,
            'fecha_inicio' => $fechaInicio,
            'excluidas' => $excluidas,
            'semilla' => $semilla,
            'rondas' => $rondasPrearmadas,
            'ya_programados' => $yaProgramados,
            'historial' => $historial,
            'jornada_inicial' => $jornadaInicial,
            'fecha_fin' => $fechaFinLiga,
            'bloques_playoffs' => $bloquesPlayoffs,
        ]);

        if (empty($calendarioGenerado)) {
            redirigir_con_mensaje($urlGenerar, 'error', 'No se pudo armar el calendario con esos equipos y esos días. Revisa que los cupos por día sean mayores que cero.');
        }

        // --- Vista previa: se muestra la tabla y no se toca la base ---
        if ($soloPrevia) {
            $previa = calendario_resumen($calendarioGenerado, $equiposPorId);
            $previa['semilla'] = $semilla;
            // '' = la copa no tiene fecha de cierre (la vista lo advierte); con valor, la
            // vista confirma que la final aterriza ese día.
            $previa['fecha_final'] = $fechaFinLiga;
            $previa['playoffs'] = calendario_previa_playoffs($torneo, $calendarioGenerado, $diasConfig, $excluidas);
            $accion = 'generar';
            $datosPrevios = $_POST;
        } else {
            // Al continuar NO se borra nada: los encuentros existentes se quedan como están.
            if ($reemplazar && !$continuar) {
                // No hace falta limpiar fichas: la validación de arriba ya garantizó que
                // ninguno de los encuentros que se van tiene eventos cargados.
                $partidos = array_values(array_filter($partidos, fn($p) => ($p['fase'] ?? 'grupos') !== 'grupos'));
            }

            $siguienteId = partido_nuevo_id();
            $totalCreados = 0;
            $totalAdelantados = 0;

            foreach ($calendarioGenerado as $jornada) {
                foreach ($jornada['dias'] as $dia) {
                    foreach ($dia['partidos'] as $p) {
                        $partidos[] = [
                            'id' => $siguienteId++,
                            'jornada' => $jornada['numero'],
                            'equipo_local' => $p['local'],
                            'equipo_visitante' => $p['visitante'],
                            'fecha' => $dia['fecha'],
                            'hora' => $p['hora'],
                            'cancha' => $p['cancha'],
                            'estado' => 'programado',
                            'marcador_local' => null,
                            'marcador_visitante' => null,
                            'fase' => 'grupos',
                            'arbitro' => '',
                            // Queda anotado en el propio encuentro para que el organizador
                            // sepa por qué esos dos equipos juegan dos veces ese fin de
                            // semana, y no parezca un error del calendario.
                            'observaciones' => !empty($p['adelantado']) ? 'Partido adelantado: estos dos equipos ya jugaron antes en esta misma jornada.' : '',
                            // Columnas NOT NULL del cronómetro: un encuentro nuevo las necesita
                            // explícitas (mismo motivo que al programar uno a mano).
                            'cronometro_estado' => 'detenido',
                            'cronometro_inicio' => null,
                            'cronometro_segundos' => 0,
                            'cronometro_periodo' => 1,
                            'cronometro_extra_min' => 0,
                        ];
                        $totalCreados++;
                        if (!empty($p['adelantado'])) {
                            $totalAdelantados++;
                        }
                    }
                }
            }

            partidos_guardar_todos($partidos, $torneo['id']);

            // Se recuerda cómo juega esta copa. Sin esto, al armar cuartos y semis la app
            // ya no sabía que se juega sábado y domingo, a qué hora ni qué fechas están
            // excluidas, y los cruces nacían todos el mismo día y sin hora.
            torneos_guardar(array_merge($torneo, [
                'calendario_config' => calendario_config_serializar($diasConfig, $canchas, $excluidas),
            ]));

            $textoVueltas = $vueltasGenerar === 2 ? 'ida y vuelta' : 'una vuelta';
            $nombresDias = implode(' y ', array_map(fn($d) => mb_strtolower(CALENDARIO_DIAS[$d['dia']]), $diasConfig));
            bitacora_registrar(
                'calendario_generado',
                "Calendario generado: {$totalCreados} encuentros en " . count($calendarioGenerado) . " jornadas ({$textoVueltas}, " . count($equipoIds) . " equipos, se juega {$nombresDias}, {$totalAdelantados} partidos adelantados)",
                $torneo['id']
            );

            $aviso = "Calendario generado: {$totalCreados} encuentros en " . count($calendarioGenerado) . ' jornadas.';
            if ($totalAdelantados > 0) {
                $aviso .= " {$totalAdelantados} son partidos adelantados para llenar el cupo del fin de semana.";
            }
            if (!empty($excluidas)) {
                $aviso .= ' Se saltaron ' . count($excluidas) . ' fecha(s) excluida(s).';
            }
            // Que quede claro si el calendario aterrizó en la fecha de la final o si
            // generó "al aire". Sin este aviso, una copa sin fecha de cierre configurada
            // generaba sin ancla y el organizador no tenía forma de saberlo.
            if ($fechaFinLiga !== '') {
                $aviso .= ' La temporada está repartida para cerrar con la final el ' . formatear_fecha_corta($fechaFinLiga) . '.';
            } else {
                $aviso .= ' Ojo: esta copa no tiene fecha de cierre configurada, así que el calendario termina cuando se acaban los partidos. Ponle fecha de fin en la configuración de la copa para que la final aterrice en un día exacto.';
            }
            redirigir_con_mensaje(url('admin/partidos.php'), 'success', $aviso);
        }
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

// Vista previa del calendario. Solo tiene contenido cuando el organizador acaba de pedir
// "ver previa": el resto del tiempo el formulario se muestra vacío.
$previa = $previa ?? null;
$datosPrevios = $datosPrevios ?? [];

// Fase de grupos: tablas por grupo y si ya se puede armar el cuadro.
$tieneGrupos = torneo_tiene_grupos($torneo);
$tablasGrupo = $tieneGrupos ? grupos_tablas($equipos, $partidos, $torneo) : [];
$gruposPendientes = $tieneGrupos
    ? count(array_filter($partidos, fn($p) => ($p['fase'] ?? 'grupos') === 'grupos' && ($p['estado'] ?? '') !== 'jugado'))
    : 0;

// Qué ronda del cuadro se puede armar ahora. Vale para los dos formatos con fase final.
$pasoEliminacion = !empty($fasesTorneo)
    ? eliminacion_siguiente_paso($torneo, $partidos, $equiposPorId)
    : ['fase' => null, 'label' => '', 'origen' => null, 'listo' => false, 'motivo' => ''];
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
    'datosPrevios',
    'equipos',
    'equiposPorId',
    'errores',
    'gruposPendientes',
    'pasoEliminacion',
    'previa',
    'tablasGrupo',
    'tieneGrupos',
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
