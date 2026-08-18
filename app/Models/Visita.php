<?php
declare(strict_types=1);

require_once __DIR__ . '/../Support/bd.php';

// Contador agregado (por día) de visitas al sitio público de cada copa.
// Modelo: solo acceso a datos. No imprime nada ni sabe de HTTP; de eso se encargan
// los controladores (app/Controllers) y las vistas (app/Views).

/**
 * Suma una visita de hoy al sitio público de la copa (contador agregado por día).
 */
function visitas_registrar(int $torneoId): void
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare(
        'INSERT INTO visitas_diarias (torneo_id, fecha, visitas) VALUES (:torneo_id, CURRENT_DATE, 1)
         ON CONFLICT (torneo_id, fecha) DO UPDATE SET visitas = visitas_diarias.visitas + 1'
    );
    $stmt->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * Resumen de visitas de una copa para el dashboard del organizador:
 * hoy, últimos 7 días y total histórico.
 *
 * @return array{hoy:int, semana:int, total:int}
 */
function visitas_resumen(int $torneoId): array
{
    try {
        $pdo = db_conexion();
        $stmt = $pdo->prepare(
            "SELECT
                COALESCE(SUM(visitas) FILTER (WHERE fecha = CURRENT_DATE), 0) AS hoy,
                COALESCE(SUM(visitas) FILTER (WHERE fecha > CURRENT_DATE - 7), 0) AS semana,
                COALESCE(SUM(visitas), 0) AS total
             FROM visitas_diarias WHERE torneo_id = :torneo_id"
        );
        $stmt->bindValue(':torneo_id', $torneoId, PDO::PARAM_INT);
        $stmt->execute();
        $fila = $stmt->fetch();
        return [
            'hoy' => (int) ($fila['hoy'] ?? 0),
            'semana' => (int) ($fila['semana'] ?? 0),
            'total' => (int) ($fila['total'] ?? 0),
        ];
    } catch (Throwable $e) {
        // Sin la tabla (migración pendiente) el dashboard muestra ceros en vez de caerse.
        return ['hoy' => 0, 'semana' => 0, 'total' => 0];
    }
}
