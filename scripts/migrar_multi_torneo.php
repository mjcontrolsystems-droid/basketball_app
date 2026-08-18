<?php
declare(strict_types=1);

require_once __DIR__ . "/../config/bootstrap.php";

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse desde la línea de comandos (CLI).');
}

echo "== Migración a plataforma multi-torneo ==\n\n";

$pdo = db_conexion();

// Si ya existe una copa marcada como predeterminada, no hace falta repetir la migración.
$yaMigrado = $pdo->query("SELECT id FROM torneos WHERE es_predeterminado = true LIMIT 1")->fetchColumn();
if ($yaMigrado) {
    echo "Ya existe una copa predeterminada (id={$yaMigrado}). No se repite la migración.\n";
    exit;
}

$torneoViejo = $pdo->query("SELECT * FROM torneo WHERE id = 1")->fetch();
if (!$torneoViejo) {
    echo "No se encontró la tabla/fila 'torneo' antigua. Nada que migrar (¿instalación nueva?).\n";
    exit;
}

$totalEquipos = (int) $pdo->query('SELECT count(*) FROM equipos WHERE torneo_id IS NULL')->fetchColumn();

echo "Creando la copa 'Copa Estrellas' como torneo predeterminado...\n";
$stmt = $pdo->prepare(
    'INSERT INTO torneos
        (slug, nombre, subtitulo, temporada, descripcion, sede_principal, logo,
         color_primario, color_secundario, color_acento, fecha_inicio, fecha_fin,
         formato, instagram, hero_frase, deporte, num_equipos, fases_playoff,
         permite_empates, puntos_victoria, puntos_empate, puntos_derrota,
         es_predeterminado, activo)
     VALUES
        (:slug, :nombre, :subtitulo, :temporada, :descripcion, :sede_principal, :logo,
         :color_primario, :color_secundario, :color_acento, :fecha_inicio, :fecha_fin,
         :formato, :instagram, :hero_frase, :deporte, :num_equipos, :fases_playoff,
         :permite_empates, :puntos_victoria, :puntos_empate, :puntos_derrota,
         true, true)
     RETURNING id'
);
$stmt->execute([
    ':slug' => 'copa-estrellas',
    ':nombre' => $torneoViejo['nombre'],
    ':subtitulo' => $torneoViejo['subtitulo'],
    ':temporada' => $torneoViejo['temporada'],
    ':descripcion' => $torneoViejo['descripcion'],
    ':sede_principal' => $torneoViejo['sede_principal'],
    ':logo' => $torneoViejo['logo'],
    ':color_primario' => $torneoViejo['color_primario'],
    ':color_secundario' => $torneoViejo['color_secundario'],
    ':color_acento' => $torneoViejo['color_acento'],
    ':fecha_inicio' => $torneoViejo['fecha_inicio'],
    ':fecha_fin' => $torneoViejo['fecha_fin'],
    ':formato' => $torneoViejo['formato'],
    ':instagram' => $torneoViejo['instagram'],
    ':hero_frase' => $torneoViejo['hero_frase'],
    ':deporte' => 'basketball',
    ':num_equipos' => max($totalEquipos, 8),
    ':fases_playoff' => '{cuartos,semifinal,final}',
    ':permite_empates' => 0,
    ':puntos_victoria' => 2,
    ':puntos_empate' => 0,
    ':puntos_derrota' => 1,
]);
$torneoId = (int) $stmt->fetchColumn();
echo "  Copa Estrellas creada con id={$torneoId}\n";

echo "\nAsignando torneo_id a los datos existentes...\n";
foreach (['equipos', 'partidos', 'patrocinadores', 'comentarios'] as $tabla) {
    $n = $pdo->exec("UPDATE {$tabla} SET torneo_id = {$torneoId} WHERE torneo_id IS NULL");
    echo "  {$tabla}: {$n} filas asignadas a Copa Estrellas\n";
}

echo "\nHaciendo torneo_id obligatorio ahora que ya no hay nulos...\n";
foreach (['equipos', 'partidos', 'patrocinadores', 'comentarios'] as $tabla) {
    $pdo->exec("ALTER TABLE {$tabla} ALTER COLUMN torneo_id SET NOT NULL");
    echo "  {$tabla}.torneo_id ahora es NOT NULL\n";
}

echo "\n== Migración completa ==\n";
echo "La tabla 'torneo' antigua se deja intacta por ahora (no se borra en este script).\n";
