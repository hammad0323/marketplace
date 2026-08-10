<?php
require_once __DIR__ . '/../config/config.php';

$slug = clean_input($_GET['slug'] ?? '');
$category = $slug ? db_select_one($conn, 'SELECT * FROM categories WHERE slug = ? AND is_active = 1', [$slug]) : null;

if (!$category) {
    http_response_code(404);
    require ROOT_PATH . '/404.php';
    exit;
}

$serviceCount = db_count($conn, 'SELECT COUNT(*) FROM services WHERE category_id = ? AND status = "approved"', [(int) $category['id']]);

$pageTitle = $category['name'];
$metaDescription = $category['seo_description'] ?: $category['description'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head">
      <span class="eyebrow"><i class="bi <?php echo e($category['icon'] ?: 'bi-grid'); ?>"></i> Category</span>
      <h1 class="section-heading"><?php echo e($category['name']); ?></h1>
      <p class="section-sub"><?php echo e($category['description']); ?></p>
    </div>

    <?php if ($serviceCount === 0): ?>
      <div class="empty-state">
        <div class="icon-wrap"><i class="bi bi-search"></i></div>
        <h4>No <?php echo e(strtolower($category['name'])); ?> listed yet</h4>
        <p>Search, filters and listings for this category land in Phase 3–4 of the build. Approved provider services will appear here automatically.</p>
        <a href="/provider/register.php" class="btn-w btn-primary">Become a provider</a>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
