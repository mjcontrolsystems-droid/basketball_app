<?php
declare(strict_types=1);

require_once __DIR__ . '/../Support/bd.php';

// Plantilla de jugadores de cada equipo.
// Modelo: solo acceso a datos. Envuelve la capa genérica (db_leer/db_guardar) para que
// los controladores hablen de "jugadores" y no de nombres de tabla sueltos.

function jugadores_listar(int $torneoId): array
{
    return db_leer('jugadores', $torneoId);
}

function jugadores_guardar_todos(array $jugadores, int $torneoId): bool
{
    return db_guardar('jugadores', $jugadores, $torneoId);
}

function jugador_buscar(array $jugadores, int $id): ?array
{
    return db_buscar_por_id($jugadores, $id);
}

function jugador_nuevo_id(): int
{
    return db_siguiente_id_global('jugadores');
}

/**
 * Plantilla de un equipo concreto, dentro de la lista completa de la copa.
 */
function jugadores_de_equipo(array $jugadores, int $equipoId): array
{
    return array_values(array_filter($jugadores, fn($j) => (int) $j['equipo_id'] === $equipoId));
}
