#!/usr/bin/env php
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
    echo "Uso: php pipeline.php [--ambito españa|europa|global] [--temas N] [--dry-run]\n";
    exit(0);
}

$max_temas = (int)($opts['temas'] ?? PRISMA_CONFIG['articulos_dia']);
$ambito = $opts['ambito'] ?? 'españa';
$dry_run = isset($opts['dry-run']);

// ── Log dir ──────────────────────────────────────────────────────────

$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) mkdir($log_dir, 0755, true);

// ── Pipeline ─────────────────────────────────────────────────────────

prisma_log("MAIN", "═══════════════════════════════════════════════");
prisma_log("MAIN", "Prisma — Pipeline automático");
prisma_log("MAIN", "Ámbito: $ambito | Temas: $max_temas | Dry-run: " . ($dry_run ? 'sí' : 'no'));
prisma_log("MAIN", "═══════════════════════════════════════════════");

// 1. Leer RSS
prisma_log("MAIN", "Paso 1: Leyendo RSS ($ambito)...");
$articles = rss_fetch_all($ambito);

if (empty($articles)) {
    prisma_log("MAIN", "No se obtuvieron artículos de ningún RSS. Abortando.");
    exit(1);
}

// 2. Seleccionar temas
prisma_log("MAIN", "Paso 2: Seleccionando temas...");
$temas = curador_seleccionar($articles, $max_temas);

if (empty($temas)) {
    prisma_log("MAIN", "No hay temas con suficiente diversidad de cuadrantes. Abortando.");
    exit(1);
}

prisma_log("MAIN", count($temas) . " temas seleccionados.");

// 3. Procesar cada tema
$publicados = 0;
$rechazados = 0;

foreach ($temas as $i => $tema) {
    $seq = $i + 1;
    $article_id = prisma_gen_id($seq);

    prisma_log("MAIN", "");
    prisma_log("MAIN", "───────────────────────────────────────────────");
    prisma_log("MAIN", "Tema $seq/$max_temas: " . mb_substr($tema['titulo_tema'], 0, 80));
    prisma_log("MAIN", "  Artículos: {$tema['n_articulos']} | Cuadrantes: {$tema['n_cuadrantes']}");
    prisma_log("MAIN", "───────────────────────────────────────────────");

    $contexto = curador_preparar_contexto($tema);

    if ($dry_run) {
        prisma_log("MAIN", "[DRY-RUN] Saltando procesamiento.");
        continue;
    }

    try {
        $result = prisma_procesar_tema($contexto, $article_id, $ambito);
        if ($result) {
            $publicados++;
        } else {
            $rechazados++;
        }
    } catch (Throwable $e) {
        prisma_log("MAIN", "ERROR: " . $e->getMessage());
        $rechazados++;
    }
}

// 4. Resumen
prisma_log("MAIN", "");
prisma_log("MAIN", "═══════════════════════════════════════════════");
prisma_log("MAIN", "RESUMEN: $publicados publicados, $rechazados rechazados de " . count($temas) . " temas");
prisma_log("MAIN", "═══════════════════════════════════════════════");

exit($publicados > 0 ? 0 : 1);
