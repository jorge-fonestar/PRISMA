<?php
/**
 * PolarPrisma — Backfill de tarjetas sociales (OG) para artículos ya publicados.
 *
 * Uso (CLI, dentro del contenedor):
 *   php og_backfill.php            # genera las que falten + la de marca (default)
 *   php og_backfill.php --force    # regenera todas
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/og.php';

$force = in_array('--force', $argv, true);

echo "Tarjeta de marca (default): og " . (og_generar_default('og') ? "OK" : "FALLO")
   . " · sq " . (og_generar_default('sq') ? "OK" : "FALLO") . "\n";

$db = prisma_db();
$ids = $db->query("SELECT id FROM articulos ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN);

$n = 0; $ok = 0;
foreach ($ids as $id) {
    if (!$force && is_file(og_ruta($id)) && is_file(og_ruta($id . '-sq'))) continue;
    $n++;
    $r = og_generar_articulo($id, 'og') && og_generar_articulo($id, 'sq');
    if ($r) $ok++;
    echo ($r ? "✓" : "✗") . " $id (og+sq)\n";
}
echo "== $ok/$n artículos con ambas tarjetas" . ($force ? " (--force)" : " (solo los que faltaban)") . " ==\n";
