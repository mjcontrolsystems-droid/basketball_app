<?php
declare(strict_types=1);

require_once __DIR__ . '/../Support/bd.php';

// La copa o liga en sí: alta y edición, búsqueda por slug/código/id, y borrado.
// Modelo: solo acceso a datos. No imprime nada ni sabe de HTTP; de eso se encargan
// los controladores (app/Controllers) y las vistas (app/Views).

// 'modo' vuelve a estar aquí, pero con OTRO significado que el original: ya no es el
// interruptor de "jugadores/eventos/PDF" (eso está disponible siempre), sino el FORMATO
// de la competencia — 'liga' (solo tabla de puntos) o 'copa' (campeonato con eliminación
// directa). Ver torneo_es_liga()/torneo_fases_playoff() en app/Support/liga.php. Las copas
// que ya existían quedaron en 'copa' por el DEFAULT de la columna, así que siguen
// comportándose exactamente igual que antes.
const COLUMNAS_TORNEO = [
    'slug', 'nombre', 'subtitulo', 'temporada', 'descripcion', 'sede_principal', 'logo',
    'color_primario', 'color_secundario', 'color_acento', 'fecha_inicio', 'fecha_fin', 'formato',
    'instagram', 'hero_frase', 'deporte', 'num_equipos', 'fases_playoff', 'permite_empates',
    'puntos_victoria', 'puntos_empate', 'puntos_derrota', 'es_predeterminado', 'activo',
    'genero', 'modalidad', 'duracion_periodo_min', 'modo', 'vueltas',
    'multa_amarilla', 'multa_roja', 'sancion_bloquea', 'partidos_suspension_roja', 'moneda',
    'reglamento', 'reglamento_nombre',
    'amarillas_para_suspension', 'partidos_suspension_amarillas',
];

/**
 * true si la copa tiene un reglamento en PDF cargado. Gobierna si aparecen el enlace del
 * menú público y el apartado del sitio: una copa sin reglamento no muestra nada.
 */
function torneo_tiene_reglamento(?array $torneo): bool
{
    return !empty($torneo['reglamento']);
}

/**
 * URL del PDF del reglamento. $descargar fuerza la descarga en vez de abrirlo en el visor.
 */
function url_reglamento(array $torneo, bool $descargar = false): string
{
    if (empty($torneo['reglamento'])) {
        return '#';
    }
    // El nombre viaja en la URL solo para que el archivo descargado se llame bonito;
    // el contenido se resuelve por el id.
    $nombre = trim((string) ($torneo['reglamento_nombre'] ?? '')) !== ''
        ? pathinfo((string) $torneo['reglamento_nombre'], PATHINFO_FILENAME)
        : 'reglamento-' . ($torneo['slug'] ?? 'copa');

    return url('documento.php?id=' . rawurlencode((string) $torneo['reglamento'])
        . '&nombre=' . rawurlencode($nombre)
        . ($descargar ? '&descargar=1' : ''));
}

function db_parsear_array_pg(?string $valor): array
{
    if ($valor === null) {
        return [];
    }
    $valor = trim($valor, '{}');
    return $valor === '' ? [] : explode(',', $valor);
}

function db_normalizar_torneo(array $fila): array
{
    foreach (['id', 'usuario_id', 'num_equipos', 'puntos_victoria', 'puntos_empate', 'puntos_derrota', 'duracion_periodo_min', 'vueltas', 'partidos_suspension_roja', 'amarillas_para_suspension', 'partidos_suspension_amarillas'] as $col) {
        if (array_key_exists($col, $fila) && $fila[$col] !== null) {
            $fila[$col] = (int) $fila[$col];
        }
    }
    // Las multas son decimales (NUMERIC), no enteros: PDO las devuelve como texto.
    foreach (['multa_amarilla', 'multa_roja'] as $col) {
        if (array_key_exists($col, $fila) && $fila[$col] !== null) {
            $fila[$col] = (float) $fila[$col];
        }
    }
    foreach (['permite_empates', 'es_predeterminado', 'activo', 'sancion_bloquea'] as $col) {
        if (array_key_exists($col, $fila)) {
            $fila[$col] = (bool) (
                is_string($fila[$col]) ? ($fila[$col] === 't' || $fila[$col] === '1') : $fila[$col]
            );
        }
    }
    if (array_key_exists('fases_playoff', $fila)) {
        $fila['fases_playoff'] = db_parsear_array_pg($fila['fases_playoff']);
    }
    return $fila;
}

/**
 * Lista copas. Si se pasa $usuarioId, solo devuelve las copas de ese usuario (uso normal
 * del panel admin, "Mis Copas"); sin él, lista todas (páginas públicas).
 */
function torneos_listar(bool $soloActivos = true, ?int $usuarioId = null): array
{
    $pdo = db_conexion();
    $condiciones = [];
    if ($soloActivos) {
        $condiciones[] = 'activo = true';
    }
    if ($usuarioId !== null) {
        $condiciones[] = 'usuario_id = :usuario_id';
    }
    $sql = 'SELECT * FROM torneos' . ($condiciones ? ' WHERE ' . implode(' AND ', $condiciones) : '') . ' ORDER BY es_predeterminado DESC, creado_en ASC';
    $stmt = $pdo->prepare($sql);
    if ($usuarioId !== null) {
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    }
    $stmt->execute();
    return array_map('db_normalizar_torneo', $stmt->fetchAll());
}

/**
 * Cuántas copas o ligas tiene creadas este usuario ahora mismo. Lo usa el control de cupo
 * por usuario (ver usuario_puede_crear_torneo() en app/Models/Usuario.php). Cuenta también
 * las inactivas: siguen ocupando un lugar mientras no se borren.
 */
function torneos_contar_por_usuario(int $usuarioId): int
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM torneos WHERE usuario_id = :usuario_id');
    $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    $stmt->execute();
    return (int) $stmt->fetchColumn();
}

function torneos_obtener_por_slug(string $slug): ?array
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT * FROM torneos WHERE slug = :slug AND activo = true');
    $stmt->bindValue(':slug', $slug, PDO::PARAM_STR);
    $stmt->execute();
    $fila = $stmt->fetch();
    return $fila ? db_normalizar_torneo($fila) : null;
}

function torneos_obtener_predeterminado(): ?array
{
    $pdo = db_conexion();
    $fila = $pdo->query('SELECT * FROM torneos WHERE es_predeterminado = true LIMIT 1')->fetch();
    return $fila ? db_normalizar_torneo($fila) : null;
}

/**
 * Si se pasa $usuarioId, solo devuelve la copa si además pertenece a ese usuario —
 * es el filtro que evita que un usuario entre/edite/borre la copa de otro adivinando su id.
 * Sin él (páginas públicas, resolución por slug/código), devuelve cualquier copa por id.
 */
function torneos_obtener_por_id(int $id, ?int $usuarioId = null): ?array
{
    $pdo = db_conexion();
    $sql = 'SELECT * FROM torneos WHERE id = :id' . ($usuarioId !== null ? ' AND usuario_id = :usuario_id' : '');
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    if ($usuarioId !== null) {
        $stmt->bindValue(':usuario_id', $usuarioId, PDO::PARAM_INT);
    }
    $stmt->execute();
    $fila = $stmt->fetch();
    return $fila ? db_normalizar_torneo($fila) : null;
}

/**
 * Alfabeto sin caracteres ambiguos (sin 0/O, 1/I/L) para que el código sea fácil de
 * leer/dictar en voz alta, tipo código de sala de juego.
 */
const TORNEO_CODIGO_ALFABETO = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

function torneos_generar_codigo_unico(int $largo = 6): string
{
    do {
        $codigo = '';
        for ($i = 0; $i < $largo; $i++) {
            $codigo .= TORNEO_CODIGO_ALFABETO[random_int(0, strlen(TORNEO_CODIGO_ALFABETO) - 1)];
        }
    } while (torneos_obtener_por_codigo($codigo) !== null);
    return $codigo;
}

function torneos_obtener_por_codigo(string $codigo): ?array
{
    $pdo = db_conexion();
    $stmt = $pdo->prepare('SELECT * FROM torneos WHERE codigo = :codigo AND activo = true');
    $stmt->bindValue(':codigo', $codigo, PDO::PARAM_STR);
    $stmt->execute();
    $fila = $stmt->fetch();
    return $fila ? db_normalizar_torneo($fila) : null;
}

function torneos_regenerar_codigo(int $id, int $usuarioId): string
{
    if (torneos_obtener_por_id($id, $usuarioId) === null) {
        throw new RuntimeException('Copa o liga no encontrada.');
    }
    $nuevo = torneos_generar_codigo_unico();
    $pdo = db_conexion();
    $stmt = $pdo->prepare('UPDATE torneos SET codigo = :codigo WHERE id = :id');
    $stmt->bindValue(':codigo', $nuevo, PDO::PARAM_STR);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    return $nuevo;
}

/**
 * Crea o actualiza una copa (según traiga o no 'id'). Devuelve el id.
 * Si se marca es_predeterminado, se le quita esa marca a cualquier otra copa
 * (solo una puede responder en las URLs sin prefijo).
 *
 * $usuarioIdCreador se usa SOLO al crear (nunca al actualizar): 'usuario_id' y 'codigo'
 * no forman parte de COLUMNAS_TORNEO a propósito, así que editar una copa jamás los toca
 * — es imposible "robar" una copa ajena o cambiarle el código manipulando el formulario.
 */
function torneos_guardar(array $datos, ?int $usuarioIdCreador = null): int
{
    $pdo = db_conexion();

    // Con prepared statements emulados (necesario por el pooler de Neon, ver db_conexion()),
    // Postgres ya no acepta 0/1 como boolean de forma implícita como sí hacía con prepares
    // nativos: hay que mandar el texto 'true'/'false' para estas 3 columnas.
    $columnasBooleanas = ['permite_empates', 'es_predeterminado', 'activo', 'sancion_bloquea'];

    $valores = [];
    foreach (COLUMNAS_TORNEO as $c) {
        $v = $datos[$c] ?? null;
        if ($c === 'fases_playoff') {
            $v = '{' . implode(',', (array) $v) . '}';
        } elseif (in_array($c, $columnasBooleanas, true)) {
            $v = !empty($v) ? 'true' : 'false';
        }
        $valores[$c] = $v;
    }

    $pdo->beginTransaction();
    try {
        if (!empty($datos['es_predeterminado'])) {
            $pdo->exec('UPDATE torneos SET es_predeterminado = false');
        }

        if (!empty($datos['id'])) {
            $id = (int) $datos['id'];
            $sets = implode(', ', array_map(fn($c) => "{$c} = :{$c}", COLUMNAS_TORNEO));
            $stmt = $pdo->prepare("UPDATE torneos SET {$sets} WHERE id = :id");
            foreach ($valores as $c => $v) {
                db_bind($stmt, ":{$c}", $v);
            }
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $valores['usuario_id'] = $usuarioIdCreador;
            $valores['codigo'] = torneos_generar_codigo_unico();
            $cols = array_merge(COLUMNAS_TORNEO, ['usuario_id', 'codigo']);
            $marcadores = implode(', ', array_map(fn($c) => ":{$c}", $cols));
            $stmt = $pdo->prepare('INSERT INTO torneos (' . implode(', ', $cols) . ") VALUES ({$marcadores}) RETURNING id");
            foreach ($valores as $c => $v) {
                db_bind($stmt, ":{$c}", $v);
            }
            $stmt->execute();
            $id = (int) $stmt->fetchColumn();
        }

        $pdo->commit();
        return $id;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Elimina una copa y (por ON DELETE CASCADE) todos sus equipos, partidos,
 * patrocinadores y comentarios. No se permite borrar la copa predeterminada.
 * $usuarioId exige además que la copa pertenezca a ese usuario.
 */
function torneos_eliminar(int $id, ?int $usuarioId = null): void
{
    $torneo = torneos_obtener_por_id($id, $usuarioId);
    if ($torneo === null) {
        throw new RuntimeException('Copa o liga no encontrada.');
    }
    if ($torneo['es_predeterminado']) {
        throw new RuntimeException('No se puede eliminar la copa o liga predeterminada.');
    }
    $pdo = db_conexion();
    $stmt = $pdo->prepare('DELETE FROM torneos WHERE id = :id');
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
}
