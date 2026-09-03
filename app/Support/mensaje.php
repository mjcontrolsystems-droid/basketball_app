<?php
declare(strict_types=1);

/**
 * El texto de la jornada, listo para pegar en el grupo de WhatsApp.
 *
 * Por qué existe
 * -------------
 * La app ya sabe todo lo que el organizador termina escribiendo a mano cada semana: quién
 * juega contra quién, a qué hora, en qué cancha, quién está suspendido y quién debe multa.
 * Lo escribía igual, mensaje por mensaje, y después contestaba diez veces lo mismo porque
 * alguien no leyó. Esto arma el texto completo de una vez.
 *
 * Se genera TEXTO y no una imagen ni un enlace a propósito: en el grupo, un texto se lee
 * sin abrir nada, se busca con el buscador de WhatsApp y se reenvía a la interna de cada
 * equipo. Un enlace se ignora.
 *
 * La función es pura —entra un arreglo, sale una cadena— para poder comprobar el formato
 * con casos de prueba sin base de datos.
 */

/**
 * Arma el mensaje de una jornada.
 *
 * @param array  $torneo   La copa (para el nombre y el deporte).
 * @param int    $jornada  Número de jornada.
 * @param array  $partidos Encuentros de esa jornada, ya ordenados por fecha y hora. Cada
 *   uno con: fecha, hora, cancha, local (nombre), visitante (nombre), y opcionalmente
 *   'avisos' => ['texto', ...] con lo que hay que advertir de ESE encuentro.
 * @param string $enlace   URL pública de la copa. Vacío para no incluirla.
 * @param string $nota     Un renglón libre del organizador ("llevar carné"), opcional.
 */
function mensaje_jornada(array $torneo, int $jornada, array $partidos, string $enlace = '', string $nota = ''): string
{
    $lineas = [];

    $nombreCopa = trim((string) ($torneo['nombre'] ?? ''));
    $lineas[] = '*JORNADA ' . $jornada . '*' . ($nombreCopa !== '' ? ' — ' . $nombreCopa : '');

    if (empty($partidos)) {
        $lineas[] = '';
        $lineas[] = 'Todavía no hay encuentros programados para esta jornada.';
        return implode("\n", $lineas);
    }

    // Los partidos se agrupan por día. Una jornada de fin de semana se juega sábado y
    // domingo, y en el grupo la primera pregunta siempre es "¿qué día jugamos?".
    $porDia = [];
    foreach ($partidos as $p) {
        $porDia[(string) ($p['fecha'] ?? '')][] = $p;
    }
    ksort($porDia);

    foreach ($porDia as $fecha => $delDia) {
        $lineas[] = '';
        // En negrita y no en mayúsculas: en WhatsApp los asteriscos ya destacan el día, y
        // un renglón en mayúsculas se lee como si el organizador estuviera gritando.
        $lineas[] = '*' . formatear_fecha_larga($fecha) . '*';

        foreach ($delDia as $p) {
            $hora = trim((string) ($p['hora'] ?? ''));
            $cancha = trim((string) ($p['cancha'] ?? ''));

            $encabezado = [];
            if ($hora !== '') {
                $encabezado[] = $hora;
            }
            if ($cancha !== '') {
                $encabezado[] = $cancha;
            }

            $lineas[] = '';
            $lineas[] = trim((string) ($p['local'] ?? '?')) . '  vs  ' . trim((string) ($p['visitante'] ?? '?'));
            if (!empty($encabezado)) {
                $lineas[] = '  ' . implode(' · ', $encabezado);
            }

            // Los avisos van pegados a SU encuentro y no en una lista al final: así el
            // capitán ve lo suyo sin leer los de los otros siete partidos.
            foreach ((array) ($p['avisos'] ?? []) as $aviso) {
                $lineas[] = '  ' . $aviso;
            }
        }
    }

    if (trim($nota) !== '') {
        $lineas[] = '';
        $lineas[] = trim($nota);
    }

    if (trim($enlace) !== '') {
        $lineas[] = '';
        $lineas[] = 'Tabla, resultados y nóminas:';
        $lineas[] = trim($enlace);
    }

    return implode("\n", $lineas);
}

/**
 * El renglón de aviso de un jugador que no puede entrar a la cancha.
 *
 * Se dice el motivo, no solo "no juega": el capitán tiene que poder explicárselo al
 * jugador sin escribirle al organizador para preguntar por qué.
 */
function mensaje_aviso_suspendido(string $jugador, string $equipo, string $motivo): string
{
    $texto = 'NO JUEGA: ' . $jugador;
    if ($equipo !== '') {
        $texto .= ' (' . $equipo . ')';
    }
    if ($motivo !== '') {
        $texto .= ' — ' . $motivo;
    }

    return $texto;
}

function mensaje_aviso_deudor(string $jugador, string $equipo, string $monto): string
{
    $texto = 'DEBE ' . $monto . ': ' . $jugador;
    if ($equipo !== '') {
        $texto .= ' (' . $equipo . ')';
    }

    return $texto . ' — paga antes de jugar';
}
