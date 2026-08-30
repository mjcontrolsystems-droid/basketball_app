<?php
declare(strict_types=1);

/**
 * Sanciones disciplinarias: la multa que genera cada tarjeta y su cobro.
 *
 * Regla del negocio (ligas amateur): un jugador amonestado debe pagar la multa antes de
 * volver a jugar. Mientras no pague queda "en deuda" y la app lo marca como NO habilitado
 * para la alineación del siguiente encuentro.
 *
 * Cada sanción nace de un evento de tarjeta (partido_eventos) y guarda el monto que regía
 * en ese momento, no el actual: si la liga sube la tarifa a mitad de temporada, las multas
 * viejas conservan su precio original.
 *
 * Estados:
 *   'pendiente'  debe pagar, bloquea al jugador
 *   'pagada'     ya pagó, habilitado, suma al dinero recaudado
 *   'condonada'  el organizador la perdonó: habilitado, pero NO suma al recaudo
 */

const SANCION_PENDIENTE = 'pendiente';
const SANCION_PAGADA = 'pagada';
const SANCION_CONDONADA = 'condonada';

/**
 * Crea la sanción de una tarjeta. $tipo es 'amarilla' o 'roja'; el monto sale de las
 * tarifas de la copa. Si la tarifa es 0 no se crea nada (esa liga no cobra ese tipo).
 */
function sancion_crear_por_evento(int $torneoId, array $evento, array $torneo): void
{
    $tipo = (string) ($evento['tipo'] ?? '');
    if (!in_array($tipo, ['amarilla', 'roja'], true)) {
        return;
    }
    $jugadorId = (int) ($evento['jugador_id'] ?? 0);
    if ($jugadorId <= 0) {
        return;
    }

    $monto = $tipo === 'roja'
        ? (float) ($torneo['multa_roja'] ?? 0)
        : (float) ($torneo['multa_amarilla'] ?? 0);
    if ($monto <= 0) {
        return;
    }

    try {
        $pdo = db_conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO sanciones (torneo_id, evento_id, partido_id, jugador_id, equipo_id, tipo, monto, estado)
             VALUES (:torneo_id, :evento_id, :partido_id, :jugador_id, :equipo_id, :tipo, :monto, :estado)
             ON CONFLICT (evento_id) DO NOTHING'
        );
        $stmt->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
        $stmt->bindValue(':evento_id', (int) $evento['id'], PDO::PARAM_INT);
        $stmt->bindValue(':partido_id', (int) $evento['partido_id'], PDO::PARAM_INT);
        $stmt->bindValue(':jugador_id', $jugadorId, PDO::PARAM_INT);
        $stmt->bindValue(':equipo_id', (int) ($evento['equipo_id'] ?? 0), PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo, PDO::PARAM_STR);
        $stmt->bindValue(':monto', number_format($monto, 2, '.', ''), PDO::PARAM_STR);
        $stmt->bindValue(':estado', SANCION_PENDIENTE, PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('sancion_crear_por_evento: ' . $e->getMessage());
    }
}

/**
 * Al borrar el evento de una tarjeta se borra su sanción, PERO solo si sigue pendiente:
 * una multa ya cobrada es un movimiento de dinero y no debe desaparecer del registro
 * porque alguien corrija la ficha del partido.
 *
 * @return bool true si se borró; false si se conservó por estar pagada/condonada.
 */
function sancion_borrar_por_evento(int $eventoId): bool
{
    try {
        $pdo = db_conexion();
        $stmt = $pdo->prepare('DELETE FROM sanciones WHERE evento_id = :evento_id AND estado = :estado');
        $stmt->bindValue(':evento_id', $eventoId, PDO::PARAM_INT);
        $stmt->bindValue(':estado', SANCION_PENDIENTE, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
        error_log('sancion_borrar_por_evento: ' . $e->getMessage());
        return false;
    }
}

/**
 * Todas las sanciones de la copa, de la más reciente a la más antigua.
 * $soloEstado filtra por estado ('pendiente', 'pagada', 'condonada').
 */
function sanciones_listar(int $torneoId, string $soloEstado = ''): array
{
    try {
        $pdo = db_conexion();
        $sql = 'SELECT * FROM sanciones WHERE torneo_id = :torneo_id';
        if ($soloEstado !== '') {
            $sql .= ' AND estado = :estado';
        }
        $sql .= ' ORDER BY creada_en DESC, id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
        if ($soloEstado !== '') {
            $stmt->bindValue(':estado', $soloEstado, PDO::PARAM_STR);
        }
        $stmt->execute();
        return array_map(function (array $f): array {
            $f['id'] = (int) $f['id'];
            $f['jugador_id'] = (int) $f['jugador_id'];
            $f['equipo_id'] = (int) $f['equipo_id'];
            $f['partido_id'] = (int) $f['partido_id'];
            $f['monto'] = (float) $f['monto'];
            return $f;
        }, $stmt->fetchAll());
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * IDs de los jugadores con al menos una sanción pendiente: es la lista de "no
 * habilitados" que consulta la hoja de solvencia y el armado de alineaciones.
 *
 * @return array<int,array{total:float,cantidad:int}> jugador_id => resumen de su deuda
 */
function sanciones_deuda_por_jugador(int $torneoId): array
{
    $deuda = [];
    foreach (sanciones_listar($torneoId, SANCION_PENDIENTE) as $s) {
        $jid = $s['jugador_id'];
        if (!isset($deuda[$jid])) {
            $deuda[$jid] = ['total' => 0.0, 'cantidad' => 0];
        }
        $deuda[$jid]['total'] += $s['monto'];
        $deuda[$jid]['cantidad']++;
    }
    return $deuda;
}

/**
 * Deuda VIGENTE para una jornada: solo las multas nacidas en jornadas ANTERIORES.
 *
 * La regla de la liga: la multa de la jornada 2 se paga antes de la jornada 3. En la
 * hoja de solvencia de la jornada 2 ese jugador todavía está limpio — la tarjeta ocurrió
 * jugando esa misma fecha y nadie puede exigir un pago que aún no vence. Sin este corte,
 * la hoja lo marcaba moroso retroactivamente en la propia jornada de la falta (y en las
 * ya jugadas), que era falso.
 *
 * @param array<int,array> $partidos Para resolver en qué jornada nació cada multa.
 * @return array<int, array{total: float, cantidad: int}> jugador_id => deuda exigible.
 */
function sanciones_deuda_vigente_para_jornada(int $torneoId, array $partidos, int $jornadaTope): array
{
    $jornadaDePartido = [];
    foreach ($partidos as $p) {
        $jornadaDePartido[(int) $p['id']] = (int) ($p['jornada'] ?? 0);
    }

    $deuda = [];
    foreach (sanciones_listar($torneoId, SANCION_PENDIENTE) as $s) {
        $jornadaSancion = $jornadaDePartido[(int) ($s['partido_id'] ?? 0)] ?? 0;
        // Solo es exigible la multa de una jornada estrictamente anterior. Una sanción
        // cuyo partido no se encuentra (0) se cobra siempre: mejor exigir de más un caso
        // raro que dejar pasar a un moroso por un dato roto.
        if ($jornadaSancion !== 0 && $jornadaSancion >= $jornadaTope) {
            continue;
        }
        $jid = $s['jugador_id'];
        if (!isset($deuda[$jid])) {
            $deuda[$jid] = ['total' => 0.0, 'cantidad' => 0];
        }
        $deuda[$jid]['total'] += $s['monto'];
        $deuda[$jid]['cantidad']++;
    }
    return $deuda;
}

/**
 * Marca una sanción como pagada o condonada, dejando constancia de cuándo y quién la
 * registró (para poder responder un "yo sí pagué" semanas después).
 */
function sancion_actualizar_estado(int $id, int $torneoId, string $estado, string $nota = ''): bool
{
    if (!in_array($estado, [SANCION_PENDIENTE, SANCION_PAGADA, SANCION_CONDONADA], true)) {
        return false;
    }
    try {
        $pdo = db_conexion();
        $stmt = $pdo->prepare(
            'UPDATE sanciones
                SET estado = :estado,
                    nota = :nota,
                    cobrada_en = CASE WHEN :estado2 = \'pendiente\' THEN NULL ELSE now() END,
                    cobrada_por = CASE WHEN :estado3 = \'pendiente\' THEN NULL ELSE :usuario END
              WHERE id = :id AND torneo_id = :torneo_id'
        );
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':estado2', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':estado3', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':nota', mb_substr($nota, 0, 200), PDO::PARAM_STR);
        db_bind($stmt, ':usuario', !empty($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
        return $stmt->execute();
    } catch (Throwable $e) {
        error_log('sancion_actualizar_estado: ' . $e->getMessage());
        return false;
    }
}

/**
 * Cobra de una vez todas las pendientes de un equipo (el capitán paga por todos).
 * Devuelve cuántas se cobraron.
 */
function sanciones_cobrar_equipo(int $torneoId, int $equipoId, string $nota = ''): int
{
    $cobradas = 0;
    foreach (sanciones_listar($torneoId, SANCION_PENDIENTE) as $s) {
        if ($s['equipo_id'] === $equipoId && sancion_actualizar_estado($s['id'], $torneoId, SANCION_PAGADA, $nota)) {
            $cobradas++;
        }
    }
    return $cobradas;
}

/**
 * Corte de caja de la copa: cuánto se ha cobrado, cuánto falta y cuánto se condonó.
 *
 * @return array{recaudado:float, pendiente:float, condonado:float, cantidad_pendiente:int}
 */
function sanciones_resumen(int $torneoId): array
{
    $r = ['recaudado' => 0.0, 'pendiente' => 0.0, 'condonado' => 0.0, 'cantidad_pendiente' => 0];
    foreach (sanciones_listar($torneoId) as $s) {
        if ($s['estado'] === SANCION_PAGADA) {
            $r['recaudado'] += $s['monto'];
        } elseif ($s['estado'] === SANCION_CONDONADA) {
            $r['condonado'] += $s['monto'];
        } else {
            $r['pendiente'] += $s['monto'];
            $r['cantidad_pendiente']++;
        }
    }
    return $r;
}

/**
 * Tarifa configurada de la copa para cada tipo de tarjeta (0 = esa liga no cobra).
 */
function torneo_multa(array $torneo, string $tipo): float
{
    return $tipo === 'roja'
        ? (float) ($torneo['multa_roja'] ?? 0)
        : (float) ($torneo['multa_amarilla'] ?? 0);
}

/**
 * true si la copa cobra multas (alguna de las dos tarifas es mayor que cero). Cuando es
 * false, toda la función de sanciones queda oculta: una liga que no cobra no debería ver
 * pantallas ni avisos de dinero.
 */
function torneo_cobra_multas(array $torneo): bool
{
    return torneo_multa($torneo, 'amarilla') > 0 || torneo_multa($torneo, 'roja') > 0;
}

/**
 * true si la copa BLOQUEA al moroso en la alineación; false = solo advertir y dejar
 * que el organizador decida (algunas ligas cobran en la cancha antes del pitazo).
 */
function torneo_bloquea_morosos(array $torneo): bool
{
    return !empty($torneo['sancion_bloquea']);
}

/**
 * Símbolo de moneda de la copa, para no asumir quetzales en todos lados.
 */
function torneo_moneda(array $torneo): string
{
    $m = trim((string) ($torneo['moneda'] ?? ''));
    return $m !== '' ? $m : 'Q';
}

/**
 * Formatea un monto con la moneda de la copa: "Q75.00".
 */
function sancion_monto_texto(array $torneo, float $monto): string
{
    return torneo_moneda($torneo) . number_format($monto, 2);
}
