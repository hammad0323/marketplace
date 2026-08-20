<?php
require __DIR__ . '/includes/config.php';
$tools = get_trending_tools(60);
$pageTitle = 'Trending Tools — ' . tp_setting('site_name');
$pageDescriptionFallback = 'Tools gaining traction right now on ' . tp_setting('site_name') . '.';
require __DIR__ . '/includes/header.php';
?>
<div class="tp-container py-5">
  <h1 class="h3 fw-bold mb-4"><i class="bi bi-graph-up-arrow text-info"></i> Trending Tools</h1>
  <div class="row g-3">
    <?php foreach ($tools as $t): ?>
      <div class="col-sm-6 col-lg-4">
        <a href="<?= tp_url($t['slug'] . '.php') ?>" class="tp-card tp-tool-card text-decoration-none">
          <span class="tp-icon"><i class="bi <?= e($t['icon'] ?: 'bi-calculator') ?>"></i></span>
          <h3><?= e($t['name']) ?></h3>
          <p><?= e($t['short_description']) ?></p>
        </a>
      </div>
    <?php endforeach; ?>
    <?php if (!$tools): ?><p class="text-muted">No trending tools yet — check back soon.</p><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
