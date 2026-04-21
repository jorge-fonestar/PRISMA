<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/theme.php';
require_once __DIR__ . '/lib/layout.php';

ini_set('default_charset', 'UTF-8');
$db = prisma_db();

// Available dates for date picker
$fechas_rows = $db->query("SELECT DISTINCT fecha FROM radar ORDER BY fecha DESC LIMIT 30")->fetchAll();
$fechas_disponibles = array_column($fechas_rows, 'fecha');

// Selected date (from query param or latest)
$fecha_sel = isset($_GET['fecha']) && in_array($_GET['fecha'], $fechas_disponibles)
    ? $_GET['fecha']
    : (isset($fechas_disponibles[0]) ? $fechas_disponibles[0] : null);

// Selected ambito filter — validate against actual DB values, not hardcoded list
$ambito_sel = 'all';
if (isset($_GET['ambito']) && $_GET['ambito'] !== '') {
    $ambito_sel = $_GET['ambito'];
}

// Fetch radar topics for selected date
$temas = [];
$ambitos_count = [];
if ($fecha_sel) {
    $stmt = $db->prepare("SELECT * FROM radar WHERE fecha = :f ORDER BY h_score DESC");
    $stmt->execute(array(':f' => $fecha_sel));
    $temas = $stmt->fetchAll();
    foreach ($temas as $t) {
        $a = $t['ambito'];
        $ambitos_count[$a] = isset($ambitos_count[$a]) ? $ambitos_count[$a] + 1 : 1;
    }
}

// Fallback: articles mode
$articles = [];
if (empty($temas)) {
    $rows = $db->query('SELECT id, fecha_publicacion, ambito, titular_neutral, resumen, payload, veredicto FROM articulos ORDER BY fecha_publicacion DESC LIMIT 50')->fetchAll();
    foreach ($rows as $row) {
        $art = json_decode($row['payload'], true);
        $art['_id'] = $row['id'];
        $articles[] = $art;
        $a = isset($art['ambito']) ? $art['ambito'] : '';
        $ambitos_count[$a] = isset($ambitos_count[$a]) ? $ambitos_count[$a] + 1 : 1;
    }
}

// Validate ambito against actual values from DB
if ($ambito_sel !== 'all' && !isset($ambitos_count[$ambito_sel])) {
    $ambito_sel = 'all';
}

function format_fecha($iso) {
    $ts = strtotime($iso);
    $meses = array('enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre');
    return date('j', $ts) . ' de ' . $meses[date('n', $ts) - 1] . ' de ' . date('Y', $ts);
}

function format_fecha_corta($iso) {
    $ts = strtotime($iso);
    $dias = array('dom','lun','mar','mié','jue','vie','sáb');
    $meses = array('ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic');
    return $dias[date('w', $ts)] . ' ' . date('j', $ts) . ' ' . $meses[date('n', $ts) - 1];
}

function ambito_label($ambito) {
    $map = array(
        'españa' => 'España', 'espana' => 'España', 'espa\xc3\xb1a' => 'España',
        'europa' => 'Europa',
        'global' => 'Global'
    );
    $key = mb_strtolower(trim($ambito), 'UTF-8');
    return isset($map[$key]) ? $map[$key] : ucfirst($ambito);
}

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$B = prisma_base();
$cfg = prisma_cfg();
$total_temas = !empty($temas) ? count($temas) : count($articles);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Prisma — Radar informativo</title>
  <meta name="description" content="Radar informativo: todos los temas políticos del día puntuados por polarización informativa. Sin editorial, sin cámaras de eco.">
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
      font-size: 18px; line-height: 1.65;
      color: var(--text); background: var(--bg);
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
    p { margin: 0 0 1.2em 0; }
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

    /* Main content */
    main { padding-top: 5rem; min-height: 100vh; }

    /* Compact banner */
    .banner {
      padding: 1.2rem 0;
      border-bottom: 1px solid var(--border);
      margin-bottom: 1.5rem;
      display: flex; align-items: center; justify-content: space-between;
      gap: 1rem; flex-wrap: wrap;
    }
    .banner-text {
      flex: 1; min-width: 280px;
    }
    .banner-text h1 {
      font-size: clamp(1.3rem, 3vw, 1.8rem); margin: 0 0 0.15em 0;
      color: var(--text);
    }
    .banner-text h1 em {
      font-style: italic;
      background: linear-gradient(90deg, #ff4d6d, #ff9e4d, #f2f24a, #4ade80, #4dc3ff, #a855f7);
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .banner-text p {
      color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin: 0;
    }
    .banner-text a {
      color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.82rem;
      font-family: 'Inter', Arial, sans-serif;
    }
    .banner-text a:hover { color: var(--text); }

    /* Toolbar: filters + date */
    .toolbar {
      display: flex; align-items: center; gap: 12px; flex-wrap: wrap;
      margin-bottom: 1.5rem; padding-bottom: 1rem;
      border-bottom: 1px solid var(--border);
    }
    .toolbar-group {
      display: flex; align-items: center; gap: 6px;
    }
    .toolbar-label {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem; font-weight: 600;
      letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-faint);
      margin-right: 4px; white-space: nowrap;
    }
    .toolbar-sep {
      width: 1px; height: 20px; background: var(--border); margin: 0 4px;
    }
    .filter-btn {
      padding: 5px 14px; border: 1px solid var(--border-card); border-radius: 999px;
      background: transparent; color: var(--text-muted); font-family: 'Inter', Arial, sans-serif;
      font-size: 0.75rem; font-weight: 600; letter-spacing: 0.04em; cursor: pointer;
      transition: all 0.15s; white-space: nowrap;
    }
    .filter-btn:hover { border-color: var(--border-hover); color: var(--text); }
    .filter-btn.active {
      background: var(--accent-bg); border-color: var(--accent-border); color: var(--accent);
    }
    .filter-btn .count {
      display: inline-block; margin-left: 3px; padding: 1px 5px;
      border-radius: 99px; background: var(--chip-bg);
      font-size: 0.65rem; font-weight: 700; color: var(--text-faint);
    }
    .filter-btn.active .count { background: var(--accent-bg); color: var(--accent); }

    /* Date scroller */
    .date-scroll {
      display: flex; gap: 4px; overflow-x: auto; -webkit-overflow-scrolling: touch;
      scrollbar-width: none; padding: 2px 0;
    }
    .date-scroll::-webkit-scrollbar { display: none; }
    .date-btn {
      padding: 5px 12px; border: 1px solid var(--border-card); border-radius: 999px;
      background: transparent; color: var(--text-muted); font-family: 'Inter', Arial, sans-serif;
      font-size: 0.72rem; font-weight: 600; cursor: pointer; transition: all 0.15s;
      white-space: nowrap; text-decoration: none; text-transform: capitalize;
    }
    .date-btn:hover { border-color: var(--border-hover); color: var(--text); }
    .date-btn.active {
      background: var(--accent-bg); border-color: var(--accent-border); color: var(--accent);
    }
    .date-btn .date-today {
      display: inline-block; width: 5px; height: 5px; border-radius: 50%;
      background: var(--green); margin-right: 3px; vertical-align: middle;
    }

    /* Day group header */
    .day-header {
      display: flex; align-items: center; gap: 12px;
      padding: 0.6rem 0; margin-top: 0.5rem;
    }
    .day-header span {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.78rem; font-weight: 600;
      letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-faint);
      white-space: nowrap;
    }
    .day-header::after {
      content: ''; flex: 1; height: 1px; background: var(--border);
    }

    /* Article cards */
    .articles-list { display: flex; flex-direction: column; gap: 16px; padding-bottom: 5rem; }
    .article-card {
      display: flex; gap: 16px; align-items: flex-start;
      padding: 1.4rem; text-decoration: none; color: inherit;
      border: 1px solid var(--border-card); border-radius: 6px;
      background: var(--bg-card); transition: border-color 0.2s, background 0.2s;
    }
    .article-card:hover {
      border-color: var(--border-hover); background: var(--bg-card-hover);
    }
    .article-card.hidden, .article-card.hidden-polar { display: none; }
    .article-meta {
      display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem; flex-wrap: wrap;
    }
    .article-date {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.78rem;
      letter-spacing: 0.1em; text-transform: uppercase; color: var(--text-faint);
    }
    .badge-ambito {
      display: inline-block; padding: 2px 8px;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.68rem; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      border-radius: 999px; border: 1px solid var(--border-card); color: var(--text-muted);
    }
    .badge-apto {
      display: inline-flex; align-items: center; gap: 5px;
      padding: 2px 8px; background: var(--green-bg); color: var(--green);
      border: 1px solid var(--green-border); border-radius: 999px;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.68rem; font-weight: 600;
      letter-spacing: 0.05em;
    }
    .badge-apto::before {
      content: ""; width: 5px; height: 5px; border-radius: 50%;
      background: var(--green); box-shadow: 0 0 6px var(--green);
    }
    .article-card h2 {
      font-size: clamp(1rem, 2vw, 1.25rem); margin-bottom: 0.2em; color: var(--text);
    }
    .article-card .frase {
      color: var(--text-faint); font-size: 0.84rem; font-style: italic; margin: 0 0 0.6em 0;
    }
    .article-card .resumen {
      color: var(--text-muted); font-size: 0.92rem; line-height: 1.5; margin: 0;
      display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
    }
    .fuentes-row {
      display: flex; gap: 5px; flex-wrap: wrap; margin-top: 0.6rem;
    }
    .postura-chip {
      padding: 3px 10px; font-family: 'Inter', Arial, sans-serif;
      font-size: 0.68rem; font-weight: 500; letter-spacing: 0.03em;
      border-radius: 999px; background: var(--chip-bg); color: var(--text-muted);
    }
    .posturas-preview {
      display: flex; gap: 6px; margin-top: 0.8rem; flex-wrap: wrap;
    }

    /* Stats bar */
    .stats-bar {
      display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.78rem;
      color: var(--text-faint); margin-bottom: 1rem;
    }
    .stats-bar strong { color: var(--text-muted); }

    /* Sort toggle */
    .sort-toggle {
      margin-left: auto; display: flex; align-items: center; gap: 6px;
    }
    .sort-btn {
      padding: 4px 10px; border: 1px solid var(--border-card); border-radius: 999px;
      background: transparent; color: var(--text-faint); font-family: 'Inter', Arial, sans-serif;
      font-size: 0.7rem; font-weight: 600; cursor: pointer; transition: all 0.15s;
    }
    .sort-btn:hover { border-color: var(--border-hover); color: var(--text); }
    .sort-btn.active { background: var(--accent-bg); border-color: var(--accent-border); color: var(--accent); }

    /* Empty state */
    .empty-state {
      text-align: center; padding: 4rem 2rem; color: var(--text-faint);
    }
    .empty-state h2 { color: var(--text); font-size: 1.3rem; }

    /* Footer */
    footer[role="contentinfo"] {
      padding: 2rem 0 1.5rem 0;
      border-top: 1px solid var(--border); background: var(--bg-footer);
    }
    .footer-bottom {
      display: flex; justify-content: space-between; align-items: center;
      flex-wrap: wrap; gap: 12px;
    }
    .footer-bottom p { color: var(--text-faintest); font-size: 0.82rem; margin: 0; }
    .ai-notice {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 4px 12px; background: var(--accent-bg);
      border: 1px solid var(--accent-border); border-radius: 999px;
      color: var(--accent); font-family: 'Inter', Arial, sans-serif;
      font-size: 0.72rem; font-weight: 500; letter-spacing: 0.05em;
    }
    .ai-notice::before {
      content: ""; width: 5px; height: 5px; border-radius: 50%;
      background: var(--accent); box-shadow: 0 0 6px var(--accent);
    }

    @media (max-width: 640px) {
      .toolbar { gap: 8px; }
      .toolbar-sep { display: none; }
      .banner { flex-direction: column; }
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

  <?= render_nav('', array('' => 'Radar')) ?>

  <main id="main-content" role="main">
    <div class="container">

      <!-- Compact banner -->
      <div class="banner">
        <div class="banner-text">
          <h1>Tu algoritmo te encierra. Prisma te da el <em>contexto</em>.</h1>
          <p>Las redes te muestran lo que ya crees. Aquí, cada noticia se analiza desde todas las posturas enfrentadas
            y se audita contra 11 criterios de neutralidad. Sin editorial. Sin personalización. Sin decirte qué pensar.
            <a href="<?= $B ?>presentacion.php">Cómo funciona &rarr;</a>
          </p>
        </div>
      </div>

      <!-- Toolbar: date + ambito filters -->
      <?php if (!empty($temas) || !empty($articles)): ?>
        <div class="toolbar">
          <?php if (!empty($fechas_disponibles) && count($fechas_disponibles) > 1): ?>
            <div class="toolbar-group">
              <span class="toolbar-label">Fecha</span>
              <div class="date-scroll">
                <?php foreach (array_slice($fechas_disponibles, 0, 10) as $fd): ?>
                  <a href="?fecha=<?= urlencode($fd) ?><?= $ambito_sel !== 'all' ? '&ambito=' . urlencode($ambito_sel) : '' ?>"
                     class="date-btn <?= $fd === $fecha_sel ? 'active' : '' ?>">
                    <?php if ($fd === date('Y-m-d')): ?><span class="date-today"></span><?php endif; ?>
                    <?= $fd === date('Y-m-d') ? 'Hoy' : h(format_fecha_corta($fd)) ?>
                  </a>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="toolbar-sep"></div>
          <?php endif; ?>

          <div class="toolbar-group">
            <span class="toolbar-label">Ámbito</span>
            <a href="?<?= $fecha_sel ? 'fecha=' . urlencode($fecha_sel) : '' ?>"
               class="filter-btn <?= $ambito_sel === 'all' ? 'active' : '' ?>" data-filter="all">
              Todos <span class="count"><?= $total_temas ?></span>
            </a>
            <?php foreach ($ambitos_count as $amb => $cnt): ?>
              <a href="?<?= $fecha_sel ? 'fecha=' . urlencode($fecha_sel) . '&' : '' ?>ambito=<?= urlencode($amb) ?>"
                 class="filter-btn <?= $ambito_sel === $amb ? 'active' : '' ?>" data-filter="<?= h($amb) ?>">
                <?= h(ambito_label($amb)) ?> <span class="count"><?= $cnt ?></span>
              </a>
            <?php endforeach; ?>
          </div>

          <div class="toolbar-sep"></div>

          <div class="toolbar-group">
            <span class="toolbar-label">Polarización</span>
            <button class="filter-btn polar-btn" data-min="0" data-max="100">Todas</button>
            <button class="filter-btn polar-btn active" data-min="50" data-max="100">&ge;50%</button>
            <button class="filter-btn polar-btn" data-min="0" data-max="49">&lt;50%</button>
          </div>

          <div class="sort-toggle">
            <button class="sort-btn active" data-sort="tension" title="Ordenar por polarización">Polarización</button>
            <button class="sort-btn" data-sort="alpha" title="Ordenar alfabéticamente">A-Z</button>
          </div>
        </div>

        <!-- Stats -->
        <div class="stats-bar">
          <span><strong id="polar-count"><?= $total_temas ?></strong> temas visibles de <?= $total_temas ?></span>
          <?php if ($fecha_sel): ?>
            <span><?= format_fecha($fecha_sel) ?></span>
          <?php endif; ?>
          <?php
            $analizados = 0;
            if (!empty($temas)) {
              foreach ($temas as $t) { if ($t['analizado']) $analizados++; }
            }
            if ($analizados > 0):
          ?>
            <span><strong><?= $analizados ?></strong> analizados en profundidad</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if (empty($temas) && empty($articles)): ?>
        <div class="empty-state">
          <h2>No hay noticias disponibles</h2>
          <p>Todavía no se han detectado temas. Vuelve pronto.</p>
        </div>

      <?php elseif (!empty($temas)): ?>
        <div class="articles-list" id="articles-list">
          <?php
            // Filter by ambito if selected
            $temas_filtrados = $temas;
            if ($ambito_sel !== 'all') {
              $temas_filtrados = array_filter($temas, function($t) use ($ambito_sel) {
                return $t['ambito'] === $ambito_sel;
              });
            }
          ?>
          <?php foreach ($temas_filtrados as $tema):
            $fuentes = json_decode($tema['fuentes_json'], true);
            if (!$fuentes) $fuentes = array();
            $link = $tema['analizado'] && $tema['articulo_id']
                ? $B . 'articulo.php?id=' . urlencode($tema['articulo_id'])
                : $B . 'articulo.php?radar=' . urlencode($tema['id']);
            $frase = $tema['haiku_frase'] ? $tema['haiku_frase'] : tension_frase_generica($tema['h_asimetria'], $tema['h_divergencia']);
          ?>
            <a href="<?= $link ?>" class="article-card"
               data-ambito="<?= h($tema['ambito']) ?>"
               data-score="<?= $tema['h_score'] ?>"
               data-title="<?= h($tema['titulo_tema']) ?>">
              <?= render_circulo_tension($tema['h_score']) ?>
              <div style="flex:1;min-width:0">
                <div class="article-meta">
                  <span class="badge-ambito"><?= h(ambito_label($tema['ambito'])) ?></span>
                  <?php if ($tema['analizado']): ?>
                    <span class="badge-apto">Analizado</span>
                  <?php endif; ?>
                  <span style="font-family:'Inter',Arial,sans-serif;font-size:0.7rem;color:var(--text-faint);margin-left:auto">
                    H <?= number_format($tema['h_score'] * 100, 0) ?>%
                  </span>
                </div>
                <h2><?= h($tema['titulo_tema']) ?></h2>
                <p class="frase"><?= h($frase) ?></p>
                <div class="fuentes-row">
                  <?php foreach ($fuentes as $f): ?>
                    <span class="postura-chip" style="border-left:3px solid <?= cuadrante_color($f['cuadrante']) ?>;padding-left:7px">
                      <?= h($f['medio']) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
              </div>
            </a>
          <?php endforeach; ?>

          <?php if (empty($temas_filtrados)): ?>
            <div class="empty-state">
              <p>No hay temas para este ámbito en la fecha seleccionada.</p>
            </div>
          <?php endif; ?>
        </div>

      <?php else: ?>
        <!-- Fallback: articles mode -->
        <div class="articles-list" id="articles-list">
          <?php foreach ($articles as $art): ?>
            <a href="<?= $B ?>articulo.php?id=<?= urlencode($art['_id']) ?>" class="article-card"
               data-ambito="<?= h(isset($art['ambito']) ? $art['ambito'] : '') ?>">
              <div style="flex:1;min-width:0">
                <div class="article-meta">
                  <span class="article-date"><?= format_fecha($art['fecha_publicacion']) ?></span>
                  <span class="badge-ambito"><?= h(ambito_label($art['ambito'])) ?></span>
                  <?php if ((isset($art['auditoria_moralcore']['veredicto']) ? $art['auditoria_moralcore']['veredicto'] : '') === 'APTO'): ?>
                    <span class="badge-apto">Moral Core · APTO</span>
                  <?php endif; ?>
                </div>
                <h2><?= h($art['titular_neutral']) ?></h2>
                <p class="resumen"><?= h($art['resumen']) ?></p>
                <?php if (!empty($art['mapa_posturas'])): ?>
                  <div class="posturas-preview">
                    <?php foreach ($art['mapa_posturas'] as $postura): ?>
                      <span class="postura-chip"><?= h($postura['etiqueta']) ?></span>
                    <?php endforeach; ?>
                  </div>
                <?php endif; ?>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </main>

  <footer role="contentinfo">
    <div class="container">
      <?= render_footer_bottom() ?>
    </div>
  </footer>

  <script>
  // Polarization filter state — default: show >=50% only
  var polarMin = 50, polarMax = 100;

  function applyPolarFilter() {
    var list = document.getElementById('articles-list');
    if (!list) return;
    var cards = Array.prototype.slice.call(list.querySelectorAll('.article-card'));
    cards.forEach(function(card) {
      var score = Math.round(parseFloat(card.dataset.score || 0) * 100);
      var hidden = score < polarMin || score > polarMax;
      card.classList.toggle('hidden-polar', hidden);
    });
    // Update visible count
    var visible = list.querySelectorAll('.article-card:not(.hidden-polar)').length;
    var counter = document.getElementById('polar-count');
    if (counter) counter.textContent = visible;
  }

  // Polarization filter buttons
  document.querySelectorAll('.polar-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.polar-btn').forEach(function(b) { b.classList.remove('active'); });
      this.classList.add('active');
      polarMin = parseInt(this.dataset.min, 10);
      polarMax = parseInt(this.dataset.max, 10);
      applyPolarFilter();
    });
  });

  // Apply default filter on load
  applyPolarFilter();

  // Client-side sort (tension vs alphabetical)
  document.querySelectorAll('.sort-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      document.querySelectorAll('.sort-btn').forEach(function(b) { b.classList.remove('active'); });
      this.classList.add('active');
      var mode = this.dataset.sort;
      var list = document.getElementById('articles-list');
      if (!list) return;
      var cards = Array.prototype.slice.call(list.querySelectorAll('.article-card:not(.hidden-polar)'));
      cards.sort(function(a, b) {
        if (mode === 'tension') {
          return parseFloat(b.dataset.score || 0) - parseFloat(a.dataset.score || 0);
        } else {
          return (a.dataset.title || '').localeCompare(b.dataset.title || '', 'es');
        }
      });
      cards.forEach(function(card) { list.appendChild(card); });
    });
  });
  </script>
  <?= theme_js() ?>
</body>
</html>
