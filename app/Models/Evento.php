<?php
declare(strict_types=1);

require_once __DIR__ . '/../Support/bd.php';

// Ficha de eventos de un partido: goles/puntos, tarjetas/faltas y cambios.
// Modelo: solo acceso a datos. No imprime nada ni sabe de HTTP; de eso se encargan
// los controladores (app/Controllers) y las vistas (app/Views).

/**
 * partido_eventos NO usa db_leer()/db_guardar_coleccion(): esas funciones borran y reescriben
 * TODA la tabla del torneo, lo que aquí sería reescribir los eventos de TODOS los partidos de
 * la temporada cada vez que se agrega un solo gol o tarjeta a UNO de ellos (riesgo de choque si
 * hay dos partidos editándose a la vez, y un costo innecesario). En su lugar, estas dos funciones
 * acotan el DELETE+INSERT a un partido puntual, forzando torneo_id y partido_id siempre desde los
 * parámetros (nunca del array), mismo principio de seguridad que db_guardar_coleccion().
 */
function db_leer_eventos_partido(int $torneoId, int $partidoId): array
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT * FROM partido_eventos WHERE torneo_id = :torneo_id AND partido_id = :partido_id ORDER BY id');
    $stmt->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
    $stmt->bindValue(':partido_id', $partidoId, PDO::PARAM_INT);
    $stmt->execute();
    $filas = $stmt->fetchAll();
    return array_map(fn($fila) => db_normalizar_fila('partido_eventos', $fila), $filas);
}

function db_guardar_eventos_partido(int $torneoId, int $partidoId, array $eventos): bool
{
    $pdo = db_conexion();
    $columnas = COLUMNAS_POR_TABLA['partido_eventos'];
    $marcadores = array_map(fn($c) => ":{$c}", $columnas);
    $sql = sprintf(
        'INSERT INTO partido_eventos (%s) VALUES (%s)',
        implode(', ', $columnas),
        implode(', ', $marcadores)
    );

    $pdo->beginTransaction();
    try {
        $stmtBorrar = $pdo->prepare('DELETE FROM partido_eventos WHERE torneo_id = :torneo_id AND partido_id = :partido_id');
        $stmtBorrar->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
        $stmtBorrar->bindValue(':partido_id', $partidoId, PDO::PARAM_INT);
        $stmtBorrar->execute();

        $stmt = $pdo->prepare($sql);
        foreach ($eventos as $evento) {
            foreach ($columnas as $col) {
                $valor = match ($col) {
                    'torneo_id' => $torneoId,
                    'partido_id' => $partidoId,
                    default => $evento[$col] ?? null,
                };
                db_bind($stmt, ":{$col}", $valor);
            }
            $stmt->execute();
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return true;
}

/**
 * Todos los eventos de la copa (no de un solo partido): lo usan la tabla de posiciones
 * (para sumar tarjetas por equipo) y el ranking de goleadores.
 */
function eventos_de_torneo(int $torneoId): array
{
    return db_leer('partido_eventos', $torneoId);
}

function evento_nuevo_id(): int
{
    return db_siguiente_id_global('partido_eventos');
}
