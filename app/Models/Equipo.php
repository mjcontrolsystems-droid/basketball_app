<?php
declare(strict_types=1);

require_once __DIR__ . '/../Support/bd.php';

// Equipos de una copa o liga.
// Modelo: solo acceso a datos. Envuelve la capa genérica (db_leer/db_guardar) para que
// los controladores hablen de "equipos" y no de nombres de tabla sueltos.

function equipos_listar(int $torneoId): array
{
    return db_leer('equipos', $torneoId);
}

function equipos_guardar_todos(array $equipos, int $torneoId): bool
{
    return db_guardar('equipos', $equipos, $torneoId);
}

function equipo_buscar(array $equipos, int $id): ?array
{
    return db_buscar_por_id($equipos, $id);
}

function equipo_nuevo_id(): int
{
    return db_siguiente_id_global('equipos');
}

/**
 * Indexa los equipos por id. Casi toda pantalla que muestra un partido necesita resolver
 * "equipo_local" -> ficha del equipo, y antes cada archivo armaba este mismo arreglo a mano.
 */
function equipos_indexar(array $equipos): array
{
    $porId = [];
    foreach ($equipos as $equipo) {
        $porId[(int) $equipo['id']] = $equipo;
    }
    return $porId;
}
