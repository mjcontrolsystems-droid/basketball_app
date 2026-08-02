<?php
declare(strict_types=1);

function e(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanea una URL externa (patrocinador, Instagram, etc.) antes de usarla en un href.
 * Solo permite http/https; cualquier otro esquema (javascript:, data:, etc.) se descarta,
 * ya que un enlace así podría ejecutar código si alguien le da clic.
 */
function url_externa_segura(?string $url): string
{
    $url = trim((string) $url);
    if ($url === '') {
        return '#';
    }
    $esquema = parse_url($url, PHP_URL_SCHEME);
    if ($esquema !== null && !in_array(strtolower($esquema), ['http', 'https'], true)) {
        return '#';
    }
    return e($url);
}

function iniciales_de(string $nombre): string
{
    $palabras = preg_split('/\s+/', trim($nombre));
    $palabras = array_filter($palabras, fn($p) => mb_strlen($p) > 0);
    $palabras = array_values($palabras);
    if (count($palabras) === 0) {
        return '?';
    }
    if (count($palabras) === 1) {
        return mb_strtoupper(mb_substr($palabras[0], 0, 2));
    }
    return mb_strtoupper(mb_substr($palabras[0], 0, 1) . mb_substr($palabras[count($palabras) - 1], 0, 1));
}

/**
 * Genera un escudo circular en SVG a partir de las iniciales y colores del equipo.
 * Se usa como respaldo cuando el equipo no tiene un logo cargado.
 */
function escudo_svg(string $nombre, string $color1 = '#7b2ff7', string $color2 = '#ff6b35', int $size = 96): string
{
    $iniciales = e(iniciales_de($nombre));
    $gradId = 'g' . substr(md5($nombre . $color1), 0, 8);
    $c1 = e($color1);
    $c2 = e($color2);
    $fontSize = (int) round($size * 0.36);

    return <<<SVG
<svg viewBox="0 0 100 100" width="{$size}" height="{$size}" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{$iniciales}">
    <defs>
        <linearGradient id="{$gradId}" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="{$c1}" />
            <stop offset="100%" stop-color="{$c2}" />
        </linearGradient>
    </defs>
    <circle cx="50" cy="50" r="48" fill="url(#{$gradId})" stroke="rgba(255,255,255,.55)" stroke-width="2" />
    <text x="50" y="50" text-anchor="middle" dominant-baseline="central" font-family="Poppins, Arial, sans-serif" font-weight="700" font-size="{$fontSize}" fill="#ffffff">{$iniciales}</text>
</svg>
SVG;
}

/**
 * Las columnas logo/foto guardan el id de la imagen en la tabla `imagenes` (ver includes/upload.php),
 * no una ruta de archivo. Esta función arma la URL pública que la sirve.
 */
function url_imagen(string $idImagen): string
{
    return url('imagen.php?id=' . rawurlencode($idImagen));
}

/**
 * Devuelve el HTML (img o svg inline) para el logo de un equipo, usando el escudo generado si no hay logo propio.
 */
function logo_equipo(array $equipo, int $size = 96, string $clase = ''): string
{
    if (!empty($equipo['logo'])) {
        $src = e(url_imagen((string) $equipo['logo']));
        $alt = e($equipo['nombre'] ?? '');
        return "<img src=\"{$src}\" alt=\"{$alt}\" width=\"{$size}\" height=\"{$size}\" class=\"{$clase}\" style=\"object-fit:cover;border-radius:50%;\">";
    }
    $c1 = $equipo['color_primario'] ?? '#7b2ff7';
    $c2 = $equipo['color_secundario'] ?? '#ff6b35';
    return "<span class=\"{$clase}\">" . escudo_svg($equipo['nombre'] ?? '?', $c1, $c2, $size) . '</span>';
}

/**
 * Insignia (wordmark) de patrocinador cuando no hay logo cargado.
 */
function badge_patrocinador(array $patrocinador): string
{
    if (!empty($patrocinador['logo'])) {
        $src = e(url_imagen((string) $patrocinador['logo']));
        $alt = e($patrocinador['nombre'] ?? '');
        return "<img src=\"{$src}\" alt=\"{$alt}\" class=\"sponsor-logo-img\" loading=\"lazy\">";
    }
    $nombre = e($patrocinador['nombre'] ?? '');
    return "<span class=\"sponsor-wordmark\">{$nombre}</span>";
}

/**
 * Tarjeta de un encuentro para las listas del panel admin (fase de grupos y playoffs comparten el mismo diseño).
 * Requiere sesión con csrf_token() disponible.
 */
function admin_tarjeta_partido(array $p, array $equiposPorId): string
{
    $local = $equiposPorId[$p['equipo_local']] ?? null;
    $visit = $equiposPorId[$p['equipo_visitante']] ?? null;
    if (!$local || !$visit) {
        return '';
    }

    // Textos según el deporte de la copa activa: en basketball se habla de puntos y
    // faltas, no de goles y tarjetas (mismo criterio que la ficha de eventos).
    global $torneo;
    $deporteCopa = $torneo['deporte'] ?? null;
    $txtAnotaciones = mb_strtolower(etiqueta_anotaciones($deporteCopa));
    $txtFaltas = mb_strtolower(etiqueta_faltas_leves($deporteCopa));

    $jugado = $p['estado'] === 'jugado';
    $fecha = e(formatear_fecha_larga($p['fecha']));
    $hora = e($p['hora']);
    $cancha = e($p['cancha']);
    $logoLocal = logo_equipo($local, 40);
    $logoVisit = logo_equipo($visit, 40);
    $nombreLocal = e($local['nombre']);
    $nombreVisit = e($visit['nombre']);
    $badgeEstado = $jugado
        ? '<span class="badge badge-estado-jugado rounded-pill px-2 py-1 small">Jugado</span>'
        : '<span class="badge badge-estado-programado rounded-pill px-2 py-1 small">Programado</span>';
    $botonEditar = $jugado
        ? '<i class="bi bi-pencil"></i>'
        : '<i class="bi bi-clipboard-check"></i> Capturar';
    $urlEditar = e(url('admin/partidos.php?accion=editar&id=' . $p['id']));
    $csrf = e(csrf_token());
    $id = (int) $p['id'];

    // Se ofrece desde que se crea el partido (no solo cuando ya está "jugado"): el
    // árbitro/admin suele ir llenando la ficha -goles, tarjetas, cambios- a medida que
    // ocurren, no solo después de capturar el marcador final.
    $urlEventos = e(url('admin/partido_eventos.php?partido_id=' . $id));
    $tituloEventos = e(ucfirst($txtAnotaciones) . ', ' . $txtFaltas . ' y cambios');
    $botonEventos = "<a href=\"{$urlEventos}\" class=\"btn btn-sm btn-outline-secondary\" title=\"{$tituloEventos}\"><i class=\"bi bi-clipboard-data\"></i> Eventos</a>";

    // Enlace público de transmisión en vivo: se puede abrir en una pantalla/TV para la
    // afición (marcador grande y feed de eventos que se refresca solo) o copiar y compartir.
    // El enlace que se COPIA debe ser ABSOLUTO (con https://dominio): antes se copiaba la
    // ruta relativa (/copa/partido_vivo.php?id=N) y al pegarla en WhatsApp no era un link
    // válido — quien lo recibía no podía abrir nada.
    $urlVivo = e(SITE_ORIGIN . url_copa('partido_vivo.php?id=' . $id));
    $botonVivo = "<a href=\"{$urlVivo}\" target=\"_blank\" class=\"btn btn-sm btn-outline-secondary\" title=\"Abrir transmisión en vivo\"><i class=\"bi bi-broadcast\"></i></a>";
    $botonCopiarVivo = "<button type=\"button\" class=\"btn btn-sm btn-outline-secondary btn-copiar-url\" data-url=\"{$urlVivo}\" title=\"Copiar enlace de transmisión en vivo\"><i class=\"bi bi-link-45deg\"></i></button>";

    $botonDescargar = '';
    $botonImagen = '';
    if ($jugado) {
        $urlDescargar = e(url_copa('partido.php?id=' . $id . '&imprimir=1'));
        $botonDescargar = "<a href=\"{$urlDescargar}\" target=\"_blank\" class=\"btn btn-sm btn-outline-secondary\" title=\"Descargar ficha en PDF\"><i class=\"bi bi-download\"></i></a>";
        // Imagen del marcador lista para Instagram/WhatsApp (ver partido_imagen.php)
        $urlImagen = e(url_copa('partido_imagen.php?id=' . $id));
        $botonImagen = "<a href=\"{$urlImagen}\" target=\"_blank\" class=\"btn btn-sm btn-outline-secondary\" title=\"Imagen del resultado para compartir\"><i class=\"bi bi-image\"></i></a>";
    }

    // Interruptor rápido para marcar jugado/programado sin abrir el formulario completo
    // (útil a mitad de temporada, cuando hay que ir capturando encuentros seguidos). Lleva
    // texto visible ("Jugado") porque un switch sin etiqueta no comunica qué hace.
    $toggleChecked = $jugado ? 'checked' : '';
    // Desmarcar un encuentro ya jugado es REABRIR un resultado en firme (sale de la tabla
    // de posiciones hasta volver a marcarlo): lleva confirmación explícita y va a bitácora.
    $confirmarReapertura = $jugado
        ? ' data-confirm="Este resultado ya está en firme. ¿Reabrirlo para corrección? Saldrá de la tabla de posiciones hasta que lo marques como jugado de nuevo, y quedará registrado en la bitácora."'
        : '';
    $toggleJugado = <<<HTML
<form method="post" class="d-flex align-items-center gap-1 mb-0" title="Marcar como jugado"{$confirmarReapertura}>
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="accion" value="alternar_jugado">
    <input type="hidden" name="id" value="{$id}">
    <input class="form-check-input m-0" type="checkbox" role="switch" id="switchJugado{$id}" style="cursor:pointer;" data-envia-al-cambiar {$toggleChecked}>
    <label class="form-check-label small text-muted mb-0" for="switchJugado{$id}" style="cursor:pointer;">Jugado</label>
</form>
HTML;

    // Marcador de solo lectura: ya no se captura a mano aquí. El resultado se calcula
    // automáticamente desde los goles registrados en la ficha de "Eventos" (botón abajo),
    // así que aquí solo se muestra. Un guion cuando todavía no hay goles / no está jugado.
    $valorLocal = $p['marcador_local'] !== null ? (int) $p['marcador_local'] : '–';
    $valorVisit = $p['marcador_visitante'] !== null ? (int) $p['marcador_visitante'] : '–';
    $marcadorDisplay = <<<HTML
<div class="d-flex align-items-center gap-2" title="El marcador se calcula desde los {$txtAnotaciones} registrados en Eventos">
    <span class="fs-3 fw-bold" style="min-width:34px;text-align:center;">{$valorLocal}</span>
    <span class="text-muted">-</span>
    <span class="fs-3 fw-bold" style="min-width:34px;text-align:center;">{$valorVisit}</span>
</div>
HTML;

    return <<<HTML
<div class="col">
    <div class="card-suave p-3">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small text-muted">{$fecha} · {$hora}</span>
            <div class="d-flex align-items-center gap-2">
                {$toggleJugado}
                {$badgeEstado}
            </div>
        </div>
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="equipo-col">{$logoLocal}<span class="nombre">{$nombreLocal}</span></div>
            {$marcadorDisplay}
            <div class="equipo-col">{$logoVisit}<span class="nombre">{$nombreVisit}</span></div>
        </div>
        <div class="d-flex justify-content-between align-items-center">
            <span class="small text-muted"><i class="bi bi-geo-alt me-1"></i>{$cancha}</span>
            <div class="d-flex gap-1">
                {$botonEventos}
                {$botonVivo}
                {$botonCopiarVivo}
                {$botonDescargar}
                {$botonImagen}
                <a href="{$urlEditar}" class="btn btn-sm btn-outline-secondary">{$botonEditar}</a>
                <form method="post" data-confirm="¿Eliminar este encuentro?">
                    <input type="hidden" name="csrf_token" value="{$csrf}">
                    <input type="hidden" name="accion" value="eliminar">
                    <input type="hidden" name="id" value="{$id}">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                </form>
            </div>
        </div>
    </div>
</div>
HTML;
}

function formatear_fecha_larga(string $fecha): string
{
    $dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    $ts = strtotime($fecha);
    if ($ts === false) {
        return e($fecha);
    }
    $dia = $dias[(int) date('w', $ts)];
    $numero = date('d', $ts);
    $mes = $meses[(int) date('n', $ts)];
    return "{$dia} {$numero} {$mes}";
}

function formatear_fecha_corta(string $fecha): string
{
    $ts = strtotime($fecha);
    if ($ts === false) {
        return e($fecha);
    }
    $meses = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    return date('d', $ts) . ' ' . $meses[(int) date('n', $ts)];
}

function nivel_patrocinio_label(string $nivel): string
{
    return match ($nivel) {
        'oficial' => 'Patrocinador Oficial',
        'oro' => 'Patrocinador Oro',
        'plata' => 'Patrocinador Plata',
        default => ucfirst($nivel),
    };
}

function icono_balon(int $size = 24): string
{
    return <<<SVG
<svg width="{$size}" height="{$size}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="12" cy="12" r="10.5" stroke="currentColor" stroke-width="1.6"/>
    <path d="M2 12h20M12 1.5v21M4.5 4.5c3 3 3 12 0 15M19.5 4.5c-3 3-3 12 0 15" stroke="currentColor" stroke-width="1.4" stroke-linecap="round"/>
</svg>
SVG;
}

function icono_futbol(int $size = 24): string
{
    return <<<SVG
<svg width="{$size}" height="{$size}" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
    <circle cx="12" cy="12" r="10.5" stroke="currentColor" stroke-width="1.6"/>
    <path d="M12 7.2l4.2 3-1.6 4.9h-5.2l-1.6-4.9z" stroke="currentColor" stroke-width="1.2" stroke-linejoin="round"/>
    <path d="M12 7.2V2.3M16.2 10.2l4.4-1.4M14.8 15.1l2.7 3.9M9.2 15.1l-2.7 3.9M7.8 10.2l-4.4-1.4" stroke="currentColor" stroke-width="1.2" stroke-linecap="round"/>
</svg>
SVG;
}

/**
 * Logo oficial de la plataforma (círculo con los 4 balones), usado en navbar, footer,
 * login y registro cuando no hay una copa concreta detrás — no tendría sentido mostrar
 * un balón de basketball como si fuera el ícono genérico del sitio.
 */
function icono_multideporte(int $size = 24): string
{
    $src = e(url('assets/img/logo.png'));
    return "<img src=\"{$src}\" width=\"{$size}\" height=\"{$size}\" style=\"border-radius:50%;object-fit:cover;display:block;\" alt=\"\">";
}

/**
 * Balón REAL del deporte (las fotos PNG de assets/img) como icono inline. Es el toque
 * visual característico de la app: donde un sitio genérico pondría un punto o un emoji,
 * aquí aparece el balón del deporte de la copa (basketball naranja o fútbol clásico).
 */
function icono_balon_img(?string $deporte, int $size = 20, string $clase = ''): string
{
    $archivo = ($deporte === 'futbol') ? 'balon-futbol.png' : 'balon-basketball.png';
    $src = e(url('assets/img/' . $archivo));
    return "<img src=\"{$src}\" width=\"{$size}\" height=\"{$size}\" class=\"{$clase}\" style=\"object-fit:contain;vertical-align:-0.18em;\" alt=\"\">";
}

/**
 * Icono según el deporte de la copa, para que basketball y fútbol se vean distintos
 * en el navbar, footer y panel admin (no solo en el nombre). Sin deporte (contexto
 * genérico, sin copa activa) usa el ícono multideporte en vez de asumir basketball.
 */
function icono_deporte(?string $deporte, int $size = 24): string
{
    if ($deporte === null) {
        return icono_multideporte($size);
    }
    return $deporte === 'futbol' ? icono_futbol($size) : icono_balon($size);
}

/**
 * Nombre del deporte para usar en párrafos genéricos (patrocinadores, etc.) que antes
 * asumían "basketball femenino" sin importar la copa/liga real. Cada deporte nuevo que
 * se agregue al catálogo (ver admin/torneos.php) debe sumarse aquí también.
 */
function nombre_deporte(?string $deporte): string
{
    return $deporte === 'futbol' ? 'fútbol' : 'basketball';
}

/**
 * Forma masculina o femenina de una palabra ("Entrenador"/"Entrenadora",
 * "Jugador"/"Jugadora"...) según torneos.genero. "Mixto" o sin configurar usa la forma
 * masculina, que es la que ya usaba el sitio como genérica antes de tener este campo.
 */
function forma_genero(?string $genero, string $masculino, string $femenino): string
{
    return $genero === 'femenino' ? $femenino : $masculino;
}

/**
 * Sufijo para frases tipo "el fútbol{sufijo}" / "el basketball{sufijo}" (categoría del
 * deporte, no la persona: siempre "femenino"/"masculino", nunca "femenina"/"masculina").
 * Vacío en modo mixto para no forzar una categoría que la copa no declaró.
 */
function sufijo_genero_deporte(?string $genero): string
{
    return match ($genero) {
        'femenino' => ' femenino',
        'masculino' => ' masculino',
        default => '',
    };
}

/**
 * Oscurece un color hex (#rrggbb) un porcentaje dado, para derivar variantes
 * "oscuras" de los colores que el admin elige por copa (ej. hover de botones).
 */
function color_oscurecer(string $hex, float $factor): string
{
    if (!preg_match('/^#?([0-9a-fA-F]{6})$/', $hex, $m)) {
        return '#000000';
    }
    $valor = $m[1];
    $r = (int) max(0, min(255, hexdec(substr($valor, 0, 2)) * (1 - $factor)));
    $g = (int) max(0, min(255, hexdec(substr($valor, 2, 2)) * (1 - $factor)));
    $b = (int) max(0, min(255, hexdec(substr($valor, 4, 2)) * (1 - $factor)));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

function color_hex_valido(?string $hex, string $porDefecto): string
{
    if (is_string($hex) && preg_match('/^#[0-9a-fA-F]{6}$/', $hex)) {
        return $hex;
    }
    return $porDefecto;
}

/**
 * Genera las variables CSS (--color-primario, etc.) para que cada copa se vea con
 * SUS propios colores en vez del morado/rosa fijo de Copa Estrellas. El acento rosa
 * de la marca (--color-rosa) solo se mantiene para la copa predeterminada (Copa
 * Estrellas); el resto usa su propio color de acento, así el panel admin y el sitio
 * público de las demás copas se ven neutros según lo que el organizador eligió.
 */
function color_hex_a_rgb(string $hex): string
{
    if (!preg_match('/^#?([0-9a-fA-F]{6})$/', $hex, $m)) {
        return '0,0,0';
    }
    $valor = $m[1];
    return hexdec(substr($valor, 0, 2)) . ',' . hexdec(substr($valor, 2, 2)) . ',' . hexdec(substr($valor, 4, 2));
}

/**
 * Ya no existe ninguna copa "predeterminada" (ese concepto se quitó), así que el acento
 * rosa fijo de marca tampoco aplica a nadie: cada copa (o el contexto genérico sin copa)
 * usa su propio acento como "rosa" también, para que degradados/sombras que dependan de
 * --color-rosa se vean coherentes con los colores que el organizador eligió.
 */
function torneo_variables_css(?array $torneo): string
{
    // Contexto genérico (portada, login, listado de copas — sin copa activa): usa la
    // paleta VIBRANTE de la marca (morado -> naranja del logo), no grises neutros. Los
    // grises pizarra que había antes apagaban toda la primera impresión del sitio:
    // botones, círculos de pasos y acentos se veían desteñidos. Cada copa sigue
    // pintándose con SUS propios colores cuando está activa.
    $primario = color_hex_valido($torneo['color_primario'] ?? null, '#7b2ff7');
    $secundario = color_hex_valido($torneo['color_secundario'] ?? null, '#ff6b35');
    $acento = color_hex_valido($torneo['color_acento'] ?? null, '#ffc93c');
    $oscuro = color_oscurecer($primario, 0.35);

    $variables = [
        'color-primario' => $primario,
        'color-primario-oscuro' => $oscuro,
        'color-secundario' => $secundario,
        'color-acento' => $acento,
        'color-rosa' => $acento,
    ];

    $css = '';
    foreach ($variables as $nombre => $valor) {
        $css .= "--{$nombre}:" . e($valor) . ';';
        $css .= "--{$nombre}-rgb:" . color_hex_a_rgb($valor) . ';';
    }

    return "<style>:root{{$css}}</style>";
}

function redirigir_con_mensaje(string $ruta, string $tipo, string $mensaje): void
{
    $_SESSION['flash'] = ['tipo' => $tipo, 'mensaje' => $mensaje];
    header('Location: ' . $ruta);
    exit;
}

function obtener_flash(): ?array
{
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}
