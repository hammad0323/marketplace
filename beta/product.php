<?php
require __DIR__ . '/config/config.php';

$slug = $_GET['slug'] ?? '';
$product = db_fetch_one("SELECT p.*, s.shop_name, s.slug as shop_slug, s.logo as shop_logo, s.status as shop_status,
                                 c.name as category_name, c.slug as category_slug
                          FROM products p JOIN shops s ON s.id = p.shop_id JOIN categories c ON c.id = p.category_id
                          WHERE p.slug = ?", 's', [$slug]);

if (!$product || !in_array($product['status'], ['active']) || $product['shop_status'] !== 'active') {
    http_response_code(404); require __DIR__ . '/404.php'; exit;
}

db_exec("UPDATE products SET views = views + 1 WHERE id = ?", 'i', [$product['id']]);

$images = db_fetch_all("SELECT * FROM product_images WHERE product_id=? ORDER BY sort_order", 'i', [$product['id']]);
$variations = db_fetch_all("SELECT * FROM product_variations WHERE product_id=?", 'i', [$product['id']]);
$reviews = db_fetch_all("SELECT r.*, c.first_name, c.last_name FROM reviews r JOIN customers c ON c.id = r.customer_id
                          WHERE r.product_id=? AND r.status='approved' ORDER BY r.created_at DESC", 'i', [$product['id']]);
$related = db_fetch_all("SELECT p.*, s.shop_name, s.slug as shop_slug FROM products p JOIN shops s ON s.id=p.shop_id
                          WHERE p.category_id=? AND p.id != ? AND p.status='active' LIMIT 4", 'ii', [$product['category_id'], $product['id']]);

$price = $product['sale_price'] ?? $product['regular_price'];
$hasDiscount = !empty($product['sale_price']) && $product['sale_price'] < $product['regular_price'];

$customer = current_customer();
$isWishlisted = $customer ? (bool)db_fetch_one("SELECT id FROM wishlists WHERE customer_id=? AND product_id=?", 'ii', [$customer['id'], $product['id']]) : false;
$canReview = false;
if ($customer) {
    $canReview = (bool)db_fetch_one("SELECT oi.id FROM order_items oi JOIN shop_orders so ON so.id = oi.shop_order_id
        JOIN orders o ON o.id = so.order_id WHERE o.customer_id=? AND oi.product_id=? AND so.status='delivered'", 'ii', [$customer['id'], $product['id']]);
}

$seoEntityType = 'product'; $seoEntityId = $product['id'];
$pageTitle = $product['name'];
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/product-card.php';

echo render_schema([
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product['name'],
    'image' => product_image_or_default($product['main_image']),
    'description' => strip_tags($product['short_description'] ?? ''),
    'sku' => $product['sku'],
    'brand' => ['@type' => 'Brand', 'name' => $product['shop_name']],
    'offers' => ['@type' => 'Offer', 'priceCurrency' => 'PKR', 'price' => $price,
        'availability' => $product['stock_status'] === 'in_stock' ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock'],
    'aggregateRating' => $product['rating_count'] > 0 ? ['@type' => 'AggregateRating', 'ratingValue' => $product['rating_avg'], 'reviewCount' => $product['rating_count']] : null,
]);
?>
<div class="container">
  <div class="breadcrumb">
    <a href="<?= base_url() ?>">Home</a> / <a href="<?= base_url('category.php?slug=' . $product['category_slug']) ?>"><?= clean($product['category_name']) ?></a> / <?= clean($product['name']) ?>
  </div>
  <div class="product-detail">
    <div class="product-gallery">
      <img id="main-product-image" src="<?= product_image_or_default($product['main_image']) ?>" alt="<?= clean($product['name']) ?>">
      <?php if ($images): ?>
        <div class="gallery-thumbs">
          <img src="<?= product_image_or_default($product['main_image']) ?>" class="thumb active" onclick="document.getElementById('main-product-image').src=this.src;document.querySelectorAll('.thumb').forEach(t=>t.classList.remove('active'));this.classList.add('active');">
          <?php foreach ($images as $img): ?>
            <img src="<?= upload_url($img['image_path']) ?>" class="thumb" onclick="document.getElementById('main-product-image').src=this.src;document.querySelectorAll('.thumb').forEach(t=>t.classList.remove('active'));this.classList.add('active');">
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
    <div class="product-info">
      <h1><?= clean($product['name']) ?></h1>
      <div class="product-card-rating">
        <?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-<?= $i <= round($product['rating_avg']) ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?>
        <span>(<?= (int)$product['rating_count'] ?> reviews) &nbsp;|&nbsp; <?= (int)$product['total_sold'] ?> sold</span>
      </div>
      <div class="product-price-block">
        <span class="price-now"><?= format_price($price) ?></span>
        <?php if ($hasDiscount): ?><span class="price-old"><?= format_price($product['regular_price']) ?></span><?php endif; ?>
      </div>
      <p class="product-short-desc"><?= clean($product['short_description']) ?></p>
      <p class="stock-status <?= $product['stock_status'] ?>"><?= $product['stock_status'] === 'in_stock' ? 'In Stock (' . $product['stock_quantity'] . ' available)' : 'Out of Stock' ?></p>

      <?php if ($variations): ?>
        <div class="variation-select">
          <label>Options</label>
          <select id="variation-select">
            <?php foreach ($variations as $v): ?>
              <option value="<?= $v['id'] ?>" data-price="<?= $v['price'] ?>"><?= clean($v['variation_label']) ?> — <?= format_price($v['price']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="qty-cart-row">
        <div class="qty-input">
          <button type="button" onclick="document.getElementById('qty').stepDown()">-</button>
          <input type="number" id="qty" value="1" min="1" max="<?= (int)$product['stock_quantity'] ?>">
          <button type="button" onclick="document.getElementById('qty').stepUp()">+</button>
        </div>
        <button class="btn btn-primary add-to-cart-btn" data-product-id="<?= $product['id'] ?>" data-qty-input="qty" <?= $product['stock_status'] !== 'in_stock' ? 'disabled' : '' ?>>
          <i class="fa-solid fa-cart-plus"></i> Add to Cart
        </button>
        <button class="wishlist-btn <?= $isWishlisted ? 'active' : '' ?>" data-product-id="<?= $product['id'] ?>">
          <i class="fa-<?= $isWishlisted ? 'solid' : 'regular' ?> fa-heart"></i>
        </button>
      </div>
      <a href="<?= base_url('checkout.php?buy_now=' . $product['id']) ?>" class="btn btn-accent btn-block">Buy Now</a>

      <a class="shop-mini-card" href="<?= base_url('shop.php?slug=' . $product['shop_slug']) ?>">
        <img src="<?= shop_logo_or_default($product['shop_logo']) ?>" alt="">
        <span>Sold by <strong><?= clean($product['shop_name']) ?></strong></span>
      </a>
    </div>
  </div>

  <div class="product-tabs">
    <h2>Description</h2>
    <div class="product-description"><?= $product['description'] ?></div>

    <h2>Reviews (<?= count($reviews) ?>)</h2>
    <?php if ($canReview): ?>
      <form class="review-form" id="review-form" data-product-id="<?= $product['id'] ?>">
        <label>Your Rating</label>
        <select name="rating"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?> Star</option><?php endfor; ?></select>
        <input type="text" name="title" placeholder="Review title">
        <textarea name="comment" placeholder="Write your review..." required></textarea>
        <button type="submit" class="btn btn-primary">Submit Review</button>
      </form>
    <?php endif; ?>
    <?php if ($reviews): foreach ($reviews as $r): ?>
      <div class="review-item">
        <div class="review-head">
          <strong><?= clean($r['first_name'] . ' ' . $r['last_name']) ?></strong>
          <span><?php for ($i = 1; $i <= 5; $i++): ?><i class="fa-<?= $i <= $r['rating'] ? 'solid' : 'regular' ?> fa-star"></i><?php endfor; ?></span>
        </div>
        <p><?= clean($r['title']) ?></p>
        <p><?= clean($r['comment']) ?></p>
      </div>
    <?php endforeach; else: ?>
      <p class="text-muted">No reviews yet.</p>
    <?php endif; ?>
  </div>

  <?php if ($related): ?>
    <section class="section">
      <div class="section-head"><h2>Related Products</h2></div>
      <div class="product-grid"><?php foreach ($related as $p) render_product_card($p); ?></div>
    </section>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
