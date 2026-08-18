<?php
declare(strict_types=1);

require_once __DIR__ . '/../Support/bd.php';

// Bitácora de acciones del panel: quién hizo qué y cuándo.
// Modelo: solo acceso a datos. No imprime nada ni sabe de HTTP; de eso se encargan
// los controladores (app/Controllers) y las vistas (app/Views).

/**
 * Registra una acción en la bitácora. Nunca lanza excepción: el registro es un extra,
 * no debe tumbar la acción principal (misma filosofía que visitas y correos).
 */
function bitacora_registrar(string $accion, string $detalle = '', ?int $torneoId = null): void
{
    try {
        $pdo = db_conexion();
        $stmt = $pdo->prepare(
            'INSERT INTO bitacora (usuario_id, torneo_id, accion, detalle) VALUES (:usuario_id, :torneo_id, :accion, :detalle)'
        );
        db_bind($stmt, ':usuario_id', !empty($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null);
        db_bind($stmt, ':torneo_id', $torneoId);
        $stmt->bindValue(':accion', $accion, PDO::PARAM_STR);
        $stmt->bindValue(':detalle', mb_substr($detalle, 0, 500), PDO::PARAM_STR);
        $stmt->execute();
    } catch (Throwable $e) {
        error_log('bitacora_registrar: ' . $e->getMessage());
    }
}

/**
 * Últimas entradas de la bitácora. Con $usuarioId solo las de ese usuario (lo que ve un
 * organizador normal); sin él, todas (vista de super-admin).
 */
function bitacora_listar(?int $usuarioId = null, int $limite = 200): array
{
    try {
        $pdo = db_conexion();
        $sql = 'SELECT b.*, u.nombre AS usuario_nombre, u.usuario AS usuario_usuario, t.nombre AS torneo_nombre
                FROM bitacora b
                LEFT JOIN usuarios u ON u.id = b.usuario_id
                LEFT JOIN torneos t ON t.id = b.torneo_id'
            . ($usuarioId !== null ? ' WHERE b.usuario_id = :usuario_id' : '')
            . ' ORDER BY b.creado_en DESC LIMIT ' . max(1, min(500, $limite));
        $stmt = $pdo->prepare($sql);
        if ($usuarioId !== null) {
            $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
}
