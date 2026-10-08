<?php
require __DIR__ . '/includes/bootstrap.php';

$p = row('SELECT p.*, c.name category_name, c.slug category_slug, s.name sub_name
          FROM products p LEFT JOIN categories c ON c.id = p.category_id LEFT JOIN categories s ON s.id = p.subcategory_id
          WHERE p.slug = ? AND p.status = 1', [get('slug')]);
if (!$p) not_found();

if (empty($_SESSION['viewed'][$p['id']])) {
    q('UPDATE products SET views = views + 1 WHERE id = ?', [$p['id']]);
    $_SESSION['viewed'][$p['id']] = 1;
}

$images = product_images((int)$p['id']);
if (!$images) $images = [['image' => '']];
$sizes = sizes_of($p);
$colors = colors_of($p);
$rating = rating_for((int)$p['id']);
$reviews = rows('SELECT * FROM reviews WHERE product_id = ? AND status = 1 ORDER BY created_at DESC LIMIT 20', [$p['id']]);
$related = product_list('p.id <> ? AND (p.subcategory_id = ? OR p.category_id = ?)', [$p['id'], (int)$p['subcategory_id'], (int)$p['category_id']], 'p.subcategory_id = ' . (int)$p['subcategory_id'] . ' DESC, RAND()', 8);
$wish = wishlist_ids();
$cat = $p['category_id'] ? category_by_id((int)$p['category_id']) : null;
$subc = $p['subcategory_id'] ? category_by_id((int)$p['subcategory_id']) : null;
$canonical = $p['canonical_url'] ?: abs_url('product/' . $p['slug']);
$firstImg = $p['og_image'] ?: $images[0]['image'];

$schema = [
    '@context' => 'https://schema.org/', '@type' => 'Product',
    'name' => $p['name'],
    'image' => array_map(fn($i) => site_url() . substr(img($i['image']), strlen(base_path())), $images),
    'description' => excerpt($p['short_description'] ?: $p['description'], 300),
    'sku' => $p['sku'], 'brand' => ['@type' => 'Brand', 'name' => setting('site_name')],
    'offers' => [
        '@type' => 'Offer', 'url' => abs_url('product/' . $p['slug']), 'priceCurrency' => 'PKR', 'price' => price_now($p),
        'availability' => (int)$p['stock'] > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'itemCondition' => 'https://schema.org/NewCondition',
    ],
];
if ($rating['count']) {
    $schema['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => $rating['avg'], 'reviewCount' => $rating['count']];
}
$crumbs = [['Home', abs_url('')]];
if ($cat) $crumbs[] = [$cat['name'], site_url() . substr(category_url($cat), strlen(base_path()))];
$crumbs[] = [$p['name'], abs_url('product/' . $p['slug'])];

$seo = [
    'title'       => $p['meta_title'] ?: $p['name'] . ' | ' . ($p['category_name'] ? $p['category_name'] . ' | ' : '') . setting('site_name'),
    'description' => $p['meta_description'] ?: excerpt($p['short_description'] ?: $p['description'], 158),
    'keywords'    => $p['meta_keywords'] ?: trim($p['focus_keyword'] . ', ' . $p['category_name'] . ', ' . $p['fabric'], ', '),
    'canonical'   => $canonical,
    'image'       => $firstImg,
    'type'        => 'product',
    'noindex'     => (bool)$p['noindex'],
    'schema'      => [$schema, ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => array_map(fn($c, $i) => ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $c[0], 'item' => $c[1]], $crumbs, array_keys($crumbs))]],
];
require ROOT . '/includes/header.php';
?>
<div class="container">
  <nav class="breadcrumbs dark">
    <a href="<?= url('') ?>">Home</a><span>/</span>
    <?php if ($cat): ?><a href="<?= category_url($cat) ?>"><?= e($cat['name']) ?></a><span>/</span><?php endif; ?>
    <?php if ($subc): ?><a href="<?= category_url($subc) ?>"><?= e($subc['name']) ?></a><span>/</span><?php endif; ?>
    <span><?= e($p['name']) ?></span>
  </nav>
</div>

<section class="section section--tight">
  <div class="container product-layout">
    <div class="gallery" data-reveal>
      <div class="swiper gallery-thumbs">
        <div class="swiper-wrapper">
          <?php foreach ($images as $im): ?><div class="swiper-slide"><img src="<?= e(img($im['image'])) ?>" alt="" loading="lazy"></div><?php endforeach; ?>
        </div>
      </div>
      <div class="swiper gallery-main">
        <div class="swiper-wrapper">
          <?php foreach ($images as $i => $im): ?>
            <div class="swiper-slide"><div class="zoom" data-zoom><img src="<?= e(img($im['image'])) ?>" alt="<?= e($p['name']) ?><?= $i ? ' view ' . ($i + 1) : '' ?>"></div></div>
          <?php endforeach; ?>
        </div>
        <div class="gallery-pagination"></div>
        <?php if (on_sale($p)): ?><span class="tag tag--gold gallery-tag">-<?= discount_pct($p) ?>%</span><?php endif; ?>
      </div>
    </div>

    <div class="product-info" data-reveal data-delay="1">
      <?php if ($p['category_name']): ?><a class="eyebrow gold" href="<?= category_url($cat) ?>"><?= e($p['category_name']) ?><?= $p['sub_name'] ? ' · ' . e($p['sub_name']) : '' ?></a><?php endif; ?>
      <h1 class="product-title"><?= e($p['name']) ?></h1>
      <?php if ($rating['count']): ?><a href="#reviews" class="rating-line"><?= stars($rating['avg']) ?> <span><?= $rating['avg'] ?> (<?= $rating['count'] ?> reviews)</span></a><?php endif; ?>
      <div class="price price--lg">
        <span class="price__now"><?= money(price_now($p)) ?></span>
        <?php if (on_sale($p)): ?><del><?= money($p['price']) ?></del><span class="save">Save <?= money((float)$p['price'] - price_now($p)) ?></span><?php endif; ?>
      </div>
      <?php if ($p['short_description']): ?><p class="lead"><?= e($p['short_description']) ?></p><?php endif; ?>

      <dl class="spec-list">
        <?php if ($p['sku']): ?><div><dt>SKU</dt><dd><?= e($p['sku']) ?></dd></div><?php endif; ?>
        <?php if ($p['fabric']): ?><div><dt>Fabric</dt><dd><?= e($p['fabric']) ?></dd></div><?php endif; ?>
        <?php if ($p['pieces']): ?><div><dt>Pieces</dt><dd><?= e($p['pieces']) ?></dd></div><?php endif; ?>
        <div><dt>Availability</dt><dd><?php if ((int)$p['stock'] <= 0): ?><span class="text-danger">Out of stock</span><?php elseif ((int)$p['stock'] <= (int)setting('low_stock', 3)): ?><span class="text-gold">Only <?= (int)$p['stock'] ?> left</span><?php else: ?>In stock<?php endif; ?></dd></div>
      </dl>

      <form class="add-form" data-add-form action="<?= url('cart-action') ?>" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <?php if ($colors): ?>
          <div class="opt-group">
            <span class="opt-label">Colour: <b data-opt-value><?= e($colors[0]) ?></b></span>
            <div class="opt-pills">
              <?php foreach ($colors as $i => $col): ?><label><input type="radio" name="color" value="<?= e($col) ?>" <?= $i ? '' : 'checked' ?>><span><?= e($col) ?></span></label><?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        <?php if ($sizes): ?>
          <div class="opt-group">
            <span class="opt-label">Size: <b data-opt-value><?= count($sizes) === 1 ? e($sizes[0]) : 'Select' ?></b> <button type="button" class="link-underline sm" data-open="sizeGuide">Size Guide</button></span>
            <div class="opt-pills">
              <?php foreach ($sizes as $s): ?><label><input type="radio" name="size" value="<?= e($s) ?>" <?= count($sizes) === 1 ? 'checked' : '' ?> required><span><?= e($s) ?></span></label><?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
        <div class="buy-row">
          <div class="qty"><button type="button" data-qty="-1"><?= icon('minus', 14) ?></button><input type="number" name="qty" value="1" min="1" max="<?= max(1, (int)$p['stock']) ?>"><button type="button" data-qty="1"><?= icon('plus', 14) ?></button></div>
          <button class="btn btn-dark btn-grow" type="submit" <?= (int)$p['stock'] <= 0 ? 'disabled' : '' ?>><?= (int)$p['stock'] <= 0 ? 'Sold Out' : 'Add to Bag' ?></button>
          <button type="button" class="icon-btn wish-lg<?= in_array((int)$p['id'], $wish, true) ? ' active' : '' ?>" data-wish="<?= (int)$p['id'] ?>" aria-label="Wishlist"><?= icon('heart', 22) ?></button>
        </div>
        <?php if ((int)$p['stock'] > 0): ?><button class="btn btn-gold btn-block" type="submit" name="buy_now" value="1">Buy It Now</button><?php endif; ?>
      </form>

      <ul class="assurance">
        <li><?= icon('truck', 18) ?> <?= e(setting('shipping_note', 'Nationwide delivery')) ?></li>
        <li><?= icon('cash', 18) ?> Cash on Delivery, EasyPaisa, JazzCash & Card</li>
        <li><?= icon('refresh', 18) ?> Easy 7-day exchange</li>
      </ul>
      <?php if (setting('whatsapp')): ?>
        <a class="btn btn-outline btn-block" target="_blank" rel="noopener" href="https://wa.me/<?= e(preg_replace('~\D~', '', setting('whatsapp'))) ?>?text=<?= rawurlencode('Hi, I am interested in ' . $p['name'] . ' (' . abs_url('product/' . $p['slug']) . ')') ?>"><?= icon('whatsapp', 18) ?> Order on WhatsApp</a>
      <?php endif; ?>

      <div class="accordion">
        <details open><summary>Description</summary><div class="rich"><?= $p['description'] ?: '<p>' . e($p['short_description']) . '</p>' ?></div></details>
        <details><summary>Delivery & Returns</summary><div class="rich"><p><?= e(setting('shipping_note')) ?> Free delivery on orders above <?= money(setting('free_shipping_min', 0)) ?>.</p><p>Exchanges are accepted within 7 days in original condition. <a href="<?= url('page/return-exchange') ?>">Read policy</a>.</p></div></details>
        <details><summary>Care Instructions</summary><div class="rich"><p>Dry clean recommended for embellished pieces. Iron on reverse at low heat. Store in a breathable garment bag.</p></div></details>
      </div>
    </div>
  </div>
</section>

<section class="section section--cream" id="reviews">
  <div class="container reviews-wrap">
    <div class="reviews-summary" data-reveal>
      <h2 class="section-title sm">Customer Reviews</h2>
      <div class="big-rating"><?= $rating['avg'] ?: '—' ?></div>
      <?= stars($rating['avg']) ?>
      <p class="muted">Based on <?= $rating['count'] ?> review<?= $rating['count'] === 1 ? '' : 's' ?></p>
      <form class="review-form" method="post" action="<?= url('review') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
        <h4>Write a review</h4>
        <div class="star-input">
          <?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" id="r<?= $i ?>" name="rating" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>><label for="r<?= $i ?>">★</label><?php endfor; ?>
        </div>
        <input type="text" name="name" placeholder="Your name" required maxlength="120" value="<?= e(customer()['name'] ?? '') ?>">
        <textarea name="comment" rows="3" placeholder="Share your experience" required maxlength="1500"></textarea>
        <button class="btn btn-dark btn-sm" type="submit">Submit Review</button>
      </form>
    </div>
    <div class="reviews-list">
      <?php foreach ($reviews as $r): ?>
        <div class="review" data-reveal>
          <div class="review__head"><strong><?= e($r['name']) ?></strong><?= stars((float)$r['rating']) ?></div>
          <p><?= nl2br(e($r['comment'])) ?></p>
          <time class="muted"><?= date('d M Y', strtotime($r['created_at'])) ?></time>
        </div>
      <?php endforeach; ?>
      <?php if (!$reviews): ?><p class="muted">No reviews yet — be the first to share your thoughts.</p><?php endif; ?>
    </div>
  </div>
</section>

<?php if ($related): ?>
<section class="section">
  <div class="container">
    <div class="section-head" data-reveal><span class="ornament">✦</span><h2 class="section-title">You May Also Like</h2></div>
    <div class="carousel" data-reveal>
      <div class="swiper product-swiper"><div class="swiper-wrapper">
        <?php foreach ($related as $p): ?><div class="swiper-slide"><?php require ROOT . '/includes/product-card.php'; ?></div><?php endforeach; ?>
      </div></div>
      <button class="car-nav car-prev" aria-label="Previous"><?= icon('left', 20) ?></button>
      <button class="car-nav car-next" aria-label="Next"><?= icon('right', 20) ?></button>
    </div>
  </div>
</section>
<?php endif; ?>

<div class="modal" id="sizeGuide" aria-hidden="true">
  <div class="modal__box">
    <button class="icon-btn modal__close" data-close aria-label="Close"><?= icon('close', 22) ?></button>
    <h3 class="section-title sm">Size Guide (inches)</h3>
    <table class="table">
      <thead><tr><th>Size</th><th>Bust</th><th>Waist</th><th>Hip</th><th>Shirt Length</th></tr></thead>
      <tbody>
        <tr><td>XS</td><td>34</td><td>30</td><td>38</td><td>40</td></tr>
        <tr><td>S</td><td>36</td><td>32</td><td>40</td><td>41</td></tr>
        <tr><td>M</td><td>38</td><td>34</td><td>42</td><td>42</td></tr>
        <tr><td>L</td><td>41</td><td>37</td><td>45</td><td>43</td></tr>
        <tr><td>XL</td><td>44</td><td>40</td><td>48</td><td>44</td></tr>
      </tbody>
    </table>
  </div>
</div>
<?php require ROOT . '/includes/footer.php';
