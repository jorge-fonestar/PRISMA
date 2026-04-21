<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/lib/theme.php';
$B = prisma_base();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <title>Prisma — Rompe tu burbuja informativa. Recupera el criterio.</title>
  <meta name="description" content="Cada día sintetizamos las noticias más relevantes desde todas las posturas enfrentadas. Sin editorial, sin algoritmo, sin cámaras de eco. Únete a Prisma.">

  <link rel="canonical" href="https://prisma.example/">
  <meta name="robots" content="index, follow">
  <meta name="author" content="Equipo Prisma">
  <meta name="theme-color" content="#0a0a12">
  <?= theme_head_script() ?>
  <?= theme_css() ?>

  <!-- Open Graph -->
  <meta property="og:type" content="website">
  <meta property="og:title" content="Prisma — Rompe tu burbuja informativa. Recupera el criterio.">
  <meta property="og:description" content="Cada día, las noticias más relevantes vistas desde todas las posturas enfrentadas. Un servicio público contra la polarización.">
  <meta property="og:url" content="https://prisma.example/">
  <meta property="og:image" content="https://prisma.example/og-prisma.jpg">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="600">
  <meta property="og:image:alt" content="Logo de Prisma: un prisma descomponiendo luz blanca en múltiples colores sobre fondo oscuro">
  <meta property="og:site_name" content="Prisma">
  <meta property="og:locale" content="es_ES">

  <!-- Twitter Cards -->
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="Prisma — Rompe tu burbuja informativa">
  <meta name="twitter:description" content="Las noticias más relevantes del día desde todas las posturas enfrentadas. Contra la polarización y las cámaras de eco.">
  <meta name="twitter:image" content="https://prisma.example/og-prisma.jpg">

  <!-- Schema: Organization -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "Organization",
    "name": "Prisma",
    "url": "https://prisma.example",
    "logo": "https://prisma.example/logo.png",
    "description": "Sintetizador neutral de actualidad política que presenta cada tema desde múltiples posturas enfrentadas, auditado bajo el estándar Moral Core.",
    "foundingDate": "2026",
    "sameAs": []
  }
  </script>

  <!-- Schema: WebPage -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "WebPage",
    "name": "Prisma — Rompe tu burbuja informativa",
    "description": "Cada día sintetizamos las noticias más relevantes desde todas las posturas enfrentadas. Sin editorial, sin algoritmo, sin cámaras de eco.",
    "url": "https://prisma.example/",
    "inLanguage": "es",
    "isPartOf": {
      "@type": "WebSite",
      "name": "Prisma",
      "url": "https://prisma.example"
    },
    "breadcrumb": {
      "@type": "BreadcrumbList",
      "itemListElement": [
        {
          "@type": "ListItem",
          "position": 1,
          "name": "Inicio",
          "item": "https://prisma.example/"
        }
      ]
    }
  }
  </script>

  <!-- Schema: FAQPage -->
  <script type="application/ld+json">
  {
    "@context": "https://schema.org",
    "@type": "FAQPage",
    "mainEntity": [
      {
        "@type": "Question",
        "name": "¿Qué es Prisma?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Prisma es un servicio público de información neutral que cada día sintetiza las noticias políticas más relevantes, mostrando todas las posturas enfrentadas sobre cada tema. No es un medio de comunicación: es un cartógrafo de posturas."
        }
      },
      {
        "@type": "Question",
        "name": "¿Quién decide qué posturas aparecen?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Nadie las decide editorialmente. Un sistema automatizado lee fuentes de todo el espectro ideológico y sintetiza cada tema. Un segundo sistema independiente audita cada publicación contra 11 criterios objetivos del estándar Moral Core antes de que se publique."
        }
      },
      {
        "@type": "Question",
        "name": "¿Cómo garantizáis la neutralidad?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Cada publicación es auditada automáticamente contra 11 axiomas verificables: pluralidad de posturas, simetría léxica, atribución de fuentes, separación entre hechos y opiniones, ausencia de conclusiones prescriptivas y más. Solo se publica lo que pasa la auditoría."
        }
      },
      {
        "@type": "Question",
        "name": "¿Prisma lo escribe una inteligencia artificial?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Sí. El proceso completo de selección, síntesis y auditoría está automatizado con agentes de IA. Esto es precisamente lo que permite la neutralidad: el sistema no tiene intereses políticos ni editoriales, y aplica los mismos criterios a cada publicación sin excepción."
        }
      },
      {
        "@type": "Question",
        "name": "¿Es gratis?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "Sí. Prisma es un servicio público sin ánimo de lucro, sin publicidad y sin muros de pago. La información imparcial no debería ser un privilegio."
        }
      },
      {
        "@type": "Question",
        "name": "¿Cómo elegís qué noticias cubrir?",
        "acceptedAnswer": {
          "@type": "Answer",
          "text": "No las elegimos editorialmente. Un algoritmo calcula un índice de tensión informativa para cada tema detectado, midiendo la divergencia entre cómo lo cubren medios de distintos cuadrantes ideológicos. Los temas con mayor tensión se analizan automáticamente. El índice es público y verificable en cada tema."
        }
      }
    ]
  }
  </script>

  <style>
    /* ============ RESET + BASE ============ */
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

    /* ============ SKIP LINK ============ */
    .skip-link {
      position: absolute;
      top: -40px;
      left: 0;
      padding: 12px 20px;
      background: #fff;
      color: #0a0a12;
      text-decoration: none;
      z-index: 1000;
      font-weight: 600;
      transition: top 0.2s;
    }
    .skip-link:focus { top: 0; }

    /* ============ FOCUS STATES ============ */
    :focus-visible {
      outline: 3px solid #f2f24a;
      outline-offset: 3px;
      border-radius: 2px;
    }

    /* ============ TYPOGRAPHY ============ */
    h1, h2, h3 {
      font-family: 'Canela', 'Playfair Display', 'Didot', Georgia, serif;
      font-weight: 500;
      letter-spacing: -0.02em;
      line-height: 1.12;
      margin: 0 0 0.6em 0;
    }
    h1 { font-size: clamp(2.5rem, 6vw, 5rem); }
    h2 { font-size: clamp(2rem, 4vw, 3.25rem); }
    h3 { font-size: clamp(1.3rem, 2vw, 1.65rem); }

    p { margin: 0 0 1.2em 0; }

    .eyebrow {
      font-family: 'Inter', 'Helvetica Neue', Arial, sans-serif;
      font-size: 0.78rem;
      font-weight: 600;
      letter-spacing: 0.22em;
      text-transform: uppercase;
      color: var(--text-muted);
      margin-bottom: 1.5rem;
    }

    /* ============ LAYOUT ============ */
    .container {
      width: 100%;
      max-width: 1100px;
      margin: 0 auto;
      padding: 0 24px;
    }

    section {
      padding: clamp(5rem, 10vw, 9rem) 0;
      position: relative;
    }

    /* ============ HEADER ============ */
    header[role="banner"] {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 100;
      background: var(--bg-header);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
    }
    header nav {
      max-width: 1100px;
      margin: 0 auto;
      padding: 16px 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .logo {
      display: flex;
      align-items: center;
      gap: 10px;
      color: var(--text);
      text-decoration: none;
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
      font-size: 1.35rem;
      font-weight: 500;
      letter-spacing: -0.01em;
    }
    .logo-mark {
      width: 28px;
      height: 28px;
      flex-shrink: 0;
    }
    header .nav-links {
      display: flex;
      gap: 28px;
      list-style: none;
      margin: 0;
      padding: 0;
    }
    header .nav-links a {
      color: var(--text-muted);
      text-decoration: none;
      font-family: 'Inter', Arial, sans-serif;
      font-size: 0.92rem;
      transition: color 0.15s;
    }
    header .nav-links a:hover { color: var(--text); }
    header .nav-links a.active { color: var(--accent); }
    @media (max-width: 640px) {
      header .nav-links { display: none; }
    }

    /* ============ HERO ============ */
    #hero {
      min-height: 100vh;
      display: flex;
      align-items: center;
      padding-top: 8rem;
      padding-bottom: 6rem;
      position: relative;
      overflow: hidden;
    }

    /* Prism light refraction background */
    .hero-bg {
      position: absolute;
      inset: 0;
      z-index: 0;
      pointer-events: none;
    }
    .light-beam {
      position: absolute;
      top: 50%;
      left: -20%;
      width: 60%;
      height: 3px;
      background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.9) 70%, #fff 100%);
      transform: translateY(-50%);
      box-shadow: 0 0 20px rgba(255,255,255,0.6);
    }
    .refraction {
      position: absolute;
      top: 50%;
      right: -10%;
      width: 70%;
      height: 400px;
      transform: translateY(-50%);
      opacity: 0.55;
      filter: blur(2px);
    }
    .refraction span {
      position: absolute;
      left: 0;
      height: 2px;
      width: 100%;
      transform-origin: left center;
      border-radius: 2px;
    }
    .refraction span:nth-child(1) { top: 30%; background: linear-gradient(90deg, transparent, #ff4d6d); transform: rotate(-14deg); }
    .refraction span:nth-child(2) { top: 38%; background: linear-gradient(90deg, transparent, #ff9e4d); transform: rotate(-9deg); }
    .refraction span:nth-child(3) { top: 46%; background: linear-gradient(90deg, transparent, #f2f24a); transform: rotate(-4deg); }
    .refraction span:nth-child(4) { top: 54%; background: linear-gradient(90deg, transparent, #4ade80); transform: rotate(1deg); }
    .refraction span:nth-child(5) { top: 62%; background: linear-gradient(90deg, transparent, #4dc3ff); transform: rotate(6deg); }
    .refraction span:nth-child(6) { top: 70%; background: linear-gradient(90deg, transparent, #a855f7); transform: rotate(11deg); }

    .hero-content {
      position: relative;
      z-index: 1;
      max-width: 820px;
    }
    .hero-content h1 {
      color: var(--text);
    }
    .hero-content h1 em {
      font-style: italic;
      background: linear-gradient(90deg, #ff4d6d, #ff9e4d, #f2f24a, #4ade80, #4dc3ff, #a855f7);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
      font-weight: 500;
    }
    .hero-lede {
      font-size: clamp(1.15rem, 1.8vw, 1.4rem);
      color: var(--text-muted);
      max-width: 640px;
      margin: 1.5rem 0 2.5rem 0;
      line-height: 1.55;
    }

    /* ============ BUTTONS ============ */
    .btn-group {
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      align-items: center;
    }
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 16px 28px;
      border-radius: 999px;
      font-family: 'Inter', Arial, sans-serif;
      font-size: 0.98rem;
      font-weight: 600;
      text-decoration: none;
      transition: transform 0.15s, background 0.15s, border-color 0.15s;
      cursor: pointer;
      border: 1.5px solid transparent;
    }
    .btn-primary {
      background: #fff;
      color: #0a0a12;
    }
    .btn-primary:hover {
      transform: translateY(-2px);
      background: #f2f24a;
    }
    .btn-secondary {
      background: transparent;
      color: var(--text);
      border-color: rgba(255,255,255,0.25);
    }
    .btn-secondary:hover {
      border-color: var(--text);
      background: rgba(255,255,255,0.06);
    }

    /* ============ PROBLEM SECTION ============ */
    #problem {
      background: var(--bg-alt);
      border-top: 1px solid rgba(255, 255, 255, 0.05);
      border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    #problem h2 {
      color: var(--text);
      max-width: 900px;
    }
    .problem-lede {
      font-size: 1.25rem;
      color: var(--text-muted);
      max-width: 720px;
      margin-bottom: 4rem;
    }
    .problem-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 32px;
      margin-top: 4rem;
    }
    .problem-card {
      padding: 2rem;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 4px;
      background: rgba(255,255,255,0.015);
      position: relative;
    }
    .problem-card .stat {
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
      font-size: clamp(2.8rem, 4vw, 3.5rem);
      font-weight: 500;
      color: #ff4d6d;
      line-height: 1;
      margin-bottom: 1rem;
      letter-spacing: -0.02em;
    }
    .problem-card h3 {
      color: var(--text);
      font-size: 1.25rem;
      margin-bottom: 0.5rem;
    }
    .problem-card p {
      color: var(--text-muted);
      font-size: 0.98rem;
      margin: 0;
    }

    /* ============ SOLUTION SECTION ============ */
    #solution {
      background: var(--bg);
    }
    #solution h2 {
      color: var(--text);
      max-width: 880px;
    }
    .solution-lede {
      font-size: 1.2rem;
      color: var(--text-muted);
      max-width: 720px;
      margin-bottom: 5rem;
    }

    /* Visual anatomy of an artifact */
    .artifact-preview {
      background: #121220;
      border: 1px solid rgba(255,255,255,0.08);
      border-radius: 6px;
      padding: 2.5rem;
      margin: 3rem 0;
      position: relative;
      box-shadow: 0 30px 80px rgba(0,0,0,0.4);
    }
    .artifact-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid rgba(255,255,255,0.1);
      margin-bottom: 2rem;
      flex-wrap: wrap;
      gap: 12px;
    }
    .artifact-date {
      font-family: 'Inter', Arial, sans-serif;
      font-size: 0.82rem;
      letter-spacing: 0.14em;
      text-transform: uppercase;
      color: var(--text-faint);
    }
    .badge-apto {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      background: rgba(74, 222, 128, 0.12);
      color: #4ade80;
      border: 1px solid rgba(74, 222, 128, 0.3);
      border-radius: 999px;
      font-family: 'Inter', Arial, sans-serif;
      font-size: 0.78rem;
      font-weight: 600;
      letter-spacing: 0.05em;
    }
    .badge-apto::before {
      content: "";
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #4ade80;
      box-shadow: 0 0 8px #4ade80;
    }
    .artifact-title {
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
      font-size: 1.6rem;
      color: var(--text);
      line-height: 1.25;
      margin-bottom: 1.5rem;
    }
    .artifact-label {
      font-family: 'Inter', Arial, sans-serif;
      font-size: 0.72rem;
      font-weight: 600;
      letter-spacing: 0.18em;
      text-transform: uppercase;
      color: var(--text-faint);
      margin: 2rem 0 1rem 0;
    }
    .positions-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 16px;
    }
    .position {
      padding: 1.25rem;
      border-left: 3px solid;
      background: rgba(255,255,255,0.02);
      border-radius: 0 4px 4px 0;
    }
    .position.a { border-left-color: #ff4d6d; }
    .position.b { border-left-color: var(--accent); }
    .position.c { border-left-color: #4dc3ff; }
    .position-label {
      font-family: 'Inter', Arial, sans-serif;
      font-size: 0.72rem;
      font-weight: 700;
      letter-spacing: 0.1em;
      text-transform: uppercase;
      color: var(--text-muted);
      margin-bottom: 0.5rem;
    }
    .position p {
      margin: 0;
      font-size: 0.95rem;
      color: var(--text-muted);
      line-height: 1.55;
    }

    /* Pillars */
    .pillars {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 48px;
      margin-top: 5rem;
    }
    .pillar {
      position: relative;
    }
    .pillar-num {
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
      font-size: 3rem;
      color: transparent;
      -webkit-text-stroke: 1.5px rgba(255,255,255,0.25);
      line-height: 1;
      margin-bottom: 1rem;
      font-weight: 300;
    }
    .pillar h3 {
      color: var(--text);
      font-size: 1.35rem;
      margin-bottom: 0.8rem;
    }
    .pillar p {
      color: var(--text-muted);
      font-size: 1rem;
      margin: 0;
    }

    /* ============ HOW IT WORKS ============ */
    #how {
      background: var(--bg-alt);
      border-top: 1px solid rgba(255, 255, 255, 0.05);
    }
    #how h2 {
      color: var(--text);
    }
    .steps {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 40px;
      margin-top: 4rem;
      counter-reset: step;
    }
    .step {
      position: relative;
      padding-top: 1.5rem;
    }
    .step::before {
      counter-increment: step;
      content: counter(step, decimal-leading-zero);
      font-family: 'Inter', Arial, sans-serif;
      font-size: 0.82rem;
      font-weight: 700;
      letter-spacing: 0.2em;
      color: var(--accent);
      position: absolute;
      top: 0;
      left: 0;
    }
    .step h3 {
      color: var(--text);
      font-size: 1.2rem;
      margin-top: 0;
    }
    .step p {
      color: var(--text-muted);
      font-size: 0.98rem;
      margin: 0;
    }

    /* ============ FAQ ============ */
    #faq { background: var(--bg); }
    #faq h2 { color: var(--text); }
    .faq-list {
      margin-top: 3rem;
      max-width: 820px;
    }
    details {
      border-bottom: 1px solid rgba(255,255,255,0.08);
      padding: 1.5rem 0;
    }
    details[open] summary { color: var(--text); }
    summary {
      font-family: 'Canela', 'Playfair Display', Georgia, serif;
      font-size: 1.35rem;
      font-weight: 500;
      color: var(--text);
      cursor: pointer;
      list-style: none;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 24px;
      transition: color 0.15s;
    }
    summary::-webkit-details-marker { display: none; }
    summary::after {
      content: "+";
      font-family: 'Inter', Arial, sans-serif;
      font-size: 1.6rem;
      font-weight: 300;
      color: var(--accent);
      transition: transform 0.2s;
      flex-shrink: 0;
    }
    details[open] summary::after {
      transform: rotate(45deg);
    }
    details p {
      color: var(--text-muted);
      margin: 1rem 0 0 0;
      font-size: 1rem;
      line-height: 1.65;
    }
    summary:hover { color: var(--text); }

    /* ============ CTA ============ */
    #cta {
      background: linear-gradient(180deg, #0a0a12 0%, #12121f 100%);
      text-align: center;
      border-top: 1px solid rgba(255, 255, 255, 0.05);
    }
    #cta .container {
      max-width: 820px;
    }
    #cta h2 {
      color: var(--text);
      font-size: clamp(2.2rem, 5vw, 3.8rem);
    }
    #cta h2 em {
      font-style: italic;
      background: linear-gradient(90deg, #ff4d6d, #f2f24a, #4dc3ff);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    #cta p {
      color: var(--text-muted);
      font-size: 1.15rem;
      margin: 1.5rem auto 3rem auto;
      max-width: 620px;
    }
    #cta .btn-group { justify-content: center; }

    /* ============ FOOTER ============ */
    footer[role="contentinfo"] {
      padding: 4rem 0 3rem 0;
      border-top: 1px solid rgba(255,255,255,0.06);
      background: var(--bg-footer);
    }
    footer .footer-grid {
      display: grid;
      grid-template-columns: 2fr 1fr 1fr 1fr;
      gap: 48px;
      margin-bottom: 3rem;
    }
    @media (max-width: 720px) {
      footer .footer-grid {
        grid-template-columns: 1fr 1fr;
        gap: 32px;
      }
    }
    .footer-brand p {
      color: var(--text-faint);
      font-size: 0.95rem;
      margin-top: 1rem;
      max-width: 320px;
    }
    footer h4 {
      font-family: 'Inter', Arial, sans-serif;
      font-size: 0.78rem;
      font-weight: 600;
      letter-spacing: 0.16em;
      text-transform: uppercase;
      color: var(--text-muted);
      margin: 0 0 1rem 0;
    }
    footer ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }
    footer li { margin-bottom: 0.6rem; }
    footer a {
      color: var(--text-faint);
      text-decoration: none;
      font-size: 0.92rem;
      transition: color 0.15s;
    }
    footer a:hover { color: var(--text); }
    .footer-bottom {
      padding-top: 2rem;
      border-top: 1px solid rgba(255,255,255,0.05);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 16px;
    }
    .footer-bottom p {
      color: var(--text-faintest);
      font-size: 0.85rem;
      margin: 0;
    }
    .ai-notice {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 14px;
      background: rgba(242, 242, 74, 0.08);
      border: 1px solid rgba(242, 242, 74, 0.2);
      border-radius: 999px;
      color: var(--accent);
      font-family: 'Inter', Arial, sans-serif;
      font-size: 0.78rem;
      font-weight: 500;
      letter-spacing: 0.05em;
    }
    .ai-notice::before {
      content: "";
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: #f2f24a;
      box-shadow: 0 0 6px #f2f24a;
    }

    /* ============ ACCESSIBILITY ============ */
    @media (prefers-reduced-motion: reduce) {
      *, *::before, *::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
      }
    }
  </style>
</head>
<body>
  <a href="#main-content" class="skip-link">Saltar al contenido principal</a>

  <header role="banner">
    <nav aria-label="Navegación principal">
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
        <li><a href="<?= $B ?>manifiesto.php" class="active">El proyecto</a></li>
      </ul>
      <?= theme_toggle() ?>
    </nav>
  </header>

  <main id="main-content" role="main">

    <!-- ============ HERO ============ -->
    <section id="hero" aria-labelledby="hero-heading">
      <div class="hero-bg" aria-hidden="true">
        <div class="light-beam"></div>
        <div class="refraction">
          <span></span><span></span><span></span>
          <span></span><span></span><span></span>
        </div>
      </div>
      <div class="container">
        <div class="hero-content">
          <p class="eyebrow">Información · Neutralidad · Pensamiento crítico</p>
          <h1 id="hero-heading">
            Rompe tu burbuja.<br>
            Recupera tu <em>criterio</em>.
          </h1>
          <p class="hero-lede">
            Cada día, las noticias políticas más relevantes presentadas desde
            todas las posturas enfrentadas. Sin editorial. Sin algoritmo que
            te dé la razón. Sin cámaras de eco. Un servicio público contra la
            polarización.
          </p>
          <div class="btn-group">
            <a href="<?= $B ?>" class="btn btn-primary">
              Ver las noticias de hoy
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
            </a>
            <a href="#problem" class="btn btn-secondary">Entender el problema</a>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ PROBLEM ============ -->
    <section id="problem" aria-labelledby="problem-heading">
      <div class="container">
        <p class="eyebrow">El problema</p>
        <h2 id="problem-heading">
          Cada día lees noticias. Cada día estás más convencido<br>
          de que los otros están equivocados.
        </h2>
        <p class="problem-lede">
          No es casualidad. Los algoritmos que deciden qué ves están diseñados
          para mantenerte en la pantalla, no para informarte. Y la forma más
          rentable de mantenerte es darte la razón una y otra vez. Hasta que
          dejas de entender al que piensa distinto.
        </p>

        <div class="problem-grid">
          <article class="problem-card">
            <div class="stat" aria-hidden="true">73%</div>
            <h3>Evita perspectivas opuestas</h3>
            <p>De los usuarios de redes sociales consume principalmente contenido
            que refuerza sus creencias previas, según estudios sobre sesgo de
            confirmación digital.</p>
          </article>
          <article class="problem-card">
            <div class="stat" aria-hidden="true">×3</div>
            <h3>Polarización acelerada</h3>
            <p>La distancia ideológica percibida entre grupos se ha triplicado
            en una década. Ya no discrepamos: nos consideramos incompatibles.</p>
          </article>
          <article class="problem-card">
            <div class="stat" aria-hidden="true">0</div>
            <h3>Contacto con la otra mitad</h3>
            <p>La mayoría de ciudadanos no ha leído un argumento bien expuesto
            del bando contrario en todo el año. Solo caricaturas.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- ============ SOLUTION ============ -->
    <section id="solution" aria-labelledby="solution-heading">
      <div class="container">
        <p class="eyebrow">La propuesta</p>
        <h2 id="solution-heading">
          No te decimos qué pensar.<br>
          Te mostramos todo lo que se está pensando.
        </h2>
        <p class="solution-lede">
          Prisma no es un medio de comunicación. Es un cartógrafo de posturas.
          Cada día seleccionamos las 5 noticias políticas más relevantes y, para
          cada una, mostramos simultáneamente las distintas posturas enfrentadas
          con sus argumentos y sus fuentes.
        </p>

        <!-- Anatomy of an artifact -->
        <div class="artifact-preview" role="img" aria-label="Ejemplo visual de cómo se presenta una noticia en Prisma, con tres posturas enfrentadas">
          <div class="artifact-header">
            <span class="artifact-date">Muestra · Así se ve una noticia</span>
            <span class="badge-apto">Auditoría Moral Core · APTO</span>
          </div>
          <h3 class="artifact-title">[Titular neutral reformulado sin carga emocional]</h3>
          <p style="color:#9a9aaa;font-size:0.95rem;margin:0 0 1rem 0">
            Resumen factual en 3-4 líneas que describen el tema sin posicionamiento,
            separando lo que es hecho verificable de lo que es interpretación.
          </p>
          <p class="artifact-label">Mapa de posturas</p>
          <div class="positions-grid">
            <div class="position a">
              <div class="position-label">Postura A</div>
              <p>Argumentos, defensores y fuentes citadas del primer ángulo del debate.</p>
            </div>
            <div class="position b">
              <div class="position-label">Postura B</div>
              <p>Argumentos, defensores y fuentes citadas del segundo ángulo del debate.</p>
            </div>
            <div class="position c">
              <div class="position-label">Postura C</div>
              <p>Argumentos, defensores y fuentes citadas del tercer ángulo del debate.</p>
            </div>
          </div>
          <p class="artifact-label">Lo que no se está diciendo</p>
          <p style="color:#9a9aaa;font-size:0.95rem;margin:0 0 1.5rem 0;">
            Ángulos ausentes en la cobertura dominante. Silencios deliberados o no.
          </p>
          <p class="artifact-label">Preguntas para pensar</p>
          <p style="color:#9a9aaa;font-size:0.95rem;margin:0;">
            2-3 preguntas abiertas. Sin respuesta implícita. Sin recomendación.
          </p>
        </div>

        <div class="pillars">
          <div class="pillar">
            <div class="pillar-num" aria-hidden="true">01</div>
            <h3>Neutralidad por diseño</h3>
            <p>Nuestro sistema audita cada publicación contra 11 criterios objetivos
            antes de publicarla: pluralidad de posturas, simetría léxica, atribución
            de fuentes, separación hecho/opinión.</p>
          </div>
          <div class="pillar">
            <div class="pillar-num" aria-hidden="true">02</div>
            <h3>Visión 360°</h3>
            <p>Cada tema se presenta con al menos tres posturas enfrentadas,
            citadas con fuentes de distintos cuadrantes ideológicos. Tú decides
            con qué te quedas.</p>
          </div>
          <div class="pillar">
            <div class="pillar-num" aria-hidden="true">03</div>
            <h3>Transparencia radical</h3>
            <p>Todas las fuentes citadas. Todos los criterios explicados.
            Auditoría pública. Sin publicidad. Sin editorial. Sin muros de pago.
            Licencia abierta.</p>
          </div>
        </div>
      </div>
    </section>

    <!-- ============ HOW ============ -->
    <section id="how" aria-labelledby="how-heading">
      <div class="container">
        <p class="eyebrow">El proceso</p>
        <h2 id="how-heading">Cómo se construye una noticia en Prisma</h2>
        <p style="font-size:0.95rem;color:var(--text-muted);margin-bottom:2rem">El proceso se divide en dos fases independientes. La primera es gratuita y se ejecuta varias veces al día. La segunda gasta recursos de IA y se ejecuta selectivamente.</p>

        <div class="steps">
          <article class="step">
            <h3 style="color:var(--text-faint);font-size:0.78rem;letter-spacing:0.1em;margin-bottom:0.3rem">FASE 1 · ESCANEO</h3>
            <h3>Lectura del espectro</h3>
            <p>Un sistema automatizado lee las fuentes RSS de todo el espectro
            ideológico: de izquierda, centro y derecha, española, europea y global.
            Todos los temas detectados se publican en el radar con su puntuación.</p>
          </article>
          <article class="step">
            <h3>Detección de tensión informativa</h3>
            <p>El sistema no busca las noticias más importantes ni las más virales.
            Busca las más tensas: aquellas donde los medios de distintos cuadrantes ideológicos
            cuentan la misma historia de formas radicalmente distintas — o donde un lado habla
            y el otro calla. Son los temas que más necesitan una visión multi-postura.</p>
            <details style="margin-top:0.5rem">
              <summary style="font-size:0.88rem;color:var(--text-muted);cursor:pointer">Detalles técnicos del algoritmo</summary>
              <p style="font-size:0.88rem;margin-top:0.5rem">El algoritmo calcula un índice de tensión informativa
              para cada tema detectado, combinando tres señales matemáticas: la asimetría de cobertura (qué
              proporción de fuentes de cada lado del espectro cubren el tema — un silencio editorial es tan
              revelador como un titular), la divergencia léxica (distancia Jaccard entre el vocabulario que usa
              cada cuadrante para describir el mismo hecho) y la varianza del espectro (dispersión de las
              posiciones ideológicas que cubren el tema). Investigadores del MIT Media Lab y Harvard (proyecto
              Media Cloud) han demostrado que la selección de cobertura — qué elige contar cada medio y qué
              elige ignorar — es la señal más fiable de sesgo editorial, por encima del análisis de
              sentimiento o del framing textual. El índice de tensión de cada tema es público y verificable
              en su ficha.</p>
            </details>
          </article>
          <article class="step">
            <h3 style="color:var(--text-faint);font-size:0.78rem;letter-spacing:0.1em;margin-bottom:0.3rem">FASE 2 · ANÁLISIS</h3>
            <h3>Confirmación y síntesis multi-postura</h3>
            <p>Los temas con mayor tensión pasan un filtro de confirmación (triage) que descarta
            falsos positivos. Los confirmados se sintetizan: un agente de IA genera el artefacto
            mostrando todas las posturas enfrentadas con sus argumentos y fuentes originales.</p>
          </article>
          <article class="step">
            <h3>Auditoría independiente</h3>
            <p>Un segundo agente, en contexto completamente separado, audita el resultado contra
            <a href="<?= prisma_base() ?>axiomas.php">11 axiomas verificables</a>. Si no
            pasa, se regenera o se descarta. Solo se publica lo verificado.</p>
          </article>
        </div>
      </div>
    </section>

    <!-- ============ FAQ ============ -->
    <section id="faq" aria-labelledby="faq-heading">
      <div class="container">
        <p class="eyebrow">Preguntas frecuentes</p>
        <h2 id="faq-heading">Lo que probablemente te estás preguntando</h2>

        <div class="faq-list">
          <details>
            <summary>¿Quién está detrás de Prisma?</summary>
            <p>Prisma es un proyecto anónimo e independiente, sin ánimo de lucro
            y sin afiliación política, mediática o corporativa. Esta independencia
            es deliberada: lo que importa es si el contenido pasa la auditoría, no
            quién lo firma. Es un servicio público autogestionado.</p>
          </details>
          <details>
            <summary>¿Cómo garantizáis que no tenéis sesgo propio?</summary>
            <p>Cada publicación se audita contra 11 criterios verificables: pluralidad
            de posturas (mínimo 3), pluralidad de fuentes (múltiples cuadrantes
            ideológicos), simetría de extensión, simetría léxica, atribución
            verificable, separación hecho/opinión, ausencia de conclusión prescriptiva,
            transparencia de límites y más. Si un tema no supera el umbral, no se
            publica. El sistema es el mismo para todos los temas.</p>
          </details>
          <details>
            <summary>¿Lo escribe una inteligencia artificial?</summary>
            <p>Sí. El proceso completo de selección, síntesis y auditoría está
            automatizado con agentes de IA. Esto es precisamente lo que permite
            la neutralidad: el sistema no tiene intereses políticos, no tiene
            opiniones personales que defender, y aplica exactamente los mismos
            criterios a cada publicación sin excepción. Los humanos escribimos
            el estándar; la máquina lo ejecuta.</p>
          </details>
          <details>
            <summary>¿Cómo elegís qué noticias cubrir?</summary>
            <p>No las elegimos: las calcula un algoritmo. Cada día, el sistema lee
            los titulares de más de 28 fuentes de todo el espectro ideológico, agrupa
            las que hablan del mismo tema, y calcula un índice de tensión informativa
            para cada uno. Los temas con mayor tensión — donde hay más divergencia
            entre cómo los cuenta cada lado — son seleccionados automáticamente para
            análisis. El índice es una fórmula matemática transparente, no una decisión
            editorial. Puedes ver el porcentaje de tensión y su desglose en cada tema.</p>
          </details>
          <details>
            <summary>¿Por qué debería confiar en vosotros y no en mi medio habitual?</summary>
            <p>No te pedimos que confíes en nosotros: te pedimos que verifiques.
            Cada postura tiene su fuente original enlazada. Cada afirmación
            disputada tiene atribución. Cada auditoría es pública. Puedes
            revisarlo todo. De hecho, esperamos que lo hagas.</p>
          </details>
          <details>
            <summary>¿Es gratis? ¿Tiene publicidad?</summary>
            <p>Sí, es completamente gratis. No hay publicidad, ni muros de pago,
            ni seguimiento del usuario, ni personalización algorítmica. La
            información imparcial no debería ser un privilegio. El proyecto se
            sostiene mediante donaciones voluntarias (en fases futuras).</p>
          </details>
          <details>
            <summary>¿Puedo reutilizar el contenido?</summary>
            <p>Sí. Todo el contenido publicado está disponible bajo licencia
            Creative Commons BY-SA 4.0: puedes compartirlo, adaptarlo y
            reutilizarlo libremente siempre que cites la fuente y conserves la
            misma licencia. Queremos que la información circule.</p>
          </details>
        </div>
      </div>
    </section>

    <!-- ============ CTA ============ -->
    <section id="cta" aria-labelledby="cta-heading">
      <div class="container">
        <p class="eyebrow">Democracia real</p>
        <h2 id="cta-heading">
          La democracia no es votar<br>
          cada cuatro años. Es <em>entenderse</em>.
        </h2>
        <p>
          Democracia es dialogar, escuchar, compartir y buscar puntos de
          entendimiento. La polarización destruye todo eso. Cuando dejas de
          entender al que piensa distinto, dejas de poder convivir con él.
          Y una sociedad que no convive no se gobierna: se somete.
        </p>
        <p>
          Prisma existe para eso. Para que leas una noticia desde todas sus
          caras. Para que notes cómo cambian tus certezas. Para que compartas
          con alguien que piense distinto. Así se desmonta una cámara de eco,
          una noticia a la vez.
        </p>
        <div class="btn-group">
          <a href="<?= $B ?>" class="btn btn-primary">
            Leer las noticias de hoy
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M5 12h14M13 5l7 7-7 7"/></svg>
          </a>
          <a href="<?= $B ?>axiomas.php" class="btn btn-secondary">Conocer los 11 axiomas</a>
        </div>
      </div>
    </section>

  </main>

  <!-- ============ FOOTER ============ -->
  <footer role="contentinfo">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <div class="logo" style="pointer-events:none">
            <svg class="logo-mark" viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <polygon points="16,4 28,26 4,26" fill="none" stroke="url(#prismGrad)" stroke-width="1.8" stroke-linejoin="round"/>
            </svg>
            <span>Prisma</span>
          </div>
          <p>Servicio público de información neutral. Sin editorial, sin algoritmo, sin cámaras de eco. Licencia CC BY-SA 4.0.</p>
        </div>
        <div>
          <h4>Proyecto</h4>
          <ul>
            <li><a href="<?= $B ?>">Hoy</a></li>
            <li><a href="<?= $B ?>manifiesto.php">El proyecto</a></li>
            <li><a href="<?= $B ?>archivo.php">Archivo</a></li>
            <li><a href="<?= $B ?>ia.php">Aviso de IA</a></li>
          </ul>
        </div>
        <div>
          <h4>Estándar</h4>
          <ul>
            <li><a href="https://moralcore.org">Moral Core</a></li>
            <li><a href="<?= $B ?>axiomas.php">Los 11 axiomas</a></li>
            <li><a href="<?= $B ?>fuentes.php">Fuentes consultadas</a></li>
          </ul>
        </div>
        <div>
          <h4>Legal</h4>
          <ul>
            <li><a href="<?= $B ?>aviso-legal.php">Aviso legal</a></li>
            <li><a href="<?= $B ?>privacidad.php">Privacidad</a></li>
            <li><a href="<?= $B ?>cookies.php">Cookies</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2026 Prisma · Proyecto independiente · Contenido bajo licencia Creative Commons BY-SA 4.0</p>
        <span class="ai-notice">Contenido generado y auditado por IA</span>
      </div>
    </div>
  </footer>

  <?= theme_js() ?>
</body>
</html>
