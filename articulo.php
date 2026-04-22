<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/theme.php';
require_once __DIR__ . '/lib/layout.php';

$id = $_GET['id'] ?? '';
$art = null;

if ($id) {
    $db = prisma_db();
    $stmt = $db->prepare('SELECT payload FROM articulos WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    if ($row) {
        $art = json_decode($row['payload'], true);
    }
}

// Radar mode: articulo.php?radar=N
$radar = null;
$radar_id = $_GET['radar'] ?? '';

if ($radar_id) {
    $db = prisma_db();
    $stmt = $db->prepare('SELECT * FROM radar WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $radar_id]);
    $radar = $stmt->fetch();

    if ($radar && $radar['articulo_id']) {
        // Redirect to analyzed article
        header('Location: ' . prisma_base() . 'articulo.php?id=' . urlencode($radar['articulo_id']), true, 301);
        exit;
    }
}

// Tension data for analyzed articles
$tension_data = null;
if ($art) {
    $db = prisma_db();
    $stmt = $db->prepare('SELECT * FROM radar WHERE articulo_id = :aid LIMIT 1');
    $stmt->execute([':aid' => $id]);
    $tension_data = $stmt->fetch();
}

if (!$art && !$radar) {
    http_response_code(404);
    $page_title = 'Articulo no encontrado — Prisma';
} elseif ($radar) {
    $page_title = htmlspecialchars($radar['titulo_tema']) . ' — Radar Prisma';
} else {
    $page_title = htmlspecialchars($art['titular_neutral']) . ' — Prisma';
}

function format_fecha($iso) {
    $ts = strtotime($iso);
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    return date('j', $ts) . ' de ' . $meses[date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

function ambito_label($ambito) {
    $map = ['españa' => 'España', 'europa' => 'Europa', 'global' => 'Global'];
    return $map[$ambito] ?? ucfirst($ambito);
}

$position_colors = ['#ff4d6d', '#f2f24a', '#4dc3ff', '#4ade80', '#a855f7', '#ff9e4d'];
$B = prisma_base();

$axiom_names = [
    'A1'  => 'Pluralidad de posturas — ≥3 posturas distintas',
    'A2'  => 'Pluralidad de fuentes — múltiples cuadrantes ideológicos',
    'A3'  => 'Simetría de extensión — ninguna postura >50% ni <15%',
    'A4'  => 'Simetría léxica — mismo registro emocional en todas',
    'A5'  => 'Atribución verificable — fuente concreta para cada dato',
    'A6'  => 'Distinción hecho/opinión — hechos vs. interpretaciones',
    'A7'  => 'Sin conclusión prescriptiva — no dice qué pensar',
    'A8'  => 'Transparencia de límites — incertidumbre declarada',
    'A9'  => 'Sin omisión crítica — todas las posturas relevantes',
    'A10' => 'Coherencia con fuentes — anti-alucinación',
    'A11' => 'Sin sesgo geopolítico — neutral entre bloques',
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $page_title ?></title>
  <?php if ($art): ?>
  <meta name="description" content="<?= htmlspecialchars(mb_substr($art['resumen'], 0, 160)) ?>">
  <meta name="robots" content="index, follow">
  <?php elseif ($radar): ?>
  <?php
    $r_sv = isset($radar['scoring_version']) ? $radar['scoring_version'] : 'v1';
    $r_m1 = ($r_sv === 'v2' && $radar['h_cobertura_mutua'] !== null) ? (float)$radar['h_cobertura_mutua'] : (float)$radar['h_asimetria'];
    $r_m2 = ($r_sv === 'v2' && $radar['h_framing'] !== null) ? (float)$radar['h_framing'] : (float)$radar['h_divergencia'];
    $r_rel = isset($radar['relevancia']) ? $radar['relevancia'] : null;
    $r_fd = isset($radar['framing_divergence']) ? (int)$radar['framing_divergence'] : null;
  ?>
  <meta name="description" content="Tema detectado el <?= htmlspecialchars($radar['fecha']) ?> con <?= round($radar['h_score'] * 100) ?>% de polarización informativa. <?= htmlspecialchars($radar['haiku_frase'] ?: tension_frase_generica($r_m1, $r_m2, $r_rel, $r_fd)) ?>">
  <meta name="robots" content="noindex, follow">
  <?php else: ?>
  <meta name="robots" content="noindex, follow">
  <?php endif; ?>
  <meta name="theme-color" content="#0a0a12">
  <?= theme_head_script() ?>
  <?= theme_css() ?>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
    body {
      margin: 0;
      font-family: 'Charter', 'Iowan Old Style', 'Palatino Linotype', Georgia, serif;
      font-size: 18px; line-height: 1.65; color: var(--text); background: var(--bg);
      -webkit-font-smoothing: antialiased; -moz-osx-font-smoothing: grayscale;
      overflow-x: hidden;
    }
    .skip-link {
      position: absolute; top: -40px; left: 0;
      padding: 12px 20px; background: #fff; color: #0a0a12;
      text-decoration: none; z-index: 1000; font-weight: 600; transition: top 0.2s;
    }
    .skip-link:focus { top: 0; }
    :focus-visible { outline: 3px solid #f2f24a; outline-offset: 3px; border-radius: 2px; }
    h1, h2, h3, h4 {
      font-family: 'Canela', 'Playfair Display', 'Didot', Georgia, serif;
      font-weight: 500; letter-spacing: -0.02em; line-height: 1.2; margin: 0 0 0.6em 0;
    }
    h1 { font-size: clamp(1.8rem, 4vw, 2.8rem); line-height: 1.18; }
    h2 { font-size: clamp(1.3rem, 2.5vw, 1.8rem); }
    h3 { font-size: clamp(1.1rem, 2vw, 1.4rem); }
    p { margin: 0 0 1.2em 0; }
    a { color: var(--link); }
    a:hover { color: var(--text); }
    .eyebrow {
      font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
      font-size: 0.72rem; font-weight: 600; letter-spacing: 0.18em;
      text-transform: uppercase; color: var(--text-faint); margin-bottom: 1rem;
    }
    .container { width: 100%; max-width: 820px; margin: 0 auto; padding: 0 24px; }

    /* Header */
    header[role="banner"] {
      position: fixed; top: 0; left: 0; right: 0; z-index: 100;
      background: var(--bg-header);
      backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
      border-bottom: 1px solid var(--border);
    }
    header nav {
      max-width: 1100px; margin: 0 auto; padding: 16px 24px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .logo {
      display: flex; align-items: center; gap: 10px; color: var(--text); text-decoration: none;
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
      font-size: 1.35rem; font-weight: 500; letter-spacing: -0.01em;
    }
    .logo-mark { width: 28px; height: 28px; flex-shrink: 0; }
    header .nav-links {
      display: flex; gap: 28px; list-style: none; margin: 0; padding: 0;
    }
    header .nav-links a {
      color: var(--text-muted); text-decoration: none;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.92rem; transition: color 0.15s;
    }
    header .nav-links a:hover { color: var(--text); }
    header .nav-links a.active { color: var(--accent); }
    @media (max-width: 640px) { header .nav-links { display: none; } }

    /* Article */
    main { padding-top: 5rem; min-height: 100vh; }
    .article-header { padding: 3rem 0 2rem 0; border-bottom: 1px solid var(--border-card); margin-bottom: 2.5rem; }
    .article-meta {
      display: flex; align-items: center; gap: 16px; margin-bottom: 1.5rem; flex-wrap: wrap;
    }
    .article-date {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem;
      letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-faint);
    }
    .badge-ambito {
      display: inline-block; padding: 3px 10px;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      border-radius: 999px; border: 1px solid rgba(255,255,255,0.15); color: var(--text-muted);
    }
    .badge-apto {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 3px 10px; background: rgba(74, 222, 128, 0.12); color: var(--green);
      border: 1px solid rgba(74, 222, 128, 0.3); border-radius: 999px;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
    }
    .badge-apto::before {
      content: ""; width: 6px; height: 6px; border-radius: 50%;
      background: #4ade80; box-shadow: 0 0 8px #4ade80;
    }
    .article-header h1 { color: var(--text); }

    /* Resumen */
    .section-label {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
      letter-spacing: 0.18em; text-transform: uppercase; color: var(--accent);
      margin: 3rem 0 1rem 0; padding-bottom: 0.5rem;
      border-bottom: 1px solid rgba(242, 242, 74, 0.2);
    }
    .resumen { color: var(--text-muted); font-size: 1.05rem; line-height: 1.7; }

    /* Posturas */
    .posturas-grid { display: flex; flex-direction: column; gap: 12px; }
    .postura-etiqueta {
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
      font-size: 1.15rem; font-weight: 500; color: var(--text); margin-bottom: 0.3rem;
    }
    .postura-defensores {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem;
      color: var(--text-muted); margin-bottom: 0; line-height: 1.5;
    }
    .postura-argumentos { list-style: none; padding: 0; margin: 0 0 1.2rem 0; }
    .postura-argumentos li {
      padding: 0.4rem 0 0.4rem 1.2rem; position: relative;
      color: var(--text-muted); font-size: 0.95rem; line-height: 1.55;
    }
    .postura-argumentos li::before {
      content: ""; position: absolute; left: 0; top: 0.85rem;
      width: 6px; height: 6px; border-radius: 50%; background: rgba(255,255,255,0.2);
    }
    .postura-fuentes { display: flex; flex-direction: column; gap: 6px; }
    .fuente-link {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem;
      color: var(--link); text-decoration: none; transition: color 0.15s;
      display: flex; align-items: baseline; gap: 6px;
    }
    .fuente-link:hover { color: var(--text); }
    .fuente-medio {
      color: var(--text-faint); font-size: 0.75rem; flex-shrink: 0;
    }
    .fuente-cuadrante {
      display: inline-block; padding: 1px 6px; margin-left: 4px;
      font-size: 0.68rem; border-radius: 3px;
      background: rgba(255,255,255,0.06); color: var(--text-muted);
    }

    /* Ausencias */
    .ausencias-list { list-style: none; padding: 0; margin: 0; }
    .ausencias-list li {
      padding: 1rem 0; border-bottom: 1px solid var(--border);
      color: var(--text-muted); font-size: 0.95rem; line-height: 1.6;
    }
    .ausencias-list li:last-child { border-bottom: none; }

    /* Preguntas */
    .preguntas-list { list-style: none; padding: 0; margin: 0; counter-reset: pregunta; }
    .preguntas-list li {
      padding: 1.2rem 0 1.2rem 3rem; position: relative;
      border-bottom: 1px solid var(--border);
      color: var(--text); font-size: 1.05rem; line-height: 1.6;
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
    }
    .preguntas-list li:last-child { border-bottom: none; }
    .preguntas-list li::before {
      counter-increment: pregunta;
      content: counter(pregunta);
      position: absolute; left: 0; top: 1.2rem;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem; font-weight: 700;
      color: var(--accent); letter-spacing: 0.1em;
    }

    /* Audit bar (compact, top of article) */
    .audit-bar {
      display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
      padding: 1rem 1.5rem; margin-bottom: 2.5rem;
      border: 1px solid rgba(74, 222, 128, 0.2); border-radius: 6px;
      background: rgba(74, 222, 128, 0.04);
    }
    .audit-bar.audit-fail {
      border-color: rgba(255, 77, 109, 0.2);
      background: rgba(255, 77, 109, 0.04);
    }
    .audit-bar .audit-score {
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
      font-size: 2.2rem; font-weight: 500; color: var(--green);
      line-height: 1; letter-spacing: -0.02em;
    }
    .audit-bar.audit-fail .audit-score { color: var(--red); }
    .audit-bar .audit-info {
      display: flex; flex-direction: column; gap: 2px;
    }
    .audit-bar .audit-label {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
      letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-faint);
    }
    .audit-bar .audit-axioms {
      display: flex; flex-wrap: wrap; gap: 5px;
    }
    .axiom-dot {
      width: 18px; height: 18px; border-radius: 3px;
      display: inline-flex; align-items: center; justify-content: center;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.58rem; font-weight: 700;
      position: relative; cursor: help;
    }
    .axiom-dot.pass { background: rgba(74, 222, 128, 0.15); color: var(--green); }
    .axiom-dot.fail { background: rgba(255, 77, 109, 0.15); color: var(--red); }
    .axiom-dot .axiom-tip {
      display: none; position: absolute; bottom: calc(100% + 8px); left: 50%;
      transform: translateX(-50%); white-space: nowrap;
      padding: 6px 12px; border-radius: 4px;
      background: #1a1a2e; border: 1px solid rgba(255,255,255,0.12);
      color: var(--text); font-size: 0.72rem; font-weight: 500;
      letter-spacing: 0; text-transform: none;
      box-shadow: 0 4px 16px rgba(0,0,0,0.4); z-index: 10;
      pointer-events: none;
    }
    .axiom-dot .axiom-tip::after {
      content: ""; position: absolute; top: 100%; left: 50%;
      transform: translateX(-50%);
      border: 5px solid transparent; border-top-color: #1a1a2e;
    }
    .axiom-dot:hover .axiom-tip { display: block; }
    @media (max-width: 640px) {
      .axiom-dot .axiom-tip { white-space: normal; width: 200px; left: 0; transform: none; }
      .axiom-dot .axiom-tip::after { left: 9px; transform: none; }
    }
    .audit-bar .audit-meta {
      margin-left: auto;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.75rem; color: var(--text-faint);
      display: flex; flex-direction: column; align-items: flex-end; gap: 2px;
    }

    /* Posturas collapsible */
    .postura-card { border-left: 4px solid; border-radius: 0 4px 4px 0; background: var(--bg-card); }
    .postura-summary {
      padding: 1.2rem 1.5rem; cursor: pointer; list-style: none;
      display: flex; align-items: flex-start; justify-content: space-between; gap: 12px;
    }
    .postura-summary::-webkit-details-marker { display: none; }
    .postura-summary::after {
      content: "+"; flex-shrink: 0; margin-top: 0.1em;
      font-family: 'Inter', Arial, sans-serif; font-size: 1.2rem; font-weight: 300;
      color: rgba(255,255,255,0.3); transition: transform 0.2s;
    }
    details.postura-card[open] .postura-summary::after { transform: rotate(45deg); color: var(--accent); }
    .postura-summary:hover::after { color: var(--text); }
    .postura-body { padding: 0 1.5rem 1.5rem 1.5rem; }

    /* Back link */
    .back-link {
      display: inline-flex; align-items: center; gap: 6px;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.88rem;
      color: var(--text-muted); text-decoration: none; transition: color 0.15s;
      margin-bottom: 1rem;
    }
    .back-link:hover { color: var(--text); }

    /* Fuentes total */
    .fuentes-total {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.85rem;
      color: var(--text-faint); margin-top: 1rem;
    }

    /* Footer */
    footer[role="contentinfo"] {
      padding: 3rem 0 2rem 0; margin-top: 4rem;
      border-top: 1px solid var(--border); background: var(--bg-footer);
    }
    .footer-bottom {
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 16px;
    }
    .footer-bottom p { color: var(--text-faintest); font-size: 0.85rem; margin: 0; }
    .ai-notice {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 6px 14px; background: rgba(242, 242, 74, 0.08);
      border: 1px solid rgba(242, 242, 74, 0.2); border-radius: 999px;
      color: var(--accent); font-family: 'Inter', Arial, sans-serif;
      font-size: 0.78rem; font-weight: 500;
    }
    .ai-notice::before {
      content: ""; width: 6px; height: 6px; border-radius: 50%;
      background: #f2f24a; box-shadow: 0 0 6px #f2f24a;
    }

    /* 404 */
    .not-found { text-align: center; padding: 6rem 2rem; }
    .not-found h1 { color: var(--text); }
    .not-found p { color: var(--text-muted); }

    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: 0.01ms !important; transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
      }
    }
  </style>
</head>
<body>
  <a href="#main-content" class="skip-link">Saltar al contenido principal</a>

  <?= render_nav() ?>

  <main id="main-content" role="main">
    <div class="container">

    <?php if ($art): ?>

      <a href="<?= $B ?>" class="back-link" style="margin-top: 3rem; display: inline-flex;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Todas las noticias
      </a>

      <!-- Header -->
      <div class="article-header">
        <div class="article-meta">
          <span class="article-date"><?= format_fecha($art['fecha_publicacion']) ?></span>
          <span class="badge-ambito"><?= htmlspecialchars(ambito_label($art['ambito'])) ?></span>
          <?php if ($tension_data): ?>
            <?= render_circulo_tension($tension_data['h_score']) ?>
            <span style="font-family:'Inter',Arial,sans-serif;font-size:0.72rem;font-weight:700;color:<?= tension_color($tension_data['h_score']) ?>"><?= round($tension_data['h_score'] * 100) ?>% polarización</span>
          <?php endif; ?>
        </div>
        <h1><?= htmlspecialchars($art['titular_neutral']) ?></h1>
      </div>

      <!-- Auditoria Moral Core (compact bar) -->
      <?php if (!empty($art['auditoria_moralcore'])):
        $audit = $art['auditoria_moralcore'];
        $is_apto = ($audit['veredicto'] ?? '') === 'APTO';
      ?>
        <div class="audit-bar<?= $is_apto ? '' : ' audit-fail' ?>">
          <?php if (isset($audit['puntuacion'])): ?>
            <div class="audit-score"><?= number_format($audit['puntuacion'] * 100, 0) ?>%</div>
          <?php endif; ?>
          <div class="audit-info">
            <span class="audit-label">Moral Core · <?= htmlspecialchars($audit['veredicto']) ?></span>
            <?php if (!empty($audit['axiomas_detalle'])): ?>
              <div class="audit-axioms">
                <?php foreach ($audit['axiomas_detalle'] as $axiom => $pass): ?>
                  <?php $tip = ($axiom_names[$axiom] ?? $axiom) . ' — ' . ($pass ? '✓ Cumple' : '✗ No cumple'); ?>
                  <span class="axiom-dot <?= $pass ? 'pass' : 'fail' ?>"><?= preg_replace('/^A/', '', $axiom) ?><span class="axiom-tip"><?= htmlspecialchars($tip) ?></span></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="audit-meta">
            <?php if (isset($audit['version_estandar'])): ?>
              <span><?= htmlspecialchars($audit['version_estandar']) ?></span>
            <?php endif; ?>
            <?php if (isset($art['fuentes_consultadas_total'])): ?>
              <span><?= (int)$art['fuentes_consultadas_total'] ?> fuentes</span>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>

      <!-- Polarización informativa -->
      <?php if ($tension_data):
        $td_sv = isset($tension_data['scoring_version']) ? $tension_data['scoring_version'] : 'v1';
        $td_m1 = ($td_sv === 'v2' && $tension_data['h_cobertura_mutua'] !== null) ? (float)$tension_data['h_cobertura_mutua'] : (float)$tension_data['h_asimetria'];
        $td_m2 = ($td_sv === 'v2' && $tension_data['h_framing'] !== null) ? (float)$tension_data['h_framing'] : (float)$tension_data['h_divergencia'];
        $td_m3 = ($td_sv === 'v2' && $tension_data['h_silencio'] !== null) ? (float)$tension_data['h_silencio'] : (float)$tension_data['h_varianza'];
      ?>
        <div style="margin-bottom:2rem">
          <p class="section-label" style="margin-bottom:0.8rem">Polarización informativa</p>
          <?= render_barras_tension($td_m1, $td_m2, $td_m3, (float)$tension_data['h_score'], $td_sv) ?>
        </div>
      <?php endif; ?>

      <!-- Resumen -->
      <p class="section-label">Resumen</p>
      <p class="resumen"><?= htmlspecialchars($art['resumen']) ?></p>

      <!-- Mapa de posturas -->
      <p class="section-label">Mapa de posturas</p>
      <div class="posturas-grid">
        <?php foreach ($art['mapa_posturas'] as $i => $postura): ?>
          <?php $color = $position_colors[$i % count($position_colors)]; ?>
          <details class="postura-card" style="border-left-color: <?= $color ?>">
            <summary class="postura-summary">
              <div>
                <div class="postura-etiqueta"><?= htmlspecialchars($postura['etiqueta']) ?></div>
                <div class="postura-defensores"><?= htmlspecialchars(implode(' · ', $postura['defensores'])) ?></div>
              </div>
            </summary>
            <div class="postura-body">
              <ul class="postura-argumentos">
                <?php foreach ($postura['argumentos'] as $arg): ?>
                  <li><?= htmlspecialchars($arg) ?></li>
                <?php endforeach; ?>
              </ul>
              <?php if (!empty($postura['fuentes'])): ?>
                <div class="postura-fuentes">
                  <?php foreach ($postura['fuentes'] as $fuente): ?>
                    <a href="<?= htmlspecialchars($fuente['url']) ?>" class="fuente-link" target="_blank" rel="noopener noreferrer">
                      <span class="fuente-medio"><?= htmlspecialchars($fuente['medio']) ?>:</span>
                      <?= htmlspecialchars($fuente['titulo']) ?>
                      <span class="fuente-cuadrante"><?= htmlspecialchars($fuente['cuadrante']) ?></span>
                    </a>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </details>
        <?php endforeach; ?>
      </div>

      <!-- Ausencias -->
      <?php if (!empty($art['ausencias'])): ?>
        <p class="section-label">Lo que no se esta diciendo</p>
        <ul class="ausencias-list">
          <?php foreach ($art['ausencias'] as $ausencia): ?>
            <li><?= htmlspecialchars($ausencia) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <!-- Preguntas -->
      <?php if (!empty($art['preguntas'])): ?>
        <p class="section-label">Preguntas para pensar</p>
        <ol class="preguntas-list">
          <?php foreach ($art['preguntas'] as $pregunta): ?>
            <li><?= htmlspecialchars($pregunta) ?></li>
          <?php endforeach; ?>
        </ol>
      <?php endif; ?>


    <?php elseif ($radar): ?>

      <a href="<?= $B ?>" class="back-link" style="margin-top: 3rem; display: inline-flex;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Todas las noticias
      </a>

      <!-- Radar Mode Header -->
      <div class="article-header">
        <div class="article-meta">
          <span class="article-date"><?= format_fecha($radar['fecha']) ?></span>
          <span class="badge-ambito"><?= htmlspecialchars(ambito_label($radar['ambito'])) ?></span>
          <?= render_circulo_tension($radar['h_score']) ?>
          <span style="font-family:'Inter',Arial,sans-serif;font-size:0.72rem;font-weight:700;color:<?= tension_color($radar['h_score']) ?>"><?= round($radar['h_score'] * 100) ?>% polarización</span>
        </div>
        <h1><?= htmlspecialchars($radar['titulo_tema']) ?></h1>
      </div>

      <?php
        $cfg = prisma_cfg();
        $umbral_pct = round($cfg['umbral_tension'] * 100);
        $supera_umbral = ($radar['h_score'] >= $cfg['umbral_tension']);
      ?>

      <!-- Explanation box -->
      <div class="card" style="margin-bottom:2rem;border-left:3px solid <?= tension_color($radar['h_score']) ?>">
        <?php if (!$supera_umbral): ?>
          <!-- Caso 1: No supera el umbral -->
          <p style="margin:0 0 0.5em 0;color:var(--text)"><strong>Este tema no superó el umbral mínimo de polarización informativa (<?= $umbral_pct ?>%) configurado para activar el análisis multi-postura de Prisma.</strong></p>
          <?php if ($radar['haiku_frase']): ?>
            <p style="margin:0;color:var(--text-muted);font-style:italic"><?= htmlspecialchars($radar['haiku_frase']) ?></p>
          <?php else: ?>
            <p style="margin:0;color:var(--text-muted);font-style:italic"><?= htmlspecialchars(tension_frase_generica($r_m1, $r_m2, $r_rel, $r_fd)) ?></p>
          <?php endif; ?>
        <?php else: ?>
          <!-- Caso 2: Supera el umbral pero aún no hay artículo analizado -->
          <p style="margin:0 0 0.5em 0;color:var(--text)"><strong>
            <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:<?= tension_color($radar['h_score']) ?>;margin-right:6px;vertical-align:middle"></span>
            Polarización informativa detectada</strong></p>
          <p style="margin:0;color:var(--text-muted)">Este tema superó el umbral de polarización (<?= $umbral_pct ?>%), pero el análisis multi-postura aún no se ha completado. Puede estar pendiente de procesamiento o no haberse encontrado suficientes fuentes para elaborar el mapa de posturas.</p>
        <?php endif; ?>
      </div>

      <!-- Tension breakdown -->
      <div style="margin-bottom:2rem">
        <p class="section-label" style="margin-bottom:0.8rem">Desglose de polarización informativa</p>
        <?php
          $r_m3 = ($r_sv === 'v2' && $radar['h_silencio'] !== null) ? (float)$radar['h_silencio'] : (float)$radar['h_varianza'];
        ?>
        <?= render_barras_tension($r_m1, $r_m2, $r_m3, (float)$radar['h_score'], $r_sv) ?>
      </div>

      <!-- Source list -->
      <p class="section-label">Fuentes detectadas</p>
      <?php $fuentes = json_decode($radar['fuentes_json'], true) ?: []; ?>
      <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:2rem">
        <?php foreach ($fuentes as $f): ?>
          <div style="display:flex;align-items:flex-start;gap:10px;padding:12px 16px;border:1px solid var(--border-card);border-radius:6px;border-left:3px solid <?= cuadrante_color($f['cuadrante']) ?>">
            <div style="flex:1;min-width:0">
              <a href="<?= htmlspecialchars($f['url']) ?>" target="_blank" rel="noopener" style="color:var(--text);font-weight:500;text-decoration:none;font-size:0.95rem"><?= htmlspecialchars($f['titulo']) ?></a>
              <div style="font-family:'Inter',Arial,sans-serif;font-size:0.72rem;color:var(--text-faint);margin-top:4px">
                <?= htmlspecialchars($f['medio']) ?> · <span style="color:<?= cuadrante_color($f['cuadrante']) ?>"><?= htmlspecialchars($f['cuadrante']) ?></span>
              </div>
            </div>
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--text-faint)" stroke-width="2" style="flex-shrink:0;margin-top:4px"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6M15 3h6v6M10 14L21 3"/></svg>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if (!$supera_umbral): ?>
        <p style="color:var(--text-faint);font-size:0.9rem;font-style:italic">
          Prisma analiza en profundidad los temas con mayor polarización informativa. Este tema no cruza ese umbral — puedes consultar las fuentes directamente para formarte tu propia opinión.
        </p>
      <?php else: ?>
        <p style="color:var(--text-faint);font-size:0.9rem;font-style:italic">
          Este tema está marcado para análisis. Mientras tanto, puedes consultar las fuentes directamente para formarte tu propia opinión.
        </p>
      <?php endif; ?>


    <?php else: ?>

      <div class="not-found">
        <h1>Artículo no encontrado</h1>
        <p>El artefacto que buscas no existe o ha sido retirado.</p>
        <a href="<?= $B ?>" class="back-link">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
          Volver a las noticias
        </a>
      </div>

    <?php endif; ?>

    </div>
  </main>

  <footer role="contentinfo">
    <div class="container" style="max-width:1100px">
      <?= render_footer_bottom() ?>
    </div>
  </footer>
  <?= theme_js() ?>
</body>
</html>
