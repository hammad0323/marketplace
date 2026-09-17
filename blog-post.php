<?php
require __DIR__ . '/config.php';
$businessId = wh_current_business_id();
$slug = wh_input_get('slug');
$post = wh_fetch_one("SELECT * FROM blog_posts WHERE business_id=? AND slug=? AND status='published'", 'is', [$businessId, $slug]);
if (!$post) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$pageTitle = $post['seo_title'] ?: $post['title'];
$metaDescription = $post['meta_description'] ?: $post['excerpt'];
$activeNav = 'blog';
require __DIR__ . '/header.php';
$img = !empty($post['featured_image']) ? BASE_URL . '/' . $post['featured_image'] : 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=1600&auto=format&fit=crop';
?>
<section class="section" style="padding-top:48px;">
  <div class="container" style="max-width:760px;">
    <div class="breadcrumb reveal"><a href="<?= e(BASE_URL) ?>/blog">Blog</a> / <?= e($post['title']) ?></div>
    <h1 class="reveal"><?= e($post['title']) ?></h1>
    <p class="hint reveal"><?= wh_format_date($post['published_at']) ?><?= $post['category'] ? ' · ' . e($post['category']) : '' ?></p>
    <img src="<?= e($img) ?>" alt="<?= e($post['title']) ?>" class="reveal" style="border-radius:var(--radius);margin:24px 0;">
    <div class="reveal" style="font-size:1.05rem;"><?= $post['content'] ?></div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
