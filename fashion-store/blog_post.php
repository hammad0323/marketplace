<?php
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$stmt = mysqli_prepare($mysqli, "SELECT p.*, c.name AS category_name FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.blog_category_id WHERE p.slug = ? AND p.status='published'");
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$post = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$post) { http_response_code(404); require __DIR__ . '/404.php'; exit; }

$pageTitle = ($post['seo_title'] ?: $post['title']) . ' | ' . get_setting('store_name');
$pageDescription = $post['seo_description'] ?: $post['excerpt'];
$seoType = 'article';
if ($post['featured_image']) $seoImage = BASE_URL . '/' . $post['featured_image'];
$structuredData = [
    breadcrumb_schema([
        ['name' => 'Home', 'url' => url()],
        ['name' => 'Journal', 'url' => url('blog')],
        ['name' => $post['title'], 'url' => blog_url($post['slug'])],
    ]),
    [
        '@context' => 'https://schema.org',
        '@type' => 'Article',
        'headline' => $post['title'],
        'datePublished' => date('c', strtotime($post['created_at'])),
        'author' => ['@type' => 'Organization', 'name' => $post['author'] ?: get_setting('store_name')],
        'image' => $post['featured_image'] ? [BASE_URL . '/' . $post['featured_image']] : [],
    ],
];
require_once __DIR__ . '/includes/header.php';
?>
<div class="container section-tight" style="max-width:800px">
  <p class="small text-muted text-uppercase mb-2"><?= e($post['category_name'] ?: 'Journal') ?> &middot; <?= e(date('d M Y', strtotime($post['created_at']))) ?> <?= $post['author'] ? '&middot; By '.e($post['author']) : '' ?></p>
  <h1 class="font-serif mb-4"><?= e($post['title']) ?></h1>
  <?php if ($post['featured_image']): ?><img src="<?= e(BASE_URL . '/' . $post['featured_image']) ?>" class="w-100 mb-4" style="border-radius:8px;max-height:460px;object-fit:cover"><?php endif; ?>
  <div class="blog-content"><?= $post['content'] ?></div>
  <a href="<?= url('blog') ?>" class="btn-outline-brand mt-4">&larr; Back to Journal</a>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
