<?php
/**
 * Prisma — Diccionarios de filtrado para scoring v2.
 *
 * Pre-filtro determinista: lista negativa descarta temas triviales,
 * lista positiva marca clusters con actores políticos (hint, no gate).
 */

/**
 * Normaliza un título para matching: lowercase, sin acentos, solo alfanumérico+espacios.
 * Usa la misma lógica que extraer_keywords() en curador.php.
 */
function normalizar_titulo(string $texto): string {
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, array(
        'á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u',
        'ü'=>'u','ñ'=>'n',
        'à'=>'a','è'=>'e','ì'=>'i','ò'=>'o','ù'=>'u',
        'â'=>'a','ê'=>'e','î'=>'i','ô'=>'o','û'=>'u',
        'ä'=>'a','ë'=>'e','ï'=>'i','ö'=>'o',
    ));
    $texto = preg_replace('/[^a-z0-9\s]/', ' ', $texto);
    $texto = preg_replace('/\s+/', ' ', trim($texto));
    return $texto;
}

/**
 * Checks if any keyword from the list matches in the normalized title.
 * Uses word-boundary matching (\b) to avoid substring false positives.
 *
 * @param string $titulo_normalizado Already normalized title
 * @param array $lista Keywords to match
 * @return string|null The first matched keyword, or null if no match
 */
function matchear_lista(string $titulo_normalizado, array $lista) {
    foreach ($lista as $keyword) {
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/';
        if (preg_match($pattern, $titulo_normalizado)) {
            return $keyword;
        }
    }
    return null;
}

/**
 * Aplica la lista negativa al título de un cluster.
 *
 * @param string $titulo_tema Raw title from cluster
 * @return array ['descartado' => bool, 'keyword' => string|null]
 */
function aplicar_lista_negativa(string $titulo_tema): array {
    $cfg = prisma_cfg();
    $norm = normalizar_titulo($titulo_tema);

    // Check config list first
    $match = matchear_lista($norm, $cfg['lista_negativa']);
    if ($match !== null) {
        return array('descartado' => true, 'keyword' => $match);
    }

    // Check custom DB list (learned from panel discards)
    $custom = lista_negativa_custom();
    $match = matchear_lista($norm, $custom);
    return array(
        'descartado' => $match !== null,
        'keyword' => $match,
    );
}

/**
 * Loads custom negative keywords from the DB.
 * Cached per request to avoid repeated queries.
 */
function lista_negativa_custom(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    try {
        require_once dirname(__DIR__) . '/db.php';
        $db = prisma_db();
        $rows = $db->query('SELECT keyword FROM lista_negativa_custom')->fetchAll();
        $cache = array_column($rows, 'keyword');
    } catch (Exception $e) {
        $cache = array();
    }
    return $cache;
}

/**
 * Detecta si el cluster matchea la lista positiva (hint para Haiku).
 *
 * @param string $titulo_tema Raw title from cluster
 * @return bool true if any political actor/institution found
 */
function detectar_lista_positiva(string $titulo_tema): bool {
    $cfg = prisma_cfg();
    $norm = normalizar_titulo($titulo_tema);
    return matchear_lista($norm, $cfg['lista_positiva']) !== null;
}
