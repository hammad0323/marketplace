<?php
/** /offers/{slug}/ - one promotion with its products */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
require FS_ROOT . '/partials/product-card.php';
$pr = db_row('SELECT * FROM promotions WHERE slug = ? AND ' . sql_active_window(), [$GLOBALS['ROUTE_PARAMS']['slug']]);
if (!$pr) route_404();
$ids = array_map('intval', db_col('SELECT product_id FROM promotion_products WHERE promotion_id = ? ORDER BY sort_order', [$pr['id']]));
$products = $ids ? products_query(['ids' => $ids, 'sort' => 'ids', 'limit' => 100])['items'] : [];
$crumbs = [['Home', url('')], ['Offers', url('offers/')], [$pr['title'], null]];
seo_apply(seo_get('promotion', (int) $pr['id']), ['title' => $pr['title'], 'description' => $pr['subtitle'] ?: str_limit(strip_tags((string) $pr['description']), 160),
    'canonical' => abs_url('offers/' . $pr['slug'] . '/'), 'og_image' => $pr['image'] ? upload_url($pr['image']) : '']);
page_set(['breadcrumbs' => $crumbs]);
require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="page-head"><?= breadcrumbs_html($crumbs) ?></div>
  <div class="offer-hero mb-4" style="background:<?= e($pr['bg_color'] ?: '#0f766e') ?>;color:<?= e($pr['text_color'] ?: '#fff') ?>">
    <div class="row align-items-center">
      <div class="col-md-8">
        <span class="hero-kicker"><?= e(promo_types()[$pr['promo_type']] ?? 'Offer') ?></span>
        <h1 class="fw-bold"><?= e($pr['title']) ?></h1>
        <p class="lead mb-1"><?= e($pr['subtitle']) ?></p>
        <?php if ($pr['ends_at']): ?><p class="small opacity-75 mb-0"><i class="bi bi-clock me-1"></i>Offer valid till <?= fmt_datetime($pr['ends_at']) ?></p><?php endif; ?>
      </div>
      <?php if ($pr['image']): ?><div class="col-md-4 text-center"><img src="<?= e(upload_url($pr['image'])) ?>" alt="<?= e($pr['title']) ?>" class="img-fluid" style="max-height:200px"></div><?php endif; ?>
    </div>
  </div>
  <?php if ($pr['description']): ?><div class="mb-4"><?= strip_tags($pr['description'], '<p><br><ul><ol><li><strong><b><em><h2><h3>') ?></div><?php endif; ?>
  <?= render_product_grid($products) ?>
</div>
<?php require FS_ROOT . '/partials/footer.php';
