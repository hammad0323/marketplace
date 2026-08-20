<?php
/**
 * blog-post.php — generic renderer for /blog/<slug>.php, written by the
 * Admin Blog CMS the same way tool-page.php/category-page.php are.
 */

require_once __DIR__ . '/config.php';

$slug = $blogSlug ?? '';
$post = get_blog_post_by_slug($slug);

if (!$post) {
    http_response_code(404);
    require __DIR__ . '/../404.php';
    exit;
}

tp_execute('UPDATE blog_posts SET views = views + 1 WHERE id = ?', 'i', [$post['id']]);

$relatedTools = tp_query(
    "SELECT t.* FROM tools t JOIN blog_post_tools bpt ON bpt.tool_id = t.id
     WHERE bpt.blog_post_id = ? AND t.status = 'published' LIMIT 6",
    'i',
    [$post['id']]
);

$seoRow = get_seo_settings('blog_post', (int) $post['id']) ?? [];
$pageTitle = $seoRow['seo_title'] ?? ($post['title'] . ' — ' . tp_setting('site_name'));
$pageDescriptionFallback = $seoRow['meta_description'] ?? $post['excerpt'];
$pageSeo = $seoRow;
$breadcrumbItems = [
    ['label' => 'Home', 'url' => tp_url()],
    ['label' => 'Blog', 'url' => tp_url('blog.php')],
    ['label' => $post['title'], 'url' => null],
];
require __DIR__ . '/header.php';
?>
<article class="tp-container py-5" style="max-width:800px;">
  <h1 class="fw-bold mb-2"><?= e($post['title']) ?></h1>
  <p class="text-muted mb-4"><?= date('F j, Y', strtotime($post['created_at'])) ?></p>
  <?php if ($post['featured_image']): ?>
    <img src="<?= e($post['featured_image']) ?>" alt="<?= e($post['title']) ?>" class="img-fluid rounded-4 mb-4">
  <?php endif; ?>
  <div class="cms-content"><?= $post['content'] /* CKEditor HTML, admin-authored & trusted */ ?></div>

  <?php if ($relatedTools): ?>
  <section class="mt-5">
    <h2 class="h5 fw-bold mb-3">Related Tools</h2>
    <div class="row g-3">
      <?php foreach ($relatedTools as $t): ?>
        <div class="col-sm-6">
          <a href="<?= tp_url($t['slug'] . '.php') ?>" class="tp-card tp-tool-card text-decoration-none">
            <span class="tp-icon"><i class="bi <?= e($t['icon'] ?: 'bi-calculator') ?>"></i></span>
            <h3><?= e($t['name']) ?></h3>
            <p><?= e($t['short_description']) ?></p>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
</article>
<?php require __DIR__ . '/footer.php'; ?>
