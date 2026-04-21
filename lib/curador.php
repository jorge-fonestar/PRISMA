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

    // 1. Extraer palabras clave de cada titular
    $indexed = [];
    foreach ($articles as $i => $art) {
        $indexed[$i] = [
            'article'  => $art,
            'keywords' => extraer_keywords($art['titulo']),
        ];
    }

    // 2. Agrupar por similitud de keywords
    $clusters = [];
    $assigned = [];

    foreach ($indexed as $i => $item) {
        if (isset($assigned[$i])) continue;

        $cluster = [$i];
        $assigned[$i] = true;

        foreach ($indexed as $j => $other) {
            if ($i === $j || isset($assigned[$j])) continue;
            if (keywords_similarity($item['keywords'], $other['keywords']) >= 0.3) {
                $cluster[] = $j;
                $assigned[$j] = true;
            }
        }

        // Solo clusters con ≥2 artículos son candidatos
        if (count($cluster) >= 2) {
            $clusters[] = $cluster;
        }
    }

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

    foreach ($scored as $tema) {
        prisma_log("CURADOR", sprintf(
            "Tema: \"%s\" — %d arts, %d cuad, H=%.0f%% (A=%.0f%% D=%.0f%% V=%.0f%%)",
            mb_substr($tema['titulo_tema'], 0, 60),
            $tema['n_articulos'],
            $tema['n_cuadrantes'],
            $tema['h_score'] * 100,
            $tema['h_asimetria'] * 100,
            $tema['h_divergencia'] * 100,
            $tema['h_varianza'] * 100
        ));
    }

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
    $kw_izq = [];
    $kw_der = [];
    foreach ($articles as $art) {
        $kw = extraer_keywords($art['titulo']);
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
