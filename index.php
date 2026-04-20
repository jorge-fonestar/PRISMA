<?php
require_once __DIR__ . '/db.php';

$db = prisma_db();
$rows = $db->query('SELECT id, fecha_publicacion, ambito, titular_neutral, resumen, payload, veredicto FROM articulos ORDER BY fecha_publicacion DESC LIMIT 50')->fetchAll();

$articles = [];
foreach ($rows as $row) {
    $art = json_decode($row['payload'], true);
    $art['_id'] = $row['id'];
    $articles[] = $art;
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

$B = prisma_base();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prisma — Noticias de hoy</title>
  <meta name="description" content="Las noticias políticas más relevantes del día, presentadas desde todas las posturas enfrentadas. Sin editorial, sin algoritmo, sin cámaras de eco.">
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#0a0a12">
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
    body {
      margin: 0;
      font-family: 'Charter', 'Iowan Old Style', 'Palatino Linotype', Georgia, serif;
      font-size: 18px;
      line-height: 1.65;
      color: #e8e8ec;
      background: #0a0a12;
      -webkit-font-smoothing: antialiased;
      -moz-osx-font-smoothing: grayscale;
      overflow-x: hidden;
    }
    .skip-link {
      position: absolute; top: -40px; left: 0;
      padding: 12px 20px; background: #fff; color: #0a0a12;
      text-decoration: none; z-index: 1000; font-weight: 600; transition: top 0.2s;
    }
    .skip-link:focus { top: 0; }
    :focus-visible { outline: 3px solid #f2f24a; outline-offset: 3px; border-radius: 2px; }
    h1, h2, h3 {
      font-family: 'Canela', 'Playfair Display', 'Didot', Georgia, serif;
      font-weight: 500; letter-spacing: -0.02em; line-height: 1.12; margin: 0 0 0.6em 0;
    }
    h1 { font-size: clamp(2.5rem, 6vw, 4rem); }
    h2 { font-size: clamp(1.4rem, 2.5vw, 1.8rem); }
    p { margin: 0 0 1.2em 0; }
    .eyebrow {
      font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
      font-size: 0.78rem; font-weight: 600; letter-spacing: 0.22em;
      text-transform: uppercase; color: #9a9aaa; margin-bottom: 1.5rem;
    }
    .container { width: 100%; max-width: 1100px; margin: 0 auto; padding: 0 24px; }

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
    header .nav-links a.active { color: #f2f24a; }
    @media (max-width: 640px) { header .nav-links { display: none; } }

    /* Main content */
    main { padding-top: 5rem; min-height: 100vh; }

    /* Hero block */
    .hero-intro {
      padding: 3.5rem 0 3rem 0;
      border-bottom: 1px solid rgba(255,255,255,0.06);
      margin-bottom: 2.5rem;
    }
    .hero-intro h1 {
      color: #fff; margin-bottom: 0.3em;
      font-size: clamp(2.2rem, 5vw, 3.5rem);
    }
    .hero-intro h1 em {
      font-style: italic;
      background: linear-gradient(90deg, #ff4d6d, #ff9e4d, #f2f24a, #4ade80, #4dc3ff, #a855f7);
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .hero-intro .lede {
      color: #c8c8d4; font-size: 1.12rem; line-height: 1.6;
      max-width: 720px; margin: 1rem 0 2rem 0;
    }
    .hero-intro .lede strong { color: #fff; font-weight: 600; }
    .hero-pillars {
      display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 1.5rem;
    }
    .hero-pillar {
      flex: 1 1 200px; padding: 1.2rem 0; position: relative;
      padding-left: 1rem; border-left: 2px solid rgba(255,255,255,0.1);
    }
    .hero-pillar strong {
      display: block; color: #fff;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem;
      font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
      margin-bottom: 0.3rem;
    }
    .hero-pillar span { color: #9a9aaa; font-size: 0.92rem; line-height: 1.45; }
    .hero-cta {
      display: inline-flex; align-items: center; gap: 6px;
      color: #f2f24a; text-decoration: none;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.88rem; font-weight: 600;
      transition: color 0.15s;
    }
    .hero-cta:hover { color: #fff; }

    /* Section header */
    .section-header {
      padding: 0 0 1.5rem 0;
    }
    .section-header h2 {
      color: #fff; font-size: clamp(1.5rem, 3vw, 2rem); margin-bottom: 0.2em;
    }
    .section-header p { color: #7a7a8a; font-size: 0.95rem; margin: 0; }

    /* Article cards */
    .articles-list { display: flex; flex-direction: column; gap: 24px; padding-bottom: 5rem; }
    .article-card {
      display: block; padding: 2rem; text-decoration: none; color: inherit;
      border: 1px solid rgba(255,255,255,0.08); border-radius: 6px;
      background: rgba(255,255,255,0.015); transition: border-color 0.2s, background 0.2s;
    }
    .article-card:hover {
      border-color: rgba(255,255,255,0.2); background: rgba(255,255,255,0.03);
    }
    .article-meta {
      display: flex; align-items: center; gap: 16px; margin-bottom: 1rem; flex-wrap: wrap;
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
      letter-spacing: 0.05em;
    }
    .badge-apto::before {
      content: ""; width: 6px; height: 6px; border-radius: 50%;
      background: #4ade80; box-shadow: 0 0 8px #4ade80;
    }
    .article-card h2 { color: #fff; margin-bottom: 0.5em; }
    .article-card .resumen {
      color: #9a9aaa; font-size: 0.95rem; line-height: 1.55; margin: 0;
      display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
    }
    .posturas-preview {
      display: flex; gap: 8px; margin-top: 1.2rem; flex-wrap: wrap;
    }
    .postura-chip {
      padding: 4px 12px; font-family: 'Inter', Arial, sans-serif;
      font-size: 0.72rem; font-weight: 500; letter-spacing: 0.03em;
      border-radius: 999px; background: rgba(255,255,255,0.06); color: #b8b8c4;
    }

    /* Empty state */
    .empty-state {
      text-align: center; padding: 6rem 2rem; color: #7a7a8a;
    }
    .empty-state h2 { color: #fff; }

    /* Footer */
    footer[role="contentinfo"] {
      padding: 3rem 0 2rem 0;
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
      font-size: 0.78rem; font-weight: 500; letter-spacing: 0.05em;
    }
    .ai-notice::before {
      content: ""; width: 6px; height: 6px; border-radius: 50%;
      background: #f2f24a; box-shadow: 0 0 6px #f2f24a;
    }

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
        <li><a href="<?= $B ?>" class="active">Hoy</a></li>
        <li><a href="<?= $B ?>manifiesto.php">El proyecto</a></li>
      </ul>
    </nav>
  </header>

  <main id="main-content" role="main">
    <div class="container">

      <!-- Hero intro -->
      <div class="hero-intro">
        <p class="eyebrow">Contra las cámaras de eco</p>
        <h1>Los algoritmos te dan la razón.<br>Prisma te da el <em>contexto</em>.</h1>
        <p class="lede">
          <strong>El 73% de los usuarios de redes solo consume información que confirma lo que ya cree.</strong>
          Los algoritmos no te informan: te encierran. La polarización crece porque cada bando consume
          una realidad distinta y deja de entender a la otra mitad.
        </p>
        <p class="lede" style="margin-top: 0;">
          Prisma lo rompe. Cada día, las noticias políticas más relevantes presentadas
          desde <strong>todas las posturas enfrentadas</strong>, auditadas contra 11 criterios de neutralidad.
          Sin editorial. Sin algoritmo. Sin decirte qué pensar.
        </p>
        <div class="hero-pillars">
          <div class="hero-pillar">
            <strong>Mínimo 3 posturas</strong>
            <span>Cada tema muestra al menos tres ángulos con sus argumentos y fuentes reales.</span>
          </div>
          <div class="hero-pillar">
            <strong>Auditoría pública</strong>
            <span>Cada publicación pasa 11 axiomas verificables. Si no los supera, no se publica.</span>
          </div>
          <div class="hero-pillar">
            <strong>Sin intereses</strong>
            <span>Sin publicidad, sin personalización, sin muros de pago. Servicio público.</span>
          </div>
        </div>
        <a href="<?= $B ?>manifiesto.php" class="hero-cta">
          Conoce el proyecto completo
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
        </a>
      </div>

      <!-- Noticias -->
      <div class="section-header">
        <p class="eyebrow">Noticias</p>
        <h2>Hoy en Prisma</h2>
      </div>

      <?php if (empty($articles)): ?>
        <div class="empty-state">
          <h2>No hay noticias disponibles</h2>
          <p>Todavia no se han publicado artefactos. Vuelve pronto.</p>
        </div>
      <?php else: ?>
        <div class="articles-list">
          <?php foreach ($articles as $art): ?>
            <a href="<?= $B ?>articulo.php?id=<?= urlencode($art['_id']) ?>" class="article-card">
              <div class="article-meta">
                <span class="article-date"><?= format_fecha($art['fecha_publicacion']) ?></span>
                <span class="badge-ambito"><?= htmlspecialchars(ambito_label($art['ambito'])) ?></span>
                <?php if (($art['auditoria_moralcore']['veredicto'] ?? '') === 'APTO'): ?>
                  <span class="badge-apto">Moral Core · APTO</span>
                <?php endif; ?>
              </div>
              <h2><?= htmlspecialchars($art['titular_neutral']) ?></h2>
              <p class="resumen"><?= htmlspecialchars($art['resumen']) ?></p>
              <?php if (!empty($art['mapa_posturas'])): ?>
                <div class="posturas-preview">
                  <?php foreach ($art['mapa_posturas'] as $postura): ?>
                    <span class="postura-chip"><?= htmlspecialchars($postura['etiqueta']) ?></span>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </a>
          <?php endforeach; ?>
        </div>
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
