<?php
/**
 * Prisma — Sistema de temas claro/oscuro/sistema.
 *
 * Incluir en cada página:
 *   <?php require_once __DIR__ . '/lib/theme.php'; ?>
 *   En <head>: <?= theme_css() ?>
 *   En header nav (después de nav-links): <?= theme_toggle() ?>
 *   Antes de </body>: <?= theme_js() ?>
 */

function theme_css(): string {
    return <<<'CSS'
<style>
  /* ── Theme variables ─────────────────────────────────────────── */
  :root {
    --bg:          #0a0a12;
    --bg-alt:      #070710;
    --bg-card:     rgba(255,255,255,0.015);
    --bg-card-hover: rgba(255,255,255,0.03);
    --bg-header:   rgba(10, 10, 18, 0.85);
    --bg-footer:   #050509;
    --text:        #e8e8ec;
    --text-muted:  #9a9aaa;
    --text-faint:  #7a7a8a;
    --text-faintest:#5a5a6a;
    --border:      rgba(255,255,255,0.06);
    --border-card: rgba(255,255,255,0.08);
    --border-hover:rgba(255,255,255,0.2);
    --accent:      #f2f24a;
    --accent-bg:   rgba(242,242,74,0.08);
    --accent-border:rgba(242,242,74,0.2);
    --link:        #4dc3ff;
    --green:       #4ade80;
    --red:         #ff4d6d;
    --green-bg:    rgba(74,222,128,0.12);
    --green-border:rgba(74,222,128,0.3);
    --chip-bg:     rgba(255,255,255,0.06);
    --shadow:      rgba(0,0,0,0.4);
    color-scheme: dark;
  }

  [data-theme="light"] {
    --bg:          #f5f5f0;
    --bg-alt:      #eaeae4;
    --bg-card:     #fff;
    --bg-card-hover: #fafaf8;
    --bg-header:   rgba(245, 245, 240, 0.9);
    --bg-footer:   #e8e8e2;
    --text:        #1a1a2e;
    --text-muted:  #555566;
    --text-faint:  #777788;
    --text-faintest:#999aaa;
    --border:      rgba(0,0,0,0.08);
    --border-card: rgba(0,0,0,0.1);
    --border-hover:rgba(0,0,0,0.25);
    --accent:      #b8a900;
    --accent-bg:   rgba(184,169,0,0.08);
    --accent-border:rgba(184,169,0,0.25);
    --link:        #0070c0;
    --green:       #16a34a;
    --red:         #dc2626;
    --green-bg:    rgba(22,163,74,0.1);
    --green-border:rgba(22,163,74,0.25);
    --chip-bg:     rgba(0,0,0,0.05);
    --shadow:      rgba(0,0,0,0.08);
    color-scheme: light;
  }

  /* Apply variables */
  body { color: var(--text); background: var(--bg); }
  header[role="banner"] { background: var(--bg-header); border-bottom-color: var(--border); }
  .logo, .logo span { color: var(--text); }
  header .nav-links a { color: var(--text-muted); }
  header .nav-links a:hover { color: var(--text); }
  header .nav-links a.active { color: var(--accent); }
  .eyebrow { color: var(--text-muted); }
  footer[role="contentinfo"] { background: var(--bg-footer); border-top-color: var(--border); }
  .footer-bottom p { color: var(--text-faintest); }
  a { color: var(--link); }
  a:hover { color: var(--text); }

  /* Theme toggle button */
  .theme-toggle {
    background: none; border: 1px solid var(--border-card); border-radius: 6px;
    padding: 6px; cursor: pointer; color: var(--text-muted);
    display: flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; transition: color 0.15s, border-color 0.15s;
    flex-shrink: 0;
  }
  .theme-toggle:hover { color: var(--text); border-color: var(--border-hover); }
  .theme-toggle svg { width: 16px; height: 16px; }
  .theme-toggle .icon-dark,
  .theme-toggle .icon-light,
  .theme-toggle .icon-system { display: none; }
  [data-theme="dark"] .theme-toggle .icon-dark { display: block; }
  [data-theme="light"] .theme-toggle .icon-light { display: block; }
  :root:not([data-theme]) .theme-toggle .icon-system { display: block; }
</style>
CSS;
}

function theme_toggle(): string {
    return <<<'HTML'
<button class="theme-toggle" id="theme-toggle" aria-label="Cambiar tema" title="Cambiar tema">
  <!-- Moon (dark mode active) -->
  <svg class="icon-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1111.21 3a7 7 0 009.79 9.79z"/></svg>
  <!-- Sun (light mode active) -->
  <svg class="icon-light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="5"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
  <!-- Monitor (system mode active) -->
  <svg class="icon-system" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/></svg>
</button>
HTML;
}

function theme_js(): string {
    return <<<'JS'
<script>
(function() {
  var STORAGE_KEY = 'prisma-theme';
  var html = document.documentElement;
  var btn = document.getElementById('theme-toggle');
  var modes = ['system', 'dark', 'light'];

  function getSystemTheme() {
    return window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
  }

  function apply(mode) {
    if (mode === 'system') {
      html.removeAttribute('data-theme');
      html.setAttribute('data-theme', getSystemTheme());
      // Re-show system icon
      setTimeout(function() { html.removeAttribute('data-theme-mode'); html.setAttribute('data-theme-mode', 'system'); }, 0);
    } else {
      html.setAttribute('data-theme', mode);
      html.setAttribute('data-theme-mode', mode);
    }
  }

  function getStored() {
    try { return localStorage.getItem(STORAGE_KEY) || 'system'; } catch(e) { return 'system'; }
  }

  function store(mode) {
    try { localStorage.setItem(STORAGE_KEY, mode); } catch(e) {}
  }

  // Override the icon visibility to use mode instead of resolved theme
  var style = document.createElement('style');
  style.textContent =
    '[data-theme-mode="dark"] .theme-toggle .icon-dark { display: block !important; }' +
    '[data-theme-mode="dark"] .theme-toggle .icon-light { display: none !important; }' +
    '[data-theme-mode="dark"] .theme-toggle .icon-system { display: none !important; }' +
    '[data-theme-mode="light"] .theme-toggle .icon-dark { display: none !important; }' +
    '[data-theme-mode="light"] .theme-toggle .icon-light { display: block !important; }' +
    '[data-theme-mode="light"] .theme-toggle .icon-system { display: none !important; }' +
    '[data-theme-mode="system"] .theme-toggle .icon-dark { display: none !important; }' +
    '[data-theme-mode="system"] .theme-toggle .icon-light { display: none !important; }' +
    '[data-theme-mode="system"] .theme-toggle .icon-system { display: block !important; }';
  document.head.appendChild(style);

  // Init
  var current = getStored();
  apply(current);
  html.setAttribute('data-theme-mode', current);

  // Cycle on click: dark → light → system → dark
  if (btn) {
    btn.addEventListener('click', function() {
      var cur = getStored();
      var next = modes[(modes.indexOf(cur) + 1) % modes.length];
      store(next);
      apply(next);
      html.setAttribute('data-theme-mode', next);
      btn.title = next === 'system' ? 'Tema: sistema' : next === 'light' ? 'Tema: claro' : 'Tema: oscuro';
    });
  }

  // Listen for system changes when in system mode
  window.matchMedia('(prefers-color-scheme: light)').addEventListener('change', function() {
    if (getStored() === 'system') apply('system');
  });
})();
</script>
JS;
}

/**
 * Inline script to prevent flash of wrong theme.
 * Put this in <head> BEFORE any CSS.
 */
function theme_head_script(): string {
    return <<<'JS'
<script>
(function(){try{var t=localStorage.getItem('prisma-theme')||'system';if(t==='system'){t=window.matchMedia('(prefers-color-scheme:light)').matches?'light':'dark'}document.documentElement.setAttribute('data-theme',t)}catch(e){}})();
</script>
JS;
}
