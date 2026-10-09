<?php
/** Product detail page. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

$product = product_by_slug($slug, !admin_id());
if (!$product) {
    if ($r = redirect_lookup('/product/' . $slug)) redirect(url($r['to_path']), 301);
    not_found();
}
$pid = (int)$product['id'];
$images = product_images($pid);
$matrix = product_variant_matrix($product);
$facets = product_facets($pid);
$related = product_related($product, 8);
$crossSell = product_relations($pid, 'cross_sell', 4);
$upsell = product_relations($pid, 'upsell', 4);
$recentIds = array_values(array_diff(recently_viewed_ids(), [$pid]));
$recent = [];
if ($recentIds) [$recent] = products_query(['ids' => array_slice($recentIds, 0, 8), 'sort' => 'manual', 'limit' => 8]);
recently_viewed_push($pid);
$reviews = db_all("SELECT * FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC LIMIT 30", [$pid]);
$sizeGuide = $product['size_guide_id'] ? db_val('SELECT content FROM size_guides WHERE id = ?', [(int)$product['size_guide_id']]) : null;
if (!$sizeGuide) $sizeGuide = db_val("SELECT content FROM pages WHERE slug = 'size-guide' AND status = 'published'");
$estimate = product_delivery_estimate($product);
$cat = $product['category_id'] ? (categories_all(false)[(int)$product['category_id']] ?? null) : null;
$sub = $product['subcategory_id'] ? (categories_all(false)[(int)$product['subcategory_id']] ?? null) : null;
$canReview = customer_id() && !db_val('SELECT id FROM reviews WHERE product_id = ? AND customer_id = ?', [$pid, customer_id()]);

$crumbs = [['Shop', url('shop')]];
if ($cat) $crumbs[] = [$cat['name'], category_url($cat)];
if ($sub) $crumbs[] = [$sub['name'], category_url($sub)];
$crumbs[] = [$product['name'], null];

seo_set([
    'title' => $product['seo_title'] ?: $product['name'],
    'description' => $product['meta_description'] ?: $product['short_description'],
    'canonical' => $product['canonical_url'] ?: abs_url('product/' . $product['slug']),
    'image' => $product['og_image'] ?: ($images[0]['path'] ?? null),
    'og_title' => $product['og_title'], 'og_description' => $product['og_description'],
    'type' => 'product', 'noindex' => (bool)$product['noindex'] || $product['status'] !== 'published',
]);
seo_breadcrumbs($crumbs);
seo_schema(product_schema($product, $images, $matrix));

$details = array_filter([
    'Fabric' => $product['fabric'], 'Embroidery' => $product['embroidery_type'], 'Crochet details' => $product['crochet_details'],
    'Sleeves' => $product['sleeve_design'], 'Neckline' => $product['neckline_design'], 'Length' => $product['abaya_length'],
    'Fit & silhouette' => $product['fit_silhouette'], 'Lining' => $product['lining_info'], 'Transparency' => $product['transparency_info'],
    'Dispatch' => $product['dispatch_time'], 'Weight' => $product['weight_grams'] ? $product['weight_grams'] . ' g' : null, 'SKU' => $product['sku'],
]);
foreach ($facets as $k => $vals) $details[$k] = implode(', ', $vals);

$bodyClass = 'page-product';
require ROOT_PATH . '/templates/header.php';
?>
<?php if ($product['status'] !== 'published'): ?><div class="container-eb"><div class="alert alert-warning mt-3">Admin preview — this product is <?= e($product['status']) ?> and not visible to customers.</div></div><?php endif; ?>
<section class="product-page">
  <div class="container-eb">
    <?php include ROOT_PATH . '/templates/breadcrumbs.php'; ?>
    <div class="row g-4 g-xl-5">
      <div class="col-lg-7">
        <div class="pdp-gallery">
          <div class="swiper pdp-main">
            <div class="swiper-wrapper">
              <?php foreach ($images ?: [['path' => null, 'alt_text' => $product['name'], 'caption' => null]] as $i => $img): ?>
                <div class="swiper-slide">
                  <figure class="zoom-frame" data-zoom data-lightbox-index="<?= $i ?>">
                    <img src="<?= e(img_url($img['path'])) ?>" alt="<?= e($img['alt_text'] ?: $product['name']) ?>" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> width="900" height="1200">
                    <?php if (!empty($img['caption'])): ?><figcaption><?= e($img['caption']) ?></figcaption><?php endif; ?>
                  </figure>
                </div>
              <?php endforeach; ?>
              <?php if ($product['video_url'] && preg_match('#\.mp4(\?|$)#i', $product['video_url'])): ?>
                <div class="swiper-slide"><video src="<?= e(url($product['video_url'])) ?>" controls muted playsinline class="w-100 pdp-video"></video></div>
              <?php endif; ?>
            </div>
            <div class="swiper-pagination d-lg-none"></div>
            <button type="button" class="pdp-expand" data-lightbox-open aria-label="Open full-screen gallery"><i class="bi bi-arrows-fullscreen"></i></button>
          </div>
          <?php if (count($images) > 1): ?>
            <div class="swiper pdp-thumbs d-none d-lg-block">
              <div class="swiper-wrapper">
                <?php foreach ($images as $img): ?><div class="swiper-slide"><img src="<?= e(img_url($img['path'])) ?>" alt="" loading="lazy"></div><?php endforeach; ?>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
      <div class="col-lg-5">
        <div class="pdp-info">
          <div class="pc-badges static mb-2">
            <?php if ($product['is_handcrafted']): ?><span class="pc-badge badge-craft"><i class="bi bi-flower1"></i> Handcrafted</span><?php endif; ?>
            <?php foreach (product_badges($product + ['on_sale' => product_on_sale($product)]) as [$l, $t]): ?><span class="pc-badge badge-<?= e($t) ?>"><?= e($l) ?></span><?php endforeach; ?>
          </div>
          <h1 class="pdp-title"><?= e($product['name']) ?></h1>
          <?php if ((int)$product['rating_count'] > 0): ?>
            <a href="#reviews" class="pdp-rating"><span class="stars"><?= str_repeat('<i class="bi bi-star-fill"></i>', (int)round($product['rating_avg'])) . str_repeat('<i class="bi bi-star"></i>', 5 - (int)round($product['rating_avg'])) ?></span> <?= number_format((float)$product['rating_avg'], 1) ?> (<?= (int)$product['rating_count'] ?>)</a>
          <?php endif; ?>
          <?php if ($product['short_description']): ?><p class="pdp-short"><?= e($product['short_description']) ?></p><?php endif; ?>

          <?php include ROOT_PATH . '/templates/product-buybox.php'; ?>

          <ul class="pdp-assure">
            <?php if ($estimate): ?><li><i class="bi bi-truck"></i> Estimated delivery: <strong><?= e($estimate) ?></strong></li><?php endif; ?>
            <?php if ($product['model_info']): ?><li><i class="bi bi-person-standing-dress"></i> <?= e($product['model_info']) ?></li><?php endif; ?>
            <?php if ($product['dispatch_time']): ?><li><i class="bi bi-box-seam"></i> <?= e($product['dispatch_time']) ?></li><?php endif; ?>
          </ul>

          <div class="accordion pdp-accordion" id="pdpAcc">
            <?php
            $panels = [];
            if ($product['description']) $panels['Description'] = '<div class="rich">' . sanitize_html($product['description']) . '</div>';
            if ($details) {
                $h = '<dl class="spec-list">';
                foreach ($details as $k => $v) $h .= '<dt>' . e($k) . '</dt><dd>' . e($v) . '</dd>';
                $panels['Details & craftsmanship'] = $h . '</dl>' . ($product['material_details'] ? '<p class="mt-3">' . nl2br(e($product['material_details'])) . '</p>' : '');
            }
            if ($product['care_instructions']) $panels['Care'] = nl2p($product['care_instructions']);
            $panels['Delivery & returns'] = nl2p(setting('delivery_info')) . nl2p(setting('returns_info')) . '<p><a href="' . e(url('shipping-policy')) . '">Shipping policy</a> · <a href="' . e(url('returns-exchanges')) . '">Returns & exchanges</a></p>';
            $n = 0;
            foreach ($panels as $title => $html): $n++; ?>
              <div class="accordion-item">
                <h2 class="accordion-header"><button class="accordion-button<?= $n > 1 ? ' collapsed' : '' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#acc<?= $n ?>" aria-expanded="<?= $n === 1 ? 'true' : 'false' ?>"><?= e($title) ?></button></h2>
                <div id="acc<?= $n ?>" class="accordion-collapse collapse<?= $n === 1 ? ' show' : '' ?>" data-bs-parent="#pdpAcc"><div class="accordion-body"><?= $html ?></div></div>
              </div>
            <?php endforeach; ?>
          </div>

          <div class="pdp-share">
            <span>Share</span>
            <a href="https://wa.me/?text=<?= urlencode($product['name'] . ' ' . abs_url('product/' . $product['slug'])) ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><i class="bi bi-whatsapp"></i></a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode(abs_url('product/' . $product['slug'])) ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><i class="bi bi-facebook"></i></a>
            <a href="https://pinterest.com/pin/create/button/?url=<?= urlencode(abs_url('product/' . $product['slug'])) ?>&media=<?= urlencode(abs_url($images[0]['path'] ?? '')) ?>" target="_blank" rel="noopener" aria-label="Pin on Pinterest"><i class="bi bi-pinterest"></i></a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if ($crossSell): ?>
<section class="pdp-strip"><div class="container-eb">
  <h2 class="section-title small">Complete the Look</h2>
  <div class="product-grid grid-4"><?php foreach ($crossSell as $p): ?><div data-reveal><?php include ROOT_PATH . '/templates/product-card.php'; ?></div><?php endforeach; ?></div>
</div></section>
<?php endif; ?>

<section class="pdp-reviews" id="reviews"><div class="container-eb">
  <div class="row g-5">
    <div class="col-lg-4">
      <h2 class="section-title small">Reviews</h2>
      <?php if ((int)$product['rating_count'] > 0): ?>
        <p class="rating-big"><?= number_format((float)$product['rating_avg'], 1) ?><small>/5</small></p>
        <p class="text-muted">Based on <?= (int)$product['rating_count'] ?> review<?= (int)$product['rating_count'] === 1 ? '' : 's' ?></p>
      <?php else: ?>
        <p class="text-muted">No reviews yet.</p>
      <?php endif; ?>
      <?php if ($canReview): ?>
        <form class="review-form" data-review-form>
          <input type="hidden" name="product_id" value="<?= $pid ?>">
          <div class="star-input" role="radiogroup" aria-label="Your rating">
            <?php for ($i = 5; $i >= 1; $i--): ?><input type="radio" id="rs<?= $i ?>" name="rating" value="<?= $i ?>" required><label for="rs<?= $i ?>" title="<?= $i ?> stars"><i class="bi bi-star-fill"></i></label><?php endfor; ?>
          </div>
          <input class="form-control mb-2" name="title" maxlength="190" placeholder="Title (optional)">
          <textarea class="form-control mb-2" name="body" rows="4" maxlength="3000" required placeholder="Share your experience with this piece"></textarea>
          <button class="btn btn-eb btn-primary-eb" type="submit">Submit review</button>
          <p class="small text-muted mt-2">Reviews are published after moderation.</p>
        </form>
      <?php elseif (!customer_id()): ?>
        <p><a href="<?= e(url('account/login?return=' . urlencode($_SERVER['REQUEST_URI']))) ?>">Sign in</a> to write a review.</p>
      <?php endif; ?>
    </div>
    <div class="col-lg-8">
      <?php foreach ($reviews as $rv): ?>
        <article class="review">
          <div class="stars"><?= str_repeat('<i class="bi bi-star-fill"></i>', (int)$rv['rating']) . str_repeat('<i class="bi bi-star"></i>', 5 - (int)$rv['rating']) ?></div>
          <?php if ($rv['title']): ?><h3><?= e($rv['title']) ?></h3><?php endif; ?>
          <p><?= nl2br(e($rv['body'])) ?></p>
          <footer><?= e($rv['name']) ?><?= $rv['verified_purchase'] ? ' · <span class="verified"><i class="bi bi-patch-check"></i> Verified purchase</span>' : '' ?> · <?= e(date('j M Y', strtotime($rv['created_at']))) ?></footer>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</div></section>

<?php foreach ([['You May Also Like', $upsell ?: $related], ['Recently Viewed', $recent]] as [$h, $list]): if (!$list) continue; ?>
<section class="pdp-strip"><div class="container-eb">
  <div class="d-flex justify-content-between align-items-end mb-4">
    <h2 class="section-title small mb-0"><?= e($h) ?></h2>
    <div class="carousel-controls"><button class="car-btn car-prev" type="button" aria-label="Previous"><i class="bi bi-arrow-left"></i></button><button class="car-btn car-next" type="button" aria-label="Next"><i class="bi bi-arrow-right"></i></button></div>
  </div>
  <div class="swiper eb-carousel product-carousel"><div class="swiper-wrapper">
    <?php foreach ($list as $p): ?><div class="swiper-slide"><?php include ROOT_PATH . '/templates/product-card.php'; ?></div><?php endforeach; ?>
  </div></div>
</div></section>
<?php endforeach; ?>

<!-- Size guide -->
<div class="modal fade" id="sizeGuide" tabindex="-1" aria-labelledby="sizeGuideTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><h2 class="modal-title h5" id="sizeGuideTitle">Size Guide</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body rich"><?= $sizeGuide ? sanitize_html($sizeGuide) : '<p>Please contact us for sizing help.</p>' ?></div>
  </div></div>
</div>

<!-- Lightbox -->
<div class="modal fade lightbox" id="lightbox" tabindex="-1" aria-label="Image gallery" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen"><div class="modal-content">
    <button type="button" class="btn-close btn-close-white lb-close" data-bs-dismiss="modal" aria-label="Close"></button>
    <div class="swiper lb-swiper"><div class="swiper-wrapper">
      <?php foreach ($images as $img): ?><div class="swiper-slide"><div class="swiper-zoom-container"><img src="<?= e(img_url($img['path'])) ?>" alt="<?= e($img['alt_text'] ?: $product['name']) ?>" loading="lazy"></div></div><?php endforeach; ?>
    </div><div class="swiper-button-prev"></div><div class="swiper-button-next"></div><div class="swiper-pagination"></div></div>
  </div></div>
</div>
<?php require ROOT_PATH . '/templates/footer.php';
