<?php
declare(strict_types=1);

require_once __DIR__ . '/../Support/bd.php';

// Alineación de un encuentro: titulares, banca y posición de cada jugador.
// Modelo: solo acceso a datos. No imprime nada ni sabe de HTTP; de eso se encargan
// los controladores (app/Controllers) y las vistas (app/Views).

/**
 * Alineación de un encuentro (quién es titular, quién banca y en qué posición juega cada
 * uno). Mismo criterio que los eventos: DELETE+INSERT acotado a un partido puntual, con
 * torneo_id y partido_id forzados desde los parámetros y nunca desde el array.
 *
 * La fila no lleva id propio: la llave es (partido_id, jugador_id) — un jugador aparece
 * una sola vez en la alineación de un partido.
 */
const COLUMNAS_ALINEACION = ['torneo_id', 'partido_id', 'jugador_id', 'equipo_id', 'titular', 'posicion'];

function db_leer_alineacion(int $torneoId, int $partidoId): array
{
    try {
        $pdo = db_conexion();
        $stmt = $pdo->prepare('SELECT * FROM partido_alineacion WHERE torneo_id = :torneo_id AND partido_id = :partido_id');
        $stmt->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
        $stmt->bindValue(':partido_id', $partidoId, PDO::PARAM_INT);
        $stmt->execute();
        return array_map(function (array $fila) {
            $fila['jugador_id'] = (int) $fila['jugador_id'];
            $fila['equipo_id'] = (int) $fila['equipo_id'];
            $fila['partido_id'] = (int) $fila['partido_id'];
            $fila['titular'] = is_string($fila['titular']) ? ($fila['titular'] === 't' || $fila['titular'] === '1') : (bool) $fila['titular'];
            return $fila;
        }, $stmt->fetchAll());
    } catch (Throwable $e) {
        // La tabla puede no existir todavía en una base que aún no corrió la migración:
        // sin alineación el resto de la ficha debe seguir funcionando igual.
        error_log('db_leer_alineacion: ' . $e->getMessage());
        return [];
    }
}

function db_guardar_alineacion(int $torneoId, int $partidoId, array $filas): bool
{
    $pdo = db_conexion();
    $marcadores = array_map(fn($c) => ":{$c}", COLUMNAS_ALINEACION);
    $sql = sprintf(
        'INSERT INTO partido_alineacion (%s) VALUES (%s)',
        implode(', ', COLUMNAS_ALINEACION),
        implode(', ', $marcadores)
    );

    $pdo->beginTransaction();
    try {
        $stmtBorrar = $pdo->prepare('DELETE FROM partido_alineacion WHERE torneo_id = :torneo_id AND partido_id = :partido_id');
        $stmtBorrar->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
        $stmtBorrar->bindValue(':partido_id', $partidoId, PDO::PARAM_INT);
        $stmtBorrar->execute();

        $stmt = $pdo->prepare($sql);
        foreach ($filas as $fila) {
            foreach (COLUMNAS_ALINEACION as $col) {
                $valor = match ($col) {
                    'torneo_id' => $torneoId,
                    'partido_id' => $partidoId,
                    // Con prepares emulados Postgres no castea 0/1 a boolean (mismo caso
                    // que jugadores.activo): hay que mandarle el texto 'true'/'false'.
                    'titular' => !empty($fila['titular']) ? 'true' : 'false',
                    default => $fila[$col] ?? null,
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
