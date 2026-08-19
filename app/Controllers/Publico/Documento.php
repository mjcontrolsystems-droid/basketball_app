<?php
declare(strict_types=1);

/**
 * Sirve un PDF guardado en la base (por ahora, el reglamento del campeonato).
 *
 * Va aparte de Imagen.php porque un PDF necesita cabeceras propias: Content-Disposition
 * para que el navegador lo muestre incrustado o lo descargue con un nombre legible, y
 * X-Content-Type-Options para que nunca se interprete como otra cosa.
 *
 * ?descargar=1 fuerza la descarga en vez de abrirlo en el visor del navegador.
 */

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

// Solo se sirven PDFs por esta ruta: las imágenes tienen la suya.
if (!$fila || ($fila['mime'] ?? '') !== 'application/pdf') {
    http_response_code(404);
    exit;
}

$datos = is_resource($fila['datos']) ? stream_get_contents($fila['datos']) : $fila['datos'];

// Nombre legible al descargar; se sanea porque llega de la URL.
$nombre = trim((string) ($_GET['nombre'] ?? 'reglamento'));
$nombre = preg_replace('/[^A-Za-z0-9 _-]/', '', $nombre) ?: 'reglamento';
$disposicion = ($_GET['descargar'] ?? '') === '1' ? 'attachment' : 'inline';

header('Content-Type: application/pdf');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: ' . $disposicion . '; filename="' . $nombre . '.pdf"');
header('Cache-Control: public, max-age=3600');
header('Content-Length: ' . strlen($datos));
echo $datos;
