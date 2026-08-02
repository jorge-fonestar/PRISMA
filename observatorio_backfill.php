<?php
/**
 * PolarPrisma — Backfill del Observatorio: asigna tema a los clusters del radar
 * ya existentes, procesando día a día en orden cronológico (para que la lista de
 * temas se construya estable), y consolida los casi-duplicados al final.
 *
 * Uso (CLI):
 *   php observatorio_backfill.php                 # últimos 45 días + consolidar
 *   php observatorio_backfill.php --dias 30
 *   php observatorio_backfill.php --no-consolidar
 *   php observatorio_backfill.php --solo-consolidar
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only'); }

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/observatorio.php';

$dias = 45;
foreach ($argv as $i => $a) if ($a === '--dias' && isset($argv[$i + 1])) $dias = (int)$argv[$i + 1];
$consolidar = !in_array('--no-consolidar', $argv, true);
$solo_consolidar = in_array('--solo-consolidar', $argv, true);

$db = prisma_db();

if (!$solo_consolidar) {
    $fmin = date('Y-m-d', strtotime("-{$dias} days"));
    $st = $db->prepare("SELECT DISTINCT fecha FROM radar
        WHERE fecha >= :fmin AND tema_id IS NULL AND relevancia IN ('alta','media')
        ORDER BY fecha ASC");
    $st->execute(array(':fmin' => $fmin));
    $dias_lista = $st->fetchAll(PDO::FETCH_COLUMN);

    $total = 0;
    foreach ($dias_lista as $f) {
        $n = observatorio_asignar_dia($f);
        $total += $n;
        echo "$f → $n asignados\n";
    }
    echo "== $total clusters asignados en " . count($dias_lista) . " días ==\n";
}

if ($consolidar || $solo_consolidar) {
    $m = observatorio_consolidar();
    echo "Consolidación: $m fusiones\n";
}

echo "Temas activos: " . count(observatorio_temas(true)) . "\n";
