<?php
require_once __DIR__ . '/lib/layout.php';
page_header('Aviso de inteligencia artificial', 'Todo el contenido de Prisma es generado y auditado por agentes de IA.');
?>

<div class="page-top">
  <p class="eyebrow">Transparencia</p>
  <h1>Aviso de inteligencia artificial</h1>
</div>

<div class="content">
  <p><strong>Todo el contenido publicado en Prisma es generado y auditado íntegramente por agentes de inteligencia artificial.</strong> No hay redacción humana, no hay editor, no hay intervención manual en el proceso editorial.</p>

  <h2>Por qué usamos IA</h2>
  <p>La neutralidad requiere consistencia. Un humano tiene opiniones, preferencias y sesgos inconscientes que inevitablemente se filtran en la selección y el encuadre de las noticias. Un sistema automatizado aplica exactamente los mismos criterios a cada publicación, sin excepción, sin cansancio y sin agenda.</p>
  <p>Los humanos diseñamos el estándar y los criterios. La máquina los ejecuta.</p>

  <h2>Cómo funciona el proceso</h2>
  <p>El sistema opera en dos fases independientes:</p>

  <h3>Fase 1 — Escaneo (sin IA)</h3>
  <ol>
    <li><strong>Lectura automática de fuentes</strong> — El sistema lee periódicamente los RSS de medios de todo el espectro ideológico: de izquierda a derecha, españoles, europeos y globales.</li>
    <li><strong>Detección de polarización informativa</strong> — Un algoritmo matemático (sin IA) puntúa cada tema según tres señales ponderadas: asimetría de cobertura entre cuadrantes ideológicos (60%), divergencia de vocabulario entre fuentes (25%) y dispersión del espectro (15%). La asimetría domina porque el silencio editorial — que un lado cubra un tema y el otro lo ignore — es la señal más fiable de sesgo. El score es 100% auditable y se muestra públicamente en cada tema. Este paso no gasta recursos de IA.</li>
  </ol>

  <h3>Fase 2 — Análisis (con IA)</h3>
  <ol start="3">
    <li><strong>Triage por IA ligera</strong> — Los temas que superan el umbral de polarización son evaluados por un modelo ligero (Claude Haiku) que confirma la polarización genuina y descarta falsos positivos.</li>
    <li><strong>Síntesis multi-perspectiva</strong> — Un agente de IA (Claude Sonnet) genera el artefacto mostrando todas las posturas enfrentadas con sus fuentes originales.</li>
    <li><strong>Auditoría independiente</strong> — Un segundo agente, en contexto completamente separado, evalúa el resultado contra <a href="<?= prisma_base() ?>axiomas.php">11 axiomas verificables</a>. Si no pasa, se regenera o se descarta.</li>
  </ol>

  <h2>Limitaciones conocidas</h2>
  <ul>
    <li>Los modelos de IA pueden alucinar: generar información que suena plausible pero es incorrecta. El axioma A10 (coherencia con fuentes) mitiga esto, pero no lo elimina al 100%.</li>
    <li>Los modelos tienen sesgos de entrenamiento inherentes. El axioma A4 (simetría léxica) y A11 (sesgo geopolítico) los detectan, pero la detección es imperfecta.</li>
    <li>Las fuentes RSS disponibles determinan el alcance. Si un medio no publica RSS público, no podemos incluirlo.</li>
    <li>El sistema no verifica la veracidad de las afirmaciones de cada medio. Presenta lo que cada fuente dice, no dictamina quién tiene razón.</li>
  </ul>

  <h2>Modelos utilizados</h2>
  <ul>
    <li><strong>Síntesis</strong>: Claude Sonnet (Anthropic) — optimizado para síntesis multi-fuente con instrucciones complejas.</li>
    <li><strong>Auditoría</strong>: Claude Sonnet/Opus (Anthropic) — máxima calidad en evaluación crítica.</li>
    <li><strong>Triage</strong>: Claude Haiku (Anthropic) — confirmación rápida de candidatos. Una sola llamada diaria que evalúa todos los temas candidatos en batch. Coste aproximado: medio céntimo de dólar al día.</li>
  </ul>

  <h2>Verificación</h2>
  <p>Cada publicación incluye su auditoría Moral Core visible: el veredicto, la puntuación y el detalle de cada axioma evaluado. Cada postura tiene sus fuentes enlazadas. Puedes verificarlo todo. De hecho, esperamos que lo hagas.</p>
</div>

<?php page_footer(); ?>
