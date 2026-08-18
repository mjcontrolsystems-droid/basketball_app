<?php
declare(strict_types=1);

const SUBIDA_MAX_BYTES = 10 * 1024 * 1024;

/**
 * Procesa la subida opcional de una imagen (logo/escudo/foto) y la guarda en la base de datos
 * (no en disco: el hosting gratuito no garantiza almacenamiento persistente en el sistema de archivos).
 * Devuelve el id de la imagen guardada (para guardar en la columna logo/foto) o null si no se subió nada.
 *
 * @throws RuntimeException con un mensaje entendible por el usuario si se subió un archivo pero es inválido
 *         (para no fallar en silencio, como pasaba antes con fotos de cámara de celular que superaban el límite).
 */
function manejar_subida_imagen(string $campo, string $subcarpeta = ''): ?string
{
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$campo]['error'] === UPLOAD_ERR_INI_SIZE || $_FILES[$campo]['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('La imagen es demasiado grande. El máximo permitido es 10MB.');
    }
    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir la imagen. Intenta de nuevo.');
    }

    if ($_FILES[$campo]['size'] > SUBIDA_MAX_BYTES) {
        throw new RuntimeException('La imagen es demasiado grande. El máximo permitido es 10MB.');
    }

    // SVG queda excluido a propósito: puede llevar <script> embebido y ejecutarse
    // si el navegador lo abre directo (riesgo de XSS almacenado).
    $permitidos = ['image/png', 'image/jpeg', 'image/webp'];

    $tipoDetectado = mime_content_type($_FILES[$campo]['tmp_name']);
    if (!in_array($tipoDetectado, $permitidos, true)) {
        throw new RuntimeException('Formato de imagen no permitido. Usa PNG, JPG o WEBP.');
    }

    $datosImagen = file_get_contents($_FILES[$campo]['tmp_name']);
    if ($datosImagen === false) {
        throw new RuntimeException('No se pudo leer la imagen subida. Intenta de nuevo.');
    }

    // Optimización: las fotos de celular llegan de 3-10MB y aquí se sirven como logos de
    // menos de 200px — redimensionar al subir hace el sitio mucho más rápido en datos
    // móviles y ahorra espacio en la base. Si GD no está disponible o la imagen no se
    // puede procesar, se guarda la original tal cual (nunca se bloquea la subida por esto).
    $optimizada = optimizar_imagen($datosImagen, $tipoDetectado);
    if ($optimizada !== null) {
        [$datosImagen, $tipoDetectado] = $optimizada;
    }

    $pdo = db_conexion();
    $stmt = $pdo->prepare('INSERT INTO imagenes (mime, datos) VALUES (:mime, :datos) RETURNING id');
    $stmt->bindValue(':mime', $tipoDetectado, PDO::PARAM_STR);
    $stmt->bindValue(':datos', $datosImagen, PDO::PARAM_LOB);
    $stmt->execute();

    $id = $stmt->fetchColumn();
    return $id !== false ? (string) $id : null;
}

// Lado máximo tras optimizar: suficiente para el uso más grande del sitio (logo del
// equipo en su página, foto del organizador) manteniendo el archivo pequeño.
const IMAGEN_LADO_MAXIMO = 1000;

/**
 * Redimensiona (si hace falta) y recomprime una imagen subida. Devuelve [datos, mime]
 * o null si no se pudo/necesitó procesar (GD ausente, imagen corrupta, o ya pequeña y
 * más liviana que la versión reprocesada).
 *
 * @return array{0:string,1:string}|null
 */
function optimizar_imagen(string $datos, string $mime): ?array
{
    if (!function_exists('imagecreatefromstring')) {
        return null;
    }

    $origen = @imagecreatefromstring($datos);
    if ($origen === false) {
        return null;
    }

    $ancho = imagesx($origen);
    $alto = imagesy($origen);
    $ladoMayor = max($ancho, $alto);

    if ($ladoMayor > IMAGEN_LADO_MAXIMO) {
        $factor = IMAGEN_LADO_MAXIMO / $ladoMayor;
        $nuevoAncho = max(1, (int) round($ancho * $factor));
        $nuevoAlto = max(1, (int) round($alto * $factor));
        $reducida = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        // Conservar transparencia de PNG/WEBP (logos con fondo transparente)
        imagealphablending($reducida, false);
        imagesavealpha($reducida, true);
        imagecopyresampled($reducida, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
        imagedestroy($origen);
        $origen = $reducida;
    }

    ob_start();
    if ($mime === 'image/jpeg') {
        imagejpeg($origen, null, 84);
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
        imagewebp($origen, null, 82);
    } else {
        // PNG (o WEBP sin soporte de reencodeo): PNG conserva transparencia
        imagepng($origen, null, 8);
        $mime = 'image/png';
    }
    $resultado = ob_get_clean();
    imagedestroy($origen);

    if ($resultado === false || $resultado === '') {
        return null;
    }
    // Si la "optimizada" quedó más pesada que la original (pasa con PNGs ya comprimidos
    // que no había que redimensionar), mejor conservar la original.
    if (strlen($resultado) >= strlen($datos) && $ladoMayor <= IMAGEN_LADO_MAXIMO) {
        return null;
    }
    return [$resultado, $mime];
}

/**
 * Elimina una imagen previamente subida (al reemplazar o borrar un registro).
 * $referencia es el id guardado en la columna logo/foto (ver manejar_subida_imagen).
 */
function eliminar_imagen(?string $referencia): void
{
    if (empty($referencia) || !ctype_digit($referencia)) {
        return;
    }
    $pdo = db_conexion();
    $stmt = $pdo->prepare('DELETE FROM imagenes WHERE id = :id');
    $stmt->bindValue(':id', (int) $referencia, PDO::PARAM_INT);
    $stmt->execute();
}
