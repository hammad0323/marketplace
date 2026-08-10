<?php
require_once __DIR__ . '/../config/config.php';

$slug = clean_input($_GET['slug'] ?? '');
$page = $slug ? db_select_one($conn, 'SELECT * FROM pages WHERE slug = ? AND is_active = 1', [$slug]) : null;

if (!$page) {
    http_response_code(404);
    require ROOT_PATH . '/404.php';
    exit;
}

$pageTitle = $page['title'];
$metaDescription = $page['seo_description'] ?: get_setting($conn, 'site_tagline', '');
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl" style="max-width:860px;">
    <div class="section-head">
      <span class="eyebrow"><i class="bi bi-file-text"></i> Page</span>
      <h1 class="section-heading"><?php echo e($page['title']); ?></h1>
    </div>
    <div style="font-size:16px;line-height:1.8;color:var(--ink-soft);">
      <?php echo $page['content']; ?>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
