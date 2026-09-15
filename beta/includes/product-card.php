<?php
function render_product_card($p, $badgeOverride = null) {
    $price = $p['sale_price'] ?? $p['regular_price'];
    $hasDiscount = !empty($p['sale_price']) && $p['sale_price'] < $p['regular_price'];
    $discountPercent = $hasDiscount ? round((($p['regular_price'] - $p['sale_price']) / $p['regular_price']) * 100) : 0;
    $customer = current_customer();
    $isWishlisted = false;
    if ($customer) {
        $isWishlisted = (bool)db_fetch_one("SELECT id FROM wishlists WHERE customer_id=? AND product_id=?", 'ii', [$customer['id'], $p['id']]);
    }
    $isNew = !empty($p['created_at']) && (strtotime($p['created_at']) > strtotime('-14 days'));
    $badgeText = $badgeOverride ?: ($hasDiscount ? '-' . $discountPercent . '%' : ($isNew ? 'New' : null));
    $badgeClass = $hasDiscount ? 'badge-sale' : ($isNew ? 'badge-new' : 'badge-hot');
    ?>
    <div class="product-card" data-product-id="<?= $p['id'] ?>">
      <a href="<?= base_url('product.php?slug=' . $p['slug']) ?>" class="product-card-img">
        <img src="<?= product_image_or_default($p['main_image']) ?>" alt="<?= clean($p['name']) ?>" loading="lazy">
        <?php if ($badgeText): ?><span class="product-badge <?= $badgeClass ?>"><?= clean($badgeText) ?></span><?php endif; ?>
      </a>
      <button class="wishlist-btn <?= $isWishlisted ? 'active' : '' ?>" data-product-id="<?= $p['id'] ?>" title="Add to wishlist">
        <i class="fa-<?= $isWishlisted ? 'solid' : 'regular' ?> fa-heart"></i>
      </button>
      <div class="product-card-body">
        <a href="<?= base_url('shop.php?slug=' . ($p['shop_slug'] ?? '')) ?>" class="product-card-shop"><?= clean($p['shop_name'] ?? '') ?></a>
        <a href="<?= base_url('product.php?slug=' . $p['slug']) ?>" class="product-card-title"><?= clean($p['name']) ?></a>
        <div class="product-card-rating">
          <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-<?= $i <= round($p['rating_avg']) ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
          <span>(<?= (int)$p['rating_count'] ?>)</span>
        </div>
        <div class="product-card-price">
          <span class="price-now"><?= format_price($price) ?></span>
          <?php if ($hasDiscount): ?><span class="price-old"><?= format_price($p['regular_price']) ?></span><?php endif; ?>
        </div>
      </div>
      <button class="add-to-cart-fab add-to-cart-btn" data-product-id="<?= $p['id'] ?>" title="Add to cart">
        <i class="fa-solid fa-cart-plus"></i><span>Add</span>
      </button>
    </div>
    <?php
}

function render_deal_card($p) {
    $price = $p['sale_price'] ?? $p['regular_price'];
    $hasDiscount = !empty($p['sale_price']) && $p['sale_price'] < $p['regular_price'];
    $discountPercent = $hasDiscount ? round((($p['regular_price'] - $p['sale_price']) / $p['regular_price']) * 100) : 0;
    $sold = (int)$p['total_sold'];
    $available = $sold + (int)$p['stock_quantity'];
    $percentSold = $available > 0 ? min(100, round(($sold / $available) * 100)) : 0;
    ?>
    <div class="deal-card">
      <a href="<?= base_url('product.php?slug=' . $p['slug']) ?>" class="product-card-img">
        <img src="<?= product_image_or_default($p['main_image']) ?>" alt="<?= clean($p['name']) ?>" loading="lazy">
        <?php if ($hasDiscount): ?><span class="product-badge badge-sale">-<?= $discountPercent ?>%</span><?php endif; ?>
      </a>
      <div class="product-card-body">
        <a href="<?= base_url('product.php?slug=' . $p['slug']) ?>" class="product-card-title"><?= clean($p['name']) ?></a>
        <div class="product-card-price">
          <span class="price-now"><?= format_price($price) ?></span>
          <?php if ($hasDiscount): ?><span class="price-old"><?= format_price($p['regular_price']) ?></span><?php endif; ?>
        </div>
        <div class="deal-progress">
          <div class="deal-progress-bar"><div class="deal-progress-fill" style="width:<?= $percentSold ?>%"></div></div>
          <span class="deal-progress-label">Sold: <?= $sold ?>/<?= $available ?></span>
        </div>
        <button class="btn btn-primary btn-pill btn-block add-to-cart-btn" data-product-id="<?= $p['id'] ?>">Add to Cart</button>
      </div>
    </div>
    <?php
}
