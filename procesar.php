#!/usr/bin/env php
<?php
/**
 * Prisma — Procesador manual de un tema individual.
 *
 * Uso CLI:
 *   php procesar.php "Descripción del tema o noticia"
 *   php procesar.php --ambito europa "Debate sobre regulación de IA"
 *
 * Uso desde web (env vars):
 *   PRISMA_TEMA="..." PRISMA_AMBITO="españa" php procesar.php
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/anthropic.php';
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/sintetizador.php';
require_once __DIR__ . '/lib/auditor.php';

// ── Args: job file (from panel) > env vars > CLI ─────────────────────

$tema = '';
$ambito = '';

// 1. Check for job file (written by panel.php)
$job_path = __DIR__ . '/data/manual_job.json';
if (file_exists($job_path)) {
    $job = json_decode(file_get_contents($job_path), true);
    if ($job) {
        $tema = $job['tema'] ?? '';
        $ambito = $job['ambito'] ?? '';
    }
}

// 2. Env vars
if (!$tema) $tema = getenv('PRISMA_TEMA') ?: '';
if (!$ambito) $ambito = getenv('PRISMA_AMBITO') ?: '';

// 3. CLI args
if (!$tema && isset($argv)) {
    // CLI mode: parse args
    $opts = getopt('', ['ambito:', 'dry-run', 'help']);
    $args = [];
    $skip_next = false;
    for ($i = 1; $i < ($argc ?? 0); $i++) {
        if ($skip_next) { $skip_next = false; continue; }
        if ($argv[$i] === '--ambito') { $skip_next = true; continue; }
        if ($argv[$i] === '--dry-run' || $argv[$i] === '--help') continue;
        if (strpos($argv[$i], '--') === 0) continue;
        $args[] = $argv[$i];
    }

    if (isset($opts['help']) || empty($args)) {
        echo "Uso: php procesar.php [--ambito españa|europa|global] \"Tema\"\n";
        exit(0);
    }

    $tema = implode(' ', $args);
    if (!$ambito) $ambito = $opts['ambito'] ?? 'españa';
}

if (!$ambito) $ambito = 'españa';

if (!$tema) {
    fprintf(STDERR, "Error: no se proporcionó tema.\n");
    exit(1);
}

// ── Pipeline ─────────────────────────────────────────────────────────

prisma_log("MAIN", "Prisma — Procesador manual");
prisma_log("MAIN", "Tema: $tema");
prisma_log("MAIN", "Ámbito: $ambito");

$article_id = prisma_gen_id((int)date('His'));

try {
    $result = prisma_procesar_tema($tema, $article_id, $ambito, manual: true);

    if ($result) {
        prisma_log("MAIN", "═══ PUBLICADO ═══");
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    } else {
        prisma_log("MAIN", "═══ DESCARTADO ═══");
        exit(1);
    }
} catch (Throwable $e) {
    prisma_log("MAIN", "ERROR FATAL: " . $e->getMessage());
    exit(2);
}
