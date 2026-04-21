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

  <h2>Cómo decide el sistema qué analizar</h2>
  <p>El sistema opera en dos fases. La primera (escaneo) se ejecuta periódicamente sin coste: lee los RSS, agrupa los artículos que hablan del mismo tema y calcula un <strong>índice de polarización informativa</strong> para cada uno. Todos los temas detectados se publican en el radar público con su puntuación.</p>

  <p>El índice combina tres señales:</p>

  <h3>Asimetría de cobertura <span style="color:var(--text-faint);font-weight:normal">(60%)</span></h3>
  <p>¿Cuántas fuentes de cada lado del espectro cubren el tema? Si solo un lado habla, hay polarización editorial. Un tema cubierto por 5 medios de derecha y ninguno de izquierda (o viceversa) tiene la máxima asimetría: el silencio es tan editorial como el titular. Esta es la señal dominante de la fórmula, respaldada por investigadores del MIT Media Lab y Harvard (proyecto Media Cloud), que demostraron que lo que un medio elige cubrir — y lo que elige ignorar — es el indicador más fiable de sesgo editorial, por encima del análisis de vocabulario o del framing textual.</p>

  <h3>Divergencia léxica <span style="color:var(--text-faint);font-weight:normal">(25%)</span></h3>
  <p>¿Usan las mismas palabras para contar la misma historia? El sistema extrae las palabras clave de los titulares de cada cuadrante y mide la distancia entre los vocabularios (coeficiente de Jaccard). Si la izquierda dice «recorte» y la derecha dice «ajuste responsable» sobre el mismo hecho, la divergencia es alta. Cuanto más distintas son las palabras, más distinto es el encuadre. Esta señal complementa a la asimetría: detecta polarización editorial incluso cuando ambos lados cubren la noticia.</p>

  <h3>Varianza del espectro <span style="color:var(--text-faint);font-weight:normal">(15%)</span></h3>
  <p>¿Quién cubre el tema? Un tema que solo aparece en los extremos (izquierda-populista y derecha-populista) pero no en el centro tiene un patrón distinto a uno que aparece en todo el espectro. La varianza de las posiciones ideológicas captura esta distribución y añade un matiz contextual a la puntuación final.</p>

  <p>La segunda fase (análisis) se ejecuta selectivamente y consume recursos de IA. Los temas que superan el umbral mínimo de polarización son confirmados por un modelo ligero (triage), sintetizados en un artefacto multi-postura y auditados contra 11 axiomas de neutralidad. Solo los que pasan la auditoría se publican como análisis completo. El índice de polarización de cada tema — analizado o no — es público y verificable en su ficha.</p>

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
