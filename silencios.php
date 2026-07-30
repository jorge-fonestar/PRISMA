<?php
/**
 * PolarPrisma — Los silencios de la semana.
 *
 * Versión web de la sección §B de la newsletter (docs/diseno/NEWSLETTER-SILENCIOS.md):
 * temas relevantes de los últimos 7 días que un bloque ideológico cubrió
 * mientras otro callaba (señal h_silencio del scoring v2).
 *
 * Principios: cartografía (se documenta el silencio, no se especula el motivo),
 * simetría estructural (misma presentación para cada lado) y verificabilidad
 * (cada ítem enlaza a su ficha del radar).
 */
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/curador.php'; // constantes PRISMA_GRUPO_*

$B = prisma_base();
$db = prisma_db();

$hasta = date('Y-m-d');
$desde = date('Y-m-d', strtotime('-6 days'));

$stmt = $db->prepare("SELECT * FROM radar
    WHERE fecha >= :desde AND fecha <= :hasta
    AND scoring_version = 'v2' AND h_silencio > 0
    AND relevancia IN ('alta','media')
    ORDER BY h_score DESC");
$stmt->execute(array(':desde' => $desde, ':hasta' => $hasta));
$rows = $stmt->fetchAll();

// Clasificar por bloque silenciado y dedupe por título (conserva mayor h_score)
$listas = array('izq' => array(), 'der' => array());
$centro_callo = 0;
$vistos = array();

foreach ($rows as $row) {
    $key = mb_strtolower(trim($row['titulo_tema']), 'UTF-8');
    if (isset($vistos[$key])) continue; // rows vienen por h_score DESC
    $vistos[$key] = true;

    $fuentes = json_decode($row['fuentes_json'], true);
    if (!is_array($fuentes)) continue;

    $act = array('izq' => false, 'centro' => false, 'der' => false);
    foreach ($fuentes as $f) {
        $c = isset($f['cuadrante']) ? $f['cuadrante'] : '';
        if (in_array($c, PRISMA_GRUPO_IZQ)) $act['izq'] = true;
        elseif (in_array($c, PRISMA_GRUPO_DER)) $act['der'] = true;
        else $act['centro'] = true;
    }

    $item = array('row' => $row, 'fuentes' => $fuentes);
    if (!$act['izq']) $listas['izq'][] = $item;
    if (!$act['der']) $listas['der'][] = $item;
    if (!$act['centro'] && ($act['izq'] && $act['der'])) $centro_callo++;
}

$n_izq = count($listas['izq']);
$n_der = count($listas['der']);
$top_n = 5;

function silencio_item_html($item, $B) {
    $row = $item['row'];
    $chips = '';
    foreach ($item['fuentes'] as $f) {
        $chips .= '<span style="display:inline-block;font-family:Inter,Arial,sans-serif;font-size:0.72rem;'
            . 'color:var(--text-muted);border-left:3px solid ' . cuadrante_color($f['cuadrante'])
            . ';padding:1px 0 1px 7px;margin:0 10px 6px 0">'
            . htmlspecialchars($f['medio'], ENT_QUOTES, 'UTF-8') . '</span>';
    }
    $link = $B . 'articulo.php?radar=' . urlencode($row['id']);
    return '<div class="card" style="display:flex;gap:14px;align-items:flex-start">'
        . render_circulo_tension((float)$row['h_score'])
        . '<div style="min-width:0">'
        . '<a href="' . $link . '" style="color:var(--text);font-size:1.02rem;line-height:1.35;display:block;margin-bottom:0.4rem">'
        . htmlspecialchars($row['titulo_tema'], ENT_QUOTES, 'UTF-8') . '</a>'
        . '<div style="font-family:Inter,Arial,sans-serif;font-size:0.72rem;color:var(--text-faint);margin-bottom:0.5rem">'
        . htmlspecialchars($row['fecha']) . ' &middot; ' . htmlspecialchars(ucfirst($row['ambito']))
        . ' &middot; polarización ' . round($row['h_score'] * 100) . '%</div>'
        . '<div>' . $chips . '</div>'
        . '</div></div>';
}

page_header(
    'Los silencios de la semana',
    'Qué historias cubrió un bloque ideológico mientras el otro callaba, según el radar de PolarPrisma de los últimos 7 días.',
    'silencios',
    true // ancho de listado (1100px), como el index
);
?>

<div class="page-top">
  <p class="eyebrow">Últimos 7 días · <?= htmlspecialchars($desde) ?> a <?= htmlspecialchars($hasta) ?></p>
  <h1>Los silencios de la semana</h1>
  <p>Lo que un medio <em>no</em> cuenta es tan revelador como lo que cuenta — y es la forma de
  sesgo más difícil de percibir: nadie nota lo que su medio calla. Esta página lista los temas
  relevantes de la semana que un bloque ideológico cubrió mientras el otro guardaba silencio,
  detectados automáticamente por el radar. Documentamos el silencio; el motivo lo juzgas tú.</p>
</div>

<div class="content">

  <p style="font-family:Inter,Arial,sans-serif;font-size:0.9rem;color:var(--text-faint)">
    Esta semana el radar detectó <strong style="color:var(--text)"><?= $n_izq ?></strong> temas relevantes sin cobertura
    del bloque de izquierda, <strong style="color:var(--text)"><?= $n_der ?></strong> sin cobertura del bloque de derecha
    y <?= $centro_callo ?> sin cobertura del centro. Se muestran hasta <?= $top_n ?> por bloque, ordenados por polarización.
  </p>

  <?php if ($n_izq === 0 && $n_der === 0): ?>
    <div class="card"><p style="margin:0">Sin silencios editoriales relevantes detectados en los últimos 7 días.
    Puede deberse a poca actividad del radar esta semana — consulta el
    <a href="<?= $B ?>?vista=radar">radar de los últimos 7 días</a>.</p></div>
  <?php endif; ?>

  <?php if ($n_izq > 0): ?>
    <h2>El bloque de izquierda no lo contó</h2>
    <p style="font-size:0.9rem;color:var(--text-faint)">Temas cubiertos por medios de centro y/o derecha sin ningún artículo detectado en medios de izquierda.</p>
    <?php foreach (array_slice($listas['izq'], 0, $top_n) as $item) echo silencio_item_html($item, $B); ?>
  <?php endif; ?>

  <?php if ($n_der > 0): ?>
    <h2>El bloque de derecha no lo contó</h2>
    <p style="font-size:0.9rem;color:var(--text-faint)">Temas cubiertos por medios de izquierda y/o centro sin ningún artículo detectado en medios de derecha.</p>
    <?php foreach (array_slice($listas['der'], 0, $top_n) as $item) echo silencio_item_html($item, $B); ?>
  <?php endif; ?>

  <h2>Cómo se detecta un silencio</h2>
  <p>En el escaneo diario el radar agrupa los artículos de más de 28 fuentes de todo el
  espectro en temas. Para cada tema se registra qué bloques ideológicos lo cubren (izquierda,
  centro, derecha, según la <a href="<?= $B ?>fuentes.php">matriz pública de fuentes</a>). Si un
  bloque no tiene ningún artículo en un tema que otros bloques sí cubren, la señal de silencio
  editorial se activa. Aquí se listan solo los temas que además superan el filtro de relevancia
  (clasificación alta o media), para excluir trivialidades.</p>
  <p>Dos advertencias honestas: la detección depende de las fuentes que monitorizamos — un medio
  puede haber cubierto el tema fuera de su RSS — y un silencio no implica intención: puede ser
  agenda, recursos o casualidad. Por eso esta página <strong>documenta, no acusa</strong>.</p>

</div>

<?php page_footer(); ?>
