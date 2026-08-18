<?php
declare(strict_types=1);

require_once __DIR__ . "/../config/bootstrap.php";

/**
 * Agrega el control de cupos de copas/ligas por organizador (cobro por torneo).
 *
 * Crea la columna correos_autorizados.limite_torneos con DEFAULT 1, de modo que cada
 * correo ya autorizado queda con una copa habilitada y el super-admin va subiendo el
 * cupo conforme el organizador paga los siguientes.
 *
 * Es idempotente: se puede correr las veces que haga falta sin efectos secundarios.
 *
 * Uso:  php scripts/migrar_cupos_torneos.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos (CLI).');
}

echo "== Migración: cupos de copas y ligas por organizador ==\n\n";

$pdo = db_conexion();

$existia = (bool) $pdo->query(
    "SELECT 1 FROM information_schema.columns
     WHERE table_name = 'correos_autorizados' AND column_name = 'limite_torneos'"
)->fetchColumn();

if ($existia) {
    echo "La columna 'limite_torneos' ya existía. No se modifica nada.\n\n";
} else {
    $pdo->exec('ALTER TABLE correos_autorizados ADD COLUMN IF NOT EXISTS limite_torneos INTEGER NOT NULL DEFAULT 1');
    echo "Columna 'limite_torneos' creada (valor por defecto: 1 copa o liga por correo).\n\n";
}

// Tablas de las mejoras profesionales (idempotentes, mismo criterio que schema.sql):
// recuperación de contraseña y estadísticas de visitas por copa.
$pdo->exec(
    'CREATE TABLE IF NOT EXISTS password_resets (
        id SERIAL PRIMARY KEY,
        usuario_id INTEGER NOT NULL REFERENCES usuarios(id) ON DELETE CASCADE,
        token_hash TEXT UNIQUE NOT NULL,
        expira_en TIMESTAMPTZ NOT NULL,
        creado_en TIMESTAMP NOT NULL DEFAULT now()
    )'
);
echo "Tabla 'password_resets' lista (recuperación de contraseña).\n";

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS visitas_diarias (
        torneo_id INTEGER NOT NULL REFERENCES torneos(id) ON DELETE CASCADE,
        fecha DATE NOT NULL,
        visitas INTEGER NOT NULL DEFAULT 0,
        PRIMARY KEY (torneo_id, fecha)
    )'
);
echo "Tabla 'visitas_diarias' lista (estadísticas de visitas por copa).\n\n";

// Resumen de cómo quedó cada correo autorizado, para revisar de un vistazo quién
// necesita más cupo antes de que reclamen que no pueden crear su torneo.
$filas = $pdo->query(
    'SELECT ca.email, ca.limite_torneos,
            (SELECT COUNT(*) FROM torneos t
             JOIN usuarios u ON u.id = t.usuario_id
             WHERE lower(u.email) = lower(ca.email)) AS usadas
     FROM correos_autorizados ca
     ORDER BY ca.creado_en ASC'
)->fetchAll();

if (empty($filas)) {
    echo "Todavía no hay correos en la lista blanca.\n";
} else {
    printf("%-40s %8s %8s\n", 'CORREO', 'USADAS', 'CUPO');
    printf("%s\n", str_repeat('-', 58));
    foreach ($filas as $f) {
        printf(
            "%-40s %8d %8d%s\n",
            $f['email'],
            (int) $f['usadas'],
            (int) $f['limite_torneos'],
            (int) $f['usadas'] >= (int) $f['limite_torneos'] ? '   <- sin cupo' : ''
        );
    }
}

echo "\nListo. Ajusta los cupos desde el panel: Admin -> Correos autorizados y cupos.\n";
