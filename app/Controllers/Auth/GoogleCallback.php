<?php
declare(strict_types=1);

/**
 * Intercambia el "code" que mandó Google por un access_token, llamando directo al
 * endpoint de Google por HTTPS servidor-a-servidor (no pasa por el navegador del
 * usuario, así que no hace falta validar la firma del id_token por separado).
 */
function google_obtener_token(string $code): ?array
{
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_POSTFIELDS => http_build_query([
            'client_id' => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => SITE_ORIGIN . url('google_callback.php'),
        ]),
    ]);
    $respuesta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($respuesta === false || $codigoHttp !== 200) {
        return null;
    }
    $datos = json_decode((string) $respuesta, true);
    return isset($datos['access_token']) ? $datos : null;
}

function google_obtener_perfil(string $accessToken): ?array
{
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $accessToken],
    ]);
    $respuesta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($respuesta === false || $codigoHttp !== 200) {
        return null;
    }
    $datos = json_decode((string) $respuesta, true);
    return isset($datos['sub'], $datos['email']) ? $datos : null;
}

/**
 * Genera un nombre de usuario libre a partir del correo de Google, respetando el mismo
 * formato que exige el registro manual (registro.php): 3-30 caracteres, letras/números/._
 */
function google_generar_usuario_unico(string $email): string
{
    $base = strtolower((string) preg_replace('/[^a-zA-Z0-9_.]/', '', explode('@', $email)[0]));
    $base = substr($base, 0, 26);
    if (strlen($base) < 3) {
        $base = $base . str_repeat('0', 3 - strlen($base));
    }

    $candidato = $base;
    $sufijo = 1;
    while (usuarios_obtener_por_usuario($candidato) !== null) {
        $candidato = substr($base, 0, 26) . $sufijo;
        $sufijo++;
    }
    return $candidato;
}

if (auth_check()) {
    header('Location: ' . url('admin/index.php'));
    exit;
}

if (GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_SECRET === '') {
    redirigir_con_mensaje(url('login.php'), 'error', 'El acceso con Google no está configurado todavía.');
}

if (isset($_GET['error'])) {
    redirigir_con_mensaje(url('login.php'), 'error', 'Cancelaste el acceso con Google.');
}

$state = (string) ($_GET['state'] ?? '');
$stateGuardado = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);
if ($state === '' || !hash_equals($stateGuardado, $state)) {
    redirigir_con_mensaje(url('login.php'), 'error', 'La sesión de acceso con Google expiró. Intenta de nuevo.');
}

$code = (string) ($_GET['code'] ?? '');
if ($code === '') {
    redirigir_con_mensaje(url('login.php'), 'error', 'No se pudo completar el acceso con Google.');
}

$token = google_obtener_token($code);
if ($token === null) {
    redirigir_con_mensaje(url('login.php'), 'error', 'No se pudo confirmar tu cuenta de Google. Intenta de nuevo.');
}

$perfil = google_obtener_perfil($token['access_token']);
if ($perfil === null || empty($perfil['email_verified'])) {
    redirigir_con_mensaje(url('login.php'), 'error', 'Tu cuenta de Google necesita un correo verificado para continuar.');
}

$googleId = (string) $perfil['sub'];
$email = (string) $perfil['email'];
$nombre = (string) ($perfil['name'] ?? explode('@', $email)[0]);

$cuenta = usuarios_obtener_por_google_id($googleId);

if ($cuenta === null) {
    $existente = usuarios_obtener_por_email($email);
    if ($existente !== null) {
        usuarios_vincular_google((int) $existente['id'], $googleId);
        $cuenta = $existente;
    }
}

if ($cuenta === null) {
    // El registro público está cerrado: solo se crea una cuenta nueva si el correo
    // está en la lista blanca que administra el super-admin. No afecta a cuentas que
    // ya existían (esas entran por el bloque de arriba, sin pasar por aquí).
    if (!correo_autorizado($email)) {
        redirigir_con_mensaje(url('login.php'), 'error', 'Tu correo no está autorizado para crear una cuenta. Contacta al administrador.');
    }

    $id = usuarios_crear([
        'usuario' => google_generar_usuario_unico($email),
        'email' => $email,
        'nombre' => $nombre,
        'password_hash' => null,
        'google_id' => $googleId,
    ]);
    $cuenta = usuarios_obtener_por_id($id);
    auth_iniciar_sesion_usuario($cuenta);
    redirigir_con_mensaje(url('admin/torneos.php?accion=nuevo'), 'success', '¡Bienvenido! Crea tu primera copa o liga para empezar.');
}

auth_iniciar_sesion_usuario($cuenta);
header('Location: ' . url('admin/index.php'));
exit;
