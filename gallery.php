<?php
require __DIR__ . '/config.php';
$businessId = wh_current_business_id();
$categories = wh_fetch_all('SELECT * FROM gallery_categories WHERE business_id=? ORDER BY name', 'i', [$businessId]);
$catFilter = (int) wh_input_get('category', 0);

$sql = 'SELECT g.*, c.name AS category_name FROM gallery g LEFT JOIN gallery_categories c ON c.id=g.category_id WHERE g.business_id=?';
$types = 'i';
$params = [$businessId];
if ($catFilter) {
    $sql .= ' AND g.category_id=?';
    $types .= 'i';
    $params[] = $catFilter;
}
$sql .= ' ORDER BY g.is_featured DESC, g.sort_order ASC, g.id DESC';
$images = wh_fetch_all($sql, $types, $params);

$pageTitle = 'Gallery';
$seoPageKey = 'gallery';
$activeNav = 'gallery';
require __DIR__ . '/header.php';
?>
<section class="page-hero">
  <div class="container">
    <h1>Gallery</h1>
    <p>A glimpse of the weddings and events we've hosted.</p>
  </div>
</section>
<section class="section">
  <div class="container">
    <?php if ($categories): ?>
    <div class="badge-row reveal" style="margin-bottom:32px;">
      <a href="<?= e(BASE_URL) ?>/gallery" class="chip" style="<?= !$catFilter ? 'background:var(--primary);color:#fff;' : '' ?>">All</a>
      <?php foreach ($categories as $cat): ?>
        <a href="<?= e(BASE_URL) ?>/gallery?category=<?= (int) $cat['id'] ?>" class="chip" style="<?= $catFilter === (int) $cat['id'] ? 'background:var(--primary);color:#fff;' : '' ?>"><?= e($cat['name']) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="gallery-grid reveal">
      <?php foreach ($images as $img): ?>
      <div class="gallery-item" data-lightbox="<?= e(BASE_URL . '/' . $img['image_path']) ?>">
        <img src="<?= e(BASE_URL . '/' . $img['image_path']) ?>" alt="<?= e($img['alt_text'] ?: $img['title']) ?>" loading="lazy">
      </div>
      <?php endforeach; ?>
      <?php if (!$images): ?><p>No photos yet — check back soon.</p><?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/footer.php'; ?>
