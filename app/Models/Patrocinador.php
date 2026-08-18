<?php
declare(strict_types=1);

require_once __DIR__ . '/../Support/bd.php';

// Patrocinadores de una copa o liga.
// Modelo: solo acceso a datos. Envuelve la capa genérica (db_leer/db_guardar) para que
// los controladores hablen de "patrocinadores" y no de nombres de tabla sueltos.

function patrocinadores_listar(int $torneoId): array
{
    return db_leer('patrocinadores', $torneoId);
}

function patrocinadores_guardar_todos(array $patrocinadores, int $torneoId): bool
{
    return db_guardar('patrocinadores', $patrocinadores, $torneoId);
}

function patrocinador_buscar(array $patrocinadores, int $id): ?array
{
    return db_buscar_por_id($patrocinadores, $id);
}

function patrocinador_nuevo_id(): int
{
    return db_siguiente_id_global('patrocinadores');
}
