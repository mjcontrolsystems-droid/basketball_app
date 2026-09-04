<?php
declare(strict_types=1);

const SUBIDA_MAX_BYTES = 10 * 1024 * 1024;

// Los reglamentos suelen ser PDF escaneados y pesan más que un logo, así que llevan su
// propio límite (la tabla `imagenes` guarda cualquier binario, no solo imágenes).
const SUBIDA_PDF_MAX_BYTES = 15 * 1024 * 1024;

/**
 * Procesa la subida opcional de un PDF (por ahora, el reglamento del campeonato) y lo
 * guarda en la misma tabla binaria que las imágenes. Devuelve el id guardado o null si
 * no se subió nada.
 *
 * Solo se acepta application/pdf verificado por su contenido real, no por la extensión
 * del nombre: un .pdf renombrado con otra cosa adentro se rechaza.
 *
 * @throws RuntimeException con un mensaje entendible si el archivo es inválido.
 */
function manejar_subida_pdf(string $campo): ?string
{
    if (empty($_FILES[$campo]) || $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES[$campo]['error'] === UPLOAD_ERR_INI_SIZE || $_FILES[$campo]['error'] === UPLOAD_ERR_FORM_SIZE) {
        throw new RuntimeException('El PDF es demasiado grande. El máximo permitido es 15MB.');
    }
    if ($_FILES[$campo]['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('No se pudo subir el PDF. Intenta de nuevo.');
    }
    if ($_FILES[$campo]['size'] > SUBIDA_PDF_MAX_BYTES) {
        throw new RuntimeException('El PDF es demasiado grande. El máximo permitido es 15MB.');
    }

    $tipoDetectado = mime_content_type($_FILES[$campo]['tmp_name']);
    if ($tipoDetectado !== 'application/pdf') {
        throw new RuntimeException('El archivo debe ser un PDF.');
    }

    $datos = file_get_contents($_FILES[$campo]['tmp_name']);
    if ($datos === false) {
        throw new RuntimeException('No se pudo leer el PDF subido. Intenta de nuevo.');
    }

    $pdo = db_conexion();
    $stmt = $pdo->prepare('INSERT INTO imagenes (mime, datos) VALUES (:mime, :datos) RETURNING id');
    $stmt->bindValue(':mime', 'application/pdf', PDO::PARAM_STR);
    $stmt->bindValue(':datos', $datos, PDO::PARAM_LOB);
    $stmt->execute();

    $id = $stmt->fetchColumn();
    return $id !== false ? (string) $id : null;
}

/**
 * Procesa la subida opcional de una imagen (logo/escudo/foto) y la guarda en la base de datos
 * (no en disco: el hosting gratuito no garantiza almacenamiento persistente en el sistema de archivos).
 * Devuelve el id de la imagen guardada (para guardar en la columna logo/foto) o null si no se subió nada.
 *
 * @throws RuntimeException con un mensaje entendible por el usuario si se subió un archivo pero es inválido
 *         (para no fallar en silencio, como pasaba antes con fotos de cámara de celular que superaban el límite).
 */
function manejar_subida_imagen(string $campo, string $subcarpeta = '', ?int $ladoMaximo = null): ?string
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
    $optimizada = optimizar_imagen($datosImagen, $tipoDetectado, $ladoMaximo);
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

// Las fotos de jugador nunca se muestran a más de 96px (perfil público) — 400 sobra
// incluso en pantallas retina. La diferencia importa: la página de un equipo carga 20
// fotos de golpe, y a 1000px eso son unos 3MB de datos móviles parado en la cancha.
const FOTO_JUGADOR_LADO_MAXIMO = 400;

/**
 * Redimensiona (si hace falta) y recomprime una imagen subida. Devuelve [datos, mime]
 * o null si no se pudo/necesitó procesar (GD ausente, imagen corrupta, o ya pequeña y
 * más liviana que la versión reprocesada).
 *
 * @param ?int $ladoMaximo Lado mayor al que reducir. null = el general del sitio.
 * @return array{0:string,1:string}|null
 */
function optimizar_imagen(string $datos, string $mime, ?int $ladoMaximo = null): ?array
{
    $ladoMaximo = $ladoMaximo !== null && $ladoMaximo > 0 ? $ladoMaximo : IMAGEN_LADO_MAXIMO;

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

    if ($ladoMayor > $ladoMaximo) {
        $factor = $ladoMaximo / $ladoMayor;
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
/**
 * Decide con qué archivo se queda un campo de imagen o PDF, y limpia el que se va.
 *
 * Son los tres caminos posibles de cualquier formulario con archivo, y antes cada
 * controlador los resolvía a su manera (o directamente no dejaba quitar nada, que es lo
 * que obligaba a subir una imagen cualquiera para tapar la que se había subido por error):
 *
 *   1. Subió uno nuevo  -> se queda el nuevo y se borra el viejo.
 *   2. Marcó "quitar"   -> se queda sin nada y se borra el viejo.
 *   3. No tocó nada     -> se queda el que ya tenía.
 *
 * En los casos 1 y 2 el archivo anterior se borra de la tabla de imágenes: si no, cada
 * cambio de escudo iría dejando basura que nadie referencia.
 *
 * @param string|null $subido Lo que devolvió manejar_subida_imagen()/manejar_subida_pdf().
 * @param string $actual Referencia guardada hoy.
 * @param bool $quitar Si el organizador marcó la casilla de quitar.
 * @return string Referencia que hay que guardar ('' = sin archivo).
 */
function resolver_archivo_guardado(?string $subido, string $actual, bool $quitar): string
{
    if ($subido !== null && $subido !== '') {
        eliminar_imagen($actual !== '' ? $actual : null);

        return $subido;
    }

    if ($quitar) {
        eliminar_imagen($actual !== '' ? $actual : null);

        return '';
    }

    return $actual;
}

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
