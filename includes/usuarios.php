<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

const COLUMNAS_USUARIO = ['usuario', 'email', 'nombre', 'cargo', 'telefono', 'foto', 'bio'];

function usuarios_normalizar(array $fila): array
{
    $fila['id'] = (int) $fila['id'];
    return $fila;
}

function usuarios_obtener_por_id(int $id): ?array
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $fila = $stmt->fetch();
    return $fila ? usuarios_normalizar($fila) : null;
}

function usuarios_obtener_por_usuario(string $usuario): ?array
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE usuario = :usuario');
    $stmt->bindValue(':usuario', $usuario, PDO::PARAM_STR);
    $stmt->execute();
    $fila = $stmt->fetch();
    return $fila ? usuarios_normalizar($fila) : null;
}

function usuarios_obtener_por_email(string $email): ?array
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE email = :email');
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $fila = $stmt->fetch();
    return $fila ? usuarios_normalizar($fila) : null;
}

function usuarios_obtener_por_google_id(string $googleId): ?array
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT * FROM usuarios WHERE google_id = :google_id');
    $stmt->bindValue(':google_id', $googleId, PDO::PARAM_STR);
    $stmt->execute();
    $fila = $stmt->fetch();
    return $fila ? usuarios_normalizar($fila) : null;
}

/**
 * Crea una cuenta nueva. $datos: usuario, email, nombre; password_hash es opcional
 * (queda NULL para cuentas que solo entran con "Continuar con Google"); google_id
 * opcional. Devuelve el id ya creado.
 */
function usuarios_crear(array $datos): int
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare(
        'INSERT INTO usuarios (usuario, email, password_hash, nombre, cargo, telefono, foto, bio, google_id)
         VALUES (:usuario, :email, :password_hash, :nombre, :cargo, :telefono, :foto, :bio, :google_id) RETURNING id'
    );
    $stmt->bindValue(':usuario', $datos['usuario'], PDO::PARAM_STR);
    $stmt->bindValue(':email', $datos['email'], PDO::PARAM_STR);
    db_bind($stmt, ':password_hash', $datos['password_hash'] ?? null);
    $stmt->bindValue(':nombre', $datos['nombre'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':cargo', $datos['cargo'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':telefono', $datos['telefono'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':foto', $datos['foto'] ?? '', PDO::PARAM_STR);
    $stmt->bindValue(':bio', $datos['bio'] ?? '', PDO::PARAM_STR);
    db_bind($stmt, ':google_id', $datos['google_id'] ?? null);
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

/**
 * Vincula una cuenta de Google a una cuenta ya existente (creada con usuario/contraseña),
 * para que a partir de ahora también pueda entrar con "Continuar con Google" sin duplicar cuentas.
 */
function usuarios_vincular_google(int $id, string $googleId): void
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('UPDATE usuarios SET google_id = :google_id WHERE id = :id');
    $stmt->bindValue(':google_id', $googleId, PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * Actualiza el perfil (y opcionalmente la contraseña) del usuario ya identificado por
 * $datos['id']. Nunca toca 'usuario' (el nombre de acceso no se puede cambiar aquí).
 */
function usuarios_guardar(array $datos): bool
{
    $pdo = db_conexion();
    $columnas = ['nombre', 'cargo', 'email', 'telefono', 'foto', 'bio'];
    if (!empty($datos['password_hash'])) {
        $columnas[] = 'password_hash';
    }
    $sets = implode(', ', array_map(fn($c) => "{$c} = :{$c}", $columnas));
    $stmt = $pdo->prepare("UPDATE usuarios SET {$sets} WHERE id = :id");
    foreach ($columnas as $c) {
        db_bind($stmt, ":{$c}", $datos[$c] ?? '');
    }
    $stmt->bindValue(':id', (int) $datos['id'], PDO::PARAM_INT);
    return $stmt->execute();
}

/**
 * Dueño real de una copa (organizador.php y patrocinadores.php lo usan para mostrar
 * el contacto correcto en vez de un organizador global único).
 */
function torneo_organizador(array $torneo): ?array
{
    if (empty($torneo['usuario_id'])) {
        return null;
    }
    return usuarios_obtener_por_id((int) $torneo['usuario_id']);
}

/**
 * Un super-admin es quien puede gestionar la lista blanca de correos autorizados a
 * entrar con Google. Se define por correo en la variable de entorno SUPERADMIN_EMAILS,
 * no por una columna en la base de datos, para que otorgar/quitar el rol no dependa de
 * escribir en la tabla usuarios.
 */
function es_superadmin(?array $usuario): bool
{
    if ($usuario === null || empty($usuario['email'])) {
        return false;
    }
    return in_array(mb_strtolower($usuario['email']), SUPERADMIN_EMAILS, true);
}

/**
 * El registro público (usuario/contraseña) está cerrado: solo se puede crear una cuenta
 * nueva con "Continuar con Google" si el correo está en esta lista blanca, administrada
 * por el/los super-admin. No afecta a cuentas que ya existían antes de cerrar el registro.
 *
 * Los correos de SUPERADMIN_EMAILS siempre están autorizados, aunque no estén en la tabla:
 * si no fuera así, la primera vez que un super-admin entra con Google (cuenta todavía no
 * creada) quedaría bloqueado, y no habría nadie que lo agregara a la lista.
 */
function correo_autorizado(string $email): bool
{
    if (in_array(mb_strtolower(trim($email)), SUPERADMIN_EMAILS, true)) {
        return true;
    }

    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT 1 FROM correos_autorizados WHERE lower(email) = lower(:email)');
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    return (bool) $stmt->fetchColumn();
}

function correos_autorizados_listar(): array
{
    $pdo = db_conexion();
    $stmt = $pdo->query('SELECT * FROM correos_autorizados ORDER BY creado_en DESC');
    return $stmt->fetchAll();
}

function correos_autorizados_agregar(string $email): void
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('INSERT INTO correos_autorizados (email) VALUES (:email) ON CONFLICT (email) DO NOTHING');
    $stmt->bindValue(':email', mb_strtolower(trim($email)), PDO::PARAM_STR);
    $stmt->execute();
}

function correos_autorizados_eliminar(int $id): void
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('DELETE FROM correos_autorizados WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}
