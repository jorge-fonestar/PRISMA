<?php
/**
 * Prisma — AJAX endpoint for paginated radar results.
 *
 * GET params:
 *   fecha_desde, fecha_hasta  — date range (YYYY-MM-DD)
 *   q                         — text search in titulo_tema
 *   polar_min                 — minimum h_score percentage (0-100)
 *   solo_analizados           — "1" to show only analyzed
 *   orden                     — "fecha" or "polarizacion" (default)
 *   offset                    — pagination offset (default 0)
 *   limit                     — items per page (default 10)
 *
 * Returns JSON: { total, items: [...], has_more }
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/layout.php';

/**
 * Etiqueta legible del ámbito (local al endpoint: articulo.php define la suya
 * propia y este script no puede incluir páginas).
 */
function api_radar_ambito_label($ambito) {
    $labels = ['españa' => 'España', 'europa' => 'Europa', 'global' => 'Global'];
    return isset($labels[$ambito]) ? $labels[$ambito] : ucfirst($ambito);
}

header('Content-Type: application/json; charset=utf-8');

$db = prisma_db();

$fecha_desde = isset($_GET['fecha_desde']) ? $_GET['fecha_desde'] : date('Y-m-d');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $_GET['fecha_hasta'] : date('Y-m-d');
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$polar_min = isset($_GET['polar_min']) ? max(0, min(100, intval($_GET['polar_min']))) : 0;
$solo_analizados = isset($_GET['solo_analizados']) && $_GET['solo_analizados'] === '1';
$orden = isset($_GET['orden']) && $_GET['orden'] === 'fecha' ? 'fecha' : 'polarizacion';
$offset = isset($_GET['offset']) ? max(0, intval($_GET['offset'])) : 0;
$limit = isset($_GET['limit']) ? max(1, min(50, intval($_GET['limit']))) : 10;

$B = prisma_base();

// Build query
$where = ['r.fecha >= :fecha_desde', 'r.fecha <= :fecha_hasta'];
$params = [':fecha_desde' => $fecha_desde, ':fecha_hasta' => $fecha_hasta];

if ($polar_min > 0) {
    $where[] = 'r.h_score >= :polar_min';
    $params[':polar_min'] = $polar_min / 100.0;
}

if ($solo_analizados) {
    // "Analizado" para la web = tiene artículo multipostura publicado.
    // Un tema con analizado=1 pero sin articulo_id (rechazado en auditoría)
    // NO es un análisis: no debe figurar como tal.
    $where[] = 'r.articulo_id IS NOT NULL';
}

if ($q !== '') {
    $where[] = 'r.titulo_tema LIKE :q';
    $params[':q'] = '%' . $q . '%';
}

$where_sql = implode(' AND ', $where);

// Count total
$count_sql = "SELECT COUNT(*) FROM radar r WHERE $where_sql";
$count_stmt = $db->prepare($count_sql);
$count_stmt->execute($params);
$total = (int)$count_stmt->fetchColumn();

// Fetch items
$order_sql = $orden === 'fecha' ? 'r.fecha DESC, r.h_score DESC' : 'r.h_score DESC, r.fecha DESC';
$sql = "SELECT r.* FROM radar r WHERE $where_sql ORDER BY $order_sql LIMIT :limit OFFSET :offset";
$stmt = $db->prepare($sql);
foreach ($params as $k => $v) {
    $stmt->bindValue($k, $v);
}
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

// Build response items with rendered HTML
$items = [];
foreach ($rows as $tema) {
    $fuentes = json_decode($tema['fuentes_json'], true);
    if (!$fuentes) $fuentes = [];
    $link = $tema['analizado'] && $tema['articulo_id']
        ? $B . 'articulo.php?id=' . urlencode($tema['articulo_id'])
        : $B . 'articulo.php?radar=' . urlencode($tema['id']);
    $sv = isset($tema['scoring_version']) ? $tema['scoring_version'] : 'v1';
    $m1 = ($sv === 'v2' && $tema['h_cobertura_mutua'] !== null) ? (float)$tema['h_cobertura_mutua'] : (float)$tema['h_asimetria'];
    $m2 = ($sv === 'v2' && $tema['h_framing'] !== null) ? (float)$tema['h_framing'] : (float)$tema['h_divergencia'];
    $rel = isset($tema['relevancia']) ? $tema['relevancia'] : null;
    $fd = isset($tema['framing_divergence']) ? (int)$tema['framing_divergence'] : null;
    $frase = $tema['haiku_frase'] ? $tema['haiku_frase'] : tension_frase_generica($m1, $m2, $rel, $fd);

    // Render fuentes chips
    $fuentes_html = '';
    foreach ($fuentes as $f) {
        $color = cuadrante_color($f['cuadrante']);
        $fuentes_html .= '<span class="postura-chip" style="border-left:3px solid ' . $color . ';padding-left:7px">'
            . htmlspecialchars($f['medio'], ENT_QUOTES, 'UTF-8') . '</span>';
    }

    $items[] = [
        'id' => $tema['id'],
        'link' => $link,
        'titulo' => $tema['titulo_tema'],
        'ambito' => $tema['ambito'],
        'h_score' => (float)$tema['h_score'],
        'h_pct' => round((float)$tema['h_score'] * 100),
        'analizado' => (bool)($tema['analizado'] && $tema['articulo_id']),
        'fecha' => $tema['fecha'],
        'frase' => $frase,
        'circulo_html' => render_circulo_tension((float)$tema['h_score']),
        'fuentes_html' => $fuentes_html,
        'ambito_label' => api_radar_ambito_label($tema['ambito']),
    ];
}

echo json_encode([
    'total' => $total,
    'items' => $items,
    'has_more' => ($offset + $limit) < $total,
], JSON_UNESCAPED_UNICODE);
