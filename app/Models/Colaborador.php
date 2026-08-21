<?php
declare(strict_types=1);

/**
 * Colaboradores de una copa: gente que ayuda a administrarla sin ser su dueño.
 *
 * Una copa siempre tiene UN dueño (torneos.usuario_id) y ese no cambia. Los colaboradores
 * son un permiso adicional, por copa, que el dueño da y quita cuando quiera.
 *
 * Se guardan por CORREO y no por id de usuario, a propósito: casi siempre se invita a
 * alguien que todavía no tiene cuenta. El correo se autoriza, la persona entra con su
 * Google, y recién ahí existe el usuario. Guardar el correo permite dejar la invitación
 * hecha antes de que eso pase; usuario_id se llena cuando entra por primera vez, y sirve
 * para que un cambio de correo no le quite el acceso.
 */

const COLABORADOR_NIVELES = [
    'mesa' => [
        'nombre' => 'Mesa',
        'resumen' => 'Solo captura lo que pasa en el partido: alineación, goles, tarjetas, cronómetro y finalizar.',
    ],
    'asistente' => [
        'nombre' => 'Asistente',
        'resumen' => 'Además maneja equipos, plantillas, sanciones, patrocinadores y comentarios.',
    ],
];

function colaborador_nivel_valido(string $nivel): bool
{
    return isset(COLABORADOR_NIVELES[$nivel]);
}

function colaborador_nivel_nombre(string $nivel): string
{
    return COLABORADOR_NIVELES[$nivel]['nombre'] ?? $nivel;
}

/**
 * Los correos se comparan siempre en minúsculas y sin espacios: quien invita escribe
 * "Juan@Gmail.com " y Google devuelve "juan@gmail.com".
 */
function colaborador_normalizar_email(string $email): string
{
    return mb_strtolower(trim($email));
}

/**
 * @return array<int, array{id:int, torneo_id:int, email:string, nivel:string, usuario_id:?int, nombre:?string}>
 */
function colaboradores_listar(int $torneoId): array
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare(
        'SELECT c.*, u.nombre AS nombre, u.foto AS foto
           FROM colaboradores c
           LEFT JOIN usuarios u ON u.id = c.usuario_id
          WHERE c.torneo_id = :torneo
          ORDER BY c.creado_en ASC'
    );
    $stmt->bindValue(':torneo', $torneoId, PDO::PARAM_INT);
    $stmt->execute();

    return array_map(static fn($f) => [
        'id' => (int) $f['id'],
        'torneo_id' => (int) $f['torneo_id'],
        'email' => (string) $f['email'],
        'nivel' => (string) $f['nivel'],
        'usuario_id' => $f['usuario_id'] !== null ? (int) $f['usuario_id'] : null,
        'nombre' => $f['nombre'] !== null ? (string) $f['nombre'] : null,
        'foto' => $f['foto'] !== null ? (string) $f['foto'] : null,
        'aceptado_en' => $f['aceptado_en'] ?? null,
    ], $stmt->fetchAll());
}

/**
 * Genera (o regenera) el token de invitación y lo devuelve para armar el enlace.
 *
 * Se regenera en cada envío: si el organizador reenvía la invitación, el enlace viejo
 * deja de servir. 32 bytes al azar en hexadecimal — no se puede adivinar.
 */
function colaborador_token_nuevo(int $id): string
{
    $token = bin2hex(random_bytes(32));

    $pdo = db_conexion();
    $stmt = $pdo->prepare('UPDATE colaboradores SET token = :token WHERE id = :id');
    $stmt->bindValue(':token', $token);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    return $token;
}

/**
 * Busca la invitación de un token, con el nombre de la copa para poder mostrarla.
 *
 * @return array|null
 */
function colaborador_por_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '') {
        return null;
    }

    $pdo = db_conexion();
    $stmt = $pdo->prepare(
        'SELECT c.*, t.nombre AS torneo_nombre, t.slug AS torneo_slug, t.logo AS torneo_logo
           FROM colaboradores c
           JOIN torneos t ON t.id = c.torneo_id
          WHERE c.token = :token
          LIMIT 1'
    );
    $stmt->bindValue(':token', $token);
    $stmt->execute();
    $fila = $stmt->fetch();

    return $fila ?: null;
}

/**
 * Marca la invitación como aceptada y la amarra a la cuenta que entró.
 *
 * El token se borra al aceptar: el enlace sirve una sola vez. El acceso a partir de aquí
 * ya no depende de él, sino de que la cuenta esté en la lista de colaboradores.
 */
function colaborador_aceptar(int $id, int $usuarioId): void
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare(
        'UPDATE colaboradores
            SET usuario_id = :usuario, aceptado_en = NOW(), token = NULL
          WHERE id = :id'
    );
    $stmt->bindValue(':usuario', $usuarioId, PDO::PARAM_INT);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}

/**
 * El nivel de una persona en una copa, o null si no es colaboradora.
 *
 * Busca por id de usuario y también por correo, porque la invitación puede existir desde
 * antes de que la persona tuviera cuenta.
 */
function colaborador_nivel_de(int $torneoId, ?int $usuarioId, string $email): ?string
{
    $email = colaborador_normalizar_email($email);
    if ($torneoId <= 0 || ($usuarioId === null && $email === '')) {
        return null;
    }

    $pdo = db_conexion();
    $stmt = $pdo->prepare(
        'SELECT id, nivel, usuario_id FROM colaboradores
          WHERE torneo_id = :torneo AND (usuario_id = :usuario OR email = :email)
          LIMIT 1'
    );
    $stmt->bindValue(':torneo', $torneoId, PDO::PARAM_INT);
    $stmt->bindValue(':usuario', $usuarioId ?? 0, PDO::PARAM_INT);
    $stmt->bindValue(':email', $email);
    $stmt->execute();
    $fila = $stmt->fetch();
    if (!$fila) {
        return null;
    }

    // Primera entrada de un invitado: se deja amarrado su id para que siga teniendo
    // acceso aunque después cambie de correo.
    if ($usuarioId !== null && $fila['usuario_id'] === null) {
        $amarrar = $pdo->prepare('UPDATE colaboradores SET usuario_id = :usuario WHERE id = :id');
        $amarrar->bindValue(':usuario', $usuarioId, PDO::PARAM_INT);
        $amarrar->bindValue(':id', (int) $fila['id'], PDO::PARAM_INT);
        $amarrar->execute();
    }

    return (string) $fila['nivel'];
}

/**
 * Ids de las copas donde esta persona colabora (para que aparezcan en "Mis copas").
 *
 * @return array<int, int>
 */
function colaborador_torneos_de(?int $usuarioId, string $email): array
{
    $email = colaborador_normalizar_email($email);
    if ($usuarioId === null && $email === '') {
        return [];
    }

    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT torneo_id FROM colaboradores WHERE usuario_id = :usuario OR email = :email');
    $stmt->bindValue(':usuario', $usuarioId ?? 0, PDO::PARAM_INT);
    $stmt->bindValue(':email', $email);
    $stmt->execute();

    return array_map('intval', array_column($stmt->fetchAll(), 'torneo_id'));
}

/**
 * Invita a alguien (o le cambia el nivel si ya estaba invitado).
 *
 * @throws RuntimeException si el correo es inválido, es el del propio dueño, o el nivel no existe.
 */
function colaboradores_guardar(int $torneoId, string $email, string $nivel, int $usuarioIdDueno): void
{
    $email = colaborador_normalizar_email($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Ese correo no es válido.');
    }
    if (!colaborador_nivel_valido($nivel)) {
        throw new RuntimeException('Ese nivel de colaborador no existe.');
    }

    $dueno = usuarios_obtener_por_id($usuarioIdDueno);
    if ($dueno !== null && colaborador_normalizar_email((string) ($dueno['email'] ?? '')) === $email) {
        throw new RuntimeException('Ese es tu propio correo: ya eres el dueño de esta copa.');
    }

    $usuario = usuarios_obtener_por_email($email);

    $pdo = db_conexion();
    $stmt = $pdo->prepare(
        'INSERT INTO colaboradores (torneo_id, email, nivel, usuario_id)
         VALUES (:torneo, :email, :nivel, :usuario)
         ON CONFLICT (torneo_id, email) DO UPDATE SET nivel = EXCLUDED.nivel'
    );
    $stmt->bindValue(':torneo', $torneoId, PDO::PARAM_INT);
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':nivel', $nivel);
    $stmt->bindValue(':usuario', $usuario['id'] ?? null, $usuario !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
    $stmt->execute();
}

/**
 * Genera un token nuevo y manda (o remanda) el correo de invitación.
 *
 * Devuelve false si el correo no salió — la invitación igual queda creada, porque el
 * acceso no depende del correo sino de estar en la lista. El correo es el aviso, no la
 * llave: si no llega, la persona entra igual con su Google y el organizador puede
 * pasarle el enlace por WhatsApp.
 */
function colaborador_enviar_invitacion(array $torneo, string $email, int $usuarioIdQueInvita): bool
{
    $email = colaborador_normalizar_email($email);

    $id = null;
    $nivel = 'mesa';
    foreach (colaboradores_listar((int) $torneo['id']) as $c) {
        if ($c['email'] === $email) {
            $id = $c['id'];
            $nivel = $c['nivel'];
        }
    }
    if ($id === null) {
        return false;
    }

    $token = colaborador_token_nuevo($id);
    $quien = usuarios_obtener_por_id($usuarioIdQueInvita);
    $nombreQuien = trim((string) ($quien['nombre'] ?? '')) !== ''
        ? (string) $quien['nombre']
        : (string) ($quien['usuario'] ?? 'El organizador');

    return correo_invitar_colaborador(
        $email,
        $nombreQuien,
        (string) $torneo['nombre'],
        colaborador_nivel_nombre($nivel),
        COLABORADOR_NIVELES[$nivel]['resumen'] ?? '',
        SITE_ORIGIN . url('invitacion.php?t=' . rawurlencode($token))
    );
}

function colaboradores_eliminar(int $id, int $torneoId): void
{
    $pdo = db_conexion();
    // El torneo va en el WHERE para que nadie pueda borrar colaboradores de otra copa
    // mandando un id ajeno en el formulario.
    $stmt = $pdo->prepare('DELETE FROM colaboradores WHERE id = :id AND torneo_id = :torneo');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->bindValue(':torneo', $torneoId, PDO::PARAM_INT);
    $stmt->execute();
}
