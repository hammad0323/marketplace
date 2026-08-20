<?php
require __DIR__ . '/includes/config.php';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 30;
$total = (int) (tp_query_one("SELECT COUNT(*) c FROM tools WHERE status='published'")['c'] ?? 0);
$tools = tp_query(
    "SELECT t.*, c.name AS category_name FROM tools t JOIN categories c ON c.id=t.category_id
     WHERE t.status='published' ORDER BY t.name ASC LIMIT ? OFFSET ?",
    'ii',
    [$perPage, ($page - 1) * $perPage]
);
$totalPages = max(1, (int) ceil($total / $perPage));

$pageTitle = 'All Tools — ' . tp_setting('site_name');
$pageDescriptionFallback = 'Browse the complete directory of ' . $total . '+ free tools.';
require __DIR__ . '/includes/header.php';
?>
<div class="tp-container py-5">
  <h1 class="h3 fw-bold mb-1">All Tools</h1>
  <p class="text-muted mb-4"><?= (int) $total ?> tools and counting</p>
  <div class="row g-3">
    <?php foreach ($tools as $t): ?>
      <div class="col-sm-6 col-lg-4">
        <a href="<?= tp_url($t['slug']) ?>" class="tp-card tp-tool-card text-decoration-none">
          <span class="tp-icon"><i class="bi <?= e($t['icon'] ?: 'bi-calculator') ?>"></i></span>
          <h3><?= e($t['name']) ?></h3>
          <p><?= e($t['short_description']) ?></p>
          <span class="text-muted small"><?= e($t['category_name']) ?></span>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if ($totalPages > 1): ?>
  <nav class="mt-4"><ul class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a></li>
    <?php endfor; ?>
  </ul></nav>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
