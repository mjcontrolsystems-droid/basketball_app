<?php
declare(strict_types=1);

/**
 * El generador de archivos de Excel.
 *
 * Un .xlsx escrito a mano falla de la peor manera posible: el archivo se descarga, pesa
 * lo esperado, y Excel dice "el formato no es válido" sin explicar nada. No hay forma de
 * depurarlo mirando la pantalla, así que las piezas que arman el XML se comprueban aquí.
 */

grupo('Referencias de columna');

prueba('las columnas van de A a Z y siguen en AA', function () {
    igual('A', excel_columna(0));
    igual('B', excel_columna(1));
    igual('Z', excel_columna(25));
    igual('AA', excel_columna(26), 'la columna 27 es AA, no A1 ni AB');
    igual('AB', excel_columna(27));
    igual('AZ', excel_columna(51));
    igual('BA', excel_columna(52));
});

grupo('XML de una hoja');

prueba('la primera fila va en negrita y las demás no', function () {
    $xml = excel_hoja_xml([['Equipo'], ['Promoción 45']]);
    cierto(str_contains($xml, '<c r="A1" t="inlineStr" s="1">'), 'el encabezado lleva el estilo 1');
    cierto(str_contains($xml, '<c r="A2" t="inlineStr">'), 'la fila de datos va sin estilo');
});

prueba('los números se guardan como números', function () {
    // Si salen como texto, en Excel no se pueden sumar — que es justo para lo que uno
    // exporta las cuentas.
    $xml = excel_hoja_xml([['Saldo'], [250.75], [3]]);
    cierto(str_contains($xml, '<c r="A2"><v>250.75</v></c>'), 'un decimal');
    cierto(str_contains($xml, '<c r="A3"><v>3</v></c>'), 'un entero');
});

prueba('el texto se guarda como texto aunque parezca número', function () {
    // El caso real: el dorsal "07". Como número perdería el cero de adelante.
    $xml = excel_hoja_xml([['Dorsal'], ['07']]);
    cierto(str_contains($xml, '<is><t xml:space="preserve">07</t></is>'), 'conserva el cero');
    falso(str_contains($xml, '<v>07</v>'));
});

prueba('las celdas vacías no se escriben', function () {
    $xml = excel_hoja_xml([['A', 'B', 'C'], ['dato', null, '']]);
    cierto(str_contains($xml, 'r="A2"'), 'la celda con dato sí');
    falso(str_contains($xml, 'r="B2"'), 'la nula no');
    falso(str_contains($xml, 'r="C2"'), 'la vacía tampoco');
});

prueba('el cero SÍ se escribe', function () {
    // Un saldo en cero es información: significa "al día". Si se cuela en la comprobación
    // de vacío, el equipo aparecería con la celda en blanco.
    $xml = excel_hoja_xml([['Saldo'], [0]]);
    cierto(str_contains($xml, '<c r="A2"><v>0</v></c>'));
});

prueba('los caracteres que rompen el XML se escapan', function () {
    // Un nombre de equipo con "&" dejaba el archivo ilegible sin ningún aviso.
    $xml = excel_hoja_xml([['Equipo'], ['Promo 45 & 46'], ['<Promo>'], ["Comillas \"dobles\""]]);
    cierto(str_contains($xml, 'Promo 45 &amp; 46'));
    cierto(str_contains($xml, '&lt;Promo&gt;'));
    falso(str_contains($xml, '<Promo>'), 'nunca queda una etiqueta inventada');
});

prueba('la hoja abre y cierra bien', function () {
    $xml = excel_hoja_xml([['Uno']]);
    cierto(str_starts_with($xml, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'));
    cierto(str_contains($xml, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'));
    cierto(str_ends_with($xml, '</sheetData></worksheet>'));
});

prueba('una hoja sin filas sigue siendo una hoja válida', function () {
    // Pasa con una copa recién creada: sin sanciones todavía. Un XML incompleto ahí
    // rompería el archivo entero, no solo esa hoja.
    $xml = excel_hoja_xml([]);
    cierto(str_contains($xml, '<sheetData></sheetData>'));
});

prueba('las filas y columnas se numeran desde 1', function () {
    $xml = excel_hoja_xml([['a', 'b'], ['c', 'd']]);
    foreach (['A1', 'B1', 'A2', 'B2'] as $ref) {
        cierto(str_contains($xml, 'r="' . $ref . '"'), "falta la celda {$ref}");
    }
});
