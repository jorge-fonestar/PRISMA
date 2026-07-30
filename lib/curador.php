<?php
/**
 * Prisma — Curador de temas.
 *
 * Agrupa artículos RSS por tema (similitud de titulares) y selecciona
 * los N temas más relevantes que cumplan el criterio de diversidad.
 */

// Ideological spectrum positions for tension calculation
define('PRISMA_CUADRANTE_POS', [
    'izquierda-populista' => -3,
    'izquierda'           => -2,
    'centro-izquierda'    => -1,
    'centro'              =>  0,
    'centro-derecha'      =>  1,
    'derecha'             =>  2,
    'derecha-populista'   =>  3,
]);

define('PRISMA_GRUPO_IZQ', ['izquierda-populista', 'izquierda', 'centro-izquierda']);
define('PRISMA_GRUPO_DER', ['centro-derecha', 'derecha', 'derecha-populista']);
define('PRISMA_GRUPO_CENTRO', ['centro']);

/**
 * Selecciona los temas del día a partir de artículos RSS.
 * Returns ALL scored topics (no slicing); pipeline handles filtering.
 *
 * @param array $articles Artículos de rss_fetch_all()
 * @return array [ ['titulo_tema'=>..., 'articulos'=>[...], 'h_score'=>...], ... ]
 */
function curador_seleccionar(array $articles): array {
    if (empty($articles)) return [];

    // Auto-detectar mínimo de cuadrantes si no se especifica:
    // Cuenta cuántos cuadrantes distintos hay en los artículos disponibles
    $cfg = prisma_cfg();
    $min_cuadrantes = $cfg['min_cuadrantes'] ?? 0;
    if ($min_cuadrantes <= 0) {
        $available = count(array_unique(array_column($articles, 'cuadrante')));
        // España (6 cuadrantes) → exigir 3; Europa/Global (2-3) → exigir 2
        $min_cuadrantes = $available >= 4 ? 3 : 2;
        prisma_log("CURADOR", "Cuadrantes disponibles: $available → mínimo exigido: $min_cuadrantes");
    }

    // 1. Extraer palabras clave ponderadas (titular + descripción si está activo)
    $umbral_sim = isset($cfg['cluster_umbral']) ? (float)$cfg['cluster_umbral'] : 0.3;
    $indexed = [];
    foreach ($articles as $i => $art) {
        $indexed[$i] = [
            'article'  => $art,
            'keywords' => extraer_keywords_articulo($art),
        ];
    }

    // 2. Agrupar por similitud de keywords (Jaccard ponderado).
    // Cada artículo se une al cluster MÁS similar (no al primero que supere el
    // umbral, como hacía el greedy anterior). Así un titular con keywords
    // fuertes compartidas por varios temas —p.ej. "España/Mundial"— cae en el
    // tema correcto y no lo "roba" el primer cluster que lo rozaba.
    // Se compara contra el vocabulario acumulado de cada cluster.
    $abiertos = array(); // cada uno: ['indices'=>[], 'kw'=>[palabra=>peso]]

    foreach ($indexed as $i => $item) {
        $best = null;
        $best_sim = 0.0;
        foreach ($abiertos as $ci => $cl) {
            $sim = keywords_similarity_w($item['keywords'], $cl['kw']);
            if ($sim > $best_sim) { $best_sim = $sim; $best = $ci; }
        }

        if ($best !== null && $best_sim >= $umbral_sim) {
            $abiertos[$best]['indices'][] = $i;
            // Acumular vocabulario (peso máximo por palabra)
            foreach ($item['keywords'] as $w => $wt) {
                if (!isset($abiertos[$best]['kw'][$w]) || $abiertos[$best]['kw'][$w] < $wt) {
                    $abiertos[$best]['kw'][$w] = $wt;
                }
            }
        } else {
            $abiertos[] = array('indices' => array($i), 'kw' => $item['keywords']);
        }
    }

    // Solo clusters con ≥2 artículos son candidatos
    $clusters = [];
    foreach ($abiertos as $cl) {
        if (count($cl['indices']) >= 2) $clusters[] = $cl['indices'];
    }

    // 2b. La fusión de fragmentos ("indulta"/"concede el indulto", acentos…) la hace
    // ahora Haiku en escanear.php (gate_haiku_agrupar_clasificar), mucho más robusto
    // que los trigramas deterministas. Aquí solo dejamos el pre-agrupado barato.

    // 3. Score each cluster with tension formula — no min_cuadrantes filter (all go to radar)
    $scored = [];
    foreach ($clusters as $cluster) {
        $arts = array_map(fn($i) => $indexed[$i]['article'], $cluster);
        $cuadrantes = array_unique(array_column($arts, 'cuadrante'));

        // Título representativo: el más corto (suele ser el más factual)
        usort($arts, fn($a, $b) => mb_strlen($a['titulo']) - mb_strlen($b['titulo']));
        $titulo_tema = $arts[0]['titulo'];

        $tension = calcular_tension($arts);

        $scored[] = [
            'titulo_tema'   => $titulo_tema,
            'articulos'     => $arts,
            'cuadrantes'    => array_values($cuadrantes),
            'n_articulos'   => count($cluster),
            'n_cuadrantes'  => count($cuadrantes),
            'score'         => $tension['h_score'],
            'h_score'       => $tension['h_score'],
            'h_asimetria'   => $tension['h_asimetria'],
            'h_divergencia' => $tension['h_divergencia'],
            'h_varianza'    => $tension['h_varianza'],
        ];
    }

    // 4. Sort by tension score descending
    usort($scored, fn($a, $b) => $b['h_score'] <=> $a['h_score']);

    // Logging moved to escanear.php (scoring v2 pipeline)
    return $scored;
}

/**
 * Calcula el índice de polarización informativa de un cluster.
 *
 * @param array $articles Artículos del cluster (cada uno con 'cuadrante')
 * @return array ['h_score'=>float, 'h_asimetria'=>float, 'h_divergencia'=>float, 'h_varianza'=>float]
 */
function calcular_tension(array $articles): array {
    // --- Signal A: Coverage Asymmetry (60%) ---
    $izq_n = 0;
    $der_n = 0;
    $centro_n = 0;
    foreach ($articles as $art) {
        $c = $art['cuadrante'];
        if (in_array($c, PRISMA_GRUPO_IZQ)) $izq_n++;
        elseif (in_array($c, PRISMA_GRUPO_DER)) $der_n++;
        else $centro_n++;
    }
    $total = $izq_n + $der_n + $centro_n;
    $asimetria = ($total > 0) ? abs($izq_n - $der_n) / $total : 0.0;

    // --- Signal B: Lexical Divergence (25%) ---
    // Con cluster_usar_descripcion activo compara el vocabulario completo
    // (titular + entradilla) de cada bloque, no solo los titulares.
    $kw_izq = [];
    $kw_der = [];
    foreach ($articles as $art) {
        $kw = extraer_keywords_articulo($art);
        $c = $art['cuadrante'];
        if (in_array($c, PRISMA_GRUPO_IZQ)) {
            $kw_izq = array_merge($kw_izq, array_keys($kw));
        } elseif (in_array($c, PRISMA_GRUPO_DER)) {
            $kw_der = array_merge($kw_der, array_keys($kw));
        }
    }
    $kw_izq = array_flip(array_unique($kw_izq));
    $kw_der = array_flip(array_unique($kw_der));

    if (empty($kw_izq) || empty($kw_der)) {
        $divergencia = 0.0;
    } else {
        $divergencia = 1.0 - keywords_similarity($kw_izq, $kw_der);
    }

    // --- Signal C: Spectrum Variance (15%) ---
    $cuadrantes = array_unique(array_column($articles, 'cuadrante'));
    $posiciones = [];
    foreach ($cuadrantes as $c) {
        if (isset(PRISMA_CUADRANTE_POS[$c])) {
            $posiciones[] = PRISMA_CUADRANTE_POS[$c];
        }
    }
    $varianza_norm = 0.0;
    if (count($posiciones) >= 2) {
        $mean = array_sum($posiciones) / count($posiciones);
        $sq_diff = 0.0;
        foreach ($posiciones as $p) {
            $sq_diff += ($p - $mean) * ($p - $mean);
        }
        $variance = $sq_diff / count($posiciones);
        $varianza_norm = min($variance / 9.0, 1.0);
    }

    // --- Composite Score ---
    $h = 0.60 * $asimetria + 0.25 * $divergencia + 0.15 * $varianza_norm;

    return [
        'h_score'       => round($h, 4),
        'h_asimetria'   => round($asimetria, 4),
        'h_divergencia' => round($divergencia, 4),
        'h_varianza'    => round($varianza_norm, 4),
    ];
}

/**
 * Extrae palabras clave normalizadas de un titular.
 */
function extraer_keywords(string $texto): array {
    $texto = mb_strtolower($texto, 'UTF-8');

    // Eliminar acentos
    $texto = strtr($texto, [
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
        'ü'=>'u','ñ'=>'n',
    ]);

    // Solo letras y espacios
    $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto);
    $words = preg_split('/\s+/', $texto, -1, PREG_SPLIT_NO_EMPTY);

    // Stopwords español (mínimas)
    $stop = array_flip([
        'el','la','los','las','un','una','unos','unas','de','del','en','con','por',
        'para','al','a','y','o','que','se','su','sus','es','ha','han','no','mas',
        'pero','como','este','esta','estos','estas','ese','esa','esos','esas',
        'lo','le','les','ya','sin','sobre','entre','desde','hasta','tras','ante',
        'muy','otro','otra','otros','otras','ser','hay','fue','son','era','sido',
    ]);

    $keywords = [];
    foreach ($words as $w) {
        if (mb_strlen($w) < 3) continue;
        if (isset($stop[$w])) continue;
        $keywords[$w] = true;
    }

    return $keywords;
}

/**
 * Similitud Jaccard entre dos conjuntos de keywords.
 */
function keywords_similarity(array $a, array $b): float {
    if (empty($a) || empty($b)) return 0.0;

    $intersection = count(array_intersect_key($a, $b));
    $union = count($a) + count($b) - $intersection;

    return $union > 0 ? $intersection / $union : 0.0;
}

/**
 * Extrae keywords ponderadas de un artículo completo.
 *
 * Titular → peso 1.0; descripción RSS (truncada) → peso cluster_desc_peso.
 * Si una palabra aparece en ambos, prevalece el peso del titular.
 * Con cluster_usar_descripcion=false equivale a extraer_keywords(titulo)
 * con peso 1.0 en todo (y el Jaccard ponderado se reduce al clásico).
 *
 * @param array $art Artículo con 'titulo' y opcionalmente 'descripcion'
 * @return array [palabra => peso]
 */
function extraer_keywords_articulo(array $art): array {
    $cfg = prisma_cfg();

    $kw = [];
    foreach (extraer_keywords($art['titulo']) as $w => $_) {
        $kw[$w] = 1.0;
    }

    if (!empty($cfg['cluster_usar_descripcion']) && !empty($art['descripcion'])) {
        $peso = isset($cfg['cluster_desc_peso']) ? (float)$cfg['cluster_desc_peso'] : 0.5;
        $max_chars = isset($cfg['cluster_desc_max_chars']) ? (int)$cfg['cluster_desc_max_chars'] : 500;
        $desc = mb_substr(strip_tags($art['descripcion']), 0, $max_chars, 'UTF-8');
        foreach (extraer_keywords($desc) as $w => $_) {
            if (!isset($kw[$w])) $kw[$w] = $peso;
        }
    }

    return $kw;
}

/**
 * Jaccard ponderado: sum(min(peso_a, peso_b)) / sum(max(peso_a, peso_b)).
 *
 * Con pesos binarios (1.0/ausente) se reduce al Jaccard clásico, así que
 * es un reemplazo directo de keywords_similarity para mapas ponderados.
 * Acepta también mapas [palabra => true] (true cuenta como 1.0).
 */
function keywords_similarity_w(array $a, array $b): float {
    if (empty($a) || empty($b)) return 0.0;

    $min_sum = 0.0;
    $max_sum = 0.0;

    foreach ($a as $w => $wa) {
        $wa = ($wa === true) ? 1.0 : (float)$wa;
        if (isset($b[$w])) {
            $wb = ($b[$w] === true) ? 1.0 : (float)$b[$w];
            $min_sum += min($wa, $wb);
            $max_sum += max($wa, $wb);
        } else {
            $max_sum += $wa;
        }
    }
    foreach ($b as $w => $wb) {
        if (!isset($a[$w])) {
            $max_sum += ($wb === true) ? 1.0 : (float)$wb;
        }
    }

    return $max_sum > 0 ? $min_sum / $max_sum : 0.0;
}

/**
 * Prepara el contexto de un tema para el Sintetizador:
 * agrupa titulares y snippets por cuadrante.
 */
function curador_preparar_contexto(array $tema): string {
    $lines = [];
    $lines[] = "TEMA: " . $tema['titulo_tema'];
    $lines[] = "";

    // Agrupar por cuadrante
    $por_cuadrante = [];
    foreach ($tema['articulos'] as $art) {
        $por_cuadrante[$art['cuadrante']][] = $art;
    }

    foreach ($por_cuadrante as $cuadrante => $arts) {
        $lines[] = "## Cuadrante: $cuadrante";
        foreach ($arts as $art) {
            $lines[] = "- [{$art['medio']}] {$art['titulo']}";
            if (!empty($art['descripcion'])) {
                $lines[] = "  > " . mb_substr($art['descripcion'], 0, 300);
            }
            $lines[] = "  URL: {$art['url']}";
        }
        $lines[] = "";
    }

    return implode("\n", $lines);
}
