<?php
/**
 * Prisma — Scoring v2: funciones puras de cálculo.
 *
 * Todas las funciones son deterministas y sin efectos secundarios.
 * Dependen de constantes PRISMA_GRUPO_* definidas en curador.php.
 */

/**
 * Framing divergence mapeos.
 */
define('PRISMA_MAPEO_A', array(0 => 0.00, 1 => 0.25, 2 => 0.65, 3 => 1.00));
define('PRISMA_MAPEO_B', array(0 => 0.00, 1 => 0.15, 2 => 0.50, 3 => 1.00));

/**
 * Counts articles per ideological bloc (izq, centro, der).
 *
 * @param array $articles Articles with 'cuadrante' key
 * @return array ['izq' => int, 'centro' => int, 'der' => int, 'bloques_activos' => int]
 */
function contar_bloques(array $articles): array {
    $izq = 0;
    $centro = 0;
    $der = 0;
    foreach ($articles as $art) {
        $c = $art['cuadrante'];
        if (in_array($c, PRISMA_GRUPO_IZQ)) $izq++;
        elseif (in_array($c, PRISMA_GRUPO_DER)) $der++;
        else $centro++;
    }
    $activos = ($izq > 0 ? 1 : 0) + ($centro > 0 ? 1 : 0) + ($der > 0 ? 1 : 0);
    return array(
        'izq' => $izq,
        'centro' => $centro,
        'der' => $der,
        'bloques_activos' => $activos,
    );
}

/**
 * Calculates mutual coverage between ideological blocs.
 * Replaces the old h_asimetria signal.
 *
 * @param array $articles Articles with 'cuadrante' key
 * @return float [0.0, 1.0]
 */
function calcular_cobertura_mutua(array $articles): float {
    $b = contar_bloques($articles);

    if ($b['bloques_activos'] <= 1) return 0.0;

    $factor = ($b['bloques_activos'] === 3) ? 1.0 : 0.7;
    $counts = array($b['izq'], $b['centro'], $b['der']);
    // Only consider active blocs for min/max
    $active_counts = array_filter($counts, function($n) { return $n > 0; });
    $min_val = min($active_counts);
    $max_val = max($active_counts);

    if ($max_val === 0) return 0.0;

    return round(($min_val / $max_val) * $factor, 4);
}

/**
 * Calculates editorial silence signal.
 *
 * @param array $articles Articles with 'cuadrante' key
 * @return float 0.0 (no silence), 0.5 (1 bloc silent), 1.0 (2 blocs silent)
 */
function calcular_silencio(array $articles): float {
    $b = contar_bloques($articles);
    $silent = 3 - $b['bloques_activos'];
    if ($silent <= 0) return 0.0;
    if ($silent === 1) return 0.5;
    return 1.0;
}

/**
 * Normalizes framing_divergence (0-3) to [0,1] using configured mapping.
 *
 * @param int|null $fd Framing divergence from Haiku (null if gate skipped)
 * @return float|null Normalized value, or null if fd is null
 */
function normalizar_framing($fd) {
    if ($fd === null) return null;

    $cfg = prisma_cfg();
    $mapeo_key = isset($cfg['scoring_mapeo']) ? $cfg['scoring_mapeo'] : 'B';
    $mapeo = ($mapeo_key === 'A') ? PRISMA_MAPEO_A : PRISMA_MAPEO_B;

    $fd = (int)$fd;
    if ($fd < 0) $fd = 0;
    if ($fd > 3) $fd = 3;

    return $mapeo[$fd];
}

/**
 * Computes the full H-score v2 for a cluster.
 *
 * Implements the complete flow from DISEÑO_POLARIZACION.md section 4:
 * PASO 1 (pre-filter) and PASO 3 (Haiku) are handled upstream.
 * This function handles PASOs 4-8.
 *
 * @param array $params Associative array with keys:
 *   'h_cob'          => float   — from calcular_cobertura_mutua()
 *   'h_sil'          => float   — from calcular_silencio()
 *   'fd'             => int|null — framing_divergence from Haiku
 *   'relevancia'     => string  — from Haiku or pre-filter
 *   'lista_positiva' => bool    — whether cluster matched positive list
 * @return array ['h_score'=>float, 'relevancia_final'=>string, 'anomalies'=>array]
 */
function calcular_h_score_v2(array $params): array {
    $cfg = prisma_cfg();
    $alpha = isset($cfg['scoring_alpha']) ? $cfg['scoring_alpha'] : 0.4;
    $beta  = isset($cfg['scoring_beta'])  ? $cfg['scoring_beta']  : 0.6;
    $gamma = isset($cfg['scoring_gamma']) ? $cfg['scoring_gamma'] : 0.15;

    $h_cob = $params['h_cob'];
    $h_sil = $params['h_sil'];
    $fd = $params['fd'];
    $relevancia = $params['relevancia'];
    $lista_pos = !empty($params['lista_positiva']);

    $anomalies = array();
    $h_score = 0.0;

    // PASO 4 — Framing shortcut
    if ($fd !== null && $fd >= 2 && $relevancia === 'baja') {
        $relevancia = 'media';
        $anomalies[] = array(
            'tipo' => 'ANOMALY_FRAMING_OVERRIDE',
            'detalle' => "fd=$fd overrides relevancia baja->media",
        );
    }

    // PASO 5 — Relevance gate
    $gated = false;
    if (in_array($relevancia, array('baja', 'descartar'))) {
        $h_score = 0.0;
        $gated = true;
    } elseif ($relevancia === 'indeterminada') {
        $h_score = 0.0;
        $gated = true;
        $anomalies[] = array(
            'tipo' => 'INFO_INDETERMINADA',
            'detalle' => 'Cluster pending classification (budget/gate skip)',
        );
    }

    if (!$gated) {
        // PASO 6 — Base score (multiplicative)
        $f_fd = normalizar_framing($fd);
        if ($f_fd === null || $f_fd == 0.0 || $h_cob == 0.0) {
            $h_score = 0.0;
        } else {
            $h_score = pow($h_cob, $alpha) * pow($f_fd, $beta);
        }

        // PASO 7 — Silence bonus
        if ($h_sil > 0 && $h_score > 0) {
            $rel_peso = ($relevancia === 'alta') ? 1.0 : 0.5;
            $h_score = min($h_score + $gamma * $h_sil * $rel_peso, 1.0);
        }
    }

    // PASO 8 — Anomaly checks (ALWAYS runs)
    if ($lista_pos && in_array($relevancia, array('baja', 'descartar'))) {
        $anomalies[] = array(
            'tipo' => 'ANOMALY_POLITICAL_LOW',
            'detalle' => "Political actor cluster classified as $relevancia",
        );
    }

    return array(
        'h_score' => round($h_score, 4),
        'relevancia_final' => $relevancia,
        'anomalies' => $anomalies,
    );
}
