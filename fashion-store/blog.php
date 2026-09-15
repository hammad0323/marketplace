<?php
require_once __DIR__ . '/includes/functions.php';

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset = ($page - 1) * $perPage;
$total = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM blog_posts WHERE status='published'"))['c'];
$totalPages = max(1, ceil($total / $perPage));
$posts = mysqli_query($mysqli, "SELECT p.*, c.name AS category_name FROM blog_posts p LEFT JOIN blog_categories c ON c.id = p.blog_category_id WHERE p.status='published' ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset");

$pageTitle = 'Journal | ' . get_setting('store_name');
require_once __DIR__ . '/includes/header.php';
?>
<div class="container section-tight">
  <div class="section-head" data-aos="fade-up"><h1>The Journal</h1><p>Style stories, fabric guides and behind the scenes.</p></div>
  <div class="row row-cols-1 row-cols-md-3 g-4">
    <?php while ($p = mysqli_fetch_assoc($posts)): ?>
      <div class="col" data-aos="fade-up">
        <a href="<?= BASE_URL ?>/blog_post.php?slug=<?= e($p['slug']) ?>" class="text-decoration-none">
          <div class="promo-banner-img mb-3" style="height:220px">
            <img src="<?= e(BASE_URL . '/' . ($p['featured_image'] ?: 'assets/img/banner-placeholder.svg')) ?>">
          </div>
          <p class="small text-muted mb-1 text-uppercase"><?= e($p['category_name'] ?: 'Journal') ?> &middot; <?= e(date('d M Y', strtotime($p['created_at']))) ?></p>
          <h2 class="h5 font-serif"><?= e($p['title']) ?></h2>
          <p class="text-muted small"><?= e($p['excerpt']) ?></p>
        </a>
      </div>
    <?php endwhile; ?>
  </div>
  <?php if ($totalPages > 1): ?>
  <nav class="mt-4"><ul class="pagination justify-content-center">
    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
      <li class="page-item <?= $i==$page?'active':'' ?>"><a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a></li>
    <?php endfor; ?>
  </ul></nav>
  <?php endif; ?>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
