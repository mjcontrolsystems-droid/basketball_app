<?php
declare(strict_types=1);

/**
 * Arranque de la aplicación: carga la configuración, la capa de soporte y los modelos.
 *
 * Todo se carga de una vez a propósito. Antes cada página empezaba con seis o siete
 * `require_once` distintos y bastaba olvidar uno para que la pantalla reventara solo en
 * cierto camino (justamente el tipo de error que no se ve hasta producción). Son unos
 * pocos miles de líneas de funciones: el costo de parsearlas es despreciable frente a
 * una consulta a la base, y a cambio cualquier controlador puede asumir que tiene todo.
 *
 * Lo carga el front controller (public/index.php) y nada más.
 */

define('RAIZ_APP', dirname(__DIR__));

require_once __DIR__ . '/config.php';

// --- Soporte: infraestructura y reglas del dominio ---
foreach ([
    'bd',       // conexión, lectura/escritura genérica, migraciones
    'vista',    // renderizado de plantillas y copa actual
    'helpers',  // escapado, logos, colores, fechas
    'auth',     // sesión, CSRF, límites de intentos
    'upload',   // subida y optimización de imágenes
    'correo',   // envío de correos
    'filtro',   // saneado de texto público
    'liga',     // reglas por deporte, cronómetro, alineaciones, formato liga/copa
    'tabla',    // tabla de posiciones y fases
    'fixture',  // generación automática de calendario
    'calendario', // reparto en días, fechas, horas y canchas (usa fixture)
    'grupos',   // fase de grupos estilo mundial y cruces desde las tablas de grupo
    'eliminacion', // cuadro final: siembra desde la tabla y avance de ganadores
    'podio',    // campeón, subcampeón y tercer lugar al cerrar la temporada
    'importacion', // lectura de plantillas desde Excel o CSV
    'disciplina', // suspensiones por roja y acumulación de amarillas
    'mensaje',  // el texto de la jornada listo para pegar en WhatsApp
    'cuentas',  // saldo de cada equipo con la liga (usa Models/Cuenta.php)
] as $modulo) {
    require_once RAIZ_APP . '/app/Support/' . $modulo . '.php';
}

// --- Modelos: acceso a datos por entidad ---
foreach ([
    'Torneo', 'Equipo', 'Jugador', 'Partido', 'Evento', 'Alineacion', 'Sancion',
    'Patrocinador', 'Comentario', 'Usuario', 'Bitacora', 'Visita', 'Colaborador', 'Cuenta',
] as $modelo) {
    require_once RAIZ_APP . '/app/Models/' . $modelo . '.php';
}
