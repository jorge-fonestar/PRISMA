#!/usr/bin/env php
<?php
/**
 * Prisma — Procesador manual de un tema individual.
 *
 * Uso:
 *   php procesar.php "Descripción del tema o noticia"
 *   php procesar.php --ambito europa "Debate sobre regulación de IA en la UE"
 *   php procesar.php --dry-run "Tema de prueba"
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/anthropic.php';
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/sintetizador.php';
require_once __DIR__ . '/lib/auditor.php';

// ── Args ─────────────────────────────────────────────────────────────

$opts = getopt('', ['ambito:', 'dry-run', 'help']);
$args = [];

// Extraer args posicionales (el tema)
$skip_next = false;
for ($i = 1; $i < $argc; $i++) {
    if ($skip_next) { $skip_next = false; continue; }
    if ($argv[$i] === '--help') { /* handled below */ }
    elseif ($argv[$i] === '--ambito') { $skip_next = true; continue; }
    elseif ($argv[$i] === '--dry-run') { continue; }
    elseif (strpos($argv[$i], '--') === 0) { continue; }
    else { $args[] = $argv[$i]; }
}

if (isset($opts['help']) || empty($args)) {
    echo <<<HELP
Prisma — Procesador manual

Uso:
  php procesar.php "Descripción del tema"
  php procesar.php --ambito europa "Tema europeo"
  php procesar.php --dry-run "Tema de prueba"

Opciones:
  --ambito    españa|europa|global (default: españa)
  --dry-run   Genera y audita pero no publica
  --help      Muestra esta ayuda

HELP;
    exit(0);
}

$tema = implode(' ', $args);
$ambito = $opts['ambito'] ?? 'españa';
$dry_run = isset($opts['dry-run']);

// ── Pipeline ─────────────────────────────────────────────────────────

prisma_log("MAIN", "Prisma — Procesador manual");
prisma_log("MAIN", "Tema: $tema");
prisma_log("MAIN", "Ámbito: $ambito");

$article_id = prisma_gen_id((int)date('His'));

try {
    if ($dry_run) {
        // En dry-run, sintetizar + auditar pero guardar en output/ en vez de DB
        prisma_log("MAIN", "[DRY-RUN] No se publicará.");

        $artifact = sintetizar_manual($tema, $article_id, $ambito);
        $audit = auditar($artifact);

        $artifact['auditoria_moralcore'] = [
            'veredicto'       => $audit['veredicto'] ?? 'RECHAZO',
            'puntuacion'      => $audit['puntuacion'] ?? 0,
            'axiomas_detalle' => $audit['axiomas_detalle'] ?? [],
            'version_estandar'=> $audit['version_estandar'] ?? 'MC-1.0',
        ];

        $out_dir = __DIR__ . '/output';
        if (!is_dir($out_dir)) mkdir($out_dir, 0755, true);
        $out_path = "$out_dir/{$artifact['id']}.json";
        file_put_contents($out_path, json_encode($artifact, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        prisma_log("MAIN", "Guardado en $out_path");
        echo json_encode($artifact, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    } else {
        $result = prisma_procesar_tema($tema, $article_id, $ambito, manual: true);

        if ($result) {
            prisma_log("MAIN", "═══ PUBLICADO ═══");
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
        } else {
            prisma_log("MAIN", "═══ DESCARTADO ═══");
            exit(1);
        }
    }
} catch (Throwable $e) {
    prisma_log("MAIN", "ERROR FATAL: " . $e->getMessage());
    exit(2);
}
