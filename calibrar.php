<?php
/**
 * Prisma — Calibración de scoring v2.
 *
 * Grid search sobre α, β, γ, mapeo. Requiere etiquetas en etiquetas_calibracion.
 * Usage: php calibrar.php [--k 10]
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/curador.php';
require_once __DIR__ . '/lib/scoring.php';

$opts = getopt('', array('k:'));
$k = isset($opts['k']) ? (int)$opts['k'] : 10;

$db = prisma_db();

// Load labeled dataset
$rows = $db->query("
    SELECT r.*, e.etiqueta
    FROM radar r
    INNER JOIN etiquetas_calibracion e ON r.id = e.radar_id
    WHERE r.scoring_version = 'v2' AND r.relevancia IS NOT NULL
")->fetchAll();

if (count($rows) < 20) {
    echo "Insufficient labeled data: " . count($rows) . " (need >=20)\n";
    exit(1);
}

echo "Dataset: " . count($rows) . " labeled topics\n";
echo "k = $k\n\n";

// Grid
$alphas = array(0.3, 0.4, 0.5);
$betas  = array(0.5, 0.6, 0.7);
$gammas = array(0.10, 0.15, 0.20);
$mapeos = array('A', 'B');

$all_results = array();
$best = null;

foreach ($alphas as $alpha) {
    foreach ($betas as $beta) {
        foreach ($gammas as $gamma) {
            foreach ($mapeos as $mapeo) {
                $mapeo_table = ($mapeo === 'A') ? PRISMA_MAPEO_A : PRISMA_MAPEO_B;

                // Recompute H-score for each row
                $scores = array();
                foreach ($rows as $row) {
                    $fd = $row['framing_divergence'] !== null ? (int)$row['framing_divergence'] : null;
                    $rel = $row['relevancia'];

                    // Apply framing shortcut
                    if ($fd !== null && $fd >= 2 && $rel === 'baja') $rel = 'media';

                    // Gate
                    if (in_array($rel, array('baja', 'descartar', 'indeterminada'))) {
                        $h = 0.0;
                    } else {
                        $f_fd = ($fd !== null && isset($mapeo_table[$fd])) ? $mapeo_table[$fd] : 0.0;
                        $h_cob = $row['h_cobertura_mutua'] !== null ? (float)$row['h_cobertura_mutua'] : 0.0;
                        $h_sil = $row['h_silencio'] !== null ? (float)$row['h_silencio'] : 0.0;

                        if ($h_cob == 0.0 || $f_fd == 0.0) {
                            $h = 0.0;
                        } else {
                            $h = pow($h_cob, $alpha) * pow($f_fd, $beta);
                        }

                        if ($h_sil > 0 && $h > 0) {
                            $rel_peso = ($rel === 'alta') ? 1.0 : 0.5;
                            $h = min($h + $gamma * $h_sil * $rel_peso, 1.0);
                        }
                    }

                    $scores[] = array(
                        'h_score' => $h,
                        'etiqueta' => (int)$row['etiqueta'],
                    );
                }

                // Sort by h_score DESC
                usort($scores, function($a, $b) {
                    return $b['h_score'] <=> $a['h_score'];
                });

                // precision@k, recall@k
                $top_k = array_slice($scores, 0, $k);
                $true_positives_in_k = 0;
                foreach ($top_k as $s) {
                    if ($s['etiqueta'] === 1) $true_positives_in_k++;
                }

                $total_relevant = 0;
                foreach ($scores as $s) {
                    if ($s['etiqueta'] === 1) $total_relevant++;
                }

                $precision = ($k > 0) ? $true_positives_in_k / $k : 0;
                $recall = ($total_relevant > 0) ? $true_positives_in_k / $total_relevant : 0;

                $result = array(
                    'alpha' => $alpha, 'beta' => $beta, 'gamma' => $gamma, 'mapeo' => $mapeo,
                    'precision_at_k' => round($precision, 3),
                    'recall_at_k' => round($recall, 3),
                );
                $all_results[] = $result;

                // Best = maximize precision with recall >= 0.60
                if ($recall >= 0.60) {
                    if ($best === null || $precision > $best['precision_at_k']) {
                        $best = $result;
                    }
                }
            }
        }
    }
}

// Report
echo str_repeat('=', 70) . "\n";
if ($best) {
    echo "BEST: a={$best['alpha']} b={$best['beta']} g={$best['gamma']} mapeo={$best['mapeo']}\n";
    echo "  precision@$k = {$best['precision_at_k']}  recall@$k = {$best['recall_at_k']}\n";
} else {
    echo "No combination achieves recall@$k >= 0.60. Check labels or signals.\n";
}
echo str_repeat('=', 70) . "\n\n";

// Top 10 combinations
usort($all_results, function($a, $b) {
    if ($b['recall_at_k'] >= 0.60 && $a['recall_at_k'] < 0.60) return 1;
    if ($a['recall_at_k'] >= 0.60 && $b['recall_at_k'] < 0.60) return -1;
    return $b['precision_at_k'] <=> $a['precision_at_k'];
});

echo "Top 10 combinations:\n";
foreach (array_slice($all_results, 0, 10) as $r) {
    printf("  a=%.1f b=%.1f g=%.2f mapeo=%s -> P@%d=%.3f R@%d=%.3f\n",
        $r['alpha'], $r['beta'], $r['gamma'], $r['mapeo'],
        $k, $r['precision_at_k'], $k, $r['recall_at_k']);
}

// Persist
$stmt = $db->prepare('INSERT INTO calibraciones (fecha, dataset_size, resultados_json, params_elegidos, precision_at_k, recall_at_k, operador)
    VALUES (:f, :ds, :rj, :pe, :p, :r, :op)');
$stmt->execute(array(
    ':f' => date('Y-m-d'),
    ':ds' => count($rows),
    ':rj' => json_encode($all_results, JSON_UNESCAPED_UNICODE),
    ':pe' => $best ? json_encode($best) : '{}',
    ':p' => $best ? $best['precision_at_k'] : null,
    ':r' => $best ? $best['recall_at_k'] : null,
    ':op' => 'calibrar.php',
));

echo "\nCalibration saved to database.\n";
