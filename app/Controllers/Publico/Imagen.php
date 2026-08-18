<?php
declare(strict_types=1);

$id = $_GET['id'] ?? '';
if (!ctype_digit((string) $id)) {
    http_response_code(404);
    exit;
}

$pdo = db_conexion();
$stmt = $pdo->prepare('SELECT mime, datos FROM imagenes WHERE id = :id');
$stmt->bindValue(':id', (int) $id, PDO::PARAM_INT);
$stmt->execute();
$fila = $stmt->fetch();

if (!$fila) {
    http_response_code(404);
    exit;
}

$datos = is_resource($fila['datos']) ? stream_get_contents($fila['datos']) : $fila['datos'];

header('Content-Type: ' . $fila['mime']);
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . strlen($datos));
echo $datos;
