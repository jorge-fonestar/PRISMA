<?php
require_once __DIR__ . '/lib/layout.php';
page_header('Los 11 axiomas Moral Core', 'Los 11 criterios verificables que debe cumplir cada publicación de Prisma antes de ser publicada.');
?>

<div class="page-top">
  <p class="eyebrow">Estándar Moral Core</p>
  <h1>Los 11 axiomas</h1>
  <p>Cada publicación de Prisma se evalúa contra estos 11 criterios antes de publicarse. Si no supera el umbral, se regenera o se descarta.</p>
  <p style="font-size:0.9rem;color:var(--text-faint)">Los 11 axiomas evalúan la calidad del análisis una vez producido. La selección de qué temas analizar se rige por un criterio distinto: el <a href="<?= prisma_base() ?>fuentes.php">índice de tensión informativa</a>, una fórmula matemática que mide la divergencia editorial entre fuentes.</p>
</div>

<div class="content">

  <h2>Pluralidad</h2>

  <div class="card">
    <h3>A1 — Pluralidad de posturas</h3>
    <p>El artefacto debe identificar al menos 3 posturas distintas de forma explícita. No basta con "a favor y en contra": el mundo real tiene matices y posiciones intermedias que deben estar representadas.</p>
  </div>

  <div class="card">
    <h3>A2 — Pluralidad de fuentes</h3>
    <p>Las fuentes citadas deben provenir de múltiples cuadrantes ideológicos distintos. En España se exigen al menos 3 cuadrantes; en ámbitos europeo y global, al menos 2, en proporción a la diversidad disponible.</p>
  </div>

  <h2>Simetría</h2>

  <div class="card">
    <h3>A3 — Simetría de extensión</h3>
    <p>Ninguna postura puede ocupar más del 50% del espacio total ni menos del 15%. El espacio que dedicas a cada postura transmite un juicio de relevancia: mantener proporciones equilibradas evita el encuadre oculto.</p>
  </div>

  <div class="card">
    <h3>A4 — Simetría léxica</h3>
    <p>El lenguaje usado para describir cada postura debe ser equivalente en carga emocional. Si describes una postura con "advierte", no describas otra con "denuncia" o "clama". Las palabras transmiten juicio incluso cuando el contenido es factual.</p>
  </div>

  <h2>Atribución y rigor</h2>

  <div class="card">
    <h3>A5 — Atribución verificable</h3>
    <p>Toda afirmación fáctica disputada debe estar atribuida a una fuente concreta con enlace directo. Prohibido "los expertos dicen" o "según analistas". Si no puedes citar quién lo dice, no lo incluyas.</p>
  </div>

  <div class="card">
    <h3>A6 — Distinción hecho/opinión</h3>
    <p>Los elementos presentados como hechos deben ser verificables. Los presentados como posturas deben ser claramente opiniones o interpretaciones. Mezclar ambos sin marcarlos es una de las formas más comunes de sesgo.</p>
  </div>

  <h2>Neutralidad</h2>

  <div class="card">
    <h3>A7 — Ausencia de conclusión prescriptiva</h3>
    <p>El texto no puede recomendar qué pensar ni qué hacer. No cierres con "lo razonable sería..." ni con "queda claro que...". Cierra con preguntas abiertas genuinas.</p>
  </div>

  <div class="card">
    <h3>A8 — Transparencia de límites</h3>
    <p>Si los datos son parciales o contradictorios, hay que decirlo explícitamente. No rellenar huecos con inferencias. La incertidumbre honesta es más valiosa que una falsa certeza.</p>
  </div>

  <h2>Completitud</h2>

  <div class="card">
    <h3>A9 — Ausencia de omisión crítica</h3>
    <p>No puede faltar ninguna postura mayoritaria del debate público. Omitir una perspectiva relevante es una forma de sesgo tan potente como distorsionarla.</p>
  </div>

  <div class="card">
    <h3>A10 — Coherencia con fuentes</h3>
    <p>Cada postura debe corresponderse con lo que las fuentes citadas realmente dicen. Este axioma es el principal mecanismo anti-alucinación: impide que el sistema atribuya argumentos inventados a fuentes reales.</p>
  </div>

  <div class="card">
    <h3>A11 — Ausencia de sesgo geopolítico de bloque</h3>
    <p>En temas internacionales, el artefacto debe evitar favorecer narrativas de un bloque geopolítico específico. No presentar la perspectiva occidental como "neutral" y las demás como "propaganda", ni viceversa.</p>
  </div>

  <h2>Reglas de publicación</h2>
  <ul>
    <li><strong>APTO</strong> (≥10 de 11 axiomas pasan) — publicación automática.</li>
    <li><strong>REVISIÓN</strong> (8-9 de 11 pasan) — regeneración con feedback del Auditor. Máximo 2 reintentos. Si persiste, se publica marcado como REVISIÓN.</li>
    <li><strong>RECHAZO</strong> (&lt;8 de 11 pasan) — descarte del tema. Se guarda para análisis posterior.</li>
  </ul>
</div>

<?php page_footer(); ?>
