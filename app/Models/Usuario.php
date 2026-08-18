<?php
declare(strict_types=1);

require_once __DIR__ . '/../Support/bd.php';

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

// ---------------------------------------------------------------------------
// Recuperación de contraseña ("olvidé mi contraseña")
//
// El token que viaja en el enlace del correo NUNCA se guarda tal cual: se guarda su
// hash SHA-256, así una fuga de la tabla no permite restablecer contraseñas ajenas.
// Cada token vence en 1 hora y se destruye al usarse (un solo uso).
// ---------------------------------------------------------------------------

const PASSWORD_RESET_VIGENCIA_SEGUNDOS = 3600;

/**
 * Crea un token de restablecimiento para la cuenta del correo dado y devuelve el token
 * EN CLARO (para armar el enlace del correo). Devuelve null si el correo no corresponde
 * a una cuenta con contraseña propia — las cuentas de solo-Google no tienen contraseña
 * que restablecer, y no revelar cuáles correos existen es parte del diseño.
 */
function password_reset_crear(string $email): ?string
{
    $cuenta = usuarios_obtener_por_email($email);
    if ($cuenta === null || empty($cuenta['password_hash'])) {
        return null;
    }

    $token = bin2hex(random_bytes(32));
    $pdo = db_conexion();
    // Un token vigente por cuenta: pedir uno nuevo invalida el anterior.
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE usuario_id = :usuario_id');
    $stmt->bindValue(':usuario_id', (int) $cuenta['id'], PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare(
        'INSERT INTO password_resets (usuario_id, token_hash, expira_en) VALUES (:usuario_id, :token_hash, now() + make_interval(secs => :vigencia))'
    );
    $stmt->bindValue(':usuario_id', (int) $cuenta['id'], PDO::PARAM_INT);
    $stmt->bindValue(':token_hash', hash('sha256', $token), PDO::PARAM_STR);
    $stmt->bindValue(':vigencia', PASSWORD_RESET_VIGENCIA_SEGUNDOS, PDO::PARAM_INT);
    $stmt->execute();

    return $token;
}

/**
 * Devuelve la cuenta dueña de un token vigente, o null si el token es inválido o venció.
 */
function password_reset_validar(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT usuario_id FROM password_resets WHERE token_hash = :hash AND expira_en > now()');
    $stmt->bindValue(':hash', hash('sha256', $token), PDO::PARAM_STR);
    $stmt->execute();
    $usuarioId = $stmt->fetchColumn();
    if ($usuarioId === false) {
        return null;
    }
    return usuarios_obtener_por_id((int) $usuarioId);
}

/**
 * Cambia la contraseña de la cuenta del token y lo destruye (un solo uso). Aprovecha
 * para limpiar tokens vencidos de cualquier cuenta. Devuelve true si todo salió bien.
 */
function password_reset_consumir(string $token, string $passwordNueva): bool
{
    $cuenta = password_reset_validar($token);
    if ($cuenta === null) {
        return false;
    }
    $cuenta['password_hash'] = password_hash($passwordNueva, PASSWORD_DEFAULT);
    usuarios_guardar($cuenta);

    $pdo = db_conexion();
    $stmt = $pdo->prepare('DELETE FROM password_resets WHERE usuario_id = :usuario_id');
    $stmt->bindValue(':usuario_id', (int) $cuenta['id'], PDO::PARAM_INT);
    $stmt->execute();
    $pdo->exec('DELETE FROM password_resets WHERE expira_en < now()');
    return true;
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

/**
 * Cupo de torneos que se asigna a un correo recién autorizado. El modelo de cobro es por
 * torneo: al autorizar el correo se le habilita el primero, y el super-admin va subiendo
 * el cupo conforme el organizador paga los siguientes.
 */
const LIMITE_TORNEOS_POR_DEFECTO = 1;

/**
 * true si la columna correos_autorizados.limite_torneos ya existe en la base de datos.
 *
 * El control de cupos necesita esa columna (ver schema.sql), pero el código puede llegar
 * al servidor antes de que se corra la migración. En vez de reventar la página con un
 * error de SQL, se detecta una sola vez por petición y se cae al comportamiento anterior
 * (todos con el cupo por defecto) hasta que la columna exista.
 */
function correos_autorizados_tiene_columna_limite(): bool
{
    static $existe = null;
    if ($existe !== null) {
        return $existe;
    }
    try {
        $pdo = db_conexion();
        $stmt = $pdo->query(
            "SELECT 1 FROM information_schema.columns
             WHERE table_name = 'correos_autorizados' AND column_name = 'limite_torneos'"
        );
        $existe = (bool) $stmt->fetchColumn();
    } catch (Throwable $e) {
        $existe = false;
    }
    return $existe;
}

function correos_autorizados_agregar(string $email, int $limiteTorneos = LIMITE_TORNEOS_POR_DEFECTO): void
{
    $pdo = db_conexion();
    $conCupo = correos_autorizados_tiene_columna_limite();

    $sql = $conCupo
        ? 'INSERT INTO correos_autorizados (email, limite_torneos) VALUES (:email, :limite) ON CONFLICT (email) DO NOTHING'
        : 'INSERT INTO correos_autorizados (email) VALUES (:email) ON CONFLICT (email) DO NOTHING';

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':email', mb_strtolower(trim($email)), PDO::PARAM_STR);
    if ($conCupo) {
        $stmt->bindValue(':limite', max(0, $limiteTorneos), PDO::PARAM_INT);
    }
    $stmt->execute();
}

/**
 * Cambia cuántas copas o ligas puede tener creadas ese correo. 0 = no puede crear ninguna.
 */
function correos_autorizados_actualizar_limite(int $id, int $limiteTorneos): void
{
    if (!correos_autorizados_tiene_columna_limite()) {
        return;
    }
    $pdo = db_conexion();
    $stmt = $pdo->prepare('UPDATE correos_autorizados SET limite_torneos = :limite WHERE id = :id');
    $stmt->bindValue(':limite', max(0, $limiteTorneos), PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * Cupo de torneos de un correo según la lista blanca. Devuelve null si el correo no está
 * en la lista (cuentas anteriores al cierre del registro público), para que quien llama
 * decida el valor por defecto.
 */
function correos_autorizados_limite(string $email): ?int
{
    if (!correos_autorizados_tiene_columna_limite()) {
        return null;
    }
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT limite_torneos FROM correos_autorizados WHERE lower(email) = lower(:email)');
    $stmt->bindValue(':email', $email, PDO::PARAM_STR);
    $stmt->execute();
    $valor = $stmt->fetchColumn();
    return $valor === false ? null : (int) $valor;
}

/**
 * Cuántas copas o ligas puede tener creadas este usuario a la vez. Los super-admin no
 * tienen límite (devuelve null = ilimitado); el resto usa el cupo de su correo en la
 * lista blanca, o LIMITE_TORNEOS_POR_DEFECTO si su correo no está en ella.
 *
 * @return int|null null = sin límite
 */
function usuario_limite_torneos(?array $usuario): ?int
{
    if (es_superadmin($usuario)) {
        return null;
    }
    $email = (string) ($usuario['email'] ?? '');
    if ($email === '') {
        return LIMITE_TORNEOS_POR_DEFECTO;
    }
    return correos_autorizados_limite($email) ?? LIMITE_TORNEOS_POR_DEFECTO;
}

/**
 * true si el usuario todavía puede crear una copa o liga más. Se compara contra las copas
 * que tiene AHORA: si borra una, el cupo se libera y puede crear otra en su lugar.
 */
function usuario_puede_crear_torneo(?array $usuario): bool
{
    $limite = usuario_limite_torneos($usuario);
    if ($limite === null) {
        return true;
    }
    return torneos_contar_por_usuario((int) ($usuario['id'] ?? 0)) < $limite;
}

/**
 * Mensaje único que ve el organizador cuando se queda sin cupo, para que la alerta diga
 * lo mismo en todos los puntos donde se bloquea la creación.
 */
function mensaje_limite_torneos(?int $limite): string
{
    $cuantos = $limite === 1 ? '1 copa o liga' : $limite . ' copas o ligas';
    return "Has alcanzado el límite de {$cuantos} que tienes autorizadas. "
        . 'Por favor comunícate con el administrador para que te autorice más.';
}

function correos_autorizados_eliminar(int $id): void
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('DELETE FROM correos_autorizados WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}
