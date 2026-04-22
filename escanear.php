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
require_once __DIR__ . '/lib/scoring.php';
require_once __DIR__ . '/lib/diccionarios.php';
require_once __DIR__ . '/lib/gate_haiku.php';

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

    // 3. Scoring v2 pipeline
    prisma_log("SCAN", "Scoring v2: señales estructurales + pre-filtro...");

    $clusters_para_haiku = array();
    $cluster_id_counter = 0;

    foreach ($all_temas as &$tema) {
        $cluster_id_counter++;
        $tema['cluster_id'] = $cluster_id_counter;

        // Structural signals
        $tema['h_cobertura_mutua'] = calcular_cobertura_mutua($tema['articulos']);
        $tema['h_silencio_v2'] = calcular_silencio($tema['articulos']);
        $bloques = contar_bloques($tema['articulos']);
        $tema['bloques_activos'] = $bloques['bloques_activos'];

        // Pre-filter: negative list
        $neg = aplicar_lista_negativa($tema['titulo_tema']);
        if ($neg['descartado']) {
            $tema['h_score'] = 0.0;
            $tema['h_framing'] = 0.0;
            $tema['h_silencio'] = 0.0;
            $tema['framing_divergence'] = null;
            $tema['framing_evidence'] = null;
            $tema['relevancia'] = 'descartar';
            $tema['dominio_tematico'] = null;
            $tema['scoring_version'] = 'v2';
            prisma_log("SCAN", sprintf("  DESCARTAR: \"%s\" (keyword: %s)",
                mb_substr($tema['titulo_tema'], 0, 50, 'UTF-8'), $neg['keyword']));
            continue;
        }

        // Positive list hint
        $tema['contains_political_actor'] = detectar_lista_positiva($tema['titulo_tema']);

        // Cache check
        if ($cfg['gate_haiku_cache']) {
            $cuadrantes_sorted = $tema['cuadrantes'];
            sort($cuadrantes_sorted);
            $cached = gate_haiku_cache_check($tema['titulo_tema'], implode(',', $cuadrantes_sorted), $fecha);
            if ($cached !== null) {
                prisma_log("SCAN", sprintf("  CACHE HIT: \"%s\"", mb_substr($tema['titulo_tema'], 0, 50, 'UTF-8')));
                $tema['relevancia'] = $cached['relevancia'];
                $tema['dominio_tematico'] = $cached['dominio'];
                $tema['framing_divergence'] = $cached['framing_divergence'];
                $tema['framing_evidence'] = $cached['framing_evidence'];
                // Compute score from cached values
                $tema['h_framing'] = normalizar_framing($cached['framing_divergence']);
                $sv2 = calcular_h_score_v2(array(
                    'h_cob' => $tema['h_cobertura_mutua'],
                    'h_sil' => $tema['h_silencio_v2'],
                    'fd' => $cached['framing_divergence'],
                    'relevancia' => $cached['relevancia'],
                    'lista_positiva' => $tema['contains_political_actor'],
                ));
                $tema['h_score'] = $sv2['h_score'];
                $tema['h_silencio'] = $tema['h_silencio_v2'];
                $tema['relevancia'] = $sv2['relevancia_final'];
                $tema['scoring_version'] = 'v2';
                continue;
            }
        }

        // Queue for Haiku
        $clusters_para_haiku[] = $tema;
    }
    unset($tema);

    // Haiku batch call
    if (!empty($clusters_para_haiku) && $cfg['gate_haiku_enabled']) {
        prisma_log("SCAN", "Gate Haiku: clasificando " . count($clusters_para_haiku) . " clusters...");
        $haiku_results = gate_haiku_clasificar($clusters_para_haiku);

        foreach ($all_temas as &$tema) {
            if (isset($tema['relevancia'])) continue; // already processed (descartar or cache)

            $cid = $tema['cluster_id'];
            $hr = isset($haiku_results[$cid]) ? $haiku_results[$cid] : null;

            if ($hr === null) {
                $tema['relevancia'] = 'indeterminada';
                $tema['dominio_tematico'] = null;
                $tema['framing_divergence'] = null;
                $tema['framing_evidence'] = null;
                $tema['h_framing'] = null;
            } else {
                $tema['relevancia'] = $hr['relevancia'];
                $tema['dominio_tematico'] = $hr['dominio'];
                $tema['framing_divergence'] = $hr['framing_divergence'];
                $tema['framing_evidence'] = $hr['framing_evidence'];
                $tema['h_framing'] = normalizar_framing($hr['framing_divergence']);

                // Log anomalies from Haiku
                foreach ($hr['anomalies'] as $anom) {
                    scoring_log_anomaly($fecha, null, $anom['tipo'], $anom['detalle']);
                }
            }

            // Compute final H-score
            $sv2 = calcular_h_score_v2(array(
                'h_cob' => $tema['h_cobertura_mutua'],
                'h_sil' => $tema['h_silencio_v2'],
                'fd' => $tema['framing_divergence'],
                'relevancia' => $tema['relevancia'],
                'lista_positiva' => !empty($tema['contains_political_actor']),
            ));

            $tema['h_score'] = $sv2['h_score'];
            $tema['h_silencio'] = $tema['h_silencio_v2'];
            $tema['relevancia'] = $sv2['relevancia_final'];
            $tema['scoring_version'] = 'v2';

            // Log anomalies from score computation
            foreach ($sv2['anomalies'] as $anom) {
                scoring_log_anomaly($fecha, null, $anom['tipo'], $anom['detalle']);
            }
        }
        unset($tema);
    } elseif (!$cfg['gate_haiku_enabled']) {
        // Gate disabled: mark all non-discarded as indeterminada
        foreach ($all_temas as &$tema) {
            if (isset($tema['relevancia'])) continue;
            $tema['relevancia'] = 'indeterminada';
            $tema['dominio_tematico'] = null;
            $tema['framing_divergence'] = null;
            $tema['framing_evidence'] = null;
            $tema['h_framing'] = null;
            $tema['h_silencio'] = $tema['h_silencio_v2'];
            $tema['h_score'] = 0.0;
            $tema['scoring_version'] = 'v2';
        }
        unset($tema);
    }

    // Re-sort by new h_score
    usort($all_temas, function($a, $b) { return $b['h_score'] <=> $a['h_score']; });

    // Log scored topics
    foreach ($all_temas as $tema) {
        $rel_tag = isset($tema['relevancia']) ? $tema['relevancia'] : '?';
        $fd_tag = isset($tema['framing_divergence']) ? $tema['framing_divergence'] : '?';
        prisma_log("SCAN", sprintf(
            "  H=%.0f%% cob=%.0f%% fd=%s rel=%s | %s",
            $tema['h_score'] * 100,
            (isset($tema['h_cobertura_mutua']) ? $tema['h_cobertura_mutua'] : 0) * 100,
            $fd_tag, $rel_tag,
            mb_substr($tema['titulo_tema'], 0, 55, 'UTF-8')
        ));
    }

    // 4. Insert into radar (dedup included)
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
