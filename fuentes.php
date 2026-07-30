<?php
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/fuentes/normalizar.php';

$cfg = prisma_cfg();
page_header('Fuentes consultadas', 'Matriz completa de medios por cuadrante ideológico que Prisma consulta diariamente.');
?>


<div class="page-top">
  <p class="eyebrow">Transparencia</p>
  <h1>Fuentes consultadas</h1>
  <p>Prisma consulta diariamente estos medios, clasificados por ámbito geográfico y cuadrante ideológico.</p>
  <p style="font-size:0.88rem;color:var(--text-muted);line-height:1.6;margin-top:0.5rem">Prisma accede al contenido público de los medios mediante tres vías: RSS nativo cuando el medio lo ofrece; captura de portada (solo titulares y enlaces, respetando robots.txt y con user-agent identificable) cuando el medio no ha implementado RSS; y autorización explícita solicitada por correo cuando el medio ha retirado su RSS deliberadamente. Cualquier medio que desee no ser incluido puede escribir a contacto@prisma.example y será retirado del corpus en 48 horas.</p>
</div>

<div class="content">
  <p>La clasificación ideológica es una simplificación necesaria para garantizar diversidad. Ningún medio cabe enteramente en una etiqueta. La clasificación refleja la posición editorial predominante según el consenso de estudios de comunicación política en España y Europa. 

  <h2>La importancia de la financiación</h2>
  <p>Detrás de cada redacción hay una estructura de propiedad que condiciona qué se publica y cómo se encuadra. <br>El lector merece saber quién paga. Datos compilados de registros mercantiles, portales de transparencia e investigaciones publicadas.</p>

  <details>
    <summary style="cursor:pointer;font-family:'Inter',Arial,sans-serif;font-weight:600;font-size:0.95rem;color:var(--accent);padding:1rem 0;user-select:none">Ver listado de fuentes y análisis de financiación</summary>

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
    <h3><?= htmlspecialchars($label) ?></h3>
    <p style="font-size:0.88rem;color:var(--text-faint);margin-bottom:1rem"><?= $n_cuad ?> cuadrantes · <?= $n_medios ?> medios</p>
    <table>
      <thead><tr><th>Cuadrante</th><th>Medio</th><th>Acceso</th><th>Propiedad y financiación</th></tr></thead>
      <tbody>
      <?php foreach ($cuadrantes as $cuadrante => $medios): ?>
        <?php foreach ($medios as $i => $medio): ?>
          <?php $cfg_m = rss_normalizar_fuente($medio, $cuadrante, $ambito); ?>
          <tr>
            <?php if ($i === 0): ?>
              <td style="white-space:nowrap;vertical-align:top" rowspan="<?= count($medios) ?>"><strong><?= htmlspecialchars(ucfirst($cuadrante)) ?></strong></td>
            <?php endif; ?>
            <td style="white-space:nowrap;vertical-align:top"><strong><?= htmlspecialchars($cfg_m['medio']) ?></strong></td>
            <td style="white-space:nowrap;vertical-align:top;font-size:0.8rem"><?php
              $mod = isset($cfg_m['modalidad']) ? $cfg_m['modalidad'] : 'rss_nativo';
              if ($mod === 'rss_nativo') echo '<span style="color:var(--ok,#4ade80)">RSS</span>';
              elseif ($mod === 'captura_portada') echo '<span style="color:var(--warn,#f2f24a)">Portada</span>';
              else echo '<span style="color:var(--err,#ff4d6d)">No disponible</span>';
            ?></td>
            <td style="font-size:0.85rem;color:var(--text-muted);line-height:1.5"><?php
              $trans = isset($cfg_m['transparencia']) ? $cfg_m['transparencia'] : '';
              $perfil = isset($cfg_m['perfil_editorial']) ? $cfg_m['perfil_editorial'] : '';
              if ($perfil) echo '<em style="color:var(--text-faint,#888)">' . htmlspecialchars($perfil) . '</em><br>';
              echo htmlspecialchars($trans ? $trans : '—');
            ?></td>
          </tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endforeach; ?>

  <p style="margin-top:2rem"><strong>Total: <?= $total_medios ?> medios en <?= $total_cuad ?> cuadrantes de <?= $total_ambitos ?> ámbitos geográficos.</strong></p>

  </details>

  <h2>Cómo decide el sistema qué analizar</h2>
  <p>El sistema opera en dos fases.</p>

  <h3>Fase 1 — Escaneo y puntuación</h3>
  <p>Lee los RSS, agrupa los artículos que hablan del mismo tema y calcula un <strong>índice de polarización informativa</strong> (H-score) para cada uno. Todos los temas detectados se publican en el radar público con su puntuación.</p>

  <p>El proceso sigue estos pasos:</p>

  <ol>
    <li><strong>Pre-filtro determinista.</strong> Una lista de palabras clave descarta automáticamente temas que nunca generan polarización ideológica: resultados de lotería, retransmisiones deportivas, farándula, curiosidades virales o meteorología rutinaria. Sin coste, instantáneo.</li>
    <li><strong>Agrupación y clasificación por IA.</strong> Los temas que sobreviven al filtro se envían en lote a un modelo ligero (Haiku) que primero <strong>fusiona los que son la misma noticia</strong> aunque los titulares usen palabras distintas (una agrupación semántica que las coincidencias de vocabulario no captan), y después evalúa para cada tema: si tiene relevancia ideológica (política, economía, sanidad, tecnología, medio ambiente, inmigración...), a qué dominio temático pertenece, y en qué grado los distintos bloques ideológicos encuadran la noticia de forma diferente (<em>divergencia de framing</em>, de 0 a 3).</li>
    <li><strong>Señales estructurales.</strong> En paralelo, el sistema calcula dos métricas deterministas sin IA:
      <ul>
        <li><strong>Cobertura mutua:</strong> ¿cubren el tema medios de izquierda, centro y derecha? Un tema cubierto solo desde un lado no tiene solape suficiente para medir polarización. La cobertura equilibrada entre los tres bloques es la precondición para una puntuación alta.</li>
        <li><strong>Silencio editorial:</strong> ¿hay algún bloque ideológico que no cubre el tema? Cuando un bloque entero calla sobre un tema políticamente relevante, eso también es una señal — pero distinta de la polarización activa. Se registra como bonus, no como factor principal.</li>
      </ul>
    </li>
    <li><strong>Composición del H-score.</strong> La puntuación final combina cobertura mutua y divergencia de framing con una fórmula multiplicativa: ambas señales deben estar presentes para que el score sea alto. Si cualquiera de las dos es cero, el score es cero. Esto evita que un tema trivial cubierto por muchos medios (alta cobertura pero framing idéntico) o que un tema muy polarizado pero cubierto por un solo medio (alto framing pero sin solape) infle artificialmente su puntuación.</li>
  </ol>

  <p>Las tres barras que se muestran en cada ficha de tema representan:</p>

  <h3>Cobertura mutua</h3>
  <p>Mide el solape real entre los tres bloques ideológicos (izquierda, centro, derecha). Un valor alto significa que los tres bloques cubren el tema — precondición necesaria para poder comparar encuadres. Un valor bajo indica que la cobertura es unilateral. Esta señal reemplaza la antigua «asimetría de cobertura», que medía lo contrario y premiaba erróneamente temas cubiertos por un solo lado.</p>

  <h3>Divergencia de framing</h3>
  <p>¿Cuentan los medios la misma historia con marcos distintos? Un modelo de IA evalúa si los bloques ideológicos usan encuadres diferentes para el mismo hecho. Por ejemplo: «recortes sociales» frente a «ajuste presupuestario responsable», o «prohibición» frente a «regulación». Esta señal es la más fiable: si hay framing divergente, hay polarización real. Reemplaza la antigua «divergencia léxica» basada en Jaccard, que no capturaba el significado del vocabulario.</p>

  <h3>Silencio editorial</h3>
  <p>¿Hay un bloque ideológico entero que ignora el tema? El silencio editorial (un bloque que no publica nada sobre un tema políticamente relevante) es una señal de agenda-setting, pero distinta de la polarización activa. Se muestra como información complementaria y aporta un pequeño bonus al score, pero no puede por sí sola generar una puntuación alta.</p>

  <h3>Fase 2 — Análisis en profundidad</h3>
  <p>Los temas que superan el umbral mínimo de polarización pasan a un análisis completo que consume recursos de IA: son confirmados por un modelo ligero (triage), sintetizados en un artefacto multi-postura que presenta todas las posturas enfrentadas con fuentes citadas, y auditados contra 11 axiomas de neutralidad por un segundo agente independiente. Solo los que pasan la auditoría se publican como análisis completo. El índice de polarización de cada tema — analizado o no — es público y verificable en su ficha.</p>
  <p><strong>Revisión del índice.</strong> La detección inicial (Fase 1) solo lee titulares y entradillas, así que su índice es una estimación. Cuando un tema se analiza en profundidad, el modelo dispone del texto completo y del mapa de posturas y <strong>recalcula el índice de polarización</strong> con ese contexto: ese valor revisado sustituye al inicial en las noticias analizadas. En su ficha se indica el índice revisado y, de forma transparente, cuál fue la detección inicial — para que se vea cuándo la lectura superficial de titulares sobrestimó o infravaloró la polarización real.</p>

  <h2>Política de acceso</h2>
  <ul>
    <li><strong>RSS nativo:</strong> lectura de feeds RSS/Atom públicos.</li>
    <li><strong>Captura de portada:</strong> solo titulares y URLs de portada, respetando robots.txt, con User-Agent identificable (PrismaBot/1.0), máximo 1 petición por medio por hora.</li>
    <li><strong>Medios sin acceso:</strong> cuando un medio retira su RSS deliberadamente, se solicita autorización explícita. Sin respuesta o denegación se declara públicamente.</li>
    <li>Siempre se cita la fuente original con enlace directo.</li>
    <li>Nunca se republica el texto íntegro del artículo.</li>
    <li>Cualquier medio puede solicitar su exclusión del corpus.</li>
  </ul>
</div>

<?php page_footer(); ?>
