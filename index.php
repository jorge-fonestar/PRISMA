<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/theme.php';
require_once __DIR__ . '/lib/layout.php';

$db = prisma_db();

// Get latest date with radar data
$stmt = $db->prepare("SELECT fecha FROM radar ORDER BY fecha DESC LIMIT 1");
$stmt->execute();
$latest = $stmt->fetchColumn();

$temas = [];
$ambitos_count = [];
if ($latest) {
    $stmt = $db->prepare("SELECT * FROM radar WHERE fecha = :f ORDER BY h_score DESC");
    $stmt->execute([':f' => $latest]);
    $temas = $stmt->fetchAll();
    foreach ($temas as $t) {
        $a = $t['ambito'];
        $ambitos_count[$a] = ($ambitos_count[$a] ?? 0) + 1;
    }
}

// Also fetch analyzed articles (for backwards compat until radar is populated)
$articles = [];
if (empty($temas)) {
    $rows = $db->query('SELECT id, fecha_publicacion, ambito, titular_neutral, resumen, payload, veredicto FROM articulos ORDER BY fecha_publicacion DESC LIMIT 50')->fetchAll();
    foreach ($rows as $row) {
        $art = json_decode($row['payload'], true);
        $art['_id'] = $row['id'];
        $articles[] = $art;
        $a = $art['ambito'] ?? '';
        $ambitos_count[$a] = ($ambitos_count[$a] ?? 0) + 1;
    }
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
$cfg = prisma_cfg();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prisma — Noticias de hoy</title>
  <meta name="description" content="Radar informativo: todos los temas políticos del día puntuados por tensión informativa. Los más tensos se analizan en profundidad desde todas las posturas. Sin editorial, sin cámaras de eco.">
  <meta name="robots" content="index, follow">
  <meta name="theme-color" content="#0a0a12">
  <?= theme_head_script() ?>
  <?= theme_css() ?>
  <style>
    *, *::before, *::after { box-sizing: border-box; }
    html { scroll-behavior: smooth; -webkit-text-size-adjust: 100%; }
    body {
      margin: 0;
      font-family: 'Charter', 'Iowan Old Style', 'Palatino Linotype', Georgia, serif;
      font-size: 18px;
      line-height: 1.65;
      color: var(--text);
      background: var(--bg);
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
      text-transform: uppercase; color: var(--text-muted); margin-bottom: 1.5rem;
    }
    .container { width: 100%; max-width: 1100px; margin: 0 auto; padding: 0 24px; }

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
      border-bottom: 1px solid var(--border);
      margin-bottom: 2.5rem;
    }
    .hero-intro h1 {
      color: var(--text); margin-bottom: 0.3em;
      font-size: clamp(2.2rem, 5vw, 3.5rem);
    }
    .hero-intro h1 em {
      font-style: italic;
      background: linear-gradient(90deg, #ff4d6d, #ff9e4d, #f2f24a, #4ade80, #4dc3ff, #a855f7);
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .hero-intro .lede {
      color: var(--text-muted); font-size: 1.12rem; line-height: 1.6;
      max-width: 720px; margin: 1rem 0 2rem 0;
    }
    .hero-intro .lede strong { color: var(--text); font-weight: 600; }
    .hero-pillars {
      display: flex; gap: 2rem; flex-wrap: wrap; margin-bottom: 1.5rem;
    }
    .hero-pillar {
      flex: 1 1 200px; padding: 1.2rem 0; position: relative;
      padding-left: 1rem; border-left: 2px solid var(--border-card);
    }
    .hero-pillar strong {
      display: block; color: var(--text);
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem;
      font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
      margin-bottom: 0.3rem;
    }
    .hero-pillar span { color: var(--text-muted); font-size: 0.92rem; line-height: 1.45; }
    .hero-cta {
      display: inline-flex; align-items: center; gap: 6px;
      color: var(--accent); text-decoration: none;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.88rem; font-weight: 600;
      transition: color 0.15s;
    }
    .hero-cta:hover { color: var(--text); }

    /* Section header */
    .section-header {
      padding: 0 0 1.5rem 0;
    }
    .section-header h2 {
      color: var(--text); font-size: clamp(1.5rem, 3vw, 2rem); margin-bottom: 0.2em;
    }
    .section-header p { color: var(--text-faint); font-size: 0.95rem; margin: 0; }

    /* Filter tabs */
    .filters {
      display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 1.5rem;
    }
    .filter-btn {
      padding: 6px 16px; border: 1px solid var(--border-card); border-radius: 999px;
      background: transparent; color: var(--text-muted); font-family: 'Inter', Arial, sans-serif;
      font-size: 0.78rem; font-weight: 600; letter-spacing: 0.04em; cursor: pointer;
      transition: all 0.15s;
    }
    .filter-btn:hover { border-color: var(--border-hover); color: var(--text); }
    .filter-btn.active {
      background: var(--accent-bg); border-color: var(--accent-border);
      color: var(--accent);
    }
    .filter-btn .count {
      display: inline-block; margin-left: 4px; padding: 1px 6px;
      border-radius: 99px; background: var(--chip-bg);
      font-size: 0.68rem; font-weight: 700; color: var(--text-faint);
    }
    .filter-btn.active .count { background: var(--accent-bg); color: var(--accent); }
    .article-card.hidden { display: none; }

    /* Article cards */
    .articles-list { display: flex; flex-direction: column; gap: 24px; padding-bottom: 5rem; }
    .article-card {
      display: block; padding: 2rem; text-decoration: none; color: inherit;
      border: 1px solid var(--border-card); border-radius: 6px;
      background: var(--bg-card); transition: border-color 0.2s, background 0.2s;
    }
    .article-card:hover {
      border-color: var(--border-hover); background: var(--bg-card-hover);
    }
    .article-meta {
      display: flex; align-items: center; gap: 16px; margin-bottom: 1rem; flex-wrap: wrap;
    }
    .article-date {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem;
      letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-faint);
    }
    .badge-ambito {
      display: inline-block; padding: 3px 10px;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      border-radius: 999px; border: 1px solid var(--border-card); color: var(--text-muted);
    }
    .badge-apto {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 3px 10px; background: var(--green-bg); color: var(--green);
      border: 1px solid var(--green-border); border-radius: 999px;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
      letter-spacing: 0.05em;
    }
    .badge-apto::before {
      content: ""; width: 6px; height: 6px; border-radius: 50%;
      background: var(--green); box-shadow: 0 0 8px var(--green);
    }
    .article-card h2 { color: var(--text); margin-bottom: 0.5em; }
    .article-card .resumen {
      color: var(--text-muted); font-size: 0.95rem; line-height: 1.55; margin: 0;
      display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;
    }
    .posturas-preview {
      display: flex; gap: 8px; margin-top: 1.2rem; flex-wrap: wrap;
    }
    .postura-chip {
      padding: 4px 12px; font-family: 'Inter', Arial, sans-serif;
      font-size: 0.72rem; font-weight: 500; letter-spacing: 0.03em;
      border-radius: 999px; background: var(--chip-bg); color: var(--text-muted);
    }

    /* Empty state */
    .empty-state {
      text-align: center; padding: 6rem 2rem; color: var(--text-faint);
    }
    .empty-state h2 { color: var(--text); }

    /* Footer */
    footer[role="contentinfo"] {
      padding: 3rem 0 2rem 0;
      border-top: 1px solid var(--border); background: var(--bg-footer);
    }
    .footer-bottom {
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 16px;
    }
    .footer-bottom p { color: var(--text-faintest); font-size: 0.85rem; margin: 0; }
    .ai-notice {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 6px 14px; background: var(--accent-bg);
      border: 1px solid var(--accent-border); border-radius: 999px;
      color: var(--accent); font-family: 'Inter', Arial, sans-serif;
      font-size: 0.78rem; font-weight: 500; letter-spacing: 0.05em;
    }
    .ai-notice::before {
      content: ""; width: 6px; height: 6px; border-radius: 50%;
      background: var(--accent); box-shadow: 0 0 6px var(--accent);
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
      <?= theme_toggle() ?>
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
        <p class="eyebrow">Radar informativo</p>
        <h2>Hoy en Prisma</h2>
        <p>Todos los temas detectados, ordenados por tensión informativa. Los de mayor tensión se analizan en profundidad.</p>
      </div>

      <?php if (!empty($articles) && count($ambitos_count) > 1): ?>
        <div class="filters">
          <button class="filter-btn active" data-filter="all">Todos <span class="count"><?= !empty($temas) ? count($temas) : count($articles) ?></span></button>
          <?php foreach ($ambitos_count as $amb => $cnt): ?>
            <button class="filter-btn" data-filter="<?= htmlspecialchars($amb) ?>"><?= htmlspecialchars(ambito_label($amb)) ?> <span class="count"><?= $cnt ?></span></button>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (empty($temas) && empty($articles)): ?>
        <div class="empty-state">
          <h2>No hay noticias disponibles</h2>
          <p>Todavía no se han publicado artefactos. Vuelve pronto.</p>
        </div>

      <?php elseif (!empty($temas)): ?>
        <?php if ($latest !== date('Y-m-d')): ?>
          <p style="font-family:'Inter',Arial,sans-serif;font-size:0.78rem;color:var(--text-faint);margin-bottom:1.5rem">
            Última actualización: <?= format_fecha($latest) ?>
          </p>
        <?php endif; ?>
        <div class="articles-list">
          <?php foreach ($temas as $tema):
            $fuentes = json_decode($tema['fuentes_json'], true) ?: [];
            $link = $tema['analizado'] && $tema['articulo_id']
                ? $B . 'articulo.php?id=' . urlencode($tema['articulo_id'])
                : $B . 'articulo.php?radar=' . urlencode($tema['id']);
            $frase = $tema['haiku_frase'] ?: tension_frase_generica($tema['h_asimetria'], $tema['h_divergencia']);
          ?>
            <a href="<?= $link ?>" class="article-card" data-ambito="<?= htmlspecialchars($tema['ambito']) ?>" style="display:flex;gap:20px;align-items:flex-start">
              <?= render_circulo_tension($tema['h_score']) ?>
              <div style="flex:1;min-width:0">
                <div class="article-meta">
                  <span class="badge-ambito"><?= htmlspecialchars(ambito_label($tema['ambito'])) ?></span>
                  <?php if ($tema['analizado']): ?>
                    <span class="badge-apto" style="background:var(--green-bg);color:var(--green);border-color:var(--green-border)">Analizado</span>
                  <?php endif; ?>
                </div>
                <h2 style="font-size:clamp(1.1rem,2vw,1.4rem);margin-bottom:0.3em"><?= htmlspecialchars($tema['titulo_tema']) ?></h2>
                <p style="color:var(--text-faint);font-size:0.88rem;font-style:italic;margin:0 0 0.8em 0"><?= htmlspecialchars($frase) ?></p>
                <div style="display:flex;gap:6px;flex-wrap:wrap">
                  <?php foreach ($fuentes as $f): ?>
                    <span class="postura-chip" style="border-left:3px solid <?= cuadrante_color($f['cuadrante']) ?>;padding-left:8px">
                      <?= htmlspecialchars($f['medio']) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>

      <?php else: ?>
        <!-- Fallback: articles mode (no radar data yet) -->
        <div class="articles-list">
          <?php foreach ($articles as $art): ?>
            <a href="<?= $B ?>articulo.php?id=<?= urlencode($art['_id']) ?>" class="article-card" data-ambito="<?= htmlspecialchars($art['ambito'] ?? '') ?>">
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
    <div class="container" style="max-width:1100px">
      <div style="display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:40px;margin-bottom:2rem">
        <div>
          <div class="logo" style="pointer-events:none">
            <svg class="logo-mark" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <polygon points="16,4 28,26 4,26" fill="none" stroke="url(#prismGrad)" stroke-width="1.8" stroke-linejoin="round"/>
            </svg>
            <span>Prisma</span>
          </div>
          <p style="color:var(--text-faint);font-size:0.9rem;margin-top:0.8rem;max-width:280px">Servicio público de información neutral. Sin editorial, sin algoritmo, sin cámaras de eco.</p>
        </div>
        <div>
          <h4 style="font-family:'Inter',Arial,sans-serif;font-size:0.72rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-muted);margin:0 0 0.8rem 0">Proyecto</h4>
          <ul style="list-style:none;padding:0;margin:0">
            <li style="margin-bottom:0.5rem"><a href="<?= $B ?>" style="color:var(--text-faint);font-size:0.88rem">Hoy</a></li>
            <li style="margin-bottom:0.5rem"><a href="<?= $B ?>manifiesto.php" style="color:var(--text-faint);font-size:0.88rem">El proyecto</a></li>
            <li style="margin-bottom:0.5rem"><a href="<?= $B ?>archivo.php" style="color:var(--text-faint);font-size:0.88rem">Archivo</a></li>
            <li style="margin-bottom:0.5rem"><a href="<?= $B ?>ia.php" style="color:var(--text-faint);font-size:0.88rem">Aviso de IA</a></li>
          </ul>
        </div>
        <div>
          <h4 style="font-family:'Inter',Arial,sans-serif;font-size:0.72rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-muted);margin:0 0 0.8rem 0">Estándar</h4>
          <ul style="list-style:none;padding:0;margin:0">
            <li style="margin-bottom:0.5rem"><a href="https://moralcore.org" target="_blank" rel="noopener" style="color:var(--text-faint);font-size:0.88rem">Moral Core</a></li>
            <li style="margin-bottom:0.5rem"><a href="<?= $B ?>axiomas.php" style="color:var(--text-faint);font-size:0.88rem">Los 11 axiomas</a></li>
            <li style="margin-bottom:0.5rem"><a href="<?= $B ?>fuentes.php" style="color:var(--text-faint);font-size:0.88rem">Fuentes consultadas</a></li>
          </ul>
        </div>
        <div>
          <h4 style="font-family:'Inter',Arial,sans-serif;font-size:0.72rem;font-weight:600;letter-spacing:0.14em;text-transform:uppercase;color:var(--text-muted);margin:0 0 0.8rem 0">Legal</h4>
          <ul style="list-style:none;padding:0;margin:0">
            <li style="margin-bottom:0.5rem"><a href="<?= $B ?>aviso-legal.php" style="color:var(--text-faint);font-size:0.88rem">Aviso legal</a></li>
            <li style="margin-bottom:0.5rem"><a href="<?= $B ?>privacidad.php" style="color:var(--text-faint);font-size:0.88rem">Privacidad</a></li>
            <li style="margin-bottom:0.5rem"><a href="<?= $B ?>cookies.php" style="color:var(--text-faint);font-size:0.88rem">Cookies</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; <?= date('Y') ?> Prisma · Proyecto independiente · CC BY-SA 4.0</p>
        <span class="ai-notice">Contenido generado y auditado por IA</span>
      </div>
    </div>
  </footer>
  <script>
  document.querySelectorAll('.filter-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      var filter = this.dataset.filter;
      document.querySelectorAll('.filter-btn').forEach(function(b) { b.classList.remove('active'); });
      this.classList.add('active');
      document.querySelectorAll('.article-card').forEach(function(card) {
        card.classList.toggle('hidden', filter !== 'all' && card.dataset.ambito !== filter);
      });
    });
  });
  </script>
  <?= theme_js() ?>
</body>
</html>
