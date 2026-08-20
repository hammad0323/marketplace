<?php
/**
 * category-page.php — generic renderer for every category URL. The
 * real file on disk is .../finance-accounting-tools.php (sets
 * $categorySlug then requires this file), but .htaccess rewrites the
 * public, extension-less URL (/finance-accounting-tools) to it — see
 * the rewrite rules at the project root. Mirrors tool-page.php's
 * pattern: DB-driven content, no query strings.
 */

require_once __DIR__ . '/config.php';

if (empty($categorySlug)) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$category = get_category_by_slug($categorySlug);

if (!$category || $category['status'] !== 'published') {
    $redirect = tp_find_redirect('/' . $categorySlug);
    if ($redirect) {
        header('Location: ' . tp_resolve_redirect_target($redirect['new_url']), true, (int) $redirect['redirect_type']);
        exit;
    }
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

$perPage = 24;
$page = max(1, (int) ($_GET['page'] ?? 1));
$tools = get_tools_by_category((int) $category['id'], $perPage, ($page - 1) * $perPage);
$totalTools = get_tool_count_for_category((int) $category['id']);
$totalPages = max(1, (int) ceil($totalTools / $perPage));

$seoRow = get_seo_settings('category', (int) $category['id']) ?? [];
$pageTitle = $seoRow['seo_title'] ?? ($category['name'] . ' — ' . tp_setting('site_name'));
$pageDescriptionFallback = $seoRow['meta_description'] ?? $category['description'];
$pageSeo = $seoRow;
$pageSchemas = [generate_schema('WebSite', [])];
$breadcrumbItems = [
    ['label' => 'Home', 'url' => tp_url()],
    ['label' => $category['name'], 'url' => null],
];
$bodyClass = 'category-page';
require __DIR__ . '/header.php';
?>
<section class="parallax-hero py-5">
  <div class="parallax-hero-bg"></div>
  <div class="tp-container position-relative py-4">
    <span class="tp-eyebrow text-white-50"><?= (int) $totalTools ?> Tools</span>
    <h1 class="hero-title"><?= e($category['name']) ?></h1>
    <p class="hero-sub"><?= e($category['description']) ?></p>
  </div>
</section>

<div class="tp-container py-5">
  <div class="row g-3">
    <?php foreach ($tools as $tool): ?>
      <div class="col-sm-6 col-lg-4 reveal">
        <a href="<?= tp_url($tool['slug']) ?>" class="tp-card tp-tool-card text-decoration-none">
          <span class="tp-icon"><i class="bi <?= e($tool['icon'] ?: 'bi-calculator') ?>"></i></span>
          <h3><?= e($tool['name']) ?></h3>
          <p><?= e($tool['short_description']) ?></p>
          <div class="d-flex gap-1">
            <?php if ($tool['is_featured']): ?><span class="tp-badge tp-badge-featured">Featured</span><?php endif; ?>
            <?php if ($tool['is_popular']): ?><span class="tp-badge tp-badge-popular">Popular</span><?php endif; ?>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
    <?php if (!$tools): ?>
      <p class="text-muted">No tools published in this category yet — check back soon.</p>
    <?php endif; ?>
  </div>

  <?php if ($totalPages > 1): ?>
  <nav class="mt-4"><ul class="pagination">
    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
      <li class="page-item <?= $p === $page ? 'active' : '' ?>">
        <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
      </li>
    <?php endfor; ?>
  </ul></nav>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/footer.php'; ?>
