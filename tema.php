<?php
/**
 * PolarPrisma — Observatorio: ficha de un tema.
 * Abanico ideológico (peso de menciones por cuadrante), timeline y noticias.
 */
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/observatorio.php';

$B = prisma_base();
$slug = isset($_GET['slug']) ? trim($_GET['slug']) : '';
$tema = $slug !== '' ? observatorio_tema_por_slug($slug) : null;

if (!$tema) {
    http_response_code(404);
    page_header('Tema no encontrado', '', 'observatorio');
    echo '<div class="page-top"><h1>Tema no encontrado</h1><p>Este hilo del Observatorio no existe o se ha fusionado con otro. <a href="' . $B . 'observatorio.php">Volver al Observatorio</a>.</p></div>';
    page_footer();
    exit;
}

$tid = (int)$tema['id'];
$s = observatorio_stats($tid);
$tl = observatorio_timeline($tid);
$noticias = observatorio_noticias($tid, 80);

$orden = array_keys(PRISMA_CUADRANTE_COLORES);
$cuad_labels = array(
    'izquierda-populista' => 'Izq. populista', 'izquierda' => 'Izquierda', 'centro-izquierda' => 'Centro-izq.',
    'centro' => 'Centro', 'centro-derecha' => 'Centro-der.', 'derecha' => 'Derecha', 'derecha-populista' => 'Der. populista',
);
$total_menc = max(1, $s['menciones']);
$lado_col = observatorio_lado_color($s['lado']);
$lado_txt = $s['lado'] === 'izq' ? 'la izquierda' : ($s['lado'] === 'der' ? 'la derecha' : 'el centro');

function tema_fecha_corta($ymd) {
    $m = array('','ene','feb','mar','abr','may','jun','jul','ago','sep','oct','nov','dic');
    $ts = strtotime($ymd);
    return (int)date('j', $ts) . ' ' . $m[(int)date('n', $ts)];
}

page_header($tema['nombre'], $tema['descripcion'] ? $tema['descripcion'] : ('Recorrido del tema «' . $tema['nombre'] . '» en la agenda informativa.'), 'observatorio');
?>

<div class="page-top">
  <p class="eyebrow"><a href="<?= $B ?>observatorio.php" style="color:var(--accent);text-decoration:none">← Observatorio</a></p>
  <h1><?= htmlspecialchars($tema['nombre']) ?>
    <span style="display:inline-block;height:6px;width:56px;border-radius:3px;background:<?= $lado_col ?>;vertical-align:middle;margin-left:10px" title="Se menciona más desde <?= $lado_txt ?>"></span>
  </h1>
  <?php if ($tema['descripcion']): ?><p><?= htmlspecialchars($tema['descripcion']) ?></p><?php endif; ?>
  <p style="color:var(--text-muted);font-size:0.92rem">
    <strong style="color:var(--text)"><?= $s['n_clusters'] ?></strong> noticias ·
    <strong style="color:var(--text)"><?= $s['menciones'] ?></strong> menciones ·
    <?php if ($s['primera']): ?>desde el <?= tema_fecha_corta($s['primera']) ?> · <?php endif; ?>
    se inclina hacia <strong style="color:<?= $lado_col ?>"><?= $lado_txt ?></strong>.
  </p>
</div>

<div class="content">

  <!-- Abanico ideológico: fuerza de menciones por cuadrante -->
  <h2>Quién lo empuja</h2>
  <p style="color:var(--text-muted);font-size:0.92rem;margin-top:-0.4rem">Fuerza con la que cada sector del espectro impulsa el tema. La altura de cada barra son sus menciones; donde no hay barra, ese sector apenas lo ha tocado.</p>
  <?php $maxn = 1; foreach ($orden as $c) $maxn = max($maxn, $s['cuadrantes'][$c]); ?>
  <div style="display:flex;align-items:flex-end;gap:6px;height:150px;margin:1.4rem 0 0">
    <?php foreach ($orden as $c):
        $n = $s['cuadrantes'][$c];
        $hpct = $n > 0 ? round(10 + 90 * $n / $maxn) : 0;
    ?>
      <div style="flex:1;min-width:0;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%" title="<?= htmlspecialchars($cuad_labels[$c]) ?>: <?= $n ?> menciones">
        <span style="font-family:'Inter',Arial,sans-serif;font-size:0.74rem;font-weight:700;color:<?= $n > 0 ? cuadrante_color($c) : 'var(--text-faint)' ?>;margin-bottom:5px"><?= $n ?></span>
        <?php if ($n > 0): ?>
          <div style="width:100%;max-width:44px;height:<?= $hpct ?>%;min-height:6px;background:<?= cuadrante_color($c) ?>;border-radius:4px 4px 0 0"></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <div style="height:2px;background:var(--border-card);border-radius:2px"></div>
  <div style="display:flex;gap:6px;margin-top:6px">
    <?php foreach ($orden as $c): ?>
      <span style="flex:1;min-width:0;text-align:center;font-family:'Inter',Arial,sans-serif;font-size:0.62rem;letter-spacing:0.01em;color:var(--text-faint);line-height:1.25"><?= htmlspecialchars($cuad_labels[$c]) ?></span>
    <?php endforeach; ?>
  </div>
  <p style="color:var(--text-faint);font-size:0.85rem;margin-top:1rem">
    Izquierda <strong style="color:var(--text-muted)"><?= $s['bloques']['izq'] ?></strong> ·
    Centro <strong style="color:var(--text-muted)"><?= $s['bloques']['centro'] ?></strong> ·
    Derecha <strong style="color:var(--text-muted)"><?= $s['bloques']['der'] ?></strong> menciones.
  </p>

  <!-- Timeline de menciones: tramos de ancho fijo (agrupa por bins, cabe en móvil) -->
  <?php if (count($tl) >= 2):
      $ini  = new DateTime(array_key_first($tl));
      $fin  = new DateTime(array_key_last($tl));
      $dias = (int)$ini->diff($fin)->days + 1;          // días totales del rango
      $bins = max(2, min(20, $dias));                    // nº de barras (≤20 → cabe en móvil)
      $buckets = array_fill(0, $bins, 0);
      foreach ($tl as $ymd => $n) {
          $off = (int)(new DateTime($ymd))->diff($ini)->days;   // offset en días desde el inicio
          $bi  = (int)floor($off * $bins / $dias);
          if ($bi >= $bins) $bi = $bins - 1;
          if ($bi < 0) $bi = 0;
          $buckets[$bi] += $n;
      }
      $maxb = max($buckets); if ($maxb < 1) $maxb = 1;
  ?>
  <h2 style="margin-top:2.6rem">Recorrido en el tiempo</h2>
  <p style="color:var(--text-muted);font-size:0.92rem;margin-top:-0.4rem">Menciones agrupadas por tramos, desde que el tema aparece hasta su última noticia.</p>
  <div style="display:flex;align-items:flex-end;gap:4px;height:120px;margin:1.4rem 0 0">
    <?php for ($i = 0; $i < $bins; $i++):
        $n = $buckets[$i];
        // rango de fechas que cubre este tramo (para el tooltip)
        $d0 = (clone $ini)->modify('+' . (int)floor($i * $dias / $bins) . ' day');
        $d1 = (clone $ini)->modify('+' . ((int)floor(($i + 1) * $dias / $bins) - 1) . ' day');
        if ($d1 > $fin) $d1 = clone $fin;
        $rango = tema_fecha_corta($d0->format('Y-m-d'));
        if ($d1 > $d0) $rango .= ' – ' . tema_fecha_corta($d1->format('Y-m-d'));
        $h = $n > 0 ? round(8 + 100 * $n / $maxb) : 3;
    ?>
      <div title="<?= $rango ?>: <?= $n ?> menciones" style="flex:1;min-width:0;height:<?= $h ?>px;border-radius:3px 3px 0 0;background:<?= $n > 0 ? $lado_col : 'var(--border-card)' ?>;opacity:<?= $n > 0 ? 0.9 : 0.5 ?>"></div>
    <?php endfor; ?>
  </div>
  <div style="height:2px;background:var(--border-card);border-radius:2px"></div>
  <div style="display:flex;justify-content:space-between;font-family:'Inter',Arial,sans-serif;font-size:0.72rem;color:var(--text-faint);margin-top:6px">
    <span><?= tema_fecha_corta($ini->format('Y-m-d')) ?></span><span><?= tema_fecha_corta($fin->format('Y-m-d')) ?></span>
  </div>
  <?php endif; ?>

  <!-- Noticias relacionadas -->
  <h2 style="margin-top:2.4rem">Noticias del tema</h2>
  <div style="display:flex;flex-direction:column;gap:10px;margin-top:1rem">
    <?php foreach ($noticias as $n):
        $pct = (int)round($n['h_score'] * 100);
        $sem = $pct >= 60 ? '#ff4d6d' : ($pct >= 45 ? '#ff9e4d' : '#f2f24a');
        $analizada = ($n['analizado'] && $n['articulo_id']);
        $href = $analizada ? ($B . 'articulo.php?id=' . rawurlencode($n['articulo_id'])) : ($B . 'articulo.php?radar=' . (int)$n['id']);
    ?>
      <a href="<?= $href ?>" style="display:flex;align-items:baseline;gap:12px;padding:10px 14px;border:1px solid var(--border-card);border-radius:6px;text-decoration:none">
        <span style="flex:0 0 auto;font-family:'Inter',Arial,sans-serif;font-size:0.8rem;font-weight:700;color:<?= $sem ?>;width:42px"><?= $pct ?>%</span>
        <span style="flex:1;min-width:0;color:var(--text);font-size:0.95rem"><?= htmlspecialchars($n['titulo_tema']) ?></span>
        <span style="flex:0 0 auto;font-size:0.78rem;color:var(--text-faint)"><?= tema_fecha_corta($n['fecha']) ?><?= $analizada ? ' · 🔬' : '' ?></span>
      </a>
    <?php endforeach; ?>
  </div>

</div>

<?php page_footer(); ?>
