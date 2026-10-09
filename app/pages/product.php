<?php
$p = product_by_slug($GLOBALS['route']['slug']);
if (!$p) {
    // Admins may preview drafts.
    if (current_admin() && can('products.view')) {
        $p = product_by_slug($GLOBALS['route']['slug'], false);
    }
    if (!$p) {
        not_found();
    }
    meta_set(['noindex' => true]);
}
$images = product_images((int) $p['id']);
$variants = product_variants((int) $p['id']);
$rating = product_rating_summary((int) $p['id']);
$reviews = product_reviews((int) $p['id']);
$related = related_products($p, 8);
$crossSells = product_relations((int) $p['id'], 'cross_sell', 4);
$upsells = product_relations((int) $p['id'], 'upsell', 4);
$recent = setting_bool('recently_viewed_enabled', true) ? recently_viewed((int) $p['id'], 8) : [];
remember_viewed((int) $p['id']);
if (empty($_SESSION['viewed_' . $p['id']])) {
    $_SESSION['viewed_' . $p['id']] = 1;
    db_exec('UPDATE products SET view_count = view_count + 1 WHERE id = ?', [(int) $p['id']]);
}

$crumbs = [['name' => 'Home', 'url' => path_url('/')], ['name' => 'Shop', 'url' => path_url('shop')]];
$cat = category_by_id((int) $p['category_id']);
if ($cat) {
    $crumbs[] = ['name' => $cat['name'], 'url' => category_url($cat)];
}
if ($p['subcategory_id'] && ($sub = category_by_id((int) $p['subcategory_id']))) {
    $crumbs[] = ['name' => $sub['name'], 'url' => category_url($sub)];
}
$crumbs[] = ['name' => $p['name'], 'url' => product_url($p)];

$mainImage = $p['og_image'] ?: ($images[0]['file_path'] ?? null);
meta_set([
    'title' => $p['seo_title'] ?: $p['name'] . ($p['material'] ? ' — ' . $p['material'] : ''),
    'description' => $p['meta_description'] ?: excerpt($p['short_description'] ?: $p['description'], 158),
    'canonical' => $p['canonical_url'] ?: 'product/' . $p['slug'],
    'og_title' => $p['og_title'] ?: null,
    'og_description' => $p['og_description'] ?: null,
    'image' => $mainImage,
    'type' => 'product',
    'breadcrumbs' => $crumbs,
    'jsonld' => [product_jsonld($p, $images, $rating, $variants)],
]);
$customer = current_customer();
$specs = array_filter([
    'SKU' => $p['sku'],
    'Type' => $p['wallet_type'],
    'Material' => $p['material'],
    'Finish' => $p['finish'],
    'Colours' => implode(', ', array_unique(array_filter(array_column($variants, 'color_name')))),
    'Dimensions' => ($p['length_mm'] && $p['width_mm']) ? rtrim(rtrim($p['length_mm'], '0'), '.') . ' × ' . rtrim(rtrim($p['width_mm'], '0'), '.') . ($p['height_mm'] ? ' × ' . rtrim(rtrim($p['height_mm'], '0'), '.') : '') . ' mm' : null,
    'Weight' => $p['weight_grams'] ? (int) $p['weight_grams'] . ' g' : null,
]);
$GLOBALS['body_class'] = 'page-product';
partial('header');
?>
<div class="container container--wide pdp">
  <?php partial('breadcrumbs'); ?>
  <div class="pdp__top">
    <div class="pdp__gallery"><?php partial('product-gallery', ['images' => $images, 'p' => $p]); ?></div>
    <div class="pdp__info"><div class="pdp__sticky"><?php partial('product-buybox', ['p' => $p, 'variants' => $variants, 'images' => $images, 'rating' => $rating]); ?></div></div>
  </div>

  <div class="pdp__details">
    <div class="accordion accordion-lux" id="pdpAcc">
      <?php
      $panels = [];
      if ($p['description']) { $panels['desc'] = ['Description', '<div class="prose">' . rich_text($p['description']) . '</div>']; }
      if ($specs) {
          $html = '<dl class="spec-list">';
          foreach ($specs as $k => $v) { $html .= '<div><dt>' . e($k) . '</dt><dd>' . e($v) . '</dd></div>'; }
          $panels['specs'] = ['Specifications & dimensions', $html . '</dl>'];
      }
      if ($p['care_instructions'] || $p['material']) {
          $panels['care'] = ['Material & care', '<div class="prose">' . ($p['material'] ? '<p><strong>Material:</strong> ' . e($p['material']) . ($p['finish'] ? ' · ' . e($p['finish']) : '') . '</p>' : '') . rich_text($p['care_instructions']) . '</div>'];
      }
      if (setting('pdp_shipping_returns')) { $panels['ship'] = ['Shipping & returns', '<div class="prose">' . rich_text(setting('pdp_shipping_returns')) . '</div>']; }
      $first = true;
      foreach ($panels as $id => [$title, $body]): ?>
        <div class="accordion-item">
          <h2 class="accordion-header"><button class="accordion-button<?= $first ? '' : ' collapsed' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#acc-<?= $id ?>" aria-expanded="<?= $first ? 'true' : 'false' ?>"><?= e($title) ?></button></h2>
          <div id="acc-<?= $id ?>" class="accordion-collapse collapse<?= $first ? ' show' : '' ?>"><div class="accordion-body"><?= $body ?></div></div>
        </div>
      <?php $first = false; endforeach; ?>
    </div>
  </div>

  <?php if ($crossSells): ?>
    <section class="pdp__rail">
      <div class="section-head"><div><p class="eyebrow">Pairs well with</p><h2 class="section-title">Complete the set</h2></div></div>
      <div class="product-grid product-grid--4"><?php foreach ($crossSells as $cp) { partial('product-card', ['p' => $cp]); } ?></div>
    </section>
  <?php endif; ?>

  <section class="reviews" id="reviews">
    <div class="reviews__summary">
      <p class="eyebrow">Reviews</p>
      <h2 class="section-title">Customer reviews</h2>
      <?php if ($rating['count']): ?>
        <div class="reviews__score"><span><?= e(number_format($rating['average'], 1)) ?></span><?php partial('stars', ['rating' => $rating['average']]); ?><small><?= (int) $rating['count'] ?> verified & moderated review<?= $rating['count'] === 1 ? '' : 's' ?></small></div>
        <ul class="reviews__dist">
          <?php for ($i = 5; $i >= 1; $i--): $pct = $rating['count'] ? round($rating['distribution'][$i] / $rating['count'] * 100) : 0; ?>
            <li><span><?= $i ?> <i class="bi bi-star-fill"></i></span><span class="bar"><span style="width: <?= (int) $pct ?>%"></span></span><span><?= (int) $rating['distribution'][$i] ?></span></li>
          <?php endfor; ?>
        </ul>
      <?php else: ?>
        <p class="text-muted">No reviews yet. Owners of this piece are welcome to share their experience.</p>
      <?php endif; ?>
      <?php if (setting_bool('reviews_enabled', true)): ?>
        <button class="btn-outline-lux" type="button" data-bs-toggle="collapse" data-bs-target="#reviewForm">Write a review</button>
      <?php endif; ?>
    </div>
    <div class="reviews__list">
      <?php if (setting_bool('reviews_enabled', true)): ?>
      <form class="collapse review-form" id="reviewForm" data-review-form novalidate>
        <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
        <div class="row g-3">
          <div class="col-12">
            <span class="form-label d-block">Your rating</span>
            <div class="rating-input" role="radiogroup" aria-label="Rating">
              <?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" name="rating" id="r<?= $i ?>" value="<?= $i ?>" required><label for="r<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>"><i class="bi bi-star-fill"></i></label><?php endfor; ?>
            </div>
          </div>
          <div class="col-md-6"><label class="form-label" for="rv-name">Name</label><input class="form-control" id="rv-name" name="name" maxlength="120" required value="<?= e($customer ? trim($customer['first_name'] . ' ' . mb_substr($customer['last_name'], 0, 1)) : '') ?>"></div>
          <div class="col-md-6"><label class="form-label" for="rv-email">Email <small class="text-muted">(never published)</small></label><input class="form-control" type="email" id="rv-email" name="email" maxlength="190" required value="<?= e($customer['email'] ?? '') ?>"></div>
          <div class="col-12"><label class="form-label" for="rv-title">Title</label><input class="form-control" id="rv-title" name="title" maxlength="150"></div>
          <div class="col-12"><label class="form-label" for="rv-body">Review</label><textarea class="form-control" id="rv-body" name="body" rows="4" minlength="10" maxlength="3000" required></textarea></div>
          <div class="hp-field" aria-hidden="true"><input type="text" name="website" tabindex="-1" autocomplete="off"></div>
          <div class="col-12"><button class="btn-lux" type="submit">Submit review</button> <small class="text-muted ms-2">Reviews are published after moderation.</small></div>
        </div>
      </form>
      <?php endif; ?>
      <?php foreach ($reviews as $r): ?>
        <article class="review">
          <header><?php partial('stars', ['rating' => (float) $r['rating']]); ?><?php if ($r['title']): ?><h3><?= e($r['title']) ?></h3><?php endif; ?></header>
          <p><?= nl2br(e($r['body'])) ?></p>
          <footer><?= e($r['author_name']) ?><?= $r['is_verified_purchase'] ? ' · <span class="verified"><i class="bi bi-patch-check"></i> Verified purchase</span>' : '' ?> · <?= e(format_date($r['created_at'])) ?></footer>
          <?php if ($r['admin_reply']): ?><div class="review__reply"><strong><?= e(setting('site_name', 'Beglet')) ?>:</strong> <?= nl2br(e($r['admin_reply'])) ?></div><?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>

  <?php foreach ([['You may also like', 'Related pieces', $related], ['Step up', 'Consider also', $upsells], ['Recently viewed', 'Your recent finds', $recent]] as [$eyebrow, $title, $items]): if (!$items) continue; ?>
    <section class="pdp__rail">
      <div class="section-head"><div><p class="eyebrow"><?= e($eyebrow) ?></p><h2 class="section-title"><?= e($title) ?></h2></div></div>
      <?php partial('product-carousel', ['products' => $items, 'label' => $title]); ?>
    </section>
  <?php endforeach; ?>
</div>

<?php if (theme('theme_mobile_sticky_cart') === '1'): $pr = unit_pricing($p); ?>
<div class="sticky-atc" data-sticky-atc>
  <img src="<?= e(media_url($images[0]['file_path'] ?? null)) ?>" alt="" width="48" height="60">
  <div><strong><?= e($p['name']) ?></strong><span><?= e(money($pr['price'])) ?></span></div>
  <button type="button" class="btn-lux" data-sticky-atc-btn <?= is_purchasable($p['availability']) ? '' : 'disabled' ?>><?= is_purchasable($p['availability']) ? 'Add to bag' : 'Sold out' ?></button>
</div>
<?php endif; ?>

<div class="lightbox" data-lightbox hidden role="dialog" aria-label="Image gallery" aria-modal="true">
  <button type="button" class="lightbox__close" data-lightbox-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
  <button type="button" class="lightbox__nav lightbox__nav--prev" data-lightbox-prev aria-label="Previous"><i class="bi bi-chevron-left"></i></button>
  <img src="" alt="" data-lightbox-img>
  <button type="button" class="lightbox__nav lightbox__nav--next" data-lightbox-next aria-label="Next"><i class="bi bi-chevron-right"></i></button>
  <p class="lightbox__count" data-lightbox-count></p>
</div>
<?php partial('footer');
