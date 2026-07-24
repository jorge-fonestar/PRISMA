<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/theme.php';
require_once __DIR__ . '/lib/layout.php';

ini_set('default_charset', 'UTF-8');

function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

$B = prisma_base();
$cfg = prisma_cfg();
$today = date('Y-m-d');

// Vista: '' = Hoy (solo hoy, polarización ≥10%) | 'radar' = últimos 7 días, ≥50%.
// Misma página y mismos filtros; solo cambian los valores por defecto.
$vista = (isset($_GET['vista']) && $_GET['vista'] === 'radar') ? 'radar' : '';
$def_hasta = $today;
if ($vista === 'radar') {
    $def_desde = date('Y-m-d', strtotime('-6 days'));
    $def_polar = '50';
} else {
    $def_desde = $today;
    $def_polar = '10';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PolarPrisma — <?= $vista === 'radar' ? 'Radar de la semana' : 'Radar informativo' ?></title>
  <meta name="description" content="Radar informativo: todos los temas políticos del día puntuados por polarización informativa. Sin editorial, sin cámaras de eco.">
  <link rel="icon" type="image/svg+xml" href="<?= $B ?>favicon.svg">
  <link rel="alternate" type="application/rss+xml" title="PolarPrisma · Análisis" href="<?= $B ?>rss.php">
  <link rel="alternate" type="application/rss+xml" title="PolarPrisma · Radar de polarización" href="<?= $B ?>rss.php?feed=radar">
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
    @media (max-width: 640px) {
      header nav { flex-wrap: wrap; padding: 12px 16px; gap: 6px; }
      header .nav-links {
        order: 3; width: 100%; gap: 18px; overflow-x: auto;
        -webkit-overflow-scrolling: touch; scrollbar-width: none; padding-bottom: 2px;
      }
      header .nav-links::-webkit-scrollbar { display: none; }
      header .nav-links a { white-space: nowrap; font-size: 0.88rem; }
      main { padding-top: 6.5rem !important; }
    }

    /* Main content */
    main { padding-top: 5rem; min-height: 100vh; }

    /* Banner */
    .banner {
      padding: 1.2rem 0;
      border-bottom: 1px solid var(--border);
      margin-bottom: 1.5rem;
    }
    .banner h1 {
      font-size: clamp(1.3rem, 3vw, 1.8rem); margin: 0 0 0.15em 0;
      color: var(--text);
    }
    .banner h1 em {
      font-style: italic;
      background: linear-gradient(90deg, #ff4d6d, #ff9e4d, #f2f24a, #4ade80, #4dc3ff, #a855f7);
      -webkit-background-clip: text; background-clip: text; color: transparent;
    }
    .banner p {
      color: var(--text-muted); font-size: 0.88rem; line-height: 1.5; margin: 0;
    }
    .banner a {
      color: var(--accent); text-decoration: none; font-weight: 600; font-size: 0.82rem;
      font-family: 'Inter', Arial, sans-serif;
    }
    .banner a:hover { color: var(--text); }

    /* Filters panel */
    .filters-toggle {
      display: flex; align-items: center; gap: 8px;
      padding: 10px 0; cursor: pointer;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem; font-weight: 600;
      color: var(--text-muted); letter-spacing: 0.06em; text-transform: uppercase;
      border: none; background: none; width: 100%;
    }
    .filters-toggle:hover { color: var(--text); }
    .filters-toggle svg {
      transition: transform 0.2s;
    }
    .filters-toggle.open svg {
      transform: rotate(180deg);
    }
    .filters-toggle .filter-count {
      display: inline-block; padding: 1px 7px; border-radius: 99px;
      background: var(--accent-bg); color: var(--accent);
      font-size: 0.7rem; font-weight: 700;
    }
    .filters-panel {
      max-height: 0; overflow: hidden;
      transition: max-height 0.3s ease;
    }
    .filters-panel.open {
      max-height: 500px;
    }
    .filters-inner {
      display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 16px 24px; padding: 12px 0 20px 0;
      border-bottom: 1px solid var(--border); margin-bottom: 1rem;
    }
    .filter-group label {
      display: block; font-family: 'Inter', Arial, sans-serif;
      font-size: 0.72rem; font-weight: 600; letter-spacing: 0.1em;
      text-transform: uppercase; color: var(--text-faint); margin-bottom: 6px;
    }
    .filter-group input[type="date"],
    .filter-group input[type="text"],
    .filter-group select {
      width: 100%; padding: 7px 10px;
      border: 1px solid var(--border-card); border-radius: 6px;
      background: var(--bg-card); color: var(--text);
      font-family: 'Inter', Arial, sans-serif; font-size: 0.85rem;
      transition: border-color 0.15s;
    }
    .filter-group input:focus,
    .filter-group select:focus {
      border-color: var(--accent); outline: none;
    }
    .filter-group .check-row {
      display: flex; align-items: center; gap: 8px;
      padding: 8px 0; cursor: pointer;
    }
    .filter-group .check-row input[type="checkbox"] {
      accent-color: var(--accent); width: 16px; height: 16px; cursor: pointer;
    }
    .filter-group .check-label {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.85rem;
      color: var(--text-muted); cursor: pointer;
    }
    .filters-actions {
      display: flex; gap: 10px; align-items: center;
      padding: 6px 0 16px 0;
    }
    .btn-apply {
      padding: 7px 20px; border: none; border-radius: 6px;
      background: var(--accent); color: #fff;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem; font-weight: 600;
      cursor: pointer; transition: opacity 0.15s;
    }
    .btn-apply:hover { opacity: 0.85; }
    .btn-clear {
      padding: 7px 16px; border: 1px solid var(--border-card); border-radius: 6px;
      background: transparent; color: var(--text-muted);
      font-family: 'Inter', Arial, sans-serif; font-size: 0.82rem; font-weight: 600;
      cursor: pointer; transition: all 0.15s;
    }
    .btn-clear:hover { border-color: var(--border-hover); color: var(--text); }

    /* Stats bar */
    .stats-bar {
      display: flex; align-items: center; gap: 16px; flex-wrap: wrap;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.78rem;
      color: var(--text-faint); margin-bottom: 1rem; min-height: 24px;
    }
    .stats-bar strong { color: var(--text-muted); }

    /* Article cards */
    .articles-list { display: flex; flex-direction: column; gap: 16px; padding-bottom: 2rem; }
    .article-card {
      display: flex; gap: 16px; align-items: flex-start;
      padding: 1.4rem; text-decoration: none; color: inherit;
      border: 1px solid var(--border-card); border-radius: 6px;
      background: var(--bg-card); transition: border-color 0.2s, background 0.2s;
    }
    .article-card:hover {
      border-color: var(--border-hover); background: var(--bg-card-hover);
    }
    .article-card.card-analyzed {
      border-left: 3px solid var(--accent);
    }
    .badge-analyzed {
      display: inline-flex; align-items: center; gap: 4px;
      padding: 2px 9px; background: var(--accent-bg); color: var(--accent);
      border: 1px solid var(--accent-border); border-radius: 999px;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.68rem; font-weight: 600;
      letter-spacing: 0.04em;
    }
    .article-meta {
      display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem; flex-wrap: wrap;
    }
    .article-date {
      font-family: 'Inter', Arial, sans-serif; font-size: 0.72rem;
      letter-spacing: 0.08em; text-transform: uppercase; color: var(--text-faint);
    }
    .badge-ambito {
      display: inline-block; padding: 2px 8px;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.68rem; font-weight: 600;
      letter-spacing: 0.08em; text-transform: uppercase;
      border-radius: 999px; border: 1px solid var(--border-card); color: var(--text-muted);
    }
    .article-card h2 {
      font-size: clamp(1rem, 2vw, 1.25rem); margin-bottom: 0.2em; color: var(--text);
    }
    .article-card .frase {
      color: var(--text-faint); font-size: 0.84rem; font-style: italic; margin: 0 0 0.6em 0;
    }
    .fuentes-row {
      display: flex; gap: 5px; flex-wrap: wrap; margin-top: 0.6rem;
    }
    .postura-chip {
      padding: 3px 10px; font-family: 'Inter', Arial, sans-serif;
      font-size: 0.68rem; font-weight: 500; letter-spacing: 0.03em;
      border-radius: 999px; background: var(--chip-bg); color: var(--text-muted);
    }

    /* Load more */
    .load-more-wrap {
      text-align: center; padding: 1.5rem 0 3rem 0;
    }
    .btn-load-more {
      padding: 10px 32px; border: 1px solid var(--border-card); border-radius: 6px;
      background: var(--bg-card); color: var(--text-muted);
      font-family: 'Inter', Arial, sans-serif; font-size: 0.88rem; font-weight: 600;
      cursor: pointer; transition: all 0.15s;
    }
    .btn-load-more:hover {
      border-color: var(--border-hover); color: var(--text); background: var(--bg-card-hover);
    }
    .btn-load-more:disabled {
      opacity: 0.5; cursor: default;
    }

    /* Empty state */
    .empty-state {
      text-align: center; padding: 4rem 2rem; color: var(--text-faint);
    }
    .empty-state h2 { color: var(--text); font-size: 1.3rem; }

    /* Loading spinner */
    .loading-indicator {
      text-align: center; padding: 2rem 0;
      font-family: 'Inter', Arial, sans-serif; font-size: 0.85rem; color: var(--text-faint);
    }

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
      .filters-inner { grid-template-columns: 1fr; }
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
  <?= render_nav($vista) ?>

  <main id="main-content" role="main">
    <div class="container">

      <!-- Banner -->
      <div class="banner">
      <?php if ($vista === 'radar'): ?>
        <h1>Lo más polarizado de los <em>últimos 7 días</em>.</h1>
        <p>Los temas de la semana donde los bloques ideológicos más divergen al contar la misma
          historia (polarización &ge; 50%). El índice es una fórmula pública, no una decisión editorial.
          <a href="<?= $B ?>presentacion.php">Cómo se mide &rarr;</a>
        </p>
      <?php else: ?>
        <h1>Tu algoritmo te encierra. PolarPrisma te da el <em>contexto</em>.</h1>
        <p>Las redes te muestran lo que ya crees. Aquí, cada noticia se analiza desde todas las posturas enfrentadas
          y se audita contra 11 criterios de neutralidad. Sin editorial. Sin personalización. Sin decirte qué pensar.
          <a href="<?= $B ?>presentacion.php">Cómo funciona &rarr;</a>
        </p>
      <?php endif; ?>
        <p style="font-family:Inter,Arial,sans-serif;font-size:0.82rem;margin-top:0.6rem">
          📣 Recibe cada nuevo análisis en tu móvil:
          <a href="https://t.me/prismanews_dev" target="_blank" rel="noopener">únete al canal de Telegram</a>
        </p>
      </div>

      <!-- Collapsible Filters -->
      <button class="filters-toggle" id="filters-toggle" type="button" aria-expanded="false" aria-controls="filters-panel">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 9l6 6 6-6"/></svg>
        Filtros
        <span class="filter-count" id="filter-count" style="display:none">0</span>
      </button>

      <div class="filters-panel" id="filters-panel">
        <div class="filters-inner">
          <div class="filter-group">
            <label for="f-desde">Fecha desde</label>
            <input type="date" id="f-desde" value="<?= h($def_desde) ?>">
          </div>
          <div class="filter-group">
            <label for="f-hasta">Fecha hasta</label>
            <input type="date" id="f-hasta" value="<?= h($def_hasta) ?>">
          </div>
          <div class="filter-group">
            <label for="f-buscar">Buscar</label>
            <input type="text" id="f-buscar" placeholder="Palabra clave...">
          </div>
          <div class="filter-group">
            <label for="f-polar">Polarización mínima</label>
            <select id="f-polar">
              <option value="0">Todas</option>
              <option value="10"<?= $def_polar === '10' ? ' selected' : '' ?>>&ge; 10%</option>
              <option value="20">&ge; 20%</option>
              <option value="30">&ge; 30%</option>
              <option value="40">&ge; 40%</option>
              <option value="50"<?= $def_polar === '50' ? ' selected' : '' ?>>&ge; 50%</option>
              <option value="60">&ge; 60%</option>
              <option value="70">&ge; 70%</option>
              <option value="80">&ge; 80%</option>
              <option value="90">&ge; 90%</option>
            </select>
          </div>
          <div class="filter-group">
            <label>&nbsp;</label>
            <label class="check-row">
              <input type="checkbox" id="f-analizados">
              <span class="check-label">Solo analizados</span>
            </label>
          </div>
          <div class="filter-group">
            <label for="f-orden">Ordenar por</label>
            <select id="f-orden">
              <option value="polarizacion">Polarización (mayor a menor)</option>
              <option value="fecha">Fecha (más reciente)</option>
            </select>
          </div>
        </div>
        <div class="filters-actions">
          <button class="btn-apply" id="btn-apply" type="button">Aplicar filtros</button>
          <button class="btn-clear" id="btn-clear" type="button">Limpiar</button>
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-bar" id="stats-bar">
        <span id="stats-text"></span>
      </div>

      <!-- Results -->
      <div class="articles-list" id="articles-list"></div>

      <!-- Load more -->
      <div class="load-more-wrap" id="load-more-wrap" style="display:none">
        <button class="btn-load-more" id="btn-load-more" type="button">Ver más</button>
      </div>

      <!-- Loading -->
      <div class="loading-indicator" id="loading" style="display:none">Cargando...</div>

      <!-- Empty state -->
      <div class="empty-state" id="empty-state" style="display:none">
        <h2>No hay noticias disponibles</h2>
        <p>No se encontraron temas con los filtros aplicados.</p>
      </div>
    </div>
  </main>

  <footer role="contentinfo">
    <div class="container">
      <?= render_footer_bottom() ?>
    </div>
  </footer>

  <script>
  (function() {
    var apiUrl = <?= json_encode($B . 'api_radar.php') ?>;
    var currentOffset = 0;
    var currentTotal = 0;
    var isLoading = false;

    var $list = document.getElementById('articles-list');
    var $loadMore = document.getElementById('load-more-wrap');
    var $btnMore = document.getElementById('btn-load-more');
    var $loading = document.getElementById('loading');
    var $empty = document.getElementById('empty-state');
    var $stats = document.getElementById('stats-text');
    var $filterCount = document.getElementById('filter-count');

    // Filter elements
    var $desde = document.getElementById('f-desde');
    var $hasta = document.getElementById('f-hasta');
    var $buscar = document.getElementById('f-buscar');
    var $polar = document.getElementById('f-polar');
    var $analizados = document.getElementById('f-analizados');
    var $orden = document.getElementById('f-orden');

    // Toggle filters panel
    var $toggle = document.getElementById('filters-toggle');
    var $panel = document.getElementById('filters-panel');
    $toggle.addEventListener('click', function() {
      var open = $panel.classList.toggle('open');
      $toggle.classList.toggle('open', open);
      $toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });

    function getFilters() {
      return {
        fecha_desde: $desde.value,
        fecha_hasta: $hasta.value,
        q: $buscar.value.trim(),
        polar_min: $polar.value,
        solo_analizados: $analizados.checked ? '1' : '0',
        orden: $orden.value
      };
    }

    // Valores por defecto de la vista actual (Hoy o Radar 7 días)
    var DEF = <?= json_encode(array('desde' => $def_desde, 'hasta' => $def_hasta, 'polar' => $def_polar)) ?>;

    function countActiveFilters() {
      var n = 0;
      if ($desde.value !== DEF.desde || $hasta.value !== DEF.hasta) n++;
      if ($buscar.value.trim() !== '') n++;
      if ($polar.value !== DEF.polar) n++;
      if ($analizados.checked) n++;
      if ($orden.value !== 'polarizacion') n++;
      return n;
    }

    function updateFilterBadge() {
      var n = countActiveFilters();
      if (n > 0) {
        $filterCount.textContent = n;
        $filterCount.style.display = '';
      } else {
        $filterCount.style.display = 'none';
      }
    }

    function buildQueryString(filters, offset) {
      var parts = [
        'fecha_desde=' + encodeURIComponent(filters.fecha_desde),
        'fecha_hasta=' + encodeURIComponent(filters.fecha_hasta)
      ];
      if (filters.q) parts.push('q=' + encodeURIComponent(filters.q));
      if (filters.polar_min !== '0') parts.push('polar_min=' + encodeURIComponent(filters.polar_min));
      if (filters.solo_analizados === '1') parts.push('solo_analizados=1');
      parts.push('orden=' + encodeURIComponent(filters.orden));
      parts.push('offset=' + offset);
      parts.push('limit=10');
      return parts.join('&');
    }

    function renderCard(item) {
      var card = document.createElement('a');
      card.href = item.link;
      card.className = 'article-card' + (item.analizado ? ' card-analyzed' : '');

      var analyzedBadge = '';
      if (item.analizado) {
        analyzedBadge = '<span class="badge-analyzed">'
          + '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" style="vertical-align:-1px"><path d="M20 6L9 17l-5-5"/></svg>'
          + ' Análisis multi-postura</span>';
      }

      card.innerHTML = item.circulo_html
        + '<div style="flex:1;min-width:0">'
        + '<div class="article-meta">'
        + '<span class="article-date">' + escapeHtml(item.fecha) + '</span>'
        + '<span class="badge-ambito">' + escapeHtml(item.ambito_label) + '</span>'
        + analyzedBadge
        + '<span style="font-family:\'Inter\',Arial,sans-serif;font-size:0.7rem;color:var(--text-faint);margin-left:auto">'
        + 'H ' + item.h_pct + '%</span>'
        + '</div>'
        + '<h2>' + escapeHtml(item.titulo) + '</h2>'
        + '<p class="frase">' + escapeHtml(item.frase) + '</p>'
        + '<div class="fuentes-row">' + item.fuentes_html + '</div>'
        + '</div>';

      return card;
    }

    function escapeHtml(str) {
      var div = document.createElement('div');
      div.appendChild(document.createTextNode(str));
      return div.innerHTML;
    }

    function loadResults(append) {
      if (isLoading) return;
      isLoading = true;

      var filters = getFilters();
      if (!append) {
        currentOffset = 0;
        $list.innerHTML = '';
      }

      $loading.style.display = '';
      $loadMore.style.display = 'none';
      $empty.style.display = 'none';

      var qs = buildQueryString(filters, currentOffset);
      var xhr = new XMLHttpRequest();
      xhr.open('GET', apiUrl + '?' + qs, true);
      xhr.onload = function() {
        isLoading = false;
        $loading.style.display = 'none';

        if (xhr.status !== 200) {
          $empty.querySelector('p').textContent = 'Error al cargar los resultados.';
          $empty.style.display = '';
          return;
        }

        var data;
        try { data = JSON.parse(xhr.responseText); } catch(e) {
          $empty.querySelector('p').textContent = 'Error al procesar los resultados.';
          $empty.style.display = '';
          return;
        }

        currentTotal = data.total;
        currentOffset += data.items.length;

        // Stats
        $stats.innerHTML = '<strong>' + currentTotal + '</strong> temas encontrados';

        if (data.items.length === 0 && !append) {
          $empty.querySelector('p').textContent = 'No se encontraron temas con los filtros aplicados.';
          $empty.style.display = '';
          return;
        }

        // Render cards
        data.items.forEach(function(item) {
          $list.appendChild(renderCard(item));
        });

        // Show/hide load more
        if (data.has_more) {
          $loadMore.style.display = '';
        } else {
          $loadMore.style.display = 'none';
        }

        updateFilterBadge();
      };
      xhr.onerror = function() {
        isLoading = false;
        $loading.style.display = 'none';
        $empty.querySelector('p').textContent = 'Error de conexión.';
        $empty.style.display = '';
      };
      xhr.send();
    }

    // Apply filters
    document.getElementById('btn-apply').addEventListener('click', function() {
      loadResults(false);
    });

    // Clear filters
    document.getElementById('btn-clear').addEventListener('click', function() {
      $desde.value = DEF.desde;
      $hasta.value = DEF.hasta;
      $buscar.value = '';
      $polar.value = DEF.polar;
      $analizados.checked = false;
      $orden.value = 'polarizacion';
      loadResults(false);
    });

    // Load more
    $btnMore.addEventListener('click', function() {
      loadResults(true);
    });

    // Enter key on search field
    $buscar.addEventListener('keypress', function(e) {
      if (e.key === 'Enter') loadResults(false);
    });

    // Initial load
    loadResults(false);
  })();
  </script>
  <?= theme_js() ?>
</body>
</html>
