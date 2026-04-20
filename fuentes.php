<?php
require_once __DIR__ . '/lib/layout.php';

$cfg = prisma_cfg();
page_header('Fuentes consultadas', 'Matriz completa de medios por cuadrante ideológico que Prisma consulta diariamente.');
?>


<div class="page-top">
  <p class="eyebrow">Transparencia</p>
  <h1>Fuentes consultadas</h1>
  <p>Prisma consulta diariamente los RSS públicos de estos medios, clasificados por ámbito geográfico y cuadrante ideológico.</p>
</div>

<div class="content">
  <p>La clasificación ideológica es una simplificación necesaria para garantizar diversidad. Ningún medio cabe enteramente en una etiqueta. La clasificación refleja la posición editorial predominante según el consenso de estudios de comunicación política en España y Europa.</p>

  <?php
    $ambito_labels = array('españa' => 'España', 'europa' => 'Europa', 'global' => 'Global');
    $total_medios = 0;
    $total_cuad = 0;
    $total_ambitos = 0;
    $fuentes = $cfg['fuentes'];
    foreach ($fuentes as $ambito => $cuadrantes):
      $total_ambitos++;
      $n_medios = 0;
      $n_cuad = 0;
      foreach ($cuadrantes as $cuad_medios) {
        $n_cuad++;
        $n_medios += count($cuad_medios);
      }
      $total_cuad += $n_cuad;
      $total_medios += $n_medios;
      $label = isset($ambito_labels[$ambito]) ? $ambito_labels[$ambito] : ucfirst($ambito);
  ?>
    <h2><?= htmlspecialchars($label) ?></h2>
    <p style="font-size:0.88rem;color:var(--text-faint);margin-bottom:1rem"><?= $n_cuad ?> cuadrantes · <?= $n_medios ?> medios</p>
    <table>
      <thead><tr><th>Cuadrante</th><th>Medio</th><th>Feed RSS</th></tr></thead>
      <tbody>
      <?php foreach ($cuadrantes as $cuadrante => $medios): ?>
        <?php foreach ($medios as $i => $medio): ?>
          <tr>
            <?php if ($i === 0): ?>
              <td style="white-space:nowrap;vertical-align:top" rowspan="<?= count($medios) ?>"><strong><?= htmlspecialchars(ucfirst($cuadrante)) ?></strong></td>
            <?php endif; ?>
            <td><?= htmlspecialchars($medio[0]) ?></td>
            <td style="font-size:0.8rem;word-break:break-all;color:var(--text-faint)"><?= htmlspecialchars($medio[1]) ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>

  <p style="margin-top:2rem"><strong>Total: <?= $total_medios ?> medios en <?= $total_cuad ?> cuadrantes de <?= $total_ambitos ?> ámbitos geográficos.</strong></p>

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
