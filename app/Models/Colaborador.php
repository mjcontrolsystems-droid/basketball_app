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
    // El capitán es distinto de los otros dos: no ayuda a administrar la copa, administra
    // SU equipo. Por eso su acceso va amarrado a un equipo_id y no abarca nada más.
    'capitan' => [
        'nombre' => 'Capitán',
        'resumen' => 'Solo su equipo: arma su plantilla, actualiza el escudo y los datos, e imprime su nómina.',
        'por_equipo' => true,
    ],
];

/**
 * Qué alcanza cada nivel dentro de una copa. Lo consulta puede() en auth.php.
 *
 * El dueño puede todo. Los colaboradores son una ayuda acotada, y el corte no es
 * caprichoso: lo que se les deja es lo que se repite cada fin de semana y lo que, si se
 * equivocan, se arregla editando. Lo que decide la forma del torneo — calendario, formato,
 * reglas, quién más entra — se queda con el dueño, porque ahí un error se paga con
 * avisarle a 16 equipos que todo cambió.
 *
 * Va junto a COLABORADOR_NIVELES a propósito: agregar un nivel arriba y olvidar sus
 * permisos abajo daría un nivel que entra a todo o a nada, según el caso.
 */
const PERMISOS_POR_NIVEL = [
    // La mesa es quien está en la cancha: solo la ficha del partido.
    'mesa' => ['partido_capturar'],
    'asistente' => [
        'partido_capturar', 'partidos_editar', 'equipos', 'jugadores',
        'sanciones', 'patrocinadores', 'comentarios',
    ],
    // El capitán no administra la copa: administra SU equipo. Tiene los mismos permisos de
    // equipos y jugadores que un asistente, pero acotados a un solo equipo — el corte no lo
    // hace esta lista sino acceso_alcanza_equipo(), que se comprueba en cada pantalla. Sin
    // ese candado, este nivel sería un asistente completo.
    'capitan' => ['equipos', 'jugadores'],
];

function colaborador_nivel_valido(string $nivel): bool
{
    return isset(COLABORADOR_NIVELES[$nivel]);
}

/**
 * ¿Este nivel se da por equipo (y por lo tanto necesita que se elija cuál)?
 */
function colaborador_nivel_por_equipo(string $nivel): bool
{
    return !empty(COLABORADOR_NIVELES[$nivel]['por_equipo']);
}

/**
 * ¿Un acceso con este nivel y este equipo alcanza al equipo que se quiere tocar?
 *
 * Es la regla de seguridad del capitán, escrita sin depender de la sesión ni de la base
 * para poder comprobarla con casos de prueba. La usa capitan_puede_con_equipo() en
 * auth.php, que es la que sí sabe quién está logueado.
 *
 * Los niveles que no son por equipo (dueño, mesa, asistente) alcanzan a todos: su límite
 * es otro y ya lo puso puede(). Un nivel por equipo SIN equipo asignado no alcanza a
 * ninguno — es un dato roto, y ante la duda se cierra en vez de abrirse.
 *
 * @param ?string $nivel          Nivel del acceso ('dueno', 'mesa', 'asistente', 'capitan'...).
 * @param ?int    $equipoDelAcceso Equipo al que está amarrado (solo lo tiene el capitán).
 * @param int     $equipoId        El equipo que se quiere ver o modificar.
 */
function acceso_alcanza_equipo(?string $nivel, ?int $equipoDelAcceso, int $equipoId): bool
{
    if ($nivel === null) {
        return false;
    }
    if (!colaborador_nivel_por_equipo($nivel)) {
        return true;
    }

    return $equipoDelAcceso !== null && $equipoDelAcceso === $equipoId;
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
        'SELECT c.*, u.nombre AS nombre, u.foto AS foto, e.nombre AS equipo_nombre
           FROM colaboradores c
           LEFT JOIN usuarios u ON u.id = c.usuario_id
           LEFT JOIN equipos e ON e.id = c.equipo_id
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
        'equipo_id' => isset($f['equipo_id']) && $f['equipo_id'] !== null ? (int) $f['equipo_id'] : null,
        'equipo_nombre' => $f['equipo_nombre'] !== null ? (string) $f['equipo_nombre'] : null,
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
    return colaborador_acceso_de($torneoId, $usuarioId, $email)['nivel'] ?? null;
}

/**
 * El acceso completo de una persona en una copa: su nivel y, si es capitán, de qué equipo.
 *
 * El equipo importa tanto como el nivel: "capitán" sin equipo no significa nada, y toda la
 * seguridad de este nivel se apoya en ese número. Por eso los dos viajan juntos y se leen
 * en la misma consulta, en vez de andar preguntando el equipo por separado.
 *
 * @return array{nivel: string, equipo_id: ?int}|null null si no es colaboradora.
 */
function colaborador_acceso_de(int $torneoId, ?int $usuarioId, string $email): ?array
{
    $email = colaborador_normalizar_email($email);
    if ($torneoId <= 0 || ($usuarioId === null && $email === '')) {
        return null;
    }

    $pdo = db_conexion();
    $stmt = $pdo->prepare(
        'SELECT id, nivel, usuario_id, equipo_id FROM colaboradores
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

    return [
        'nivel' => (string) $fila['nivel'],
        'equipo_id' => $fila['equipo_id'] !== null ? (int) $fila['equipo_id'] : null,
    ];
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
function colaboradores_guardar(int $torneoId, string $email, string $nivel, int $usuarioIdDueno, ?int $equipoId = null): void
{
    $email = colaborador_normalizar_email($email);
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Ese correo no es válido.');
    }
    if (!colaborador_nivel_valido($nivel)) {
        throw new RuntimeException('Ese nivel de colaborador no existe.');
    }

    // Un capitán sin equipo no tendría nada que administrar, y —más importante— el
    // candado que le impide tocar los demás equipos se apoya en este número: si llegara
    // vacío, el nivel quedaría sin límite. Se corta aquí.
    if (colaborador_nivel_por_equipo($nivel)) {
        if ($equipoId === null || $equipoId < 1) {
            throw new RuntimeException('Elige de qué equipo es capitán.');
        }
        if (!equipo_pertenece_al_torneo($equipoId, $torneoId)) {
            throw new RuntimeException('Ese equipo no es de esta copa.');
        }
    } else {
        $equipoId = null;   // mesa y asistente trabajan en toda la copa
    }

    $dueno = usuarios_obtener_por_id($usuarioIdDueno);
    if ($dueno !== null && colaborador_normalizar_email((string) ($dueno['email'] ?? '')) === $email) {
        throw new RuntimeException('Ese es tu propio correo: ya eres el dueño de esta copa.');
    }

    $usuario = usuarios_obtener_por_email($email);

    $pdo = db_conexion();
    $stmt = $pdo->prepare(
        'INSERT INTO colaboradores (torneo_id, email, nivel, usuario_id, equipo_id)
         VALUES (:torneo, :email, :nivel, :usuario, :equipo)
         ON CONFLICT (torneo_id, email) DO UPDATE SET nivel = EXCLUDED.nivel, equipo_id = EXCLUDED.equipo_id'
    );
    $stmt->bindValue(':torneo', $torneoId, PDO::PARAM_INT);
    $stmt->bindValue(':email', $email);
    $stmt->bindValue(':nivel', $nivel);
    $stmt->bindValue(':usuario', $usuario['id'] ?? null, $usuario !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
    db_bind($stmt, ':equipo', $equipoId);
    $stmt->execute();
}

/**
 * ¿Ese equipo es de esa copa? Se pregunta antes de amarrarle un capitán, para que nadie
 * pueda mandar el id de un equipo ajeno en el formulario y quedarse con acceso a otra liga.
 */
function equipo_pertenece_al_torneo(int $equipoId, int $torneoId): bool
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT 1 FROM equipos WHERE id = :id AND torneo_id = :torneo LIMIT 1');
    $stmt->bindValue(':id', $equipoId, PDO::PARAM_INT);
    $stmt->bindValue(':torneo', $torneoId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetch() !== false;
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
    $equipoNombre = '';
    foreach (colaboradores_listar((int) $torneo['id']) as $c) {
        if ($c['email'] === $email) {
            $id = $c['id'];
            $nivel = $c['nivel'];
            $equipoNombre = (string) ($c['equipo_nombre'] ?? '');
        }
    }
    if ($id === null) {
        return false;
    }

    // Al capitán hay que decirle de qué equipo lo hicieron: "eres capitán" a secas, en una
    // liga de 16 promociones, no le dice nada.
    $resumen = COLABORADOR_NIVELES[$nivel]['resumen'] ?? '';
    if ($equipoNombre !== '') {
        $resumen = 'Tu equipo: ' . $equipoNombre . '. ' . $resumen;
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
        $resumen,
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
