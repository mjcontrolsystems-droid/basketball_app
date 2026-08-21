<?php
declare(strict_types=1);

/**
 * Envío de correos transaccionales vía la API HTTP de Resend (https://resend.com).
 *
 * Se usa Resend porque su plan gratuito (100 correos/día) alcanza de sobra para los
 * avisos de esta plataforma (autorizaciones, cupos, recuperación de contraseña) y su
 * API es un simple POST — sin SMTP, sin dependencias de Composer.
 *
 * Configuración (variables de entorno, igual que DATABASE_URL):
 *   RESEND_API_KEY  la llave "re_..." del panel de Resend
 *   MAIL_FROM       remitente verificado, ej. "MJ Control Systems <avisos@tudominio.com>"
 *                   (sin dominio propio verificado, Resend permite "onboarding@resend.dev")
 *
 * Si no están configuradas, correo_configurado() devuelve false y la plataforma sigue
 * funcionando igual que antes (los avisos simplemente no se envían).
 */

function correo_configurado(): bool
{
    return (getenv('RESEND_API_KEY') ?: '') !== '' && (getenv('MAIL_FROM') ?: '') !== '';
}

/**
 * Por qué falló el último envío, en cristiano.
 *
 * Antes el motivo solo quedaba en el log del servidor, que en un hosting administrado no
 * está a mano. Uno veía "no se pudo enviar el correo" y se quedaba adivinando entre la
 * llave, el remitente sin verificar y el dominio mal escrito. Ahora se puede mostrar en
 * pantalla a quien administra.
 */
function correo_ultimo_error(?string $nuevo = null): string
{
    static $ultimo = '';
    if ($nuevo !== null) {
        $ultimo = $nuevo;
    }
    return $ultimo;
}

/**
 * Traduce la respuesta de Resend al problema concreto que hay que arreglar.
 */
function correo_explicar_fallo(int $codigoHttp, string $respuesta): string
{
    $texto = mb_strtolower($respuesta);

    if ($codigoHttp === 0) {
        return 'No se pudo contactar a Resend (sin salida a internet o tiempo agotado).';
    }

    // El 403 de Resend significa dos cosas MUY distintas y hay que mirar el texto para
    // saber cuál: llave inválida, o la restricción de cuenta sin dominio verificado.
    // Decidirlo solo por el código mandaba a revisar la llave cuando la llave estaba bien.
    if (str_contains($texto, 'testing emails') || str_contains($texto, 'own email address')) {
        return 'Tu cuenta de Resend todavía no tiene un dominio verificado, así que solo te deja '
            . 'enviarte correos a ti mismo. Para escribirle a otras personas verifica un dominio en '
            . 'Resend → Domains. Mientras tanto usa el botón del enlace para invitar por WhatsApp.';
    }
    if (str_contains($texto, 'not found') && str_contains($texto, 'domain')) {
        return 'El dominio de MAIL_FROM no está dado de alta en Resend → Domains.';
    }
    if ($codigoHttp === 401 || $codigoHttp === 403) {
        return 'Resend rechazó la llave. Lo más común es haberla copiado incompleta: en la lista de '
            . 'API keys solo se ve un pedazo (re_abc...), y la llave entera únicamente se muestra al '
            . 'crearla. Borra esa y crea una nueva, copiándola en ese momento.';
    }
    if ($codigoHttp === 422 || str_contains($texto, 'domain')) {
        return 'Resend rechazó el remitente: revisa que MAIL_FROM sea un correo válido de un dominio '
            . 'verificado, o usa onboarding@resend.dev.';
    }
    if ($codigoHttp === 429) {
        return 'Se pasó el límite de correos por hoy en Resend.';
    }

    return "Resend respondió HTTP {$codigoHttp}: " . mb_substr(trim($respuesta), 0, 160);
}

/**
 * Envía un correo. Devuelve true si Resend lo aceptó. Nunca lanza excepción: un fallo
 * de correo no debe tumbar la acción principal (autorizar, ampliar cupo, etc.).
 */
function correo_enviar(string $para, string $asunto, string $html): bool
{
    if (!correo_configurado()) {
        correo_ultimo_error('Faltan las variables RESEND_API_KEY y MAIL_FROM en el servidor.');
        return false;
    }
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) {
        correo_ultimo_error("El correo \"{$para}\" no tiene un formato válido.");
        return false;
    }
    correo_ultimo_error('');

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . getenv('RESEND_API_KEY'),
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode([
            'from' => getenv('MAIL_FROM'),
            'to' => [$para],
            'subject' => $asunto,
            'html' => $html,
        ]),
    ]);
    $respuesta = curl_exec($ch);
    $codigoHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $ok = $respuesta !== false && $codigoHttp >= 200 && $codigoHttp < 300;
    if (!$ok) {
        error_log("correo_enviar fallo (HTTP {$codigoHttp}) hacia {$para}: " . substr((string) $respuesta, 0, 300));
        correo_ultimo_error(correo_explicar_fallo((int) $codigoHttp, (string) $respuesta));
    }
    return $ok;
}

/**
 * Plantilla base: mismo look en todos los avisos (encabezado con degradado, cuerpo
 * blanco, pie con la marca), sin depender de imágenes externas que los clientes de
 * correo suelen bloquear.
 */
function correo_plantilla(string $titulo, string $cuerpoHtml, string $botonTexto = '', string $botonUrl = ''): string
{
    $boton = '';
    if ($botonTexto !== '' && $botonUrl !== '') {
        $urlSegura = htmlspecialchars($botonUrl, ENT_QUOTES, 'UTF-8');
        $textoSeguro = htmlspecialchars($botonTexto, ENT_QUOTES, 'UTF-8');
        $boton = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:28px auto 8px;"><tr><td style="border-radius:999px;background:linear-gradient(135deg,#7b2ff7,#ff6b35);">'
            . '<a href="' . $urlSegura . '" style="display:inline-block;padding:13px 34px;color:#ffffff;text-decoration:none;font-weight:700;font-family:Arial,sans-serif;font-size:15px;">' . $textoSeguro . '</a>'
            . '</td></tr></table>';
    }

    $tituloSeguro = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');

    return '<!doctype html><html><body style="margin:0;padding:0;background:#f4f0fb;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f0fb;padding:32px 12px;"><tr><td align="center">'
        . '<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;width:100%;background:#ffffff;border-radius:18px;overflow:hidden;">'
        . '<tr><td style="background:linear-gradient(135deg,#241a3a,#3d2c63);padding:26px 32px;">'
        . '<p style="margin:0;color:#ffffff;font-family:Arial,sans-serif;font-size:19px;font-weight:700;">' . $tituloSeguro . '</p>'
        . '</td></tr>'
        . '<tr><td style="padding:30px 32px;font-family:Arial,sans-serif;font-size:15px;line-height:1.6;color:#3a3357;">'
        . $cuerpoHtml
        . $boton
        . '</td></tr>'
        . '<tr><td style="padding:18px 32px;border-top:1px solid #eee9f7;">'
        . '<p style="margin:0;font-family:Arial,sans-serif;font-size:12px;color:#8b7fa8;">MJ Control Systems · Plataformas web inteligentes, control total de tu negocio.</p>'
        . '</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/**
 * Aviso al autorizar un correo: la persona ya puede crear su cuenta con Google.
 */
function correo_avisar_autorizado(string $email, int $cupo): bool
{
    $cuantos = $cupo === 1 ? 'una copa o liga' : "{$cupo} copas o ligas";
    $cuerpo = '<p>¡Buenas noticias! Tu correo fue autorizado para crear una cuenta de organizador en nuestra plataforma de copas y ligas.</p>'
        . "<p>Tienes autorizada la creación de <strong>{$cuantos}</strong>. Entra con el botón de abajo usando <strong>Continuar con Google</strong> con este mismo correo.</p>";
    return correo_enviar(
        $email,
        'Tu acceso de organizador ya está listo',
        correo_plantilla('Bienvenido a la plataforma', $cuerpo, 'Crear mi cuenta', SITE_ORIGIN . url('registro.php'))
    );
}

/**
 * Aviso al ampliar el cupo de un organizador que ya tiene cuenta.
 */
function correo_avisar_cupo(string $email, int $cupoNuevo): bool
{
    $cuantos = $cupoNuevo === 1 ? '1 copa o liga' : "{$cupoNuevo} copas o ligas";
    $cuerpo = '<p>Tu cupo de torneos fue actualizado.</p>'
        . "<p>Ahora tienes autorizadas <strong>{$cuantos}</strong> en total. Ya puedes crear tu siguiente torneo desde el panel.</p>";
    return correo_enviar(
        $email,
        'Tu cupo de torneos fue actualizado',
        correo_plantilla('Cupo actualizado', $cuerpo, 'Ir a mi panel', SITE_ORIGIN . url('admin/torneos.php'))
    );
}

/**
 * Invitación a ayudar a administrar una copa.
 *
 * Dice tres cosas concretas —quién invita, a qué copa y con qué nivel— porque quien lo
 * recibe muchas veces no sabe que la plataforma existe, y un correo genérico con un botón
 * parece intento de estafa. También aclara con qué correo tiene que entrar: si usa otro
 * de Google, no lo va a dejar pasar y no va a entender por qué.
 */
function correo_invitar_colaborador(string $email, string $quienInvita, string $copa, string $nivel, string $resumenNivel, string $urlAceptar): bool
{
    $copaSegura = htmlspecialchars($copa, ENT_QUOTES, 'UTF-8');
    $quienSeguro = htmlspecialchars($quienInvita, ENT_QUOTES, 'UTF-8');
    $emailSeguro = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
    $nivelSeguro = htmlspecialchars($nivel, ENT_QUOTES, 'UTF-8');
    $resumenSeguro = htmlspecialchars($resumenNivel, ENT_QUOTES, 'UTF-8');

    $cuerpo = "<p><strong>{$quienSeguro}</strong> te invitó a ayudar a administrar <strong>{$copaSegura}</strong>.</p>"
        . "<p>Tu rol sería <strong>{$nivelSeguro}</strong>: {$resumenSeguro}</p>"
        . '<p>Al aceptar entras con tu cuenta de Google — no tienes que crear una contraseña nueva. '
        . "Usa el correo <strong>{$emailSeguro}</strong>, que es al que llegó esta invitación.</p>"
        . '<p style="color:#6c757d;font-size:.9rem;">Si no esperabas esto, puedes ignorar el correo: '
        . 'sin aceptar no se crea ningún acceso.</p>';

    return correo_enviar(
        $email,
        "Te invitaron a ayudar en {$copa}",
        correo_plantilla('Invitación a colaborar', $cuerpo, 'Aceptar invitación', $urlAceptar)
    );
}

/**
 * Correo de recuperación de contraseña con el enlace de restablecimiento.
 */
function correo_recuperar_password(string $email, string $urlRestablecer): bool
{
    $cuerpo = '<p>Recibimos una solicitud para restablecer la contraseña de tu cuenta de organizador.</p>'
        . '<p>El enlace es válido por <strong>1 hora</strong>. Si tú no lo pediste, ignora este correo: tu contraseña actual sigue funcionando.</p>';
    return correo_enviar(
        $email,
        'Restablecer tu contraseña',
        correo_plantilla('Restablecer contraseña', $cuerpo, 'Crear contraseña nueva', $urlRestablecer)
    );
}
