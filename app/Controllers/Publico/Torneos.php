<?php
declare(strict_types=1);

// El directorio público de todas las copas y ligas se cerró (ya no se puede "navegar"
// y ver qué existe en la plataforma): cada copa/liga solo se encuentra por su URL
// directa, su QR o su código de 6 caracteres, compartidos por el organizador.
header('Location: ' . url('/'));
exit;
