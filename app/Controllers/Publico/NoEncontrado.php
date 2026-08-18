<?php
declare(strict_types=1);

// Página de error 404 con la marca del sitio (Apache la sirve vía ErrorDocument, ver
// apache-vhost.conf). Un error con diseño transmite cuidado; un texto plano, abandono.
http_response_code(404);

vista('publico/no_encontrado');
