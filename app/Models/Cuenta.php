<?php
declare(strict_types=1);

/**
 * La cuenta de cada equipo con la liga.
 *
 * Qué resuelve
 * ------------
 * En una liga amateur el dinero se lleva en un cuaderno o en la cabeza del organizador:
 * quién pagó la inscripción, a quién le falta el arbitraje de la fecha pasada, cuánto
 * junta un capitán entre las multas de sus jugadores. Cuando alguien reclama, no hay
 * nada que enseñar.
 *
 * Cómo está armado
 * ----------------
 * Es un libro de movimientos, no un saldo guardado. Cada cargo y cada pago es un renglón
 * con fecha, concepto y de dónde salió; el saldo se suma desde ahí cada vez. Un saldo
 * guardado se desincroniza en cuanto alguien corrige un movimiento viejo, y a partir de
 * ese momento hay dos números y nadie sabe cuál es el bueno.
 *
 * Las multas de tarjetas NO se copian aquí: siguen viviendo en la tabla de sanciones, que
 * es la que bloquea al jugador en la nómina. Si la liga configuró que las multas suman al
 * equipo, se leen de allá y se suman al saldo al momento de mostrarlo. Copiarlas sería
 * tener el mismo dato en dos lugares, y bastaría cobrar una multa para que las dos
 * versiones dejaran de coincidir.
 */

const MOVIMIENTO_CARGO = 'cargo';
const MOVIMIENTO_PAGO = 'pago';

// De dónde nació el movimiento. Los dos primeros los genera la app sola y no se pueden
// repetir (índice único en la base); los otros dos los mete una persona.
const MOVIMIENTO_ORIGENES = [
    'inscripcion' => 'Inscripción',
    'arbitraje' => 'Arbitraje',
    'manual' => 'Cargo manual',
    'pago' => 'Pago recibido',
];

function movimiento_origen_nombre(string $origen): string
{
    return MOVIMIENTO_ORIGENES[$origen] ?? $origen;
}

/**
 * Movimientos de una copa, o de un solo equipo. Del más reciente al más viejo.
 *
 * @return array<int, array>
 */
function movimientos_listar(int $torneoId, ?int $equipoId = null): array
{
    try {
        $pdo = db_conexion();
        $sql = 'SELECT * FROM movimientos_equipo WHERE torneo_id = :torneo';
        if ($equipoId !== null) {
            $sql .= ' AND equipo_id = :equipo';
        }
        $sql .= ' ORDER BY fecha DESC, id DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':torneo', $torneoId, PDO::PARAM_INT);
        if ($equipoId !== null) {
            $stmt->bindValue(':equipo', $equipoId, PDO::PARAM_INT);
        }
        $stmt->execute();

        return array_map(static fn(array $f): array => [
            'id' => (int) $f['id'],
            'equipo_id' => (int) $f['equipo_id'],
            'tipo' => (string) $f['tipo'],
            'origen' => (string) $f['origen'],
            'referencia' => $f['referencia'] !== null ? (int) $f['referencia'] : null,
            'concepto' => (string) $f['concepto'],
            'monto' => (float) $f['monto'],
            'fecha' => (string) $f['fecha'],
            'nota' => (string) $f['nota'],
        ], $stmt->fetchAll());
    } catch (Throwable $e) {
        error_log('movimientos_listar: ' . $e->getMessage());
        return [];
    }
}

/**
 * Anota un movimiento. Devuelve false si ya existía uno automático igual.
 *
 * Los cargos automáticos chocan contra el índice único de la base cuando se intentan
 * repetir; eso NO es un error que haya que mostrar, es exactamente lo que se quiere (que
 * darle dos veces al botón de generar no cobre dos veces), así que se traga en silencio.
 */
function movimiento_registrar(
    int $torneoId,
    int $equipoId,
    string $tipo,
    string $origen,
    string $concepto,
    float $monto,
    ?int $referencia = null,
    string $nota = '',
    ?string $fecha = null
): bool {
    try {
        $pdo = db_conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO movimientos_equipo
                (torneo_id, equipo_id, tipo, origen, referencia, concepto, monto, fecha, nota, creado_por)
             VALUES (:torneo, :equipo, :tipo, :origen, :referencia, :concepto, :monto, :fecha, :nota, :usuario)'
        );
        $stmt->bindValue(':torneo', $torneoId, PDO::PARAM_INT);
        $stmt->bindValue(':equipo', $equipoId, PDO::PARAM_INT);
        $stmt->bindValue(':tipo', $tipo === MOVIMIENTO_PAGO ? MOVIMIENTO_PAGO : MOVIMIENTO_CARGO);
        $stmt->bindValue(':origen', $origen);
        db_bind($stmt, ':referencia', $referencia);
        $stmt->bindValue(':concepto', mb_substr($concepto, 0, 160));
        $stmt->bindValue(':monto', number_format(abs($monto), 2, '.', ''));
        $stmt->bindValue(':fecha', $fecha ?: date('Y-m-d'));
        $stmt->bindValue(':nota', mb_substr($nota, 0, 200));
        db_bind($stmt, ':usuario', !empty($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null);

        return $stmt->execute();
    } catch (Throwable $e) {
        // 23505 = clave duplicada: el cargo automático ya estaba puesto.
        if (str_contains($e->getMessage(), '23505')) {
            return false;
        }
        error_log('movimiento_registrar: ' . $e->getMessage());
        return false;
    }
}

/**
 * Borra un movimiento. Solo se usa para deshacer una equivocación reciente; la corrección
 * normal es anotar el movimiento contrario, que deja rastro.
 */
function movimiento_eliminar(int $id, int $torneoId): bool
{
    try {
        $pdo = db_conexion();
        // El torneo va en el WHERE para que nadie borre movimientos de otra copa mandando
        // un id ajeno en el formulario.
        $stmt = $pdo->prepare('DELETE FROM movimientos_equipo WHERE id = :id AND torneo_id = :torneo');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->bindValue(':torneo', $torneoId, PDO::PARAM_INT);

        return $stmt->execute();
    } catch (Throwable $e) {
        error_log('movimiento_eliminar: ' . $e->getMessage());
        return false;
    }
}

/**
 * Los ids de partido que ya tienen cobrado el arbitraje, por equipo.
 *
 * @return array<int, array<int, bool>> equipo_id => [partido_id => true]
 */
function movimientos_arbitrajes_cobrados(array $movimientos): array
{
    $cobrados = [];
    foreach ($movimientos as $m) {
        if (($m['origen'] ?? '') === 'arbitraje' && $m['referencia'] !== null) {
            $cobrados[(int) $m['equipo_id']][(int) $m['referencia']] = true;
        }
    }

    return $cobrados;
}

function movimientos_tiene_inscripcion(array $movimientos, int $equipoId): bool
{
    foreach ($movimientos as $m) {
        if ((int) $m['equipo_id'] === $equipoId && ($m['origen'] ?? '') === 'inscripcion') {
            return true;
        }
    }

    return false;
}
