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

// Defensa en profundidad: aunque la subida ya valida el tipo real del archivo, aquí se
// vuelve a exigir que el mime guardado sea una imagen conocida. Si algún día un registro
// trajera otro tipo (una migración manual, un bug futuro), se sirve como descarga
// genérica en vez de dejar que el navegador lo interprete — nunca como HTML ejecutable.
$mimesImagen = ['image/png', 'image/jpeg', 'image/webp', 'image/gif'];
$mime = in_array($fila['mime'], $mimesImagen, true) ? $fila['mime'] : 'application/octet-stream';
if ($mime === 'application/octet-stream') {
    header('Content-Disposition: attachment; filename="archivo.bin"');
}

header('Content-Type: ' . $mime);
header('Cache-Control: public, max-age=31536000, immutable');
header('Content-Length: ' . strlen($datos));
echo $datos;
