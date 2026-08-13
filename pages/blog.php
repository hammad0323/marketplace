<?php
require_once __DIR__ . '/../config/config.php';

$categorySlug = clean_input($_GET['category'] ?? '');
$tagSlug = clean_input($_GET['tag'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));

$where = ['b.status = "published"'];
$params = [];
$joins = '';
if ($categorySlug !== '') {
    $where[] = 'bc.slug = ?';
    $params[] = $categorySlug;
}
if ($tagSlug !== '') {
    $joins .= ' JOIN blog_tag_map btm ON btm.blog_id = b.id JOIN blog_tags bt ON bt.id = btm.tag_id';
    $where[] = 'bt.slug = ?';
    $params[] = $tagSlug;
}
$whereSql = implode(' AND ', $where);

$pg = paginate($conn, "SELECT COUNT(DISTINCT b.id) FROM blogs b LEFT JOIN blog_categories bc ON bc.id = b.category_id $joins WHERE $whereSql", $params, $page, 9);
$posts = db_select(
    $conn,
    "SELECT DISTINCT b.*, bc.name AS category_name FROM blogs b LEFT JOIN blog_categories bc ON bc.id = b.category_id $joins
     WHERE $whereSql ORDER BY b.published_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
    $params
);
$categories = db_select($conn, 'SELECT bc.*, COUNT(b.id) AS post_count FROM blog_categories bc JOIN blogs b ON b.category_id = bc.id AND b.status = "published" GROUP BY bc.id ORDER BY bc.name');

$pageTitle = 'Travel Guides & Blog';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl">
    <div class="section-head center">
      <span class="eyebrow"><i class="bi bi-journal-text"></i> Blog</span>
      <h1 class="section-heading">Travel guides &amp; tips</h1>
      <p class="section-sub">Destination guides, planning tips, and inspiration for your next trip.</p>
    </div>

    <?php if ($categories): ?>
      <div style="display:flex;gap:8px;justify-content:center;flex-wrap:wrap;margin-bottom:32px;">
        <a href="<?php echo url('/pages/blog.php'); ?>" class="btn-w btn-sm <?php echo $categorySlug === '' ? 'btn-primary' : 'btn-outline'; ?>">All</a>
        <?php foreach ($categories as $cat): ?>
          <a href="?category=<?php echo e($cat['slug']); ?>" class="btn-w btn-sm <?php echo $categorySlug === $cat['slug'] ? 'btn-primary' : 'btn-outline'; ?>"><?php echo e($cat['name']); ?> (<?php echo (int) $cat['post_count']; ?>)</a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($posts): ?>
      <div class="provider-grid stagger reveal in-view">
        <?php foreach ($posts as $p): ?>
          <a class="service-card" href="<?php echo url('/pages/blog-post.php'); ?>?slug=<?php echo e($p['slug']); ?>">
            <div class="thumb"><?php if ($p['featured_image']): ?><img src="<?php echo e($p['featured_image']); ?>"><?php endif; ?></div>
            <div class="card-body">
              <?php if ($p['category_name']): ?><div class="card-meta"><i class="bi bi-tag"></i> <?php echo e($p['category_name']); ?></div><?php endif; ?>
              <div class="card-title"><?php echo e($p['title']); ?></div>
              <p style="font-size:13px;color:var(--ink-mute);margin-top:6px;"><?php echo e($p['excerpt']); ?></p>
              <div class="card-meta" style="margin-top:8px;"><i class="bi bi-calendar3"></i> <?php echo e(format_date($p['published_at'])); ?></div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if ($pg['total_pages'] > 1): ?>
        <div style="display:flex;gap:6px;justify-content:center;margin-top:32px;">
          <?php for ($i = 1; $i <= $pg['total_pages']; $i++): ?>
            <a href="?page=<?php echo $i; ?>&category=<?php echo e($categorySlug); ?>" class="btn-w btn-sm <?php echo $i === $pg['page'] ? 'btn-primary' : 'btn-outline'; ?>"><?php echo $i; ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    <?php else: ?>
      <div class="empty-state"><div class="icon-wrap"><i class="bi bi-journal-text"></i></div><h4>No posts yet</h4><p>Check back soon for travel guides and tips.</p></div>
    <?php endif; ?>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
