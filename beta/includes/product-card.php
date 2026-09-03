<?php
function render_product_card($p) {
    $price = $p['sale_price'] ?? $p['regular_price'];
    $hasDiscount = !empty($p['sale_price']) && $p['sale_price'] < $p['regular_price'];
    $discountPercent = $hasDiscount ? round((($p['regular_price'] - $p['sale_price']) / $p['regular_price']) * 100) : 0;
    $customer = current_customer();
    $isWishlisted = false;
    if ($customer) {
        $isWishlisted = (bool)db_fetch_one("SELECT id FROM wishlists WHERE customer_id=? AND product_id=?", 'ii', [$customer['id'], $p['id']]);
    }
    ?>
    <div class="product-card" data-product-id="<?= $p['id'] ?>">
      <a href="<?= base_url('product.php?slug=' . $p['slug']) ?>" class="product-card-img">
        <img src="<?= product_image_or_default($p['main_image']) ?>" alt="<?= clean($p['name']) ?>" loading="lazy">
        <?php if ($hasDiscount): ?><span class="badge-discount">-<?= $discountPercent ?>%</span><?php endif; ?>
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
        <button class="btn btn-primary btn-block add-to-cart-btn" data-product-id="<?= $p['id'] ?>">Add to Cart</button>
      </div>
    </div>
    <?php
}
