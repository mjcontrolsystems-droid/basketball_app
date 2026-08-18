<?php
declare(strict_types=1);

require_once __DIR__ . "/../config/bootstrap.php";

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos (CLI).');
}

echo "== Migración de datos JSON a PostgreSQL ==\n\n";

$pdo = db_conexion();

echo "Creando tablas (si no existen)...\n";
$pdo->exec((string) file_get_contents(__DIR__ . '/../schema.sql'));

/**
 * Sube el contenido de un archivo de imagen (ruta antigua tipo "assets/img/equipos/x.jpg")
 * a la tabla `imagenes` y devuelve el nuevo id, o null si el archivo no existe o el campo está vacío.
 */
function migrar_imagen_de_archivo(?string $rutaRelativa): ?string
{
    if (empty($rutaRelativa)) {
        return null;
    }
    // Si ya es un id numérico (migración corrida más de una vez), no hay nada que hacer
    if (ctype_digit($rutaRelativa)) {
        return $rutaRelativa;
    }

    $rutaAbsoluta = BASE_DIR . '/' . ltrim($rutaRelativa, '/');
    if (!is_file($rutaAbsoluta)) {
        echo "  (aviso) no se encontró el archivo {$rutaRelativa}, se deja sin imagen\n";
        return null;
    }

    $mime = mime_content_type($rutaAbsoluta) ?: 'application/octet-stream';
    $datos = file_get_contents($rutaAbsoluta);

    $pdo = db_conexion();
    $stmt = $pdo->prepare('INSERT INTO imagenes (mime, datos) VALUES (:mime, :datos) RETURNING id');
    $stmt->bindValue(':mime', $mime, PDO::PARAM_STR);
    $stmt->bindValue(':datos', $datos, PDO::PARAM_LOB);
    $stmt->execute();

    return (string) $stmt->fetchColumn();
}

function leer_json(string $archivo): array
{
    $ruta = DATA_DIR . $archivo;
    if (!is_file($ruta)) {
        return [];
    }
    return json_decode((string) file_get_contents($ruta), true) ?: [];
}

echo "\nMigrando equipos...\n";
$equipos = leer_json('equipos.json');
foreach ($equipos as &$eq) {
    $eq['logo'] = migrar_imagen_de_archivo($eq['logo'] ?? null) ?? '';
}
unset($eq);
db_guardar('equipos', $equipos);
echo '  ' . count($equipos) . " equipos migrados\n";

echo "\nMigrando partidos...\n";
$partidos = leer_json('partidos.json');
db_guardar('partidos', $partidos);
echo '  ' . count($partidos) . " partidos migrados\n";

echo "\nMigrando patrocinadores...\n";
$patrocinadores = leer_json('patrocinadores.json');
foreach ($patrocinadores as &$p) {
    $p['logo'] = migrar_imagen_de_archivo($p['logo'] ?? null) ?? '';
}
unset($p);
db_guardar('patrocinadores', $patrocinadores);
echo '  ' . count($patrocinadores) . " patrocinadores migrados\n";

echo "\nMigrando comentarios...\n";
$comentarios = leer_json('comentarios.json');
db_guardar('comentarios', $comentarios);
echo '  ' . count($comentarios) . " comentarios migrados\n";

echo "\nMigrando configuración del torneo...\n";
$torneo = leer_json('torneo.json');
if ($torneo) {
    $torneo['logo'] = migrar_imagen_de_archivo($torneo['logo'] ?? null) ?? '';
    db_guardar('torneo', $torneo);
    echo "  Configuración del torneo migrada\n";
}

echo "\nMigrando datos del organizador...\n";
$organizador = leer_json('organizador.json');
if ($organizador) {
    $organizador['foto'] = migrar_imagen_de_archivo($organizador['foto'] ?? null) ?? '';
    db_guardar('organizador', $organizador);
    echo "  Datos del organizador migrados\n";
}

echo "\n== Migración completa ==\n";
