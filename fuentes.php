<?php
require_once __DIR__ . '/lib/layout.php';

$cfg = PRISMA_CONFIG;
page_header('Fuentes consultadas', 'Matriz completa de medios por cuadrante ideológico que Prisma consulta diariamente.');
?>

<div class="page-top">
  <p class="eyebrow">Transparencia</p>
  <h1>Fuentes consultadas</h1>
  <p>Prisma consulta diariamente los RSS públicos de estos medios, clasificados por ámbito geográfico y cuadrante ideológico.</p>
</div>

<div class="content">
  <p>La clasificación ideológica es una simplificación necesaria para garantizar diversidad. Ningún medio cabe enteramente en una etiqueta. La clasificación refleja la posición editorial predominante según el consenso de estudios de comunicación política en España y Europa.</p>

  <?php foreach ($cfg['fuentes'] as $ambito => $cuadrantes): ?>
    <h2><?= htmlspecialchars(ucfirst($ambito)) ?></h2>
    <table>
      <thead><tr><th>Cuadrante</th><th>Medios</th></tr></thead>
      <tbody>
      <?php foreach ($cuadrantes as $cuadrante => $medios): ?>
        <tr>
          <td style="white-space:nowrap"><strong><?= htmlspecialchars(ucfirst($cuadrante)) ?></strong></td>
          <td><?= htmlspecialchars(implode(', ', array_column($medios, 0))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>

  <h2>Criterios de selección de temas</h2>
  <p>Un tema se considera candidato solo si:</p>
  <ul>
    <li>Aparece en múltiples cuadrantes ideológicos de la matriz (la diversidad se exige proporcionalmente al ámbito).</li>
    <li>Tiene cobertura sustantiva, no mención marginal.</li>
    <li>Es actualidad política: no crónica rosa, deportes ni entretenimiento.</li>
  </ul>
  <p>La selección final prioriza los temas con mayor frecuencia de aparición y mayor diversidad de cuadrantes que los cubren.</p>

  <h2>Política de acceso</h2>
  <ul>
    <li>Solo RSS públicos y APIs oficiales.</li>
    <li>Solo titulares, fragmentos y metadatos (uso legítimo de feed público).</li>
    <li>Siempre se cita la fuente original con enlace directo.</li>
    <li>Nunca se republica el texto íntegro del artículo.</li>
    <li>Rate limiting: máximo 1 petición por segundo por dominio.</li>
  </ul>
</div>

<?php page_footer(); ?>
