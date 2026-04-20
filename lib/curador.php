<?php
/**
 * Prisma — Curador de temas.
 *
 * Agrupa artículos RSS por tema (similitud de titulares) y selecciona
 * los N temas más relevantes que cumplan el criterio de diversidad.
 */

/**
 * Selecciona los temas del día a partir de artículos RSS.
 *
 * @param array $articles Artículos de rss_fetch_all()
 * @param int $max_temas Número de temas a seleccionar
 * @return array [ ['titulo_tema'=>..., 'articulos'=>[...], 'cuadrantes'=>[...], 'score'=>...], ... ]
 */
function curador_seleccionar(array $articles, int $max_temas = 5): array {
    if (empty($articles)) return [];

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

    // 3. Puntuar cada cluster: diversidad de cuadrantes × frecuencia
    $scored = [];
    foreach ($clusters as $cluster) {
        $arts = array_map(fn($i) => $indexed[$i]['article'], $cluster);
        $cuadrantes = array_unique(array_column($arts, 'cuadrante'));

        // Mínimo 3 cuadrantes distintos
        if (count($cuadrantes) < 3) continue;

        // Título representativo: el más corto (suele ser el más factual)
        usort($arts, fn($a, $b) => mb_strlen($a['titulo']) - mb_strlen($b['titulo']));
        $titulo_tema = $arts[0]['titulo'];

        $score = count($cluster) * count($cuadrantes);

        $scored[] = [
            'titulo_tema' => $titulo_tema,
            'articulos'   => $arts,
            'cuadrantes'  => array_values($cuadrantes),
            'score'       => $score,
            'n_articulos' => count($cluster),
            'n_cuadrantes'=> count($cuadrantes),
        ];
    }

    // 4. Ordenar por score descendente y tomar los N mejores
    usort($scored, fn($a, $b) => $b['score'] - $a['score']);
    $selected = array_slice($scored, 0, $max_temas);

    foreach ($selected as $tema) {
        prisma_log("CURADOR", sprintf(
            "Tema: \"%s\" — %d artículos, %d cuadrantes (score: %d)",
            mb_substr($tema['titulo_tema'], 0, 80),
            $tema['n_articulos'],
            $tema['n_cuadrantes'],
            $tema['score']
        ));
    }

    return $selected;
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
