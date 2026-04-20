<?php
require_once __DIR__ . '/lib/layout.php';

$db = prisma_db();
$page = max(1, (int)($_GET['p'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

$total = (int)$db->query("SELECT COUNT(*) FROM articulos")->fetchColumn();
$rows = $db->prepare("SELECT id, fecha_publicacion, ambito, titular_neutral, veredicto FROM articulos ORDER BY fecha_publicacion DESC LIMIT :lim OFFSET :off");
$rows->bindValue(':lim', $per_page, PDO::PARAM_INT);
$rows->bindValue(':off', $offset, PDO::PARAM_INT);
$rows->execute();
$articles = $rows->fetchAll();
$total_pages = max(1, ceil($total / $per_page));
$B = prisma_base();

function ambito_label_a($a) { return ['españa'=>'España','europa'=>'Europa','global'=>'Global'][$a] ?? ucfirst($a); }

page_header('Archivo', 'Archivo completo de noticias analizadas por Prisma');
?>
<style>
  .archive-list { margin: 2rem 0; }
  .archive-item { display: flex; gap: 16px; padding: 1rem 0; border-bottom: 1px solid var(--border); align-items: baseline; }
  .archive-item:first-child { border-top: 1px solid var(--border); }
  .archive-date { font-family: 'Inter', Arial, sans-serif; font-size: 0.78rem; color: var(--text-faint); white-space: nowrap; min-width: 90px; }
  .archive-title { color: var(--text); font-size: 0.95rem; text-decoration: none; flex: 1; }
  .archive-title:hover { color: var(--link); }
  .archive-badge { font-family: 'Inter', Arial, sans-serif; font-size: 0.68rem; font-weight: 600; padding: 2px 8px; border-radius: 3px; white-space: nowrap; }
  .archive-badge.españa { background: rgba(255,77,109,0.1); color: var(--red); }
  .archive-badge.europa { background: rgba(77,195,255,0.1); color: var(--link); }
  .archive-badge.global { background: rgba(74,222,128,0.1); color: var(--green); }
  .pagination { display: flex; gap: 8px; justify-content: center; margin: 2rem 0; }
  .pagination a, .pagination span {
    padding: 6px 14px; border-radius: 4px; font-family: 'Inter', Arial, sans-serif; font-size: 0.85rem;
    text-decoration: none; border: 1px solid var(--border-card);
  }
  .pagination a { color: var(--text-muted); }
  .pagination a:hover { border-color: var(--text); color: var(--text); }
  .pagination .current { background: var(--accent-bg); border-color: var(--accent-border); color: var(--accent); font-weight: 700; }
</style>

<div class="page-top">
  <p class="eyebrow">Histórico</p>
  <h1>Archivo</h1>
  <p><?= $total ?> artículos publicados</p>
</div>

<div class="content">
  <div class="archive-list">
    <?php foreach ($articles as $art): ?>
      <div class="archive-item">
        <span class="archive-date"><?= date('j M Y', strtotime($art['fecha_publicacion'])) ?></span>
        <a href="<?= $B ?>articulo.php?id=<?= urlencode($art['id']) ?>" class="archive-title"><?= htmlspecialchars($art['titular_neutral']) ?></a>
        <span class="archive-badge <?= htmlspecialchars($art['ambito']) ?>"><?= ambito_label_a($art['ambito']) ?></span>
      </div>
    <?php endforeach; ?>
    <?php if (empty($articles)): ?>
      <p style="text-align:center;padding:3rem 0;color:var(--text-faint)">No hay artículos en el archivo todavía.</p>
    <?php endif; ?>
  </div>

  <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php if ($page > 1): ?><a href="?p=<?= $page - 1 ?>">&larr;</a><?php endif; ?>
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <?php if ($i === $page): ?>
          <span class="current"><?= $i ?></span>
        <?php else: ?>
          <a href="?p=<?= $i ?>"><?= $i ?></a>
        <?php endif; ?>
      <?php endfor; ?>
      <?php if ($page < $total_pages): ?><a href="?p=<?= $page + 1 ?>">&rarr;</a><?php endif; ?>
    </div>
  <?php endif; ?>
</div>

<?php page_footer(); ?>
