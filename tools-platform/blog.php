<?php
require __DIR__ . '/includes/config.php';

$categorySlug = trim((string) ($_GET['category'] ?? ''));
$category = $categorySlug !== '' ? tp_query_one('SELECT * FROM blog_categories WHERE slug = ?', 's', [$categorySlug]) : null;
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;

$posts = get_blog_posts($perPage, ($page - 1) * $perPage, $category['id'] ?? null);
$blogCategories = tp_query("SELECT * FROM blog_categories WHERE status='published' ORDER BY name ASC");

$pageTitle = ($category['name'] ?? 'Blog') . ' — ' . tp_setting('site_name');
$pageDescriptionFallback = 'Guides, tips and updates from ' . tp_setting('site_name') . '.';
require __DIR__ . '/includes/header.php';
?>
<div class="tp-container py-5">
  <h1 class="h3 fw-bold mb-1">Blog</h1>
  <p class="text-muted mb-4">Guides and updates to help you get more out of every tool.</p>

  <div class="d-flex gap-2 flex-wrap mb-4">
    <a href="<?= tp_url('blog.php') ?>" class="btn btn-sm <?= !$category ? 'btn-dark' : 'btn-outline-secondary' ?>">All</a>
    <?php foreach ($blogCategories as $bc): ?>
      <a href="<?= tp_url('blog.php?category=' . $bc['slug']) ?>" class="btn btn-sm <?= ($category['id'] ?? null) === $bc['id'] ? 'btn-dark' : 'btn-outline-secondary' ?>"><?= e($bc['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="row g-4">
    <?php foreach ($posts as $post): ?>
      <div class="col-md-4">
        <a href="<?= tp_url('blog/' . $post['slug'] . '.php') ?>" class="tp-card text-decoration-none d-block h-100 overflow-hidden">
          <div style="height:160px;background:var(--tp-gradient-cyan);<?= $post['featured_image'] ? "background-image:url('" . e($post['featured_image']) . "');background-size:cover;background-position:center;" : '' ?>"></div>
          <div class="p-3">
            <h3 class="h6 fw-bold"><?= e($post['title']) ?></h3>
            <p class="text-muted small"><?= e($post['excerpt']) ?></p>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
    <?php if (!$posts): ?><p class="text-muted">No articles published yet.</p><?php endif; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
