<?php
/**
 * PolarPrisma — Endpoints RSS (compatibles con Feedly y cualquier lector).
 *
 *   /rss.php               → Análisis (artículos multipostura publicados)
 *   /rss.php?feed=radar    → Radar de polarización (temas de los últimos 7 días)
 *
 * RSS 2.0 con atom:link self. URLs absolutas vía site_url. El autodiscovery
 * (<link rel="alternate">) va en el <head> de las páginas, así que pegar
 * https://polarprisma.org en Feedly encuentra el feed automáticamente.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

$cfg  = prisma_cfg();
$site = rtrim(isset($cfg['site_url']) ? $cfg['site_url'] : 'https://polarprisma.org', '/');
$db   = prisma_db();

$feed = (isset($_GET['feed']) && $_GET['feed'] === 'radar') ? 'radar' : 'analisis';

/** Escapado seguro para XML. */
function rss_esc($s) {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

$items = array();

if ($feed === 'radar') {
    $self       = $site . '/rss.php?feed=radar';
    $feed_title = 'PolarPrisma · Radar de polarización';
    $feed_desc  = 'Las noticias donde más divergen los medios de distinto signo, con su índice de polarización informativa. Últimos 7 días.';
    $feed_link  = $site . '/?vista=radar';

    $umbral = isset($cfg['telegram_digest_umbral']) ? (float)$cfg['telegram_digest_umbral'] : 0.35;
    $stmt = $db->prepare("SELECT id, fecha, created_at, ambito, titulo_tema, h_score, resumen_neutral, haiku_frase, analizado, articulo_id
        FROM radar
        WHERE fecha >= date('now','-7 days') AND relevancia IN ('alta','media') AND h_score >= :u
        ORDER BY fecha DESC, h_score DESC LIMIT 60");
    $stmt->execute(array(':u' => $umbral));

    foreach ($stmt->fetchAll() as $r) {
        $pct = (int)round($r['h_score'] * 100);
        $analizada = ($r['analizado'] && $r['articulo_id']);
        $link = $analizada
            ? $site . '/articulo.php?id=' . rawurlencode($r['articulo_id'])
            : $site . '/articulo.php?radar=' . (int)$r['id'];
        $desc = $r['resumen_neutral'] ? $r['resumen_neutral'] : ($r['haiku_frase'] ?: '');
        $desc = trim("Polarización $pct%. " . $desc);
        $ts = $r['created_at'] ? strtotime($r['created_at']) : strtotime($r['fecha']);
        $items[] = array(
            'title' => "[$pct%] " . $r['titulo_tema'],
            'link'  => $link,
            'guid'  => $site . '/articulo.php?radar=' . (int)$r['id'],
            'date'  => date('r', $ts ?: time()),
            'desc'  => $desc,
            'cat'   => ucfirst($r['ambito']),
        );
    }
} else {
    $self       = $site . '/rss.php';
    $feed_title = 'PolarPrisma · Análisis';
    $feed_desc  = 'Análisis multipostura de las noticias más polarizadas, auditados contra los 11 axiomas de neutralidad Moral Core.';
    $feed_link  = $site . '/';

    $rows = $db->query("SELECT id, fecha_publicacion, ambito, titular_neutral, resumen, veredicto, fuentes_total
        FROM articulos ORDER BY fecha_publicacion DESC LIMIT 30")->fetchAll();

    foreach ($rows as $r) {
        $link = $site . '/articulo.php?id=' . rawurlencode($r['id']);
        $desc = trim((string)$r['resumen']);
        if (!empty($r['veredicto'])) {
            $desc .= " (Auditoría Moral Core: " . $r['veredicto']
                   . (isset($r['fuentes_total']) ? ", {$r['fuentes_total']} fuentes" : '') . ".)";
        }
        $items[] = array(
            'title' => $r['titular_neutral'],
            'link'  => $link,
            'guid'  => $link,
            'date'  => date('r', strtotime($r['fecha_publicacion']) ?: time()),
            'desc'  => $desc,
            'cat'   => ucfirst($r['ambito']),
        );
    }
}

$last_build = !empty($items) ? $items[0]['date'] : date('r');

header('Content-Type: application/rss+xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= rss_esc($feed_title) ?></title>
  <link><?= rss_esc($feed_link) ?></link>
  <atom:link href="<?= rss_esc($self) ?>" rel="self" type="application/rss+xml" />
  <description><?= rss_esc($feed_desc) ?></description>
  <language>es-ES</language>
  <lastBuildDate><?= rss_esc($last_build) ?></lastBuildDate>
  <ttl>120</ttl>
<?php foreach ($items as $it): ?>
  <item>
    <title><?= rss_esc($it['title']) ?></title>
    <link><?= rss_esc($it['link']) ?></link>
    <guid isPermaLink="false"><?= rss_esc($it['guid']) ?></guid>
    <pubDate><?= rss_esc($it['date']) ?></pubDate>
    <category><?= rss_esc($it['cat']) ?></category>
    <description><?= rss_esc($it['desc']) ?></description>
  </item>
<?php endforeach; ?>
</channel>
</rss>
