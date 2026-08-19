<?php
declare(strict_types=1);

/**
 * Mapa de rutas: qué controlador atiende cada URL.
 *
 * Las claves son las rutas TAL CUAL las pide el navegador, terminadas en .php. Se
 * conservan así a propósito y no se cambiaron por URLs limpias: hay códigos QR impresos
 * y enlaces compartidos por WhatsApp apuntando a /copa/partido_vivo.php?id=7, y romperlos
 * dejaría carteles y transmisiones muertos. El .php de la URL ya no corresponde a ningún
 * archivo real — es solo la forma de la ruta.
 *
 * 'copa' => true  significa que la página vive DENTRO de una copa o liga: el front
 * controller resuelve el slug de la URL antes de llamar al controlador, y responde 404
 * si no existe. Las de 'copa' => false no pertenecen a ninguna (portada, login, panel).
 */

return [
    // --- Sitio público de cada copa o liga (/slug/...) ---
    'index.php' => ['controlador' => 'Publico/Inicio', 'copa' => true],
    'tabla.php' => ['controlador' => 'Publico/Tabla', 'copa' => true],
    'calendario.php' => ['controlador' => 'Publico/Calendario', 'copa' => true],
    'calendario_imprimir.php' => ['controlador' => 'Publico/CalendarioImprimir', 'copa' => true],
    'solvencia.php' => ['controlador' => 'Publico/Solvencia', 'copa' => true],
    'equipos.php' => ['controlador' => 'Publico/Equipos', 'copa' => true],
    'equipo.php' => ['controlador' => 'Publico/Equipo', 'copa' => true],
    'partido.php' => ['controlador' => 'Publico/Partido', 'copa' => true],
    'partido_vivo.php' => ['controlador' => 'Publico/PartidoVivo', 'copa' => true],
    'partido_vivo_datos.php' => ['controlador' => 'Publico/PartidoVivoDatos', 'copa' => true],
    'partido_imagen.php' => ['controlador' => 'Publico/PartidoImagen', 'copa' => true],
    'patrocinadores.php' => ['controlador' => 'Publico/Patrocinadores', 'copa' => true],
    'organizador.php' => ['controlador' => 'Publico/Organizador', 'copa' => true],

    // --- Páginas sin copa: portada de la plataforma y utilidades ---
    'portada.php' => ['controlador' => 'Publico/Portada', 'copa' => false],
    'codigo.php' => ['controlador' => 'Publico/Codigo', 'copa' => false],
    'imagen.php' => ['controlador' => 'Publico/Imagen', 'copa' => false],
    'torneos.php' => ['controlador' => 'Publico/Torneos', 'copa' => false],
    '404.php' => ['controlador' => 'Publico/NoEncontrado', 'copa' => false],

    // --- Acceso y cuentas ---
    'login.php' => ['controlador' => 'Auth/Login', 'copa' => false],
    'logout.php' => ['controlador' => 'Auth/Logout', 'copa' => false],
    'registro.php' => ['controlador' => 'Auth/Registro', 'copa' => false],
    'olvide_password.php' => ['controlador' => 'Auth/OlvidePassword', 'copa' => false],
    'restablecer_password.php' => ['controlador' => 'Auth/RestablecerPassword', 'copa' => false],
    'google_iniciar.php' => ['controlador' => 'Auth/GoogleIniciar', 'copa' => false],
    'google_callback.php' => ['controlador' => 'Auth/GoogleCallback', 'copa' => false],

    // --- Panel del organizador ---
    'admin/index.php' => ['controlador' => 'Admin/Dashboard', 'copa' => false],
    'admin/torneos.php' => ['controlador' => 'Admin/Torneos', 'copa' => false],
    'admin/equipos.php' => ['controlador' => 'Admin/Equipos', 'copa' => false],
    'admin/jugadores.php' => ['controlador' => 'Admin/Jugadores', 'copa' => false],
    'admin/partidos.php' => ['controlador' => 'Admin/Partidos', 'copa' => false],
    'admin/partido_eventos.php' => ['controlador' => 'Admin/PartidoEventos', 'copa' => false],
    'admin/sanciones.php' => ['controlador' => 'Admin/Sanciones', 'copa' => false],
    'admin/patrocinadores.php' => ['controlador' => 'Admin/Patrocinadores', 'copa' => false],
    'admin/comentarios.php' => ['controlador' => 'Admin/Comentarios', 'copa' => false],
    'admin/bitacora.php' => ['controlador' => 'Admin/Bitacora', 'copa' => false],
    'admin/perfil.php' => ['controlador' => 'Admin/Perfil', 'copa' => false],
    'admin/usuarios_autorizados.php' => ['controlador' => 'Admin/UsuariosAutorizados', 'copa' => false],
];
