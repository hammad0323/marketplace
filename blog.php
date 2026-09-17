<?php
require __DIR__ . '/config.php';
$businessId = wh_current_business_id();
$posts = wh_fetch_all("SELECT * FROM blog_posts WHERE business_id=? AND status='published' ORDER BY published_at DESC", 'i', [$businessId]);

$pageTitle = 'Blog';
$seoPageKey = 'blog';
$activeNav = 'blog';
require __DIR__ . '/header.php';
?>
<section class="page-hero">
  <div class="container">
    <h1>Wedding &amp; Event Blog</h1>
    <p>Planning tips and guides from our team.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <div class="grid grid-3">
      <?php foreach ($posts as $post): $img = !empty($post['featured_image']) ? BASE_URL . '/' . $post['featured_image'] : 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=800&auto=format&fit=crop'; ?>
      <a href="<?= e(BASE_URL) ?>/blog/<?= e($post['slug']) ?>" class="card reveal" style="text-decoration:none;color:inherit;">
        <div class="hall-card-img" style="background-image:url('<?= e($img) ?>');"></div>
        <div class="hall-card-body">
          <?php if ($post['category']): ?><span class="eyebrow"><?= e($post['category']) ?></span><?php endif; ?>
          <h3><?= e($post['title']) ?></h3>
          <p style="font-size:.9rem;"><?= e($post['excerpt']) ?></p>
          <span class="hint"><?= wh_format_date($post['published_at']) ?></span>
        </div>
      </a>
      <?php endforeach; ?>
      <?php if (!$posts): ?><p>No articles published yet.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
