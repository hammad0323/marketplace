<?php
require_once __DIR__ . '/includes/functions.php';

$slug = $_GET['slug'] ?? '';
$stmt = mysqli_prepare($mysqli, "SELECT p.*, c.name AS category_name, c.slug AS category_slug, b.name AS brand_name FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN brands b ON b.id = p.brand_id WHERE p.slug = ? AND p.status = 'active'");
mysqli_stmt_bind_param($stmt, 's', $slug);
mysqli_stmt_execute($stmt);
$product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$product) { http_response_code(404); require __DIR__ . '/404.php'; exit; }

mysqli_query($mysqli, "UPDATE products SET views = views + 1 WHERE id = " . (int)$product['id']);

$images = [];
$imgRes = mysqli_query($mysqli, "SELECT * FROM product_images WHERE product_id = {$product['id']} ORDER BY is_primary DESC, sort_order ASC");
while ($r = mysqli_fetch_assoc($imgRes)) $images[] = $r['image_path'];
if (!$images) $images[] = 'assets/img/placeholder.svg';

$variations = [];
$varRes = mysqli_query($mysqli, "SELECT * FROM product_variations WHERE product_id = {$product['id']} AND status='active'");
while ($v = mysqli_fetch_assoc($varRes)) {
    $labelRes = mysqli_query($mysqli, "SELECT av.value, at.name AS attr_name, av.id FROM product_variation_values pvv JOIN attribute_values av ON av.id = pvv.attribute_value_id JOIN attributes at ON at.id = av.attribute_id WHERE pvv.variation_id = {$v['id']}");
    $v['attrs'] = [];
    while ($l = mysqli_fetch_assoc($labelRes)) $v['attrs'][$l['attr_name']] = ['value' => $l['value'], 'id' => $l['id']];
    $variations[] = $v;
}
$attrGroups = [];
foreach ($variations as $v) {
    foreach ($v['attrs'] as $attrName => $av) {
        $attrGroups[$attrName][$av['id']] = $av['value'];
    }
}

$avgRating = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT AVG(rating) avg_r, COUNT(*) cnt FROM reviews WHERE product_id = {$product['id']} AND status='approved'"));
$reviews = mysqli_query($mysqli, "SELECT * FROM reviews WHERE product_id = {$product['id']} AND status='approved' ORDER BY created_at DESC");

$related = fetch_products_by_filter($mysqli, 'category', $product['category_id'], 4);
$related = array_values(array_filter($related, fn($r) => $r['id'] != $product['id']));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    csrf_verify();
    if (get_setting('reviews_enabled', '1') === '1') {
        $name = trim($_POST['name'] ?? '');
        $rating = max(1, min(5, (int)($_POST['rating'] ?? 5)));
        $comment = trim($_POST['comment'] ?? '');
        if ($name !== '' && $comment !== '') {
            $custId = customer_logged_in() ? $_SESSION['customer_id'] : null;
            $stmt = mysqli_prepare($mysqli, "INSERT INTO reviews (product_id, customer_id, name, rating, comment) VALUES (?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'iisis', $product['id'], $custId, $name, $rating, $comment);
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Thank you! Your review has been submitted and is pending approval.');
            redirect(product_url($slug) . '#reviews');
        }
    }
}

$pageTitle = ($product['seo_title'] ?: $product['name']) . ' | ' . get_setting('store_name');
$pageDescription = $product['seo_description'] ?: $product['short_description'];
$seoKeywords = $product['seo_keywords'] ?: $product['tags'];
$seoType = 'product';
$seoImage = BASE_URL . '/' . $images[0];
$seoNoindex = $product['status'] !== 'active';
$structuredData = [
    product_schema($product, $images, $avgRating),
    breadcrumb_schema([
        ['name' => 'Home', 'url' => url()],
        ['name' => $product['category_name'], 'url' => category_url($product['category_slug'])],
        ['name' => $product['name'], 'url' => product_url($product['slug'])],
    ]),
];
require_once __DIR__ . '/includes/header.php';
$onSale = !empty($product['sale_price']) && $product['sale_price'] < $product['regular_price'];
?>
<div class="container section-tight">
  <nav class="small text-muted mb-4"><a href="<?= url() ?>">Home</a> / <a href="<?= e(category_url($product['category_slug'])) ?>"><?= e($product['category_name']) ?></a> / <?= e($product['name']) ?></nav>

  <div class="row g-5">
    <div class="col-lg-6">
      <div class="pd-gallery-main"><img id="pdMainImage" src="<?= e(BASE_URL . '/' . $images[0]) ?>" alt="<?= e($product['name']) ?>"></div>
      <?php if (count($images) > 1): ?>
      <div class="pd-thumbs">
        <?php foreach ($images as $i => $img): ?>
          <img src="<?= e(BASE_URL . '/' . $img) ?>" data-full="<?= e(BASE_URL . '/' . $img) ?>" class="<?= $i===0?'active':'' ?>">
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
    <div class="col-lg-6">
      <?php if ($product['brand_name']): ?><p class="text-muted small text-uppercase mb-1"><?= e($product['brand_name']) ?></p><?php endif; ?>
      <h1 class="h3 font-serif mb-2"><?= e($product['name']) ?></h1>
      <p class="text-muted small mb-2">SKU: <?= e($product['sku']) ?></p>
      <?php if ($avgRating['cnt'] > 0): ?>
        <div class="mb-3"><span class="rating-stars"><?= str_repeat('★', round($avgRating['avg_r'])) . str_repeat('☆', 5 - round($avgRating['avg_r'])) ?></span> <span class="small text-muted">(<?= (int)$avgRating['cnt'] ?> reviews)</span></div>
      <?php endif; ?>
      <div class="product-price mb-3 fs-4">
        <?php if ($onSale): ?>
          <span class="old"><?= format_price($product['regular_price']) ?></span><span class="new"><?= format_price($product['sale_price']) ?></span>
        <?php else: ?>
          <span class="new"><?= format_price($product['regular_price']) ?></span>
        <?php endif; ?>
      </div>
      <p class="text-muted mb-4"><?= e($product['short_description']) ?></p>

      <form id="pdForm">
        <?php foreach ($attrGroups as $attrName => $values): ?>
          <div class="mb-3">
            <label class="form-label fw-medium"><?= e($attrName) ?></label><br>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($values as $vid => $val): ?>
                <label class="<?= $attrName==='Color'?'color-swatch':'size-swatch' ?>" data-attr="<?= e($attrName) ?>" data-value-id="<?= (int)$vid ?>">
                  <input type="radio" name="attr_<?= e($attrName) ?>" value="<?= (int)$vid ?>" class="d-none"><?= e($val) ?>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="mb-4 d-flex align-items-center gap-3">
          <div class="qty-box">
            <button type="button" data-dir="dec"><i class="bi bi-dash"></i></button>
            <input type="number" id="pdQty" value="1" min="1">
            <button type="button" data-dir="inc"><i class="bi bi-plus"></i></button>
          </div>
          <span id="pdStockMsg" class="small text-muted">
            <?= $product['stock_status'] === 'out_of_stock' ? '<span class="text-danger">Out of Stock</span>' : ($product['stock_qty'] <= 5 ? 'Only ' . (int)$product['stock_qty'] . ' left' : 'In Stock') ?>
          </span>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <button type="button" id="pdAddToCart" class="btn-brand" <?= $product['stock_status']==='out_of_stock'?'disabled':'' ?> data-product-id="<?= (int)$product['id'] ?>">Add to Cart</button>
          <button type="button" id="pdBuyNow" class="btn-outline-brand" <?= $product['stock_status']==='out_of_stock'?'disabled':'' ?>>Buy Now</button>
          <button type="button" class="qa-btn js-wishlist-toggle" data-product-id="<?= (int)$product['id'] ?>" style="width:48px;height:48px;"><i class="bi bi-heart"></i></button>
        </div>
      </form>

      <ul class="nav nav-tabs mt-5" id="pdTabs">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-desc">Description</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-info">Additional Info</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-shipping">Shipping &amp; Returns</button></li>
      </ul>
      <div class="tab-content py-3">
        <div class="tab-pane fade show active" id="tab-desc"><?= $product['description'] ?: '<p class="text-muted">No description available.</p>' ?></div>
        <div class="tab-pane fade" id="tab-info">
          <table class="table table-sm">
            <?php if ($product['weight']): ?><tr><th>Weight</th><td><?= e($product['weight']) ?></td></tr><?php endif; ?>
            <?php if ($product['dimensions']): ?><tr><th>Dimensions</th><td><?= e($product['dimensions']) ?></td></tr><?php endif; ?>
            <tr><th>Category</th><td><?= e($product['category_name']) ?></td></tr>
            <?php if ($product['tags']): ?><tr><th>Tags</th><td><?= e($product['tags']) ?></td></tr><?php endif; ?>
          </table>
        </div>
        <div class="tab-pane fade" id="tab-shipping">
          <p class="text-muted">Orders are processed within 1-2 business days. Delivery typically takes 3-5 business days depending on your location. Items can be returned within 7 days of delivery in original, unused condition.</p>
        </div>
      </div>
    </div>
  </div>

  <?php if (get_setting('reviews_enabled', '1') === '1'): ?>
  <div class="row mt-5" id="reviews">
    <div class="col-lg-8">
      <h2 class="h5 font-serif mb-4">Customer Reviews</h2>
      <?php mysqli_data_seek($reviews, 0); if (mysqli_num_rows($reviews) === 0): ?>
        <p class="text-muted">No reviews yet. Be the first to review this product.</p>
      <?php endif; ?>
      <?php while ($r = mysqli_fetch_assoc($reviews)): ?>
        <div class="border-bottom pb-3 mb-3">
          <div class="rating-stars"><?= str_repeat('★', (int)$r['rating']) . str_repeat('☆', 5 - (int)$r['rating']) ?></div>
          <p class="mb-1 fw-medium"><?= e($r['name']) ?></p>
          <p class="text-muted small mb-1"><?= e(date('d M Y', strtotime($r['created_at']))) ?></p>
          <p class="mb-0"><?= e($r['comment']) ?></p>
        </div>
      <?php endwhile; ?>

      <h3 class="h6 mt-4 mb-3">Write a Review</h3>
      <form method="post">
        <?= csrf_field() ?>
        <div class="row g-3">
          <div class="col-md-6"><input type="text" name="name" class="form-control" placeholder="Your Name" required value="<?= e($__customer['name'] ?? '') ?>"></div>
          <div class="col-md-6">
            <select name="rating" class="form-select">
              <?php for ($i=5;$i>=1;$i--): ?><option value="<?= $i ?>"><?= $i ?> Star<?= $i>1?'s':'' ?></option><?php endfor; ?>
            </select>
          </div>
          <div class="col-12"><textarea name="comment" class="form-control" rows="3" placeholder="Share your experience..." required></textarea></div>
          <div class="col-12"><button type="submit" name="submit_review" value="1" class="btn-brand">Submit Review</button></div>
        </div>
      </form>
    </div>
  </div>
  <?php endif; ?>

  <?php if ($related): ?>
  <div class="mt-5">
    <div class="section-head text-start"><h2>You May Also Like</h2></div>
    <div class="row row-cols-2 row-cols-md-4 g-3 g-md-4">
      <?php foreach ($related as $p): ?><div class="col"><?php render_product_card($mysqli, $p); ?></div><?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
(function(){
  var variationMap = <?= json_encode(array_map(function($v){ return ['id'=>$v['id'],'value_ids'=>array_map(fn($a)=>$a['id'],$v['attrs']),'stock'=>$v['stock_qty'],'price'=>$v['price']]; }, $variations)) ?>;
  document.querySelectorAll('.size-swatch, .color-swatch').forEach(function(el){
    el.addEventListener('click', function(){
      document.querySelectorAll('.size-swatch[data-attr="'+el.dataset.attr+'"], .color-swatch[data-attr="'+el.dataset.attr+'"]').forEach(function(o){o.classList.remove('active');});
      el.classList.add('active');
      el.querySelector('input').checked = true;
    });
  });
  function selectedVariation(){
    var selected = Array.from(document.querySelectorAll('.size-swatch input:checked, .color-swatch input:checked')).map(function(i){return parseInt(i.value,10);});
    if (!selected.length) return null;
    return variationMap.find(function(v){
      return selected.every(function(s){ return v.value_ids.indexOf(s) !== -1; }) && v.value_ids.length === selected.length;
    });
  }
  function addToCart(redirect){
    var variation = selectedVariation();
    var body = new FormData();
    body.append('product_id', document.getElementById('pdAddToCart').dataset.productId);
    if (variation) body.append('variation_id', variation.id);
    body.append('qty', document.getElementById('pdQty').value || 1);
    fetch(BASE_URL + '/ajax/add_to_cart.php', { method:'POST', body: body })
      .then(function(r){ return r.json(); })
      .then(function(data){
        if (data.success) {
          document.querySelectorAll('.cart-count').forEach(function(el){ el.textContent = data.cart_count; });
          if (redirect) { window.location.href = BASE_URL + '/checkout'; }
          else toast('Added to cart', 'success');
        } else { toast(data.message || 'Could not add to cart', 'error'); }
      });
  }
  var addBtn = document.getElementById('pdAddToCart');
  var buyBtn = document.getElementById('pdBuyNow');
  if (addBtn) addBtn.addEventListener('click', function(){ addToCart(false); });
  if (buyBtn) buyBtn.addEventListener('click', function(){ addToCart(true); });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
