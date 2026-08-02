<?php
/**
 * PolarPrisma — Fase 1: Escaneo de fuentes (una pasada diaria).
 *
 * Lee RSS de todos los ámbitos, pre-agrupa por similitud (Jaccard determinista),
 * FUSIONA + clasifica los clusters con una única llamada Haiku (resuelve la
 * fragmentación que el Jaccard no ve), re-calcula el índice de polarización sobre
 * los grupos fusionados e inserta en el radar.
 *
 * Uso:
 *   php escanear.php                       # Todos los ámbitos
 *   php escanear.php --ambito españa       # Solo un ámbito
 *
 * Cron (1×/día): 30 4 * * *  (04:30 UTC, antes del análisis y el digest).
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/common.php';
require_once __DIR__ . '/lib/rss.php';
require_once __DIR__ . '/lib/curador.php';
require_once __DIR__ . '/lib/scoring.php';
require_once __DIR__ . '/lib/diccionarios.php';
require_once __DIR__ . '/lib/gate_haiku.php';
require_once __DIR__ . '/lib/fuentes/feed_health.php';

// Purge old feed_health records (>90 days)
$purged = feed_health_purgar(90);
if ($purged > 0) {
    prisma_log("SCAN", "Purgados $purged registros de feed_health (>90 días)");
}

// ── Args ─────────────────────────────────────────────────────────────

$opts = getopt('', array('ambito:', 'help'));

if (isset($opts['help'])) {
    echo "Uso: php escanear.php [--ambito españa|europa|global|todos]\n";
    echo "Lee RSS, fusiona/clasifica con Haiku e inserta en radar.\n";
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
prisma_log("SCAN", "PolarPrisma — Escaneo diario (Fase 1)");
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

    // 2. Pre-agrupar por similitud (Jaccard determinista, gratis)
    prisma_log("SCAN", "Pre-agrupando temas...");
    $all_temas = curador_seleccionar($articles);

    if (empty($all_temas)) {
        prisma_log("SCAN", "No hay temas con suficientes artículos para $ambito. Saltando.");
        continue;
    }

    prisma_log("SCAN", count($all_temas) . " clusters preliminares.");

    // 3. Pre-filtro determinista: descarta lo trivial antes de gastar el gate
    $descartados = array();
    $para_gate = array();
    $cid = 0;
    foreach ($all_temas as $tema) {
        $cid++;
        $tema['cluster_id'] = $cid;

        $neg = aplicar_lista_negativa($tema['titulo_tema']);
        if ($neg['descartado']) {
            $tema['h_cobertura_mutua'] = calcular_cobertura_mutua($tema['articulos']);
            $tema['h_silencio'] = calcular_silencio($tema['articulos']);
            $tema['h_framing'] = 0.0;
            $tema['h_score'] = 0.0;
            $tema['framing_divergence'] = null;
            $tema['framing_evidence'] = null;
            $tema['relevancia'] = 'descartar';
            $tema['dominio_tematico'] = null;
            $tema['resumen_neutral'] = null;
            $tema['scoring_version'] = 'v2';
            $descartados[] = $tema;
            continue;
        }

        $tema['contains_political_actor'] = detectar_lista_positiva($tema['titulo_tema']);
        $para_gate[] = $tema;
    }

    // 4. Fusión semántica + clasificación (una llamada Haiku)
    if (!empty($para_gate) && $cfg['gate_haiku_enabled']) {
        prisma_log("SCAN", "Gate Haiku: fusionando + clasificando " . count($para_gate) . " clusters...");
        $grupos = gate_haiku_agrupar_clasificar($para_gate);
    } else {
        $grupos = gate_haiku_grupos_fallback($para_gate);
    }

    // 5. Construir temas fusionados + re-scoring estructural sobre el grupo
    $merged_temas = array();
    foreach ($grupos as $g) {
        $arts = $g['articulos'];
        if (empty($arts)) continue;

        usort($arts, function ($a, $b) { return mb_strlen($a['titulo']) - mb_strlen($b['titulo']); });
        $titulo = $arts[0]['titulo'];
        $cuadrantes = array_values(array_unique(array_column($arts, 'cuadrante')));

        $tema = array(
            'titulo_tema'             => $titulo,
            'articulos'               => $arts,
            'cuadrantes'              => $cuadrantes,
            'n_articulos'             => count($arts),
            'n_cuadrantes'            => count($cuadrantes),
            'contains_political_actor'=> detectar_lista_positiva($titulo),
            'h_cobertura_mutua'       => calcular_cobertura_mutua($arts),
            'h_silencio_v2'           => calcular_silencio($arts),
            'framing_divergence'      => $g['framing_divergence'],
            'framing_evidence'        => $g['framing_evidence'],
            'relevancia'              => $g['relevancia'],
            'dominio_tematico'        => $g['dominio'],
            'resumen_neutral'         => $g['resumen_neutral'],
        );
        $tema['h_framing'] = normalizar_framing($g['framing_divergence']);

        $sv2 = calcular_h_score_v2(array(
            'h_cob'          => $tema['h_cobertura_mutua'],
            'h_sil'          => $tema['h_silencio_v2'],
            'fd'             => $g['framing_divergence'],
            'relevancia'     => $g['relevancia'],
            'lista_positiva' => !empty($tema['contains_political_actor']),
        ));
        $tema['h_score']         = $sv2['h_score'];
        $tema['h_silencio']      = $tema['h_silencio_v2'];
        $tema['relevancia']      = $sv2['relevancia_final'];
        $tema['scoring_version'] = 'v2';

        foreach ($g['anomalies'] as $anom) scoring_log_anomaly($fecha, null, $anom['tipo'], $anom['detalle']);
        foreach ($sv2['anomalies'] as $anom) scoring_log_anomaly($fecha, null, $anom['tipo'], $anom['detalle']);

        $merged_temas[] = $tema;
    }

    $all_temas = array_merge($descartados, $merged_temas);

    // Re-sort by h_score
    usort($all_temas, function ($a, $b) { return $b['h_score'] <=> $a['h_score']; });

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

    // 6. Insert into radar (dedup exacto del mismo día incluido)
    $all_temas = radar_insertar_todos($all_temas, $ambito, $fecha);
    $total_radar += count($all_temas);

    // 7. Report candidates above threshold
    $umbral = $cfg['umbral_tension'];
    $min_cuad = $cfg['min_cuadrantes'];
    $candidatos = array_filter($all_temas, function ($t) use ($umbral, $min_cuad) {
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

// Observatorio: asigna los clusters relevantes de hoy a sus temas (hilos de agenda).
try {
    require_once __DIR__ . '/lib/observatorio.php';
    observatorio_asignar_dia($fecha);
} catch (\Throwable $e) {
    prisma_log("SCAN", "Observatorio: error asignando — " . $e->getMessage());
}

// Los avisos de Telegram van en el digest diario (digest_telegram.php, cron 06:00).

exit(0);
