<?php
/** /offers/ - all active promotions + discounted products */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
require FS_ROOT . '/partials/product-card.php';
$promos = db_all('SELECT * FROM promotions WHERE ' . sql_active_window() . ' ORDER BY sort_order, id');
$deals = products_query(['on_sale' => 1, 'sort' => 'discount', 'limit' => 20])['items'];
$crumbs = [['Home', url('')], ['Offers & Deals', null]];
seo_apply(seo_get('static', 0, 'offers'), ['title' => 'Offers & Deals', 'description' => 'Grocery deals, school reopening offers, stationery bundles and combo deals at Fahim Store.', 'canonical' => abs_url('offers/')]);
page_set(['breadcrumbs' => $crumbs]);
require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="page-head"><?= breadcrumbs_html($crumbs) ?><h1>Offers &amp; Deals</h1></div>
  <?php if ($promos): ?>
  <div class="row g-3 mb-4">
    <?php foreach ($promos as $pr): ?>
    <div class="col-md-6 col-xl-3">
      <a href="<?= e(promotion_url($pr)) ?>" class="promo-card" style="background:<?= e($pr['bg_color'] ?: '#0f766e') ?>;color:<?= e($pr['text_color'] ?: '#fff') ?>">
        <?php if ($pr['badge_text']): ?><span class="promo-badge"><?= e($pr['badge_text']) ?></span><?php endif; ?>
        <span class="promo-type"><?= e(promo_types()[$pr['promo_type']] ?? '') ?></span>
        <h2 class="h4 fw-bold my-2"><?= e($pr['title']) ?></h2><p><?= e($pr['subtitle']) ?></p>
        <?php if ($pr['ends_at']): ?><small class="opacity-75 mb-2">Ends <?= fmt_date($pr['ends_at']) ?></small><?php endif; ?>
        <span class="promo-link"><?= e($pr['button_text'] ?: 'Shop now') ?> <i class="bi bi-arrow-right"></i></span>
      </a>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="section-head"><h2>Discounted products</h2><a class="view-all" href="<?= url('shop/?on_sale=1') ?>">View all <i class="bi bi-arrow-right"></i></a></div>
  <?= render_product_grid($deals) ?>
</div>
<?php require FS_ROOT . '/partials/footer.php';
