<?php
declare(strict_types=1);

require_once __DIR__ . '/../Support/bd.php';

// Comentarios que deja el público desde el sitio.
// Modelo: solo acceso a datos. Envuelve la capa genérica (db_leer/db_guardar) para que
// los controladores hablen de "comentarios" y no de nombres de tabla sueltos.

function comentarios_listar(int $torneoId): array
{
    return db_leer('comentarios', $torneoId);
}

function comentarios_guardar_todos(array $comentarios, int $torneoId): bool
{
    return db_guardar('comentarios', $comentarios, $torneoId);
}

function comentario_buscar(array $comentarios, int $id): ?array
{
    return db_buscar_por_id($comentarios, $id);
}

function comentario_nuevo_id(): int
{
    return db_siguiente_id_global('comentarios');
}
