<?php
/**
 * PolarPrisma — Tarjeta social (OG) con generación perezosa + caché.
 *
 * Las tarjetas de los artículos se generan al publicar (hook en prisma_publicar)
 * y las sirve Apache directamente desde /og/<id>.png. Este endpoint es la red de
 * seguridad: genera-y-cachea la primera vez SOLO para ids válidos existentes, y
 * cubre los temas del radar sin backfill.
 *
 *   og.php?id=2026-07-30-002   → tarjeta de un artículo
 *   og.php?radar=1234          → tarjeta de un tema del radar
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/og.php';

$id    = isset($_GET['id']) ? trim($_GET['id']) : '';
$radar = isset($_GET['radar']) ? (int)$_GET['radar'] : 0;
$fmt   = (isset($_GET['fmt']) && $_GET['fmt'] === 'sq') ? 'sq' : 'og';   // og=1200x630, sq=1080x1080
$suf   = og_sufijo($fmt);

$ruta = null;

if ($id !== '' && preg_match('/^\d{4}-\d{2}-\d{2}-\d{3}$/', $id)) {
    $ruta = og_ruta($id . $suf);
    if (!is_file($ruta)) og_generar_articulo($id, $fmt);   // devuelve false si el id no existe
} elseif ($radar > 0) {
    $ruta = og_ruta('r' . $radar . $suf);
    if (!is_file($ruta)) og_generar_radar($radar, $fmt);
}

// Fallback a la tarjeta de marca.
if (!$ruta || !is_file($ruta)) {
    $ruta = og_ruta('default' . $suf);
    if (!is_file($ruta)) og_generar_default($fmt);
}

if (!is_file($ruta)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Tarjeta no disponible';
    exit;
}

header('Content-Type: image/png');
header('Cache-Control: public, max-age=86400');
header('Content-Length: ' . filesize($ruta));
readfile($ruta);
