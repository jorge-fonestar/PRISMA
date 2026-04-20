<?php
require_once __DIR__ . '/db.php';

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

if (!$art) {
    http_response_code(404);
    $page_title = 'Articulo no encontrado — Prisma';
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
  <?php endif; ?>
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#0a0a12">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
    body {
      margin: 0;
      font-family: 'Charter', 'Iowan Old Style', 'Palatino Linotype', Georgia, serif;
      font-size: 18px; line-height: 1.65; color: #e8e8ec; background: #0a0a12;
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
    a { color: #4dc3ff; }
    a:hover { color: #fff; }
    .eyebrow {
      font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
      font-size: 0.72rem; font-weight: 600; letter-spacing: 0.18em;
      text-transform: uppercase; color: #7a7a8a; margin-bottom: 1rem;
    }
    .container { width: 100%; max-width: 820px; margin: 0 auto; padding: 0 24px; }

    /* Header */
    header[role="banner"] {
      position: fixed; top: 0; left: 0; right: 0; z-index: 100;
      background: rgba(10, 10, 18, 0.85);
      backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    header nav {
      max-width: 1100px; margin: 0 auto; padding: 16px 24px;
      display: flex; align-items: center; justify-content: space-between;
    }
    .logo {
      display: flex; align-items: center; gap: 10px; color: #fff; text-decoration: none;
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
      font-size: 1.35rem; font-weight: 500; letter-spacing: -0.01em;
    }
    .logo-mark { width: 28px; height: 28px; flex-shrink: 0; }
    header .nav-links {
      display: flex; gap: 28px; list-style: none; margin: 0; padding: 0;
    }
    header .nav-links a {
      color: #c8c8d0; text-decoration: none;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.92rem; transition: color 0.15s;
    }
    header .nav-links a:hover { color: #fff; }
    @media (max-width: 640px) { header .nav-links { display: none; } }

    /* Article */
    main { padding-top: 5rem; min-height: 100vh; }
    .article-header { padding: 3rem 0 2rem 0; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 2.5rem; }
    .article-meta {
      display: flex; align-items: center; gap: 16px; margin-bottom: 1.5rem; flex-wrap: wrap;
    }
    .article-date {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem;
      letter-spacing: 0.1em; text-transform: uppercase; color: #7a7a8a;
    }
    .badge-ambito {
      display: inline-block; padding: 3px 10px;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      border-radius: 999px; border: 1px solid rgba(255,255,255,0.15); color: #c8c8d0;
    }
    .badge-apto {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 3px 10px; background: rgba(74, 222, 128, 0.12); color: #4ade80;
      border: 1px solid rgba(74, 222, 128, 0.3); border-radius: 999px;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
    }
    .badge-apto::before {
      content: ""; width: 6px; height: 6px; border-radius: 50%;
      background: #4ade80; box-shadow: 0 0 8px #4ade80;
    }
    .article-header h1 { color: #fff; }

    /* Resumen */
    .section-label {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
      letter-spacing: 0.18em; text-transform: uppercase; color: #f2f24a;
      margin: 3rem 0 1rem 0; padding-bottom: 0.5rem;
      border-bottom: 1px solid rgba(242, 242, 74, 0.2);
    }
    .resumen { color: #c8c8d4; font-size: 1.05rem; line-height: 1.7; }

    /* Posturas */
    .posturas-grid { display: flex; flex-direction: column; gap: 12px; }
    .postura-etiqueta {
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
      font-size: 1.15rem; font-weight: 500; color: #fff; margin-bottom: 0.3rem;
    }
    .postura-defensores {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem;
      color: #9a9aaa; margin-bottom: 0; line-height: 1.5;
    }
    .postura-argumentos { list-style: none; padding: 0; margin: 0 0 1.2rem 0; }
    .postura-argumentos li {
      padding: 0.4rem 0 0.4rem 1.2rem; position: relative;
      color: #c8c8d4; font-size: 0.95rem; line-height: 1.55;
    }
    .postura-argumentos li::before {
      content: ""; position: absolute; left: 0; top: 0.85rem;
      width: 6px; height: 6px; border-radius: 50%; background: rgba(255,255,255,0.2);
    }
    .postura-fuentes { display: flex; flex-direction: column; gap: 6px; }
    .fuente-link {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem;
      color: #4dc3ff; text-decoration: none; transition: color 0.15s;
      display: flex; align-items: baseline; gap: 6px;
    }
    .fuente-link:hover { color: #fff; }
    .fuente-medio {
      color: #7a7a8a; font-size: 0.75rem; flex-shrink: 0;
    }
    .fuente-cuadrante {
      display: inline-block; padding: 1px 6px; margin-left: 4px;
      font-size: 0.68rem; border-radius: 3px;
      background: rgba(255,255,255,0.06); color: #9a9aaa;
    }

    /* Ausencias */
    .ausencias-list { list-style: none; padding: 0; margin: 0; }
    .ausencias-list li {
      padding: 1rem 0; border-bottom: 1px solid rgba(255,255,255,0.05);
      color: #c8c8d4; font-size: 0.95rem; line-height: 1.6;
    }
    .ausencias-list li:last-child { border-bottom: none; }

    /* Preguntas */
    .preguntas-list { list-style: none; padding: 0; margin: 0; counter-reset: pregunta; }
    .preguntas-list li {
      padding: 1.2rem 0 1.2rem 3rem; position: relative;
      border-bottom: 1px solid rgba(255,255,255,0.05);
      color: #e8e8ec; font-size: 1.05rem; line-height: 1.6;
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
    }
    .preguntas-list li:last-child { border-bottom: none; }
    .preguntas-list li::before {
      counter-increment: pregunta;
      content: counter(pregunta);
      position: absolute; left: 0; top: 1.2rem;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem; font-weight: 700;
      color: #f2f24a; letter-spacing: 0.1em;
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
      font-size: 2.2rem; font-weight: 500; color: #4ade80;
      line-height: 1; letter-spacing: -0.02em;
    }
    .audit-bar.audit-fail .audit-score { color: #ff4d6d; }
    .audit-bar .audit-info {
      display: flex; flex-direction: column; gap: 2px;
    }
    .audit-bar .audit-label {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
      letter-spacing: 0.12em; text-transform: uppercase; color: #7a7a8a;
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
    .axiom-dot.pass { background: rgba(74, 222, 128, 0.15); color: #4ade80; }
    .axiom-dot.fail { background: rgba(255, 77, 109, 0.15); color: #ff4d6d; }
    .axiom-dot .axiom-tip {
      display: none; position: absolute; bottom: calc(100% + 8px); left: 50%;
      transform: translateX(-50%); white-space: nowrap;
      padding: 6px 12px; border-radius: 4px;
      background: #1a1a2e; border: 1px solid rgba(255,255,255,0.12);
      color: #e8e8ec; font-size: 0.72rem; font-weight: 500;
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
      font-family: 'Inter', Arial, sans-serif; font-size: 0.75rem; color: #7a7a8a;
      display: flex; flex-direction: column; align-items: flex-end; gap: 2px;
    }

    /* Posturas collapsible */
    .postura-card { border-left: 4px solid; border-radius: 0 4px 4px 0; background: rgba(255,255,255,0.02); }
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
    details.postura-card[open] .postura-summary::after { transform: rotate(45deg); color: #f2f24a; }
    .postura-summary:hover::after { color: #fff; }
    .postura-body { padding: 0 1.5rem 1.5rem 1.5rem; }

    /* Back link */
    .back-link {
      display: inline-flex; align-items: center; gap: 6px;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.88rem;
      color: #9a9aaa; text-decoration: none; transition: color 0.15s;
      margin-bottom: 1rem;
    }
    .back-link:hover { color: #fff; }

    /* Fuentes total */
    .fuentes-total {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.85rem;
      color: #7a7a8a; margin-top: 1rem;
    }

    /* Footer */
    footer[role="contentinfo"] {
      padding: 3rem 0 2rem 0; margin-top: 4rem;
      border-top: 1px solid rgba(255,255,255,0.06); background: #050509;
    }
    .footer-bottom {
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 16px;
    }
    .footer-bottom p { color: #5a5a6a; font-size: 0.85rem; margin: 0; }
    .ai-notice {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 6px 14px; background: rgba(242, 242, 74, 0.08);
      border: 1px solid rgba(242, 242, 74, 0.2); border-radius: 999px;
      color: #f2f24a; font-family: 'Inter', Arial, sans-serif;
      font-size: 0.78rem; font-weight: 500;
    }
    .ai-notice::before {
      content: ""; width: 6px; height: 6px; border-radius: 50%;
      background: #f2f24a; box-shadow: 0 0 6px #f2f24a;
    }

    /* 404 */
    .not-found { text-align: center; padding: 6rem 2rem; }
    .not-found h1 { color: #fff; }
    .not-found p { color: #9a9aaa; }

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

  <header role="banner">
    <nav aria-label="Navegacion principal">
      <a href="<?= $B ?>" class="logo" aria-label="Prisma - Inicio">
        <svg class="logo-mark" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <defs>
            <linearGradient id="prismGrad" x1="0%" y1="0%" x2="100%" y2="100%">
              <stop offset="0%" stop-color="#ff4d6d"/>
              <stop offset="25%" stop-color="#f2f24a"/>
              <stop offset="50%" stop-color="#4ade80"/>
              <stop offset="75%" stop-color="#4dc3ff"/>
              <stop offset="100%" stop-color="#a855f7"/>
            </linearGradient>
          </defs>
          <polygon points="16,4 28,26 4,26" fill="none" stroke="url(#prismGrad)" stroke-width="1.8" stroke-linejoin="round"/>
        </svg>
        <span>Prisma</span>
      </a>
      <ul class="nav-links">
        <li><a href="<?= $B ?>">Hoy</a></li>
        <li><a href="<?= $B ?>manifiesto.php">El proyecto</a></li>
      </ul>
    </nav>
  </header>

  <main id="main-content" role="main">
    <div class="container">

    <?php if (!$art): ?>
      <div class="not-found">
        <h1>Articulo no encontrado</h1>
        <p>El artefacto que buscas no existe o ha sido retirado.</p>
        <a href="<?= $B ?>" class="back-link">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
          Volver a las noticias
        </a>
      </div>
    <?php else: ?>

      <a href="<?= $B ?>" class="back-link" style="margin-top: 3rem; display: inline-flex;">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
        Todas las noticias
      </a>

      <!-- Header -->
      <div class="article-header">
        <div class="article-meta">
          <span class="article-date"><?= format_fecha($art['fecha_publicacion']) ?></span>
          <span class="badge-ambito"><?= htmlspecialchars(ambito_label($art['ambito'])) ?></span>
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


    <?php endif; ?>

    </div>
  </main>

  <footer role="contentinfo">
    <div class="container">
      <div class="footer-bottom">
        <p>&copy; 2026 Prisma · Proyecto independiente · CC BY-SA 4.0</p>
        <span class="ai-notice">Contenido generado y auditado por IA</span>
      </div>
    </div>
  </footer>
</body>
</html>
