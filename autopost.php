<?php
/**
 * PolarPrisma — Autopublicación (CLI). Postea la tarjeta del análisis más
 * polarizado del día al canal. DESACTIVADO salvo AUTOPOST_ENABLED=1 en el .env.
 *
 *   php autopost.php               # publica (si está activado)
 *   php autopost.php --dry-run     # previsualiza sin publicar (siempre funciona)
 *   php autopost.php --dry-run --id 2026-07-30-002   # previsualiza un artículo concreto
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/autopost.php';

$dry = in_array('--dry-run', $argv, true);
$force_id = null;
foreach ($argv as $i => $a) {
    if ($a === '--id' && isset($argv[$i + 1])) $force_id = $argv[$i + 1];
}

$cfg = prisma_cfg();
if (!$dry && empty($cfg['autopost_enabled'])) {
    prisma_log("AUTOPOST", "Desactivado (AUTOPOST_ENABLED != 1).");
    echo "Autopost DESACTIVADO. Pon AUTOPOST_ENABLED=1 en el .env del servidor para activarlo.\n";
    echo "Usa --dry-run para previsualizar sin publicar.\n";
    exit(0);
}

autopost_ejecutar($dry, $force_id);
