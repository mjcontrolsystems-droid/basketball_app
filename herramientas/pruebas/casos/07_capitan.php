<?php
declare(strict_types=1);

/**
 * El candado del capitán.
 *
 * Es el permiso más delicado de la app: se le da acceso al panel a gente de fuera de la
 * organización —16 capitanes— confiando en que cada uno solo alcance su propio equipo. Un
 * error aquí no se ve en pantalla; se descubre cuando alguien edita la plantilla del rival.
 *
 * Estas pruebas fijan las dos mitades del corte: QUÉ puede hacer el nivel (la lista de
 * permisos) y SOBRE QUÉ equipo puede hacerlo (acceso_alcanza_equipo).
 */

grupo('Qué alcanza el nivel capitán');

prueba('el capitán solo llega a equipos y jugadores', function () {
    igual(['equipos', 'jugadores'], PERMISOS_POR_NIVEL['capitan']);
});

prueba('el capitán NO toca nada de la competencia', function () {
    // Si alguna de estas se cuela en la lista, un capitán podría cambiar resultados,
    // cobrar multas o rehacer el calendario de toda la liga.
    foreach (['partido_capturar', 'partidos_editar', 'sanciones', 'calendario', 'configuracion', 'colaboradores', 'patrocinadores', 'comentarios'] as $prohibido) {
        falso(in_array($prohibido, PERMISOS_POR_NIVEL['capitan'], true), "el capitán no debe tener '{$prohibido}'");
    }
});

prueba('los niveles que ya existían no cambiaron', function () {
    igual(['partido_capturar'], PERMISOS_POR_NIVEL['mesa'], 'la mesa sigue siendo solo la ficha');
    cierto(in_array('sanciones', PERMISOS_POR_NIVEL['asistente'], true), 'el asistente sigue cobrando multas');
    falso(in_array('calendario', PERMISOS_POR_NIVEL['asistente'], true), 'el asistente sigue sin tocar el calendario');
});

prueba('capitán es un nivel válido y se da por equipo', function () {
    cierto(colaborador_nivel_valido('capitan'));
    cierto(colaborador_nivel_por_equipo('capitan'), 'necesita que se elija de qué equipo');
    falso(colaborador_nivel_por_equipo('mesa'), 'la mesa trabaja en toda la copa');
    falso(colaborador_nivel_por_equipo('asistente'));
});

grupo('Sobre qué equipo puede trabajar');

prueba('el capitán alcanza su equipo', function () {
    cierto(acceso_alcanza_equipo('capitan', 7, 7));
});

prueba('el capitán NO alcanza ningún otro equipo', function () {
    falso(acceso_alcanza_equipo('capitan', 7, 8), 'el equipo de al lado');
    falso(acceso_alcanza_equipo('capitan', 7, 0), 'un id vacío');
    falso(acceso_alcanza_equipo('capitan', 7, -1), 'un id inventado');
});

prueba('un capitán sin equipo asignado no alcanza a nadie', function () {
    // Dato roto. Es el caso peligroso: si esto devolviera true, el nivel quedaría sin
    // límite y sería un asistente completo sobre equipos y jugadores.
    falso(acceso_alcanza_equipo('capitan', null, 7));
    falso(acceso_alcanza_equipo('capitan', null, 0));
});

prueba('el dueño y los colaboradores de copa alcanzan cualquier equipo', function () {
    cierto(acceso_alcanza_equipo('dueno', null, 7));
    cierto(acceso_alcanza_equipo('asistente', null, 7));
    cierto(acceso_alcanza_equipo('mesa', null, 99), 'su límite es otro, no el equipo');
});

prueba('quien no tiene nivel no alcanza nada', function () {
    falso(acceso_alcanza_equipo(null, null, 7), 'sin acceso a la copa');
    falso(acceso_alcanza_equipo(null, 7, 7), 'ni aunque venga un equipo pegado');
});
