<?php
require_once __DIR__ . '/lib/layout.php';
page_header('Aviso legal');
?>

<div class="page-top">
  <h1>Aviso legal</h1>
</div>

<div class="content">
  <h2>Identificación</h2>
  <p>Prisma es un proyecto personal e independiente sin ánimo de lucro, publicado bajo el nombre colectivo "Equipo Prisma". No constituye actividad económica ni sociedad mercantil. Se desarrolla al amparo del derecho a la libertad de expresión e información (art. 20 CE) como ejercicio de ciudadanía digital.</p>
  <p>Contacto: <strong>contacto@prisma.example</strong></p>

  <h2>Naturaleza del contenido</h2>
  <p>Todo el contenido es generado por agentes de inteligencia artificial y auditado automáticamente contra el estándar Moral Core. No constituye asesoramiento profesional de ningún tipo. Para más información, consulta el <a href="<?= prisma_base() ?>ia.php">aviso de IA</a>.</p>

  <h2>Propiedad intelectual</h2>
  <p>Todo el contenido original de Prisma se publica bajo licencia <strong>Creative Commons Atribución-CompartirIgual 4.0 Internacional (CC BY-SA 4.0)</strong>. Puedes compartirlo, adaptarlo y reutilizarlo siempre que cites la fuente y mantengas la misma licencia.</p>
  <p>Los fragmentos de artículos de terceros se reproducen en el marco del derecho de cita (art. 32 TRLPI) con enlace a la fuente original.</p>

  <h2>Responsabilidad</h2>
  <p>Prisma presenta posturas existentes en el debate público. No se hace responsable de la veracidad de las afirmaciones de los medios citados. Cada postura incluye su fuente para que el lector pueda verificar directamente.</p>
</div>

<?php page_footer(); ?>
