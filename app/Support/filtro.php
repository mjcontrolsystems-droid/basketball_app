<?php
declare(strict_types=1);

/**
 * Lista de raíces de palabras consideradas lenguaje inapropiado (español e inglés).
 * Se compara contra el texto normalizado (sin acentos, en minúsculas).
 */
const PALABRAS_PROHIBIDAS = [
    'mierda', 'puta', 'puto', 'putas', 'putos', 'putazo', 'pendejo', 'pendeja', 'pendejada',
    'cabron', 'cabrona', 'cabrones', 'joder', 'jodido', 'jodida', 'cono', 'carajo', 'gilipollas',
    'maricon', 'marica', 'verga', 'pinga', 'culero', 'culera', 'culo', 'chinga', 'chingada',
    'chingar', 'chingado', 'hijueputa', 'hijodeputa', 'estupido', 'estupida', 'imbecil',
    'zorra', 'perra', 'malparido', 'malparida', 'conchatumadre', 'pajero', 'pajera',
    'mamahuevo', 'mamahuevos', 'mamon', 'mamona', 'idiota', 'baboso', 'babosa', 'tarado',
    'tarada', 'retrasado', 'retrasada', 'gonorrea', 'marimacha', 'negro de mierda',
    'fuck', 'fucking', 'shit', 'bitch', 'asshole', 'bastard', 'cunt', 'slut', 'whore',
    'faggot', 'nigger', 'retard', 'motherfucker',
];

function normalizar_texto_filtro(string $texto): string
{
    $texto = mb_strtolower($texto, 'UTF-8');
    $mapa = [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n',
        '@' => 'a', '3' => 'e', '1' => 'i', '0' => 'o', '$' => 's',
    ];
    return strtr($texto, $mapa);
}

/**
 * Devuelve true si el texto contiene alguna palabra de la lista de prohibidas.
 */
function contiene_lenguaje_inapropiado(string $texto): bool
{
    $normalizado = normalizar_texto_filtro($texto);
    $tokens = preg_split('/[^a-z]+/', $normalizado, -1, PREG_SPLIT_NO_EMPTY);
    if ($tokens === false) {
        return false;
    }

    foreach ($tokens as $token) {
        foreach (PALABRAS_PROHIBIDAS as $palabra) {
            if ($token === $palabra) {
                return true;
            }
            // Coincidencia por raíz para variantes de género/plural en palabras largas
            if (mb_strlen($palabra) >= 6 && str_starts_with($token, $palabra)) {
                return true;
            }
        }
    }

    return false;
}
