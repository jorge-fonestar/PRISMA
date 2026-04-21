<?php
/**
 * Prisma — Fase 1: Escaneo de fuentes.
 *
 * Lee RSS de todos los ámbitos, agrupa temas por similitud,
 * calcula el índice de polarización informativa e inserta en el radar.
 * No gasta tokens de IA. Se puede ejecutar tantas veces como se quiera.
 *
 * Uso:
 *   php escanear.php                       # Todos los ámbitos
 *   php escanear.php --ambito españa       # Solo un ámbito
 *
 * Cron (cada 4h): 0 0,4,8,12,16,20 * * * cd /ruta/a/prisma && php escanear.php >> logs/escaneo.log 2>&1
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/rss.php';
require_once __DIR__ . '/lib/curador.php';

// ── Args ─────────────────────────────────────────────────────────────

$opts = getopt('', array('ambito:', 'help'));

if (isset($opts['help'])) {
    echo "Uso: php escanear.php [--ambito españa|europa|global|todos]\n";
    echo "Lee RSS, calcula polarización e inserta en radar. Sin coste de IA.\n";
    exit(0);
}

$ambito_opt = isset($opts['ambito']) ? $opts['ambito'] : 'todos';
$ambitos_to_run = ($ambito_opt === 'todos')
    ? array('españa', 'europa', 'global')
    : array($ambito_opt);

// ── Log dir ──────────────────────────────────────────────────────────

$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);

// ── Escaneo ──────────────────────────────────────────────────────────

prisma_log("SCAN", "═══════════════════════════════════════════════");
prisma_log("SCAN", "Prisma — Escaneo de fuentes (Fase 1)");
prisma_log("SCAN", "Ámbitos: " . implode(', ', $ambitos_to_run));
prisma_log("SCAN", "═══════════════════════════════════════════════");

// Cleanup old radar entries (>90 days)
radar_limpiar();

$cfg = prisma_cfg();
$tz = new DateTimeZone($cfg['timezone']);
$fecha = (new DateTime('now', $tz))->format('Y-m-d');

$total_radar = 0;
$total_candidatos = 0;

foreach ($ambitos_to_run as $ambito) {

    prisma_log("SCAN", "");
    prisma_log("SCAN", "━━━ Ámbito: $ambito ━━━━━━━━━━━━━━━━━━━━━━━━━━");

    // 1. Read RSS
    prisma_log("SCAN", "Leyendo RSS ($ambito)...");
    $articles = rss_fetch_all($ambito);

    if (empty($articles)) {
        prisma_log("SCAN", "No se obtuvieron artículos para $ambito. Saltando.");
        continue;
    }

    prisma_log("SCAN", count($articles) . " artículos leídos.");

    // 2. Group & score topics
    prisma_log("SCAN", "Calculando polarización informativa...");
    $all_temas = curador_seleccionar($articles);

    if (empty($all_temas)) {
        prisma_log("SCAN", "No hay temas con suficientes artículos para $ambito. Saltando.");
        continue;
    }

    prisma_log("SCAN", count($all_temas) . " temas detectados.");

    // 3. Insert into radar (dedup included)
    $all_temas = radar_insertar_todos($all_temas, $ambito, $fecha);
    $total_radar += count($all_temas);

    // 4. Report candidates above threshold
    $umbral = $cfg['umbral_tension'];
    $min_cuad = $cfg['min_cuadrantes'];
    $candidatos = array_filter($all_temas, function($t) use ($umbral, $min_cuad) {
        return $t['h_score'] >= $umbral && $t['n_cuadrantes'] >= $min_cuad;
    });
    $total_candidatos += count($candidatos);

    prisma_log("SCAN", count($candidatos) . " temas superan umbral (" . ($umbral * 100) . "%) con >=$min_cuad cuadrantes.");

    // Log top 5
    $top = array_slice($candidatos, 0, 5);
    foreach ($top as $i => $t) {
        prisma_log("SCAN", sprintf("  #%d H=%.0f%% | %s",
            $i + 1, $t['h_score'] * 100, mb_substr($t['titulo_tema'], 0, 60, 'UTF-8')));
    }
}

// Summary
prisma_log("SCAN", "");
prisma_log("SCAN", "═══════════════════════════════════════════════");
prisma_log("SCAN", sprintf("ESCANEO COMPLETO: %d temas en radar, %d candidatos a análisis",
    $total_radar, $total_candidatos));
prisma_log("SCAN", "═══════════════════════════════════════════════");

exit(0);
