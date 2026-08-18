<?php
declare(strict_types=1);

if (auth_check()) {
    header('Location: ' . url('admin/index.php'));
    exit;
}

if (GOOGLE_CLIENT_ID === '') {
    redirigir_con_mensaje(url('login.php'), 'error', 'El acceso con Google no está configurado todavía.');
}

// Token anti-CSRF propio del flujo de OAuth: Google lo devuelve tal cual en el callback,
// y ahí se compara contra este mismo valor guardado en sesión para confirmar que la
// respuesta corresponde a una autorización que nosotros iniciamos.
$state = bin2hex(random_bytes(24));
$_SESSION['google_oauth_state'] = $state;

$parametros = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => SITE_ORIGIN . url('google_callback.php'),
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'state' => $state,
    'prompt' => 'select_account',
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($parametros));
exit;
