<?php
declare(strict_types=1);

/**
 * El slug de la URL no corresponde a ninguna copa o liga activa.
 *
 * Es una página deliberadamente autónoma (no usa el layout del sitio): sin copa no hay
 * navbar que pintar, ni colores de marca, ni menú al que volver.
 */

vista('publico/copa_no_encontrada');
