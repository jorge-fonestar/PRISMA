<?php
/**
 * Prisma — Gate Haiku: batch classification of clusters for scoring v2.
 *
 * Classifies clusters by relevance, thematic domain, and framing divergence.
 * Single batch call per scan. Results cached in radar table.
 */

require_once __DIR__ . '/anthropic.php';
require_once __DIR__ . '/scoring.php';

/**
 * Valid enum values for Haiku output validation.
 */
define('PRISMA_RELEVANCIA_VALID', array('alta', 'media', 'baja', 'descartar'));
define('PRISMA_DOMINIO_VALID', array(
    'politica_institucional', 'economia_trabajo', 'sanidad_ciencia',
    'tecnologia_regulacion', 'cultura_identidad', 'medio_ambiente',
    'educacion', 'inmigracion', 'internacional', 'otros'
));

/**
 * Classifies an array of clusters using Haiku batch.
 *
 * @param array $clusters Each must have: 'cluster_id', 'titulo_tema', 'articulos',
 *                        'contains_political_actor', 'bloques_activos'
 * @return array Indexed by cluster_id: ['relevancia', 'dominio', 'framing_divergence', 'framing_evidence', 'anomalies']
 */
function gate_haiku_clasificar(array $clusters): array {
    $cfg = prisma_cfg();

    if (empty($clusters)) return array();

    // Check budget
    try {
        anthropic_check_budget();
    } catch (Exception $e) {
        prisma_log("GATE", "Budget exhausted — skipping Haiku gate: " . $e->getMessage());
        return gate_haiku_fallback($clusters);
    }

    // Build input for prompt
    $clusters_json = array();
    foreach ($clusters as $cluster) {
        $por_bloque = array();
        foreach ($cluster['articulos'] as $art) {
            $c = $art['cuadrante'];
            if (in_array($c, PRISMA_GRUPO_IZQ)) $bloque = 'izquierda';
            elseif (in_array($c, PRISMA_GRUPO_DER)) $bloque = 'derecha';
            else $bloque = 'centro';

            if (!isset($por_bloque[$bloque])) $por_bloque[$bloque] = array();
            $por_bloque[$bloque][] = $art['titulo'] . ' (' . $art['medio'] . ')';
        }

        // Limit to 3 headlines per bloc to control token usage
        foreach ($por_bloque as $bloque => $titulares) {
            $por_bloque[$bloque] = array_slice($titulares, 0, 3);
        }

        $clusters_json[] = array(
            'cluster_id' => $cluster['cluster_id'],
            'contains_political_actor' => !empty($cluster['contains_political_actor']),
            'titulares_por_cuadrante' => $por_bloque,
        );
    }

    $system = 'Eres un clasificador de temas informativos. Evalúas clusters de titulares agrupados por cuadrante ideológico (izquierda, centro, derecha) y determinas:

1. RELEVANCIA (string, obligatorio): nivel de potencial para generar narrativas divergentes entre ejes ideológicos. Valores EXACTOS permitidos: "alta", "media", "baja", "descartar". NO devuelvas true/false ni números.
   - "alta": tema político, social o económico con marcos claramente divergentes entre cuadrantes
   - "media": tema con potencial de divergencia pero no evidente en los titulares
   - "baja": tema factual sin carga ideológica pero dentro del ámbito informativo
   - "descartar": deportes, loterías, entretenimiento, curiosidades, meteorología rutinaria, crónica social

2. DOMINIO TEMÁTICO (string, obligatorio): categoría del tema. Valores válidos: "politica_institucional", "economia_trabajo", "sanidad_ciencia", "tecnologia_regulacion", "cultura_identidad", "medio_ambiente", "educacion", "inmigracion", "internacional", "otros".

3. FRAMING DIVERGENCE (integer 0-3, obligatorio): grado de divergencia en el encuadre entre cuadrantes.
   REGLAS:
   - Si solo 1 bloque ideológico cubre el tema → framing_divergence = 0
   - Si solo 2 bloques cubren → framing_divergence máximo = 2
   - Si 3 bloques cubren → sin restricción (0-3)
   Escala:
   0 = cobertura monocorde, insuficiente para juzgar, o solo 1 bloque
   1 = diferencias menores de énfasis
   2 = marcos claramente distintos entre cuadrantes
   3 = marcos ideológicamente opuestos sobre el mismo hecho

4. FRAMING EVIDENCE (string o null): cita breve (<20 palabras) de los marcos detectados, o null.

Si contains_political_actor es true, el cluster referencia actores políticos o instituciones — calibra relevancia en consecuencia (tiende a "alta").

Cada objeto del array DEBE tener: cluster_id (int), relevancia (string), dominio_tematico (string), framing_divergence (int), framing_evidence (string o null).

Responde SOLO con un JSON array válido, sin markdown ni explicaciones.';

    $user_msg = json_encode(array('clusters' => $clusters_json), JSON_UNESCAPED_UNICODE);

    $model = $cfg['model_triage'];
    $max_retries = 1;
    $results = null;

    for ($attempt = 0; $attempt <= $max_retries; $attempt++) {
        try {
            $raw = anthropic_call($model, $system, $user_msg, 4096);
            $results = parse_json_response($raw);
            if (is_array($results)) break;
        } catch (Exception $e) {
            prisma_log("GATE", "Haiku call failed (attempt " . ($attempt + 1) . "): " . $e->getMessage());
        }
    }

    if (!is_array($results)) {
        prisma_log("GATE", "Haiku failed after retries — using fallback.");
        return gate_haiku_fallback($clusters);
    }

    // Unwrap if Haiku returned {"clusters": [...]} instead of [...]
    if (isset($results['clusters']) && is_array($results['clusters'])) {
        prisma_log("GATE", "Unwrapping nested 'clusters' key from Haiku response.");
        $results = $results['clusters'];
    }

    // Debug: log first result to diagnose type issues
    if (!empty($results)) {
        $sample = $results[0];
        prisma_log("GATE", "Sample Haiku result: " . json_encode($sample, JSON_UNESCAPED_UNICODE));
    }

    // Map and validate results
    $indexed = array();
    foreach ($results as $r) {
        $cid = isset($r['cluster_id']) ? $r['cluster_id'] : null;
        if ($cid === null) continue;

        // Find matching cluster to get bloques_activos for cap validation
        $bloques_activos = 3; // default
        foreach ($clusters as $cl) {
            if ((int)$cl['cluster_id'] === (int)$cid) {
                $bloques_activos = $cl['bloques_activos'];
                break;
            }
        }

        $rel_raw = isset($r['relevancia']) ? $r['relevancia'] : 'media';
        // Haiku may return 'dominio' or 'dominio_tematico'
        $dom = isset($r['dominio_tematico']) ? (string)$r['dominio_tematico']
             : (isset($r['dominio']) ? (string)$r['dominio'] : 'otros');
        $fd  = isset($r['framing_divergence']) ? (int)$r['framing_divergence'] : 0;
        $ev  = isset($r['framing_evidence']) ? $r['framing_evidence'] : null;

        // Haiku sometimes returns booleans instead of strings for relevancia
        if ($rel_raw === true) {
            $rel = 'alta';
        } elseif ($rel_raw === false) {
            $rel = 'baja';
        } else {
            $rel = (string)$rel_raw;
        }

        // Validate enums (strict to avoid PHP type juggling)
        if (!in_array($rel, PRISMA_RELEVANCIA_VALID, true)) $rel = 'media';
        if (!in_array($dom, PRISMA_DOMINIO_VALID, true)) $dom = 'otros';
        if ($fd < 0) $fd = 0;
        if ($fd > 3) $fd = 3;

        // Cap validation
        $anomalies = array();
        if ($bloques_activos === 1 && $fd > 0) {
            $anomalies[] = array('tipo' => 'ANOMALY_FD_CAP_VIOLATION', 'detalle' => "fd=$fd with 1 bloc, capped to 0");
            $fd = 0;
        } elseif ($bloques_activos === 2 && $fd > 2) {
            $anomalies[] = array('tipo' => 'ANOMALY_FD_CAP_VIOLATION', 'detalle' => "fd=$fd with 2 blocs, capped to 2");
            $fd = 2;
        }

        $indexed[(int)$cid] = array(
            'relevancia' => $rel,
            'dominio' => $dom,
            'framing_divergence' => $fd,
            'framing_evidence' => $ev,
            'anomalies' => $anomalies,
        );
    }

    // Handle missing clusters (conservative defaults)
    foreach ($clusters as $cl) {
        $cid = (int)$cl['cluster_id'];
        if (!isset($indexed[$cid])) {
            $indexed[$cid] = array(
                'relevancia' => 'media',
                'dominio' => 'otros',
                'framing_divergence' => 1,
                'framing_evidence' => null,
                'anomalies' => array(
                    array('tipo' => 'ANOMALY_MISSING_CLUSTER', 'detalle' => "cluster_id=$cid missing from Haiku response"),
                ),
            );
        }
    }

    prisma_log("GATE", count($indexed) . " clusters classified by Haiku.");
    return $indexed;
}

/**
 * Fallback when Haiku is unavailable: all clusters get indeterminada.
 */
function gate_haiku_fallback(array $clusters): array {
    $indexed = array();
    foreach ($clusters as $cl) {
        $indexed[$cl['cluster_id']] = array(
            'relevancia' => 'indeterminada',
            'dominio' => null,
            'framing_divergence' => null,
            'framing_evidence' => null,
            'anomalies' => array(),
        );
    }
    return $indexed;
}

/**
 * Checks if a cluster's Haiku classification is cached (already in radar with scoring v2).
 *
 * Cache key: titulo_tema + cuadrantes composition.
 * Invalidation: if the cluster now has different active cuadrantes than cached, reclassify.
 * TTL: 48h.
 *
 * @param string $titulo_tema Cluster title
 * @param string $cuadrantes_key Sorted comma-separated active cuadrantes
 * @param string $fecha Date string
 * @return array|null Cached classification or null
 */
function gate_haiku_cache_check(string $titulo_tema, string $cuadrantes_key, string $fecha) {
    require_once __DIR__ . '/../db.php';
    $db = prisma_db();

    $stmt = $db->prepare("SELECT relevancia, dominio_tematico, framing_divergence, framing_evidence, fuentes_json
        FROM radar
        WHERE titulo_tema = :titulo AND fecha >= :fecha_min
        AND scoring_version = 'v2' AND relevancia IS NOT NULL
        ORDER BY fecha DESC
        LIMIT 1");

    // TTL: 48h
    $fecha_min = date('Y-m-d', strtotime($fecha . ' -2 days'));
    $stmt->execute(array(':titulo' => $titulo_tema, ':fecha_min' => $fecha_min));
    $row = $stmt->fetch();

    if (!$row) return null;

    // Invalidation: compare cuadrantes composition
    $cached_fuentes = json_decode($row['fuentes_json'], true);
    if (is_array($cached_fuentes)) {
        $cached_cuadrantes = array_unique(array_column($cached_fuentes, 'cuadrante'));
        sort($cached_cuadrantes);
        $cached_key = implode(',', $cached_cuadrantes);
        if ($cached_key !== $cuadrantes_key) {
            // Composition changed — invalidate cache
            return null;
        }
    }

    return array(
        'relevancia' => $row['relevancia'],
        'dominio' => $row['dominio_tematico'],
        'framing_divergence' => $row['framing_divergence'] !== null ? (int)$row['framing_divergence'] : null,
        'framing_evidence' => $row['framing_evidence'],
        'anomalies' => array(),
    );
}
