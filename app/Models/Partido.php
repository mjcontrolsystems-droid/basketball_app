<?php
declare(strict_types=1);

require_once __DIR__ . '/../Support/bd.php';

// Encuentros de una copa o liga (temporada regular y eliminación directa).
// Modelo: solo acceso a datos. Envuelve la capa genérica (db_leer/db_guardar) para que
// los controladores hablen de "partidos" y no de nombres de tabla sueltos.

function partidos_listar(int $torneoId): array
{
    return db_leer('partidos', $torneoId);
}

function partidos_guardar_todos(array $partidos, int $torneoId): bool
{
    return db_guardar('partidos', $partidos, $torneoId);
}

function partido_buscar(array $partidos, int $id): ?array
{
    return db_buscar_por_id($partidos, $id);
}

function partido_nuevo_id(): int
{
    return db_siguiente_id_global('partidos');
}
