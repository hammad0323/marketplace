<?php
/**
 * /product/{slug}/ - product detail page.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
require FS_ROOT . '/partials/product-card.php';

$p = product_get_by_slug($GLOBALS['ROUTE_PARAMS']['slug']);
if (!$p || $p['status'] !== 'active') route_404();
$full = product_full((int) $p['id']);
$p = array_merge($full, $p);
$pid = (int) $p['id'];

// Review submission (logged-in customers)
if (is_post() && input('action') === 'review') {
    csrf_check();
    $c = current_customer();
    $rating = max(1, min(5, input_int('rating')));
    if (!$c) {
        flash('warning', 'Please log in to write a review.');
    } elseif (!form_throttle('review', 30)) {
        flash('warning', 'Please wait a moment before submitting another review.');
    } elseif (db_val('SELECT id FROM reviews WHERE product_id = ? AND customer_id = ?', [$pid, $c['id']])) {
        flash('info', 'You have already reviewed this product.');
    } else {
        db_insert('INSERT INTO reviews (product_id, customer_id, reviewer_name, rating, title, body) VALUES (?, ?, ?, ?, ?, ?)',
            [$pid, $c['id'], $c['name'], $rating, mb_substr(input('title'), 0, 160), mb_substr(input('body'), 0, 2000)]);
        notify_admin('review', 'New review on ' . $p['name'], $rating . ' stars by ' . $c['name'], 'reviews.php');
        flash('success', 'Thank you! Your review will appear after approval.');
    }
    redirect(product_url($p) . '#reviews');
}

db_exec('UPDATE products SET views_count = views_count + 1 WHERE id = ?', [$pid]);
// Recently viewed (cookie of ids)
$recent = array_values(array_filter(array_map('intval', explode(',', $_COOKIE['fs_recent'] ?? '')), function ($id) use ($pid) { return $id && $id !== $pid; }));
array_unshift($recent, $pid);
setcookie('fs_recent', implode(',', array_slice($recent, 0, 12)), ['expires' => time() + 86400 * 30, 'path' => base_path() ?: '/', 'samesite' => 'Lax']);

$images = product_images($pid);
$variations = $p['has_variations'] ? product_variations($pid) : [];
$price = product_display_price($p);
$stock = product_stock_label($p);
$inStock = product_in_stock($p);
$reviews = db_all("SELECT * FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY id DESC LIMIT 20", [$pid]);
$tags = db_col('SELECT tag FROM product_tags WHERE product_id = ?', [$pid]);

// Related: manual first, then same category
$relatedIds = array_map('intval', db_col('SELECT related_id FROM related_products WHERE product_id = ?', [$pid]));
$related = $relatedIds ? products_query(['ids' => $relatedIds, 'sort' => 'ids', 'limit' => 10])['items'] : [];
if (count($related) < 10 && $p['primary_category_id']) {
    $more = products_query(['category_id' => (int) $p['primary_category_id'], 'exclude' => array_merge([$pid], $relatedIds), 'limit' => 10 - count($related), 'sort' => 'popular'])['items'];
    $related = array_merge($related, $more);
}
$recentProducts = array_slice($recent, 1, 10) ? products_query(['ids' => array_slice($recent, 1, 10), 'sort' => 'ids', 'limit' => 10])['items'] : [];

// Breadcrumbs from primary category
$crumbs = [['Home', url('')]];
if ($p['primary_category_id']) foreach (category_path((int) $p['primary_category_id']) as $c) $crumbs[] = [$c['name'], category_url($c)];
$seo = seo_get('product', $pid);
$crumbs[] = [$seo['breadcrumb_title'] ?: $p['name'], null];
seo_apply($seo, [
    'title' => $p['name'] . ' - Price in Pakistan',
    'description' => $p['short_description'] ?: str_limit(strip_tags((string) $p['description']), 155) ?: ('Buy ' . $p['name'] . ' online at Fahim Store.'),
    'canonical' => abs_url('product/' . $p['slug'] . '/'),
    'og_image' => $images ? upload_url($images[0]['image']) : '',
]);
page_set(['og_type' => 'product', 'breadcrumbs' => $crumbs]);
page_add_schema(schema_product($p, $images, $variations));
$imgAlt = $seo['image_alt'] ?: $p['name'];

require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="page-head pb-2"><?= breadcrumbs_html($crumbs) ?></div>
  <?= render_flashes() ?>
  <div class="pd-wrap">
    <div class="row g-4">
      <div class="col-md-6 col-lg-5">
        <div class="pd-main-img">
          <img id="pdMainImg" src="<?= e($images ? upload_url($images[0]['image']) : product_image_url($p)) ?>" alt="<?= e($images[0]['alt_text'] ?? $imgAlt) ?>" width="600" height="600" fetchpriority="high">
        </div>
        <?php if (count($images) > 1): ?>
        <div class="pd-thumbs" role="list">
          <?php foreach ($images as $i => $img): ?>
          <button type="button" class="<?= $i ? '' : 'active' ?>" data-src="<?= e(upload_url($img['image'])) ?>" aria-label="Image <?= $i + 1 ?>"><img src="<?= e(upload_url($img['thumb'] ?: $img['image'])) ?>" alt="<?= e($img['alt_text'] ?: $imgAlt) ?>" loading="lazy"></button>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <div class="col-md-6 col-lg-7">
        <?php if ($p['brand_name']): ?><a href="<?= url('brand/' . $p['brand_slug'] . '/') ?>" class="small fw-semibold text-uppercase"><?= e($p['brand_name']) ?></a><?php endif; ?>
        <h1 class="pd-title mt-1"><?= e($p['name']) ?></h1>
        <?php if ($p['name_urdu']): ?><div class="text-muted fs-5" dir="rtl" lang="ur"><?= e($p['name_urdu']) ?></div><?php endif; ?>
        <div class="d-flex flex-wrap gap-3 align-items-center my-2 small">
          <?php if ($p['rating_count']): ?><a href="#reviews" class="text-warning"><?= str_repeat('<i class="bi bi-star-fill"></i>', (int) round($p['rating_avg'])) ?> <span class="text-muted"><?= number_format((float) $p['rating_avg'], 1) ?> (<?= (int) $p['rating_count'] ?> reviews)</span></a><?php endif; ?>
          <span class="text-<?= $stock[1] ?> fw-semibold js-stock-label"><i class="bi bi-circle-fill" style="font-size:.5rem"></i> <?= e($stock[0]) ?></span>
        </div>

        <div class="pd-price my-3">
          <span class="js-price"><?= money($variations ? effective_price($variations[0]['price'], $variations[0]['sale_price']) : $price['price']) ?></span>
          <del class="js-old-price<?= (!$variations && $price['old']) || ($variations && discount_percent($variations[0]['price'], $variations[0]['sale_price'])) ? '' : ' d-none' ?>"><?= money($variations ? $variations[0]['price'] : $price['old']) ?></del>
          <?php if (!$variations && $price['discount']): ?><span class="badge bg-danger fs-6 align-middle ms-2">-<?= $price['discount'] ?>%</span><?php endif; ?>
        </div>
        <?php if ($p['short_description']): ?><p class="text-secondary"><?= e($p['short_description']) ?></p><?php endif; ?>

        <form class="pd-buy" onsubmit="return false">
          <?php if ($variations): ?>
          <div class="mb-3">
            <div class="fw-semibold mb-2">Choose option:</div>
            <div class="variation-options">
              <?php $firstAvail = null; foreach ($variations as $i => $v):
                $vAvail = !$p['track_stock'] || $v['stock'] > 0; if ($vAvail && $firstAvail === null) $firstAvail = $i;
                $vp = effective_price($v['price'], $v['sale_price']); ?>
              <input type="radio" class="btn-check" name="variation" id="var<?= (int) $v['id'] ?>" value="<?= (int) $v['id'] ?>" <?= $vAvail ? '' : 'disabled' ?>
                data-price="<?= e(money($vp)) ?>" data-old="<?= $vp < $v['price'] ? e(money($v['price'])) : '' ?>" data-stock="<?= $p['track_stock'] ? (int) $v['stock'] : 9999 ?>">
              <label class="btn" for="var<?= (int) $v['id'] ?>"><?= e($v['name']) ?><small><?= money($vp) ?><?= $vAvail ? '' : ' · Sold out' ?></small></label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($inStock): ?>
          <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
            <div class="qty-box">
              <button type="button" data-step="-1" aria-label="Decrease quantity">−</button>
              <input type="number" id="pdQty" value="<?= max(1, (int) $p['min_order_qty']) ?>" min="<?= max(1, (int) $p['min_order_qty']) ?>" max="<?= (int) ($p['max_order_qty'] ?: ($p['track_stock'] ? max(1, (int) $p['stock']) : 999)) ?>" aria-label="Quantity">
              <button type="button" data-step="1" aria-label="Increase quantity">+</button>
            </div>
            <button type="button" class="btn btn-brand btn-lg flex-grow-1 js-add-cart" data-id="<?= $pid ?>" data-qty-input="#pdQty" <?= $variations ? 'data-variation-input="input[name=variation]"' : '' ?>><i class="bi bi-cart-plus me-2"></i>Add to cart</button>
            <button type="button" class="btn btn-warning btn-lg fw-semibold js-add-cart" data-buy-now="1" data-id="<?= $pid ?>" data-qty-input="#pdQty" <?= $variations ? 'data-variation-input="input[name=variation]"' : '' ?>>Buy now</button>
            <button type="button" class="btn btn-outline-danger btn-lg js-wishlist<?= in_array($pid, wishlist_ids(), true) ? ' active' : '' ?>" data-id="<?= $pid ?>" aria-label="Add to wishlist"><i class="bi bi-heart<?= in_array($pid, wishlist_ids(), true) ? '-fill' : '' ?>"></i></button>
          </div>
          <?php if ($p['min_order_qty'] > 1 || $p['max_order_qty']): ?><p class="small text-muted">Order quantity: <?= $p['min_order_qty'] > 1 ? 'min ' . (int) $p['min_order_qty'] : '' ?> <?= $p['max_order_qty'] ? 'max ' . (int) $p['max_order_qty'] : '' ?></p><?php endif; ?>
          <?php else: ?>
          <div class="alert alert-secondary"><i class="bi bi-bell me-2"></i>This item is currently out of stock. Please call <?= e(setting('store_phone')) ?> for availability.</div>
          <?php endif; ?>
        </form>

        <div class="pd-perks my-3">
          <div><i class="bi bi-truck"></i>Home delivery</div>
          <div><i class="bi bi-shop"></i>Store pickup</div>
          <div><i class="bi bi-cash"></i>Cash on delivery</div>
          <div><i class="bi bi-patch-check"></i>100% original</div>
        </div>
        <div class="pd-meta">
          <div>SKU: <strong><?= e($p['sku']) ?></strong></div>
          <?php if ($p['isbn']): ?><div>ISBN: <strong><?= e($p['isbn']) ?></strong></div><?php endif; ?>
          <?php if ($p['primary_category_id'] && ($pc = category_get((int) $p['primary_category_id']))): ?><div>Category: <a href="<?= e(category_url($pc)) ?>"><?= e($pc['name']) ?></a></div><?php endif; ?>
          <?php if ($tags): ?><div>Tags: <?php foreach ($tags as $t): ?><a class="chip" href="<?= url('search/') ?>?q=<?= urlencode($t) ?>"><?= e($t) ?></a><?php endforeach; ?></div><?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <div class="pd-wrap mt-3">
    <ul class="nav nav-tabs pd-tabs" role="tablist">
      <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabDesc" type="button" role="tab">Description</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabSpec" type="button" role="tab">Specifications</button></li>
      <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#reviews" type="button" role="tab">Reviews (<?= count($reviews) ?>)</button></li>
    </ul>
    <div class="tab-content pt-3">
      <div class="tab-pane fade show active" id="tabDesc" role="tabpanel">
        <div class="product-description"><?= $p['description'] ? safe_html($p['description']) : '<p>' . e($p['name']) . '</p>' ?></div>
      </div>
      <div class="tab-pane fade" id="tabSpec" role="tabpanel">
        <table class="table spec-table">
          <tbody>
          <?php if ($p['brand_name']): ?><tr><th>Brand</th><td><?= e($p['brand_name']) ?></td></tr><?php endif; ?>
          <tr><th>Unit</th><td><?= $p['unit_value'] ? e(rtrim(rtrim((string) $p['unit_value'], '0'), '.')) . ' ' : '' ?><?= e(units_list()[$p['unit']] ?? $p['unit']) ?></td></tr>
          <?php if ($p['weight_grams']): ?><tr><th>Weight</th><td><?= (int) $p['weight_grams'] ?> g</td></tr><?php endif; ?>
          <?php if ($p['length_cm'] || $p['width_cm'] || $p['height_cm']): ?><tr><th>Dimensions</th><td><?= (float) $p['length_cm'] ?> × <?= (float) $p['width_cm'] ?> × <?= (float) $p['height_cm'] ?> cm</td></tr><?php endif; ?>
          <?php if ($p['isbn']): ?><tr><th>ISBN</th><td><?= e($p['isbn']) ?></td></tr><?php endif; ?>
          <tr><th>SKU</th><td><?= e($p['sku']) ?></td></tr>
          <?php if ($p['barcode']): ?><tr><th>Barcode</th><td><?= e($p['barcode']) ?></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <div class="tab-pane fade" id="reviews" role="tabpanel">
        <?php if (!$reviews): ?><p class="text-muted">No reviews yet. Be the first to review this product.</p><?php endif; ?>
        <?php foreach ($reviews as $r): ?>
        <div class="review-item">
          <div class="text-warning small"><?= str_repeat('<i class="bi bi-star-fill"></i>', (int) $r['rating']) ?><?= str_repeat('<i class="bi bi-star"></i>', 5 - (int) $r['rating']) ?></div>
          <strong><?= e($r['title'] ?: 'Review') ?></strong> <small class="text-muted">by <?= e($r['reviewer_name']) ?> · <?= fmt_date($r['created_at']) ?></small>
          <p class="mb-1"><?= nl2br(e($r['body'])) ?></p>
          <?php if ($r['admin_reply']): ?><div class="small bg-light rounded p-2"><strong>Fahim Store:</strong> <?= e($r['admin_reply']) ?></div><?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (customer_id()): ?>
        <form method="post" class="mt-4" style="max-width:560px">
          <?= csrf_field() ?><input type="hidden" name="action" value="review">
          <h3 class="h6 fw-bold">Write a review</h3>
          <div class="stars-input mb-2" role="radiogroup" aria-label="Rating">
            <?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" name="rating" id="rs<?= $i ?>" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>><label for="rs<?= $i ?>" aria-label="<?= $i ?> stars"><i class="bi bi-star-fill"></i></label><?php endfor; ?>
          </div>
          <input type="text" name="title" class="form-control mb-2" placeholder="Title" maxlength="160">
          <textarea name="body" class="form-control mb-2" rows="3" placeholder="Share your experience" maxlength="2000" required></textarea>
          <button class="btn btn-brand">Submit review</button>
        </form>
        <?php else: ?>
        <p class="mt-3"><a href="<?= url('login/') ?>?next=<?= urlencode(product_url($p)) ?>">Log in</a> to write a review.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <?php if ($related): ?>
  <section class="home-section"><div class="section-head"><h2>Related Products</h2></div><?= render_product_scroller($related) ?></section>
  <?php endif; ?>
  <?php if ($recentProducts): ?>
  <section class="home-section pt-0"><div class="section-head"><h2>Recently Viewed</h2></div><?= render_product_scroller($recentProducts) ?></section>
  <?php endif; ?>
</div>

<?php if ($inStock): ?>
<div class="sticky-buy">
  <div class="flex-grow-1 lh-sm"><small class="text-muted d-block text-truncate" style="max-width:45vw"><?= e($p['name']) ?></small><strong class="js-price"><?= money($variations ? effective_price($variations[0]['price'], $variations[0]['sale_price']) : $price['price']) ?></strong></div>
  <button type="button" class="btn btn-brand js-add-cart" data-id="<?= $pid ?>" data-qty-input="#pdQty" <?= $variations ? 'data-variation-input="input[name=variation]"' : '' ?>><i class="bi bi-cart-plus me-1"></i>Add to cart</button>
</div>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var radios = document.querySelectorAll('input[name=variation]');
  function apply(r) {
    document.querySelectorAll('.js-price').forEach(function (el) { el.textContent = r.dataset.price; });
    var old = document.querySelector('.js-old-price');
    if (old) { old.textContent = r.dataset.old; old.classList.toggle('d-none', !r.dataset.old); }
    var q = document.getElementById('pdQty');
    if (q) { q.max = Math.max(1, parseInt(r.dataset.stock, 10)); if (+q.value > +q.max) q.value = q.max; }
  }
  radios.forEach(function (r) { r.addEventListener('change', function () { apply(r); }); });
  var first = document.querySelector('input[name=variation]:not(:disabled)');
  if (first) { first.checked = true; apply(first); }
});
</script>
<?php require FS_ROOT . '/partials/footer.php';
