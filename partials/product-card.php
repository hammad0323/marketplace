<?php
/**
 * Product card renderer used by every listing.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

function render_product_card(array $p, string $q = ''): string
{
    $price = product_display_price($p);
    $stock = product_stock_label($p);
    $inStock = product_in_stock($p);
    $url = product_url($p);
    $wish = in_array((int) $p['id'], wishlist_ids(), true);
    $name = $q !== '' ? highlight($p['name'], $q) : e($p['name']);
    ob_start(); ?>
<div class="product-card h-100">
  <div class="pc-media">
    <?php if ($price['discount'] > 0): ?><span class="pc-badge">-<?= $price['discount'] ?>%</span><?php endif; ?>
    <?php if (!$inStock): ?><span class="pc-badge pc-badge-dark">Sold out</span><?php endif; ?>
    <button type="button" class="pc-wish js-wishlist<?= $wish ? ' active' : '' ?>" data-id="<?= (int) $p['id'] ?>" aria-label="Add to wishlist" aria-pressed="<?= $wish ? 'true' : 'false' ?>"><i class="bi bi-heart<?= $wish ? '-fill' : '' ?>"></i></button>
    <a href="<?= e($url) ?>" tabindex="-1" aria-hidden="true">
      <img src="<?= e(product_thumb_url($p)) ?>" alt="<?= e($p['image_alt'] ?: $p['name']) ?>" loading="lazy" decoding="async" width="300" height="300">
    </a>
  </div>
  <div class="pc-body">
    <?php if (!empty($p['brand_name'])): ?><div class="pc-brand"><?= e($p['brand_name']) ?></div><?php endif; ?>
    <h3 class="pc-title"><a href="<?= e($url) ?>"><?= $name ?></a></h3>
    <?php if ((int) $p['rating_count'] > 0): ?>
    <div class="pc-rating" aria-label="Rated <?= e($p['rating_avg']) ?> out of 5"><i class="bi bi-star-fill"></i> <?= number_format((float) $p['rating_avg'], 1) ?> <span>(<?= (int) $p['rating_count'] ?>)</span></div>
    <?php endif; ?>
    <div class="pc-price">
      <?php if ($price['from']): ?><small>From</small> <?php endif; ?>
      <strong><?= money($price['price']) ?></strong>
      <?php if ($price['old']): ?><del><?= money($price['old']) ?></del><?php endif; ?>
    </div>
    <div class="pc-stock text-<?= $stock[1] ?>"><i class="bi bi-circle-fill"></i> <?= e($stock[0]) ?></div>
    <div class="pc-actions">
      <?php if (!$inStock): ?>
        <button class="btn btn-outline-secondary btn-sm w-100" disabled>Out of stock</button>
      <?php elseif (!empty($p['has_variations'])): ?>
        <a href="<?= e($url) ?>" class="btn btn-outline-brand btn-sm w-100"><i class="bi bi-sliders me-1"></i>Choose option</a>
      <?php else: ?>
        <button type="button" class="btn btn-brand btn-sm w-100 js-add-cart" data-id="<?= (int) $p['id'] ?>"><i class="bi bi-cart-plus me-1"></i>Add to cart</button>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php
    return ob_get_clean();
}

/** Grid of product cards. */
function render_product_grid(array $products, string $cols = 'row-cols-2 row-cols-md-3 row-cols-lg-4 row-cols-xl-5', string $q = ''): string
{
    if (!$products) {
        return '<div class="empty-state"><i class="bi bi-search"></i><p>No products found.</p></div>';
    }
    $h = '<div class="row ' . $cols . ' g-2 g-md-3 product-grid">';
    foreach ($products as $p) {
        $h .= '<div class="col">' . render_product_card($p, $q) . '</div>';
    }
    return $h . '</div>';
}

/** Horizontal scroller (homepage sections). */
function render_product_scroller(array $products): string
{
    $h = '<div class="product-scroller">';
    foreach ($products as $p) {
        $h .= '<div class="ps-item">' . render_product_card($p) . '</div>';
    }
    return $h . '</div>';
}

/** Category card (matches the bordered card style used across the store). */
function render_category_card(array $c, bool $showCount = false, ?string $customImage = null): string
{
    $count = $showCount ? category_product_count((int) $c['id']) : null;
    return '<a class="category-card" href="' . e(category_url($c)) . '">'
        . '<span class="cc-img"><img src="' . e($customImage ? upload_url($customImage) : category_image_url($c)) . '" alt="' . e($c['image_alt'] ?: $c['name']) . '" loading="lazy" width="200" height="200"></span>'
        . '<span class="cc-name">' . e($c['name']) . '</span>'
        . ($showCount ? '<span class="cc-count">' . $count . ' ' . ($count === 1 ? 'product' : 'products') . '</span>' : '')
        . '</a>';
}
