<?php
declare(strict_types=1);

require_once __DIR__ . "/../config/bootstrap.php";

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos (CLI).');
}

echo "== Migración a plataforma multi-usuario ==\n\n";

$pdo = db_conexion();

// Idempotencia: si ya hay al menos un usuario, no repetir la migración.
$yaMigrado = $pdo->query('SELECT id FROM usuarios LIMIT 1')->fetchColumn();
if ($yaMigrado) {
    echo "Ya existe al menos un usuario (id={$yaMigrado}). No se repite la migración.\n";
    exit;
}

$organizador = $pdo->query('SELECT * FROM organizador WHERE id = 1')->fetch();
if (!$organizador) {
    echo "No se encontró la fila 'organizador'. Nada que migrar (¿instalación nueva?).\n";
    exit;
}

echo "Creando el primer usuario a partir del organizador existente...\n";
$emailOrganizador = trim((string) ($organizador['email'] ?? ''));
if ($emailOrganizador === '') {
    $emailOrganizador = $organizador['usuario'] . '@migrado.local';
    echo "  (el organizador no tenía correo; se usa uno provisional: {$emailOrganizador} — actualízalo en Mi Perfil)\n";
}

$stmt = $pdo->prepare(
    'INSERT INTO usuarios (usuario, email, password_hash, nombre, cargo, telefono, foto, bio)
     VALUES (:usuario, :email, :password_hash, :nombre, :cargo, :telefono, :foto, :bio)
     RETURNING id'
);
$stmt->execute([
    ':usuario' => $organizador['usuario'],
    ':email' => $emailOrganizador,
    ':password_hash' => $organizador['password_hash'],
    ':nombre' => $organizador['nombre'],
    ':cargo' => $organizador['cargo'],
    ':telefono' => $organizador['telefono'],
    ':foto' => $organizador['foto'],
    ':bio' => $organizador['bio'],
]);
$usuarioId = (int) $stmt->fetchColumn();
echo "  Usuario creado: id={$usuarioId}, usuario={$organizador['usuario']}\n";

echo "\nAsignando las copas existentes a este usuario...\n";
$n = $pdo->exec("UPDATE torneos SET usuario_id = {$usuarioId} WHERE usuario_id IS NULL");
echo "  torneos: {$n} filas asignadas\n";
$pdo->exec('ALTER TABLE torneos ALTER COLUMN usuario_id SET NOT NULL');
echo "  torneos.usuario_id ahora es NOT NULL\n";

echo "\nGenerando código corto para las copas que todavía no tienen uno...\n";
$sinCodigo = $pdo->query('SELECT id, nombre FROM torneos WHERE codigo IS NULL')->fetchAll();
foreach ($sinCodigo as $t) {
    $codigo = torneos_generar_codigo_unico();
    $pdo->prepare('UPDATE torneos SET codigo = :codigo WHERE id = :id')
        ->execute([':codigo' => $codigo, ':id' => $t['id']]);
    echo "  {$t['nombre']} (id={$t['id']}): código {$codigo}\n";
}
$pdo->exec('ALTER TABLE torneos ALTER COLUMN codigo SET NOT NULL');
echo "  torneos.codigo ahora es NOT NULL\n";

echo "\n== Migración completa ==\n";
echo "La tabla 'organizador' se deja intacta como red de seguridad (no se usa más en el código).\n";
