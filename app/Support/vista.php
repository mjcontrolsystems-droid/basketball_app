<?php
declare(strict_types=1);

/**
 * Renderizado de vistas y contexto de la petición.
 *
 * Un controlador (app/Controllers/...) termina siempre llamando a vista(): prepara los
 * datos y le pasa la plantilla que los debe pintar. Las plantillas viven en app/Views y
 * no consultan la base de datos — si a una vista le falta un dato, el arreglo se lo tiene
 * que dar el controlador.
 */

/**
 * Pinta una plantilla de app/Views.
 *
 * @param string $plantilla Ruta relativa sin .php, ej. 'publico/tabla' o 'admin/partidos'.
 * @param array<string,mixed> $datos Variables disponibles dentro de la plantilla.
 */
function vista(string $plantilla, array $datos = []): void
{
    $rutaVista = dirname(__DIR__) . '/Views/' . $plantilla . '.php';
    if (!is_file($rutaVista)) {
        throw new RuntimeException("No existe la vista '{$plantilla}'.");
    }
    // extract() en una función (y no en el ámbito global) mantiene las variables de la
    // vista aisladas: una plantilla no puede pisar por accidente algo del controlador.
    extract($datos, EXTR_SKIP);
    require $rutaVista;
}

/**
 * Igual que vista(), pero devuelve el HTML en vez de imprimirlo. Se usa donde antes había
 * funciones que construían HTML a mano dentro de helpers.php (tarjetas de encuentro, etc.).
 */
function vista_render(string $plantilla, array $datos = []): string
{
    ob_start();
    vista($plantilla, $datos);
    return (string) ob_get_clean();
}

/**
 * Pinta una vista dentro del layout público (navbar + footer + modales).
 *
 * Los datos que necesita el layout (la copa, el usuario logueado, el aviso flash) los
 * calcula datos_layout_publico() y quedan disponibles TAMBIÉN para la vista de la página:
 * antes todo compartía el ámbito global, así que una página podía usar $torneo o $flash
 * sin declararlos. Al renderizar cada parte por separado eso dejó de ser cierto, y el
 * lugar correcto para prepararlos es aquí, no dentro de la plantilla del layout.
 */
function vista_publica(string $plantilla, array $datos = []): void
{
    $datos = datos_layout_publico($datos);

    // Modo mantenimiento: el sitio queda cerrado al público mientras se reacomoda el
    // calendario. Se corta aquí, en el único punto por donde pasan TODAS las páginas
    // públicas, para que no quede ninguna colada — la tabla, el calendario, la ficha de un
    // partido o el reporte de un equipo comparten esta puerta.
    //
    // El organizador sí entra: necesita revisar cómo va quedando antes de reabrir.
    if (torneo_en_mantenimiento($datos['torneo'] ?? null) && !puede_ver_en_mantenimiento($datos['torneo'] ?? null)) {
        vista('publico/mantenimiento', $datos);
        exit;
    }

    vista('layouts/publico_top', $datos);
    vista($plantilla, $datos);
    vista('layouts/publico_bottom', $datos);
}

/**
 * ¿Esta copa tiene el sitio público cerrado?
 */
function torneo_en_mantenimiento(?array $torneo): bool
{
    return $torneo !== null && !empty($torneo['en_mantenimiento']);
}

/**
 * Quién puede entrar aunque esté cerrado: el organizador dueño de la copa y los
 * superadmins. Cualquier otro visitante ve el aviso.
 */
function puede_ver_en_mantenimiento(?array $torneo): bool
{
    if ($torneo === null || !auth_check()) {
        return false;
    }
    $usuario = usuarios_obtener_por_id((int) ($_SESSION['usuario_id'] ?? 0));
    if ($usuario === null) {
        return false;
    }
    if (es_superadmin($usuario)) {
        return true;
    }

    return (int) ($torneo['usuario_id'] ?? 0) === (int) $usuario['id'];
}

/**
 * Pinta una vista dentro del layout del panel del organizador (sidebar + cabecera móvil).
 */
function vista_admin(string $plantilla, array $datos = []): void
{
    $datos = datos_layout_admin($datos);
    vista('layouts/admin_top', $datos);
    vista($plantilla, $datos);
    vista('layouts/admin_bottom', $datos);
}

/**
 * Datos comunes del sitio público. Lo que el controlador ya haya puesto en $datos manda:
 * el operador + conserva las claves de la izquierda.
 */
function datos_layout_publico(array $datos): array
{
    $torneo = $datos['torneo'] ?? copa_actual();

    return $datos + [
        'torneo' => $torneo,
        'pagina_activa' => '',
        'titulo_pagina' => $torneo
            ? $torneo['nombre'] . ' — ' . $torneo['subtitulo']
            : 'Plataforma de Copas y Ligas',
        'flash' => obtener_flash(),
        'usuarioActual' => auth_check() ? usuarios_obtener_por_id((int) $_SESSION['usuario_id']) : null,
    ];
}

/**
 * Datos comunes del panel: quién está logueado, qué copa tiene activa y el estado del
 * sidebar. Exige sesión iniciada (auth_requerir) y pone el esquema al día.
 */
function datos_layout_admin(array $datos): array
{
    auth_requerir();
    $usuarioIdSesion = (int) $_SESSION['usuario_id'];

    // Copa activa, solo para mostrarla en el sidebar (las páginas que de verdad la
    // necesitan la exigen ellas mismas con admin_requerir_torneo_activo()).
    // Se busca sin filtrar por dueño y el acceso lo decide nivel_en_copa(): filtrar aquí
    // por usuario_id dejaba a los colaboradores con el menú de la copa vacío — entraban,
    // veían el tablero de la liga, pero sin Equipos ni Encuentros por ningún lado.
    $torneoActivoId = $_SESSION['torneo_activo_id'] ?? null;
    $torneoActivo = $torneoActivoId !== null ? torneos_obtener_por_id((int) $torneoActivoId) : null;
    if ($torneoActivo !== null && nivel_en_copa($torneoActivo) === null) {
        $torneoActivo = null;
    }
    $organizador = usuarios_obtener_por_id($usuarioIdSesion) ?? [];

    return $datos + [
        'usuarioIdSesion' => $usuarioIdSesion,
        'torneoActivo' => $torneoActivo,
        'organizador' => $organizador,
        'esSuperadmin' => es_superadmin($organizador),
        // Quien no tiene copas propias es puro colaborador: el menú le esconde lo que solo
        // le sirve a quien responde por una copa.
        'tieneCopasPropias' => !empty(torneos_listar(false, $usuarioIdSesion)),
        'seccion_activa' => '',
        'titulo_pagina' => 'Panel del Organizador',
        'flash' => obtener_flash(),
        'comentariosNoLeidos' => $torneoActivo
            ? count(array_filter(comentarios_listar($torneoActivo['id']), fn($c) => empty($c['leido'])))
            : 0,
        'nombreMarca' => $torneoActivo['nombre'] ?? 'Panel Organizador',
    ];
}

// ---------------------------------------------------------------------------
// Copa/liga de la petición actual.
//
// Antes esto era la variable global $torneo, y funciones como url_copa() la leían con
// `global $torneo`. Eso ataba el enrutado a que existiera una variable con ese nombre
// exacto en el ámbito global — con las vistas renderizadas dentro de una función (ver
// vista() arriba) esa variable ya no es global, así que el contexto se guarda aquí.
// ---------------------------------------------------------------------------

/**
 * Guarda cuál es la copa de esta petición. La llama el front controller al resolver el
 * slug de la URL (ver public/index.php).
 */
function copa_actual_definir(?array $torneo): void
{
    $GLOBALS['__copa_actual'] = $torneo;
}

/**
 * La copa de esta petición, o null si la página no pertenece a ninguna (portada, login,
 * listado de copas del panel).
 */
function copa_actual(): ?array
{
    return $GLOBALS['__copa_actual'] ?? null;
}

/**
 * Respuesta 404 dentro del sitio de una copa (un partido o equipo que no existe, o que
 * pertenece a otra copa). Antes cada página repetía este mismo bloque: cabecera 404 +
 * layout + un mensaje + enlace de vuelta.
 */
function vista_404_copa(string $titulo, string $volverUrl, string $volverTexto): void
{
    http_response_code(404);
    $datos = [
        'torneo' => copa_actual(),
        'titulo_pagina' => $titulo,
        'volver_url' => $volverUrl,
        'volver_texto' => $volverTexto,
    ];
    vista('layouts/publico_top', $datos);
    vista('publico/no_encontrado_en_copa', $datos);
    vista('layouts/publico_bottom', $datos);
    exit;
}
