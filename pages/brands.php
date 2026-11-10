<?php
/** /brands/ */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$brands = db_all("SELECT b.*, (SELECT COUNT(*) FROM products p WHERE p.brand_id = b.id AND p.status = 'active' AND p.deleted_at IS NULL) AS cnt
    FROM brands b WHERE b.is_active = 1 AND b.deleted_at IS NULL ORDER BY b.name");
$crumbs = [['Home', url('')], ['Brands', null]];
seo_apply(seo_get('static', 0, 'brands'), ['title' => 'Shop by Brand', 'description' => 'All brands available at Fahim Store - books, stationery and grocery brands.', 'canonical' => abs_url('brands/')]);
page_set(['breadcrumbs' => $crumbs]);
require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="page-head"><?= breadcrumbs_html($crumbs) ?><h1>Brands</h1></div>
  <div class="row row-cols-2 row-cols-md-4 row-cols-lg-6 g-3">
    <?php foreach ($brands as $b): ?>
    <div class="col"><a href="<?= e(brand_url($b)) ?>" class="brand-chip d-block h-100">
      <?php if ($b['logo']): ?><img src="<?= e(upload_url($b['logo'])) ?>" alt="<?= e($b['name']) ?>" height="40" loading="lazy" class="mb-2 d-block mx-auto"><?php endif; ?>
      <?= e($b['name']) ?><small class="d-block text-muted fw-normal"><?= (int) $b['cnt'] ?> products</small></a></div>
    <?php endforeach; ?>
  </div>
</div>
<?php require FS_ROOT . '/partials/footer.php';
