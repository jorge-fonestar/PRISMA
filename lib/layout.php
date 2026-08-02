<?php
/**
 * Prisma — Shared page layout helpers.
 * Avoids repeating header/footer/CSS boilerplate across content pages.
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/theme.php';
require_once __DIR__ . '/../db.php';

// ── Reusable nav/footer primitives ────────────────────────────────────

/**
 * Renders the <header> navigation bar.
 *
 * @param string $active_nav  Key of active nav item: '' (Hoy), 'radar',
 *                            'silencios', 'presentacion'
 * @param array  $overrides   Override labels, e.g. ['' => 'Radar'] renames "Hoy"
 * @return string HTML
 */
function render_nav($active_nav = '', $overrides = array()) {
    $B = prisma_base();
    $nav_items = array(
        ''             => array('Hoy', $B),
        'radar'        => array('Radar', $B . '?vista=radar'),
        'silencios'    => array('Silencios', $B . 'silencios.php'),
        'mapa'         => array('Mapa', $B . 'mapa.php'),
        'presentacion' => array('¿Qué es PolarPrisma?', $B . 'presentacion.php'),
    );
    foreach ($overrides as $key => $label) {
        if (isset($nav_items[$key])) {
            $nav_items[$key][0] = $label;
        }
    }

    $html = '<header role="banner">'
        . '<nav aria-label="Navegación principal">'
        . '<a href="' . $B . '" class="logo" aria-label="PolarPrisma - Inicio">'
        . '<svg class="logo-mark" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<defs><linearGradient id="prismGrad" x1="0%" y1="0%" x2="100%" y2="100%">'
        . '<stop offset="0%" stop-color="#ff4d6d"/><stop offset="25%" stop-color="#f2f24a"/>'
        . '<stop offset="50%" stop-color="#4ade80"/><stop offset="75%" stop-color="#4dc3ff"/>'
        . '<stop offset="100%" stop-color="#a855f7"/>'
        . '</linearGradient></defs>'
        . '<polygon points="16,4 28,26 4,26" fill="none" stroke="url(#prismGrad)" stroke-width="1.8" stroke-linejoin="round"/>'
        . '</svg>'
        . '<span>PolarPrisma</span></a>'
        . '<ul class="nav-links">';
    foreach ($nav_items as $key => $item) {
        $label = $item[0];
        $href  = $item[1];
        $cls = ($active_nav === $key) ? ' class="active"' : '';
        $html .= '<li><a href="' . $href . '"' . $cls . '>' . $label . '</a></li>';
    }
    $html .= '</ul>' . theme_toggle() . '</nav></header>';
    return $html;
}

/**
 * Renders the 4-column footer grid (brand + proyecto + estándar + legal).
 * @return string HTML
 */
function render_footer_grid() {
    $B = prisma_base();
    return '<div class="footer-grid">'
        . '<div class="footer-brand">'
        . '<div class="logo" style="pointer-events:none">'
        . '<svg class="logo-mark" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">'
        . '<polygon points="16,4 28,26 4,26" fill="none" stroke="url(#prismGrad)" stroke-width="1.8" stroke-linejoin="round"/>'
        . '</svg><span>PolarPrisma</span></div>'
        . '<p>Servicio público de información neutral. Sin editorial, sin algoritmo, sin cámaras de eco.</p>'
        . '</div>'
        . '<div><h4>Secciones</h4><ul>'
        . '<li><a href="' . $B . '">Hoy</a></li>'
        . '<li><a href="' . $B . '?vista=radar">Radar (7 días)</a></li>'
        . '<li><a href="' . $B . 'silencios.php">Los silencios de la semana</a></li>'
        . '<li><a href="' . $B . 'mapa.php">Mapa ideológico y de financiación</a></li>'
        . '<li><a href="' . $B . 'archivo.php">Archivo</a></li>'
        . '<li><a href="https://t.me/prismanews_dev" target="_blank" rel="noopener">Canal de Telegram (avisos diarios)</a></li>'
        . '<li><a href="' . $B . 'rss.php">RSS · Análisis</a></li>'
        . '<li><a href="' . $B . 'rss.php?feed=radar">RSS · Radar</a></li>'
        . '</ul></div>'
        . '<div><h4>Proyecto</h4><ul>'
        . '<li><a href="' . $B . 'presentacion.php">¿Qué es PolarPrisma?</a></li>'
        . '<li><a href="' . $B . 'manifiesto.php">Manifiesto</a></li>'
        . '<li><a href="' . $B . 'axiomas.php">Los 11 axiomas</a></li>'
        . '<li><a href="' . $B . 'fuentes.php">Fuentes consultadas</a></li>'
        . '<li><a href="' . $B . 'ia.php">Aviso de IA</a></li>'
        . '</ul></div>'
        . '<div><h4>Legal</h4><ul>'
        . '<li><a href="' . $B . 'aviso-legal.php">Aviso legal</a></li>'
        . '<li><a href="' . $B . 'privacidad.php">Privacidad</a></li>'
        . '<li><a href="' . $B . 'cookies.php">Cookies</a></li>'
        . '</ul></div>'
        . '</div>';
}

/**
 * Renders the footer bottom bar (copyright + AI notice).
 * @return string HTML
 */
function render_footer_bottom() {
    return '<div class="footer-bottom">'
        . '<p>&copy; ' . date('Y') . ' PolarPrisma &middot; Proyecto independiente &middot; CC BY-SA 4.0</p>'
        . '<span class="ai-notice">Contenido generado y auditado por IA</span>'
        . '</div>';
}

// ── Composite page layout (used by simple content pages) ─────────────

function page_header($title, $description = '', $active_nav = '', $wide = false) {
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> — PolarPrisma</title>
  <link rel="icon" type="image/svg+xml" href="<?= prisma_base() ?>favicon.svg">
  <link rel="alternate" type="application/rss+xml" title="PolarPrisma · Análisis" href="<?= prisma_base() ?>rss.php">
  <link rel="alternate" type="application/rss+xml" title="PolarPrisma · Radar de polarización" href="<?= prisma_base() ?>rss.php?feed=radar">
  <?php if ($description): ?><meta name="description" content="<?= htmlspecialchars($description) ?>"><?php endif; ?>
  <?php $og_site = rtrim(prisma_cfg()['site_url'] ?? 'https://polarprisma.org', '/'); ?>
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="PolarPrisma">
  <meta property="og:title" content="<?= htmlspecialchars($title) ?> — PolarPrisma">
  <meta property="og:description" content="<?= htmlspecialchars($description ?: 'Cartografía de la polarización informativa') ?>">
  <meta property="og:image" content="<?= $og_site ?>/og/default.png">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:image" content="<?= $og_site ?>/og/default.png">
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#0a0a12">
  <?= theme_head_script() ?>
  <?= theme_css() ?>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; }
    body {
      margin: 0; font-family: 'Charter', 'Iowan Old Style', 'Palatino Linotype', Georgia, serif;
      font-size: 18px; line-height: 1.65; color: var(--text); background: var(--bg);
      -webkit-font-smoothing: antialiased;
    }
    h1, h2, h3 {
      font-family: 'Canela', 'Playfair Display', 'Didot', Georgia, serif;
      font-weight: 500; letter-spacing: -0.02em; line-height: 1.2; margin: 0 0 0.6em 0;
    }
    h1 { font-size: clamp(1.8rem, 4vw, 2.6rem); }
    h2 { font-size: clamp(1.3rem, 2.5vw, 1.7rem); margin-top: 2.5rem; }
    h3 { font-size: clamp(1.05rem, 1.5vw, 1.2rem); margin-top: 1.5rem; }
    p { margin: 0 0 1.1em 0; }
    a { color: var(--link); text-decoration: none; }
    a:hover { color: var(--text); }
    ul, ol { padding-left: 1.5rem; }
    li { margin-bottom: 0.4rem; color: var(--text-muted); }
    strong { color: var(--text); }
    /* 820px para páginas de lectura; las de listado (wide) usan 1100px como el index */
    .container { width: 100%; max-width: <?= $wide ? '1100px' : '820px' ?>; margin: 0 auto; padding: 0 24px; }
    .eyebrow {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
      letter-spacing: 0.18em; text-transform: uppercase; color: var(--text-faint); margin-bottom: 0.8rem;
    }
    header[role="banner"] {
      position: fixed; top: 0; left: 0; right: 0; z-index: 100;
      background: var(--bg-header); backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
    }
    header nav {
      max-width: 1100px; margin: 0 auto; padding: 16px 24px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .logo {
      display: flex; align-items: center; gap: 10px; color: var(--text); text-decoration: none;
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
      font-size: 1.35rem; font-weight: 500;
    }
    .logo-mark { width: 28px; height: 28px; }
    header .nav-links { display: flex; gap: 28px; list-style: none; margin: 0; padding: 0; }
    header .nav-links a {
      color: var(--text-muted); text-decoration: none;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.92rem; transition: color 0.15s;
    }
    header .nav-links a:hover { color: var(--text); }
    header .nav-links a.active { color: var(--accent); }
    /* Móvil: el menú se mantiene visible con scroll horizontal (antes desaparecía) */
    @media (max-width: 640px) {
      header nav { flex-wrap: wrap; padding: 12px 16px; gap: 6px; }
      header .nav-links {
        order: 3; width: 100%; gap: 18px; overflow-x: auto;
        -webkit-overflow-scrolling: touch; scrollbar-width: none; padding-bottom: 2px;
      }
      header .nav-links::-webkit-scrollbar { display: none; }
      header .nav-links a { white-space: nowrap; font-size: 0.88rem; }
    }
    main { padding-top: 6.5rem; min-height: 80vh; }
    @media (min-width: 641px) { main { padding-top: 5rem; } }
    .page-top { padding: 3rem 0 2rem 0; border-bottom: 1px solid var(--border); margin-bottom: 2rem; }
    .page-top h1 { color: var(--text); }
    .page-top p { color: var(--text-muted); max-width: 640px; }
    .content { padding-bottom: 4rem; color: var(--text-muted); }
    .content h2 { color: var(--text); }
    .content h3 { color: var(--text); }
    .content strong { color: var(--text); }
    table { width: 100%; border-collapse: collapse; margin: 1rem 0; font-size: 0.95rem; }
    th { text-align: left; padding: 0.6rem 0.8rem; border-bottom: 2px solid var(--border-card); color: var(--text); font-size: 0.85rem; }
    td { padding: 0.6rem 0.8rem; border-bottom: 1px solid var(--border); color: var(--text-muted); }
    .card {
      padding: 1.5rem; border: 1px solid var(--border-card); border-radius: 6px;
      background: var(--bg-card); margin: 1rem 0;
    }
    footer[role="contentinfo"] {
      padding: 3rem 0 2rem 0; border-top: 1px solid var(--border); background: var(--bg-footer);
    }
    .footer-grid { display: grid; grid-template-columns: 2fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 2rem; }
    @media (max-width: 720px) { .footer-grid { grid-template-columns: 1fr 1fr; gap: 24px; } }
    .footer-brand p { color: var(--text-faint); font-size: 0.9rem; margin-top: 0.8rem; max-width: 280px; }
    footer h4 {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
      letter-spacing: 0.14em; text-transform: uppercase; color: var(--text-muted); margin: 0 0 0.8rem 0;
    }
    footer ul { list-style: none; padding: 0; margin: 0; }
    footer li { margin-bottom: 0.5rem; }
    footer a { color: var(--text-faint); font-size: 0.88rem; }
    footer a:hover { color: var(--text); }
    .footer-bottom {
      padding-top: 1.5rem; border-top: 1px solid var(--border);
      display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;
    }
    .footer-bottom p { color: var(--text-faintest); font-size: 0.82rem; margin: 0; }
    .ai-notice {
      display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px;
      background: var(--accent-bg); border: 1px solid var(--accent-border); border-radius: 999px;
      color: var(--accent); font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 500;
    }
    .ai-notice::before { content: ""; width: 5px; height: 5px; border-radius: 50%; background: var(--accent); }
    :focus-visible { outline: 3px solid var(--accent); outline-offset: 3px; }
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after { animation-duration: 0.01ms !important; transition-duration: 0.01ms !important; }
    }
  </style>
</head>
<body>
  <?= render_nav($active_nav) ?>
  <main>
    <div class="container">
<?php
}

function page_footer() {
?>
    </div>
  </main>
  <footer role="contentinfo">
    <div class="container">
      <?= render_footer_grid() ?>
      <?= render_footer_bottom() ?>
    </div>
  </footer>
  <?= theme_js() ?>
</body>
</html>
<?php
}

// ── Tension UI Helpers ───────────────────────────────────────────────

define('PRISMA_CUADRANTE_COLORES', [
    'izquierda-populista' => '#ff4d6d',
    'izquierda'           => '#ff6b81',
    'centro-izquierda'    => '#ff9e4d',
    'centro'              => '#f2f24a',
    'centro-derecha'      => '#4dc3ff',
    'derecha'             => '#4d9eff',
    'derecha-populista'   => '#a855f7',
]);

/**
 * Returns the color for a tension score.
 */
function tension_color(float $score): string {
    if ($score >= 0.75) return '#ff4d6d';
    if ($score >= 0.50) return '#ff9e4d';
    if ($score >= 0.25) return '#f2f24a';
    return 'rgba(255,255,255,0.3)';
}

/**
 * Renders the SVG tension circle.
 *
 * @param float $score 0.0 to 1.0
 * @param int $size Pixel size (default 36)
 * @return string HTML
 */
function render_circulo_tension(float $score, int $size = 36): string {
    $pct = round($score * 100);
    $color = tension_color($score);
    $r = round($size * 15 / 36);
    $circum = round(2 * 3.14159 * $r, 1);
    $offset = round($circum * (1 - $score), 1);
    $sw = round($size * 3 / 36, 1);
    $cx = round($size / 2);
    $fs = round($size * 0.018, 3);

    return '<div style="position:relative;width:' . $size . 'px;height:' . $size . 'px;flex-shrink:0">'
        . '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">'
        . '<circle cx="' . $cx . '" cy="' . $cx . '" r="' . $r . '" fill="none" stroke="rgba(255,255,255,0.08)" stroke-width="' . $sw . '"/>'
        . '<circle cx="' . $cx . '" cy="' . $cx . '" r="' . $r . '" fill="none" stroke="' . $color . '" stroke-width="' . $sw . '"'
        . ' stroke-dasharray="' . $circum . '" stroke-dashoffset="' . $offset . '"'
        . ' transform="rotate(-90 ' . $cx . ' ' . $cx . ')" stroke-linecap="round"/>'
        . '</svg>'
        . '<span style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;'
        . 'font-family:Inter,Arial,sans-serif;font-size:' . $fs . 'em;font-weight:700;color:' . $color . '">'
        . $pct . '</span></div>';
}

/**
 * Renders the three tension breakdown bars.
 *
 * @param float $asimetria 0.0 to 1.0
 * @param float $divergencia 0.0 to 1.0
 * @param float $varianza 0.0 to 1.0
 * @param float $h_score For color selection
 * @return string HTML
 */
function render_barras_tension(float $asimetria, float $divergencia, float $varianza, float $h_score, $scoring_version = null): string {
    $color = tension_color($h_score);

    if ($scoring_version === 'v2') {
        $signals = [
            ['Cobertura mutua', $asimetria],
            ['Divergencia de framing', $divergencia],
            ['Silencio editorial', $varianza],
        ];
    } else {
        $signals = [
            ['Asimetría cobertura', $asimetria],
            ['Divergencia léxica', $divergencia],
            ['Varianza espectro', $varianza],
        ];
    }

    $html = '<div style="display:flex;flex-direction:column;gap:6px">';
    foreach ($signals as list($label, $val)) {
        $pct = round($val * 100);
        $html .= '<div style="display:flex;align-items:center;gap:8px">'
            . '<span style="font-family:Inter,Arial,sans-serif;font-size:0.72em;color:var(--text-faint);width:150px;flex-shrink:0">' . $label . '</span>'
            . '<div style="flex:1;height:6px;background:rgba(255,255,255,0.06);border-radius:3px;overflow:hidden">'
            . '<div style="width:' . $pct . '%;height:100%;background:' . $color . ';border-radius:3px"></div></div>'
            . '<span style="font-family:Inter,Arial,sans-serif;font-size:0.68em;color:var(--text-faint);width:32px;text-align:right">' . $pct . '%</span>'
            . '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Generates a generic tension phrase for topics below threshold (no Haiku frase).
 */
function tension_frase_generica(float $asimetria, float $divergencia, $relevancia = null, $fd = null): string {
    if ($relevancia === 'descartar') {
        return 'Tema sin carga ideológica detectada';
    }
    if ($fd !== null) {
        if ($fd == 0) return 'Cobertura insuficiente para medir divergencia';
        if ($fd == 1) return 'Diferencias menores de énfasis entre fuentes';
        if ($fd >= 2 && $asimetria < 0.3) return 'Framing divergente con cobertura limitada';
    }
    // Legacy fallback
    if ($asimetria <= $divergencia) {
        return 'Cobertura equilibrada entre cuadrantes';
    }
    return 'Las fuentes coinciden en vocabulario y enfoque';
}

/**
 * Returns color for a quadrant.
 */
function cuadrante_color(string $cuadrante): string {
    return PRISMA_CUADRANTE_COLORES[$cuadrante] ?? 'var(--text-faint)';
}

/**
 * Marcador "quién lo contó · quién calló": los 7 cuadrantes del espectro, con
 * relleno de su color quien CUBRIÓ y atenuado con ✕ quien CALLÓ. Mismo lenguaje
 * visual que las tarjetas sociales. El % (color semáforo) indica la divergencia.
 *
 * @param string|null $fuentes_json  fuentes_json del radar
 * @param float       $h_score       0..1 (índice de polarización → chip de divergencia)
 * @param string      $nota          HTML opcional que se añade dentro del bloque
 */
function render_marcador_silencios($fuentes_json, float $h_score = 0.0, string $nota = ''): string {
    $fuentes = json_decode((string)$fuentes_json, true);
    if (!is_array($fuentes)) $fuentes = array();
    $cubiertos = array();
    foreach ($fuentes as $f) {
        if (!empty($f['cuadrante'])) $cubiertos[$f['cuadrante']] = true;
    }
    $orden = array_keys(PRISMA_CUADRANTE_COLORES); // izquierda → derecha (7)
    $n_callaron = 0;
    $segs = '';
    foreach ($orden as $c) {
        $etq = ucfirst(str_replace('-', ' ', $c));
        if (!empty($cubiertos[$c])) {
            $segs .= '<div title="' . htmlspecialchars($etq) . ' · cubrió" style="flex:1;height:40px;border-radius:5px;background:' . cuadrante_color($c) . '"></div>';
        } else {
            $n_callaron++;
            $segs .= '<div title="' . htmlspecialchars($etq) . ' · calló" style="flex:1;height:40px;border-radius:5px;background:rgba(255,255,255,0.05);border:1px solid var(--border-card);display:flex;align-items:center;justify-content:center;color:#5a5a6a;font-size:0.95rem">&#10005;</div>';
        }
    }
    $pct = (int)round($h_score * 100);
    $sem = $pct >= 60 ? '#ff4d6d' : ($pct >= 45 ? '#ff9e4d' : '#f2f24a');

    $h  = '<div style="margin-bottom:2rem">';
    $h .= '<div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;margin-bottom:0.7rem">';
    $h .= '<p class="section-label" style="margin:0">Quién lo contó · quién calló</p>';
    $h .= '<span title="Índice de polarización" style="font-family:\'Inter\',Arial,sans-serif;font-size:0.78rem;color:' . $sem . ';border:1px solid ' . $sem . ';border-radius:999px;padding:2px 11px">' . $pct . '% de divergencia</span>';
    $h .= '</div>';
    $h .= '<div style="display:flex;gap:6px">' . $segs . '</div>';
    $h .= '<div style="display:flex;justify-content:space-between;margin-top:0.5rem;font-family:\'Inter\',Arial,sans-serif;font-size:0.72rem;letter-spacing:0.05em;color:var(--text-faint)"><span>IZQUIERDA</span><span>CENTRO</span><span>DERECHA</span></div>';
    if ($n_callaron > 0) {
        $h .= '<p style="margin:0.7rem 0 0;font-size:0.85rem;color:var(--text-muted)"><strong style="color:var(--text)">' . $n_callaron . '</strong> de 7 posiciones del espectro no cubrieron este tema.</p>';
    }
    if ($nota !== '') $h .= $nota;
    $h .= '</div>';
    return $h;
}
