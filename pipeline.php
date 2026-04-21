<?php
/**
 * Prisma — Pipeline automático diario (entry point para cron).
 *
 * Lee RSS de todas las fuentes, selecciona los 5 temas más relevantes
 * con cobertura en múltiples cuadrantes, y ejecuta el pipeline completo por cada uno.
 *
 * Uso:
 *   php pipeline.php
 *   php pipeline.php --temas 3        # Solo 3 temas en lugar de 5
 *   php pipeline.php --dry-run        # No publica, solo log
 *
 * Cron (17:00 hora España):
 *   0 17 * * * cd /ruta/a/prisma && php pipeline.php >> logs/pipeline.log 2>&1
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/anthropic.php';
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/rss.php';
require_once __DIR__ . '/lib/curador.php';
require_once __DIR__ . '/lib/sintetizador.php';
require_once __DIR__ . '/lib/auditor.php';

// ── Args ─────────────────────────────────────────────────────────────

$opts = getopt('', ['temas:', 'ambito:', 'dry-run', 'help']);

if (isset($opts['help'])) {
    echo "Uso: php pipeline.php [--ambito españa|europa|global|todos] [--temas N] [--dry-run]\n";
    exit(0);
}

$max_temas = (int)($opts['temas'] ?? prisma_cfg()['articulos_dia']);
$ambito_opt = isset($opts['ambito']) ? $opts['ambito'] : 'todos';
$dry_run = isset($opts['dry-run']);

// If 'todos', run for each ambito sequentially
$ambitos_to_run = ($ambito_opt === 'todos')
    ? array('españa', 'europa', 'global')
    : array($ambito_opt);

// ── Log dir ──────────────────────────────────────────────────────────

$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);

// ── Pipeline ─────────────────────────────────────────────────────────

prisma_log("MAIN", "═══════════════════════════════════════════════");
prisma_log("MAIN", "Prisma — Pipeline automático");
prisma_log("MAIN", "Ámbitos: " . implode(', ', $ambitos_to_run) . " | Temas/ámbito: $max_temas | Dry-run: " . ($dry_run ? 'sí' : 'no'));
prisma_log("MAIN", "═══════════════════════════════════════════════");

// 0. Cleanup old radar entries
radar_limpiar();

$cfg = prisma_cfg();
$tz = new DateTimeZone($cfg['timezone']);
$fecha = (new DateTime('now', $tz))->format('Y-m-d');
$umbral = $cfg['umbral_tension'];
$min_cuad = $cfg['min_cuadrantes'];

$total_publicados = 0;
$total_rechazados = 0;
$total_radar = 0;
$total_procesados = 0;

foreach ($ambitos_to_run as $ambito) {

prisma_log("MAIN", "");
prisma_log("MAIN", "━━━ Ámbito: $ambito ━━━━━━━━━━━━━━━━━━━━━━━━━━");

// 1. Read RSS
prisma_log("MAIN", "Paso 1: Leyendo RSS ($ambito)...");
$articles = rss_fetch_all($ambito);

if (empty($articles)) {
    prisma_log("MAIN", "No se obtuvieron artículos para $ambito. Saltando.");
    continue;
}

// 2. Score ALL topics with tension formula
prisma_log("MAIN", "Paso 2: Calculando tensión informativa...");
$all_temas = curador_seleccionar($articles);

if (empty($all_temas)) {
    prisma_log("MAIN", "No hay temas con suficientes artículos para $ambito. Saltando.");
    continue;
}

prisma_log("MAIN", count($all_temas) . " temas detectados.");

// 3. Insert ALL topics into radar
$all_temas = radar_insertar_todos($all_temas, $ambito, $fecha);
$total_radar += count($all_temas);

// 4. Filter by tension threshold + min cuadrantes
$candidatos = array_filter($all_temas, fn($t) => $t['h_score'] >= $umbral && $t['n_cuadrantes'] >= $min_cuad);
$candidatos = array_values($candidatos);

prisma_log("MAIN", count($candidatos) . " temas superan el umbral de tensión (" . ($umbral * 100) . "%) y mínimo de cuadrantes ($min_cuad).");

if (empty($candidatos)) {
    prisma_log("MAIN", "Ningún tema supera el umbral para $ambito. Radar publicado sin análisis.");
    continue;
}

// 5. Haiku triage (confirms + generates phrases)
prisma_log("MAIN", "Paso 3: Triage Haiku...");
$confirmados = $dry_run ? $candidatos : triage_haiku($candidatos);

// 6. Take top N for Sonnet pipeline
$to_process = array_slice($confirmados, 0, $max_temas);
$total_procesados += count($to_process);

prisma_log("MAIN", count($to_process) . " temas seleccionados para análisis.");

// 7. Process each topic through Sonnet pipeline
foreach ($to_process as $i => $tema) {
    $seq = $i + 1;
    $article_id = prisma_gen_id($seq);

    prisma_log("MAIN", "");
    prisma_log("MAIN", "───────────────────────────────────────────────");
    prisma_log("MAIN", sprintf("Tema %d/%d: %s (H=%.0f%%)",
        $seq, count($to_process),
        mb_substr($tema['titulo_tema'], 0, 60),
        $tema['h_score'] * 100
    ));
    prisma_log("MAIN", "───────────────────────────────────────────────");

    $contexto = curador_preparar_contexto($tema);

    if ($dry_run) {
        prisma_log("MAIN", "[DRY-RUN] Saltando procesamiento.");
        continue;
    }

    try {
        $result = prisma_procesar_tema($contexto, $article_id, $ambito);
        if ($result) {
            $total_publicados++;
            radar_link_articulo($tema['radar_id'], $article_id);
        } else {
            $total_rechazados++;
        }
    } catch (Throwable $e) {
        prisma_log("MAIN", "ERROR: " . $e->getMessage());
        $total_rechazados++;
    }
}

} // end foreach ambito

// 8. Summary
prisma_log("MAIN", "");
prisma_log("MAIN", "═══════════════════════════════════════════════");
prisma_log("MAIN", sprintf("RESUMEN: %d publicados, %d rechazados de %d temas | %d en radar total",
    $total_publicados, $total_rechazados, $total_procesados, $total_radar));
prisma_log("MAIN", "═══════════════════════════════════════════════");

exit($total_publicados > 0 ? 0 : 1);
