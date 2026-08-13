<?php
require_once __DIR__ . '/../config/config.php';

$slug = clean_input($_GET['slug'] ?? '');
$post = $slug ? db_select_one(
    $conn,
    'SELECT b.*, bc.name AS category_name, bc.slug AS category_slug, u.name AS author_name
     FROM blogs b LEFT JOIN blog_categories bc ON bc.id = b.category_id LEFT JOIN users u ON u.id = b.author_id
     WHERE b.slug = ? AND b.status = "published"',
    [$slug]
) : null;

if (!$post) {
    http_response_code(404);
    require ROOT_PATH . '/404.php';
    exit;
}

$tags = db_select($conn, 'SELECT t.* FROM blog_tag_map m JOIN blog_tags t ON t.id = m.tag_id WHERE m.blog_id = ?', [(int) $post['id']]);
$related = db_select($conn, 'SELECT * FROM blogs WHERE category_id = ? AND id != ? AND status = "published" ORDER BY published_at DESC LIMIT 3', [(int) $post['category_id'], (int) $post['id']]);

$pageTitle = $post['seo_title'] ?: $post['title'];
$metaDescription = $post['seo_description'] ?: $post['excerpt'];
$ogImage = $post['featured_image'];
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl" style="max-width:760px;">
    <?php if ($post['category_name']): ?><span class="eyebrow"><i class="bi bi-tag"></i> <?php echo e($post['category_name']); ?></span><?php endif; ?>
    <h1 style="font-size:clamp(26px,4vw,40px);font-weight:800;margin:16px 0 10px;"><?php echo e($post['title']); ?></h1>
    <div class="card-meta" style="margin-bottom:24px;">
      <?php if ($post['author_name']): ?><?php echo e($post['author_name']); ?> · <?php endif; ?>
      <?php echo e(format_date($post['published_at'])); ?>
    </div>

    <?php if ($post['featured_image']): ?>
      <img src="<?php echo e($post['featured_image']); ?>" style="width:100%;border-radius:var(--radius-lg);margin-bottom:32px;">
    <?php endif; ?>

    <div style="font-size:16px;line-height:1.8;color:var(--ink-soft);">
      <?php echo $post['content']; ?>
    </div>

    <?php if ($tags): ?>
      <div style="display:flex;gap:8px;margin-top:32px;flex-wrap:wrap;">
        <?php foreach ($tags as $t): ?><a href="<?php echo url('/pages/blog.php'); ?>?tag=<?php echo e($t['slug']); ?>" class="btn-w btn-outline btn-sm">#<?php echo e($t['name']); ?></a><?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($related): ?>
<div class="section-tight" style="background:var(--white);">
  <div class="container-xl">
    <h3 style="font-size:19px;font-weight:800;margin-bottom:20px;">More guides</h3>
    <div class="provider-grid">
      <?php foreach ($related as $r): ?>
        <a class="service-card" href="<?php echo url('/pages/blog-post.php'); ?>?slug=<?php echo e($r['slug']); ?>">
          <div class="thumb"><?php if ($r['featured_image']): ?><img src="<?php echo e($r['featured_image']); ?>"><?php endif; ?></div>
          <div class="card-body"><div class="card-title"><?php echo e($r['title']); ?></div></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php endif; ?>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
