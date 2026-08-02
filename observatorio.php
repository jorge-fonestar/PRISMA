<?php
/**
 * PolarPrisma — Observatorio: la ventana de la agenda.
 *
 * Temas como frases cortas; el tamaño = menciones recientes; el subrayado,
 * coloreado y posicionado, = hacia qué lado del espectro se inclina.
 */
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/observatorio.php';

$B = prisma_base();
$VENTANA_DIAS = 30;

$temas = observatorio_temas(true);
$items = array();
$maxm = 1; $minm = null;
foreach ($temas as $t) {
    $s = observatorio_stats((int)$t['id'], $VENTANA_DIAS);
    if ($s['menciones'] < 1) continue;   // sin menciones recientes → fuera de la ventana
    $items[] = array('t' => $t, 's' => $s);
    $maxm = max($maxm, $s['menciones']);
    $minm = ($minm === null) ? $s['menciones'] : min($minm, $s['menciones']);
}
if ($minm === null) $minm = 1;
usort($items, function ($a, $b) { return $b['s']['menciones'] <=> $a['s']['menciones']; });

page_header('Observatorio', 'El mapa de la agenda informativa: de qué se habla ahora, cuánto, y hacia qué lado se inclina.', true);
?>

<div class="page-top">
  <p class="eyebrow">Observatorio</p>
  <h1>De qué se está hablando</h1>
  <p>Cada tema es un hilo de la agenda pública que agrupa muchas noticias en el tiempo. El
  <strong>tamaño</strong> refleja cuánto se ha mencionado en los últimos <?= $VENTANA_DIAS ?> días;
  el <strong>subrayado</strong> —su color y su posición— indica hacia qué lado del espectro se
  inclina la cobertura (izquierda, centro o derecha). Pincha un tema para ver su recorrido.</p>
</div>

<?php if (empty($items)): ?>
  <p style="color:var(--text-muted);margin:2rem 0">Aún no hay temas con menciones recientes.</p>
<?php else: ?>
<div style="display:flex;flex-wrap:wrap;gap:1.6rem 2rem;align-items:flex-end;margin:2.4rem 0">
<?php foreach ($items as $it):
    $t = $it['t']; $s = $it['s'];
    $frac = ($maxm > $minm) ? (sqrt($s['menciones']) - sqrt($minm)) / (sqrt($maxm) - sqrt($minm)) : 0.5;
    $size = round(1.05 + $frac * 1.85, 2);   // 1.05rem .. 2.9rem
    $col = observatorio_lado_color($s['lado']);
    $mg = $s['lado'] === 'izq' ? 'margin-right:auto' : ($s['lado'] === 'der' ? 'margin-left:auto' : 'margin:0 auto');
    $lado_txt = $s['lado'] === 'izq' ? 'la izquierda' : ($s['lado'] === 'der' ? 'la derecha' : 'el centro');
?>
  <a href="<?= $B ?>tema.php?slug=<?= urlencode($t['slug']) ?>" style="text-decoration:none;display:inline-block"
     title="<?= htmlspecialchars($s['n_clusters']) ?> noticias · se menciona más desde <?= $lado_txt ?>">
    <span style="display:block;font-size:<?= $size ?>rem;font-weight:600;color:var(--text);line-height:1.08"><?= htmlspecialchars($t['nombre']) ?></span>
    <span style="display:block;height:4px;width:55%;border-radius:2px;background:<?= $col ?>;margin-top:6px;<?= $mg ?>"></span>
  </a>
<?php endforeach; ?>
</div>

<p style="color:var(--text-faint);font-size:0.85rem;margin-top:2.5rem">
  Subrayado hacia <span style="color:#ff6b81;font-weight:600">la izquierda</span> ·
  <span style="color:#f2f24a;font-weight:600">el centro</span> ·
  <span style="color:#4d9eff;font-weight:600">la derecha</span>, según de qué cuadrantes procede la mayoría de la cobertura.
  Un tema muy inclinado a un lado es uno que <em>un solo lado está empujando</em>.
</p>
<?php endif; ?>

<?php page_footer(); ?>
