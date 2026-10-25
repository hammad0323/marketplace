<?php
/** Product detail page: /product/{slug} */
$product = product_by_slug($ROUTE['params']['slug']);
if (!$product) {
    // Admins may preview unpublished products
    $draft = current_admin() ? product_by_slug($ROUTE['params']['slug'], true) : null;
    if (!$draft) {
        route_not_found($ROUTE['path']);
    }
    $product = $draft;
}
$pid = (int) $product['id'];
$images = product_images($pid);
$variants = product_variants($pid);
$groups = variant_option_groups($variants);
$attrs = product_attributes($pid);
$category = $product['category_id'] ? (categories_all(false)[(int) $product['category_id']] ?? null) : null;
$pi = price_info($product);
$reviews = product_reviews($pid);
$breakdown = review_breakdown($pid);
$related = related_products($product, 8);
$fbt = frequently_bought_together($pid, 3);
$upsells = product_relations($pid, 'upsell', 4);
$recentIds = array_values(array_diff(recently_viewed_ids(), [$pid]));
$recent = products_by_ids($recentIds, 8);
recently_viewed_track($pid);
db_exec('UPDATE products SET view_count = view_count + 1 WHERE id = ?', [$pid]);
$customer = current_customer();
$canReview = $customer && !db_val('SELECT 1 FROM reviews WHERE customer_id = ? AND product_id = ?', [(int) $customer['id'], $pid]);
if ($canReview && setting_bool('reviews_require_purchase')) {
    $canReview = customer_purchased_product((int) $customer['id'], $pid);
}
$studioGarment = $product['custom_garment_id'] ? studio_garment((int) $product['custom_garment_id']) : null;
$urgentCities = ((int) $product['allow_urgent'] && !(int) $product['made_to_order']) ? urgent_cities() : [];
$quote = shipping_quote('', '', $pi['final'], 'standard', (int) $product['made_to_order'] ? (int) $product['lead_time_days'] : 0);

$crumbs = [['Home', url()]];
if ($category) {
    foreach (category_ancestors($category) as $a) {
        $crumbs[] = [$a['name'], category_url($a)];
    }
    $crumbs[] = [$category['name'], category_url($category)];
}
$crumbs[] = [$product['name'], url('product/' . $product['slug'])];

$mainImage = $images[0]['image'] ?? null;
$inStockAny = (int) $product['has_variants'] ? (bool) array_filter($variants, fn($v) => available_qty($product, $v) > 0) : available_qty($product) > 0;
$schema = [
    '@context' => 'https://schema.org', '@type' => 'Product', 'name' => $product['name'], 'sku' => $product['sku'],
    'description' => str_limit($product['short_description'] ?: $product['description'], 500),
    'image' => array_map(fn($i) => img_url($i['image']), array_slice($images, 0, 6)),
    'brand' => ['@type' => 'Brand', 'name' => $product['brand'] ?: store_name()],
    'offers' => [
        '@type' => 'Offer', 'url' => url('product/' . $product['slug']), 'priceCurrency' => setting('currency_code', 'PKR'),
        'price' => number_format($pi['final'], 2, '.', ''), 'availability' => 'https://schema.org/' . ($inStockAny ? 'InStock' : 'OutOfStock'),
        'itemCondition' => 'https://schema.org/NewCondition', 'seller' => ['@type' => 'Organization', 'name' => store_name()],
    ],
];
if ($category) {
    $schema['category'] = $category['name'];
}
if ((int) $product['rating_count'] > 0) {
    $schema['aggregateRating'] = ['@type' => 'AggregateRating', 'ratingValue' => (float) $product['rating_avg'], 'reviewCount' => (int) $product['rating_count']];
    $schema['review'] = array_map(fn($r) => ['@type' => 'Review', 'author' => ['@type' => 'Person', 'name' => $r['author_name']],
        'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int) $r['rating']], 'reviewBody' => str_limit($r['body'], 300)], array_slice($reviews, 0, 5));
}
seo_add_schema($schema);
seo_set([
    'title' => $product['seo_title'] ?: $product['name'],
    'description' => $product['meta_description'] ?: str_limit($product['short_description'] ?: $product['description'], 160),
    'og_title' => $product['og_title'] ?: null,
    'og_description' => $product['og_description'] ?: null,
    'image' => $product['og_image'] ?: $mainImage,
    'type' => 'product',
    'canonical' => $product['canonical_url'] ?: url('product/' . $product['slug']),
    'noindex' => (bool) $product['noindex'] || $product['status'] !== 'published',
    'breadcrumbs' => $crumbs,
]);

$specs = array_filter([
    'Brand' => $product['brand'], 'Fabric / Material' => $product['fabric'], 'Fit' => $product['fit'], 'Pattern' => $product['pattern'],
    'Sleeve' => $product['sleeve_type'], 'Collar' => $product['collar_type'], 'Waist' => $product['waist_type'], 'Length' => $product['length_type'],
    'Season' => $product['season'], 'Occasion' => $product['occasion'], 'Weight' => $product['weight_kg'] ? rtrim(rtrim($product['weight_kg'], '0'), '.') . ' kg' : null,
    'Dimensions' => $product['dimensions'],
]);
foreach ($attrs as $a) {
    $specs[$a['name']] = $a['value'];
}
$sizeGuide = $product['size_guide'] ?: (db_val("SELECT content FROM pages WHERE slug = 'size-guide' AND status = 'published'") ?: '');
$video = trim((string) $product['video_url']);

$bodyClass = 'page-product';
require UC_ROOT . '/includes/header.php';
?>
<?php if ($product['status'] !== 'published'): ?>
    <div class="uc-preview-bar">Admin preview — this product is <?= e($product['status']) ?> and hidden from customers.</div>
<?php endif; ?>

<div class="uc-container uc-pdp-crumbs"><?= breadcrumbs_html($crumbs) ?></div>

<section class="uc-container uc-pdp">
    <div class="uc-gallery" data-gallery>
        <div class="uc-gallery-thumbs" role="tablist" aria-label="Product images">
            <?php foreach ($images as $i => $im): ?>
                <button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" data-gallery-thumb="<?= $i ?>" aria-label="Image <?= $i + 1 ?>">
                    <img src="<?= e(img_sized($im['image'], 200)) ?>" alt="" loading="lazy">
                </button>
            <?php endforeach; ?>
            <?php if ($video): ?><button type="button" class="uc-thumb-video" data-gallery-video aria-label="Play video"><i class="bi bi-play-fill"></i></button><?php endif; ?>
        </div>
        <div class="uc-gallery-main">
            <div class="uc-gallery-track" data-gallery-track>
                <?php foreach ($images ?: [['image' => null, 'alt_text' => $product['name'], 'caption' => null, 'view_type' => 'front']] as $i => $im): ?>
                    <figure class="uc-gallery-slide" data-zoom>
                        <img src="<?= e(img_sized($im['image'], 1200)) ?>" data-full="<?= e(img_sized($im['image'], 2000)) ?>" alt="<?= e($im['alt_text'] ?: $product['name']) ?>"
                             width="900" height="1200"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?> data-lightbox="<?= $i ?>">
                        <?php if ($im['caption']): ?><figcaption><?= e($im['caption']) ?></figcaption><?php endif; ?>
                    </figure>
                <?php endforeach; ?>
            </div>
            <?php if ($product['badge'] || $pi['on_sale']): ?><span class="uc-badge <?= e(badge_class($product['badge'] ?: 'Sale')) ?>"><?= e($product['badge'] ?: 'Sale') ?></span><?php endif; ?>
            <button type="button" class="uc-gallery-expand" data-lightbox-open aria-label="View full screen"><i class="bi bi-arrows-fullscreen"></i></button>
            <div class="uc-gallery-dots d-lg-none">
                <?php foreach ($images as $i => $im): ?><span class="<?= $i === 0 ? 'is-active' : '' ?>"></span><?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="uc-pdp-info">
        <div class="uc-pdp-sticky">
            <?php if ($category): ?><a class="uc-eyebrow" href="<?= e(category_url($category)) ?>"><?= e($category['name']) ?></a><?php endif; ?>
            <h1 class="uc-pdp-title"><?= e($product['name']) ?></h1>
            <div class="uc-pdp-meta">
                <span class="uc-sku">SKU: <span data-sku><?= e($product['sku']) ?></span></span>
                <?php if ((int) $product['rating_count']): ?>
                    <a href="#reviews" class="uc-pdp-rating"><?= render_stars((float) $product['rating_avg']) ?> <?= number_format((float) $product['rating_avg'], 1) ?> (<?= (int) $product['rating_count'] ?> reviews)</a>
                <?php endif; ?>
            </div>
            <?php if ($product['short_description']): ?><p class="uc-pdp-short"><?= e($product['short_description']) ?></p><?php endif; ?>

            <?php partial('components/product-buy', ['product' => $product, 'variants' => $variants, 'groups' => $groups, 'context' => 'page']); ?>

            <?php if ($studioGarment): ?>
                <a class="uc-studio-cta" href="<?= e(url('custom-size/' . $product['slug'])) ?>">
                    <i class="bi bi-rulers"></i>
                    <span><strong>Get it in your exact size</strong><small><?= (float) $studioGarment['fee'] > 0 ? 'Made to measure for + ' . e(money($studioGarment['fee'])) : 'Same ready-made price' ?> · choose colour, collar, cuffs &amp; <?= $studioGarment['def']['gender'] === 'women' ? 'neckline' : 'shalwar style' ?></small></span>
                    <i class="bi bi-arrow-right"></i>
                </a>
            <?php endif; ?>

            <ul class="uc-pdp-perks">
                <li><i class="bi bi-truck"></i><div><strong>Estimated delivery: <?= e($quote['estimate']) ?></strong>
                    <span><?= $quote['free_threshold'] ? 'Free standard delivery on orders over ' . e(money($quote['free_threshold'])) . '.' : 'Delivery charges shown at checkout.' ?></span>
                    <button type="button" class="uc-link-btn" data-bs-toggle="collapse" data-bs-target="#ucDeliveryCheck">Check your city</button>
                    <div class="collapse" id="ucDeliveryCheck">
                        <form class="uc-inline-form" data-delivery-check>
                            <input type="hidden" name="subtotal" value="<?= e($pi['final']) ?>">
                            <input type="hidden" name="lead" value="<?= (int) $product['made_to_order'] ? (int) $product['lead_time_days'] : 0 ?>">
                            <input type="text" name="city" placeholder="Enter your city" aria-label="City" required>
                            <button class="uc-btn uc-btn--sm" type="submit">Check</button>
                        </form>
                        <p class="small mt-2 mb-0" data-delivery-result></p>
                    </div>
                </div></li>
                <?php if ($urgentCities): ?>
                    <li data-urgent-box><i class="bi bi-lightning-charge"></i><div><strong>Need it urgently?</strong>
                        <span>Urgent delivery available in <?= count($urgentCities) ?> cities. Choose yours:</span>
                        <select class="form-select form-select-sm mt-1" data-urgent-city aria-label="Your city for urgent delivery">
                            <option value="">Select city…</option>
                            <?php foreach ($urgentCities as $uc): $ur = urgent_rate_for($uc['city']); ?>
                                <option value="<?= e($uc['city']) ?>" data-fee="<?= e(money($uc['fee'])) ?>" data-label="<?= e($uc['label']) ?>" data-eta="<?= e($ur['estimate'] ?? '') ?>"<?= ($_SESSION['urgent_city'] ?? '') === $uc['city'] ? ' selected' : '' ?>><?= e($uc['city']) ?> — <?= e(money($uc['fee'])) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="d-block mt-1" data-urgent-result></span>
                        <span class="d-block small">Select “Urgent” as the delivery method at checkout.</span></div></li>
                <?php endif; ?>
                <?php if (payment_method_get('cod') && payment_method_state(payment_method_get('cod')) === 'ready'): ?>
                    <li><i class="bi bi-cash-coin"></i><div><strong>Cash on Delivery available</strong><span><?= e(setting('cod_note')) ?></span></div></li>
                <?php endif; ?>
                <li><i class="bi bi-arrow-repeat"></i><div><strong>Easy exchanges</strong><span><?= e(setting('returns_note')) ?></span></div></li>
            </ul>

            <div class="uc-accordion">
                <details open>
                    <summary>Product Details</summary>
                    <div class="uc-rich"><?= sanitize_html($product['description']) ?></div>
                </details>
                <?php if ($specs): ?>
                <details>
                    <summary>Specifications</summary>
                    <dl class="uc-specs">
                        <?php foreach ($specs as $k => $v): ?><dt><?= e($k) ?></dt><dd><?= e($v) ?></dd><?php endforeach; ?>
                    </dl>
                </details>
                <?php endif; ?>
                <?php if ($product['care_instructions']): ?>
                <details>
                    <summary>Care Instructions</summary>
                    <p><?= nl2br(e($product['care_instructions'])) ?></p>
                    <a class="uc-link-arrow" href="<?= e(url('care-guide')) ?>">Full care guide <i class="bi bi-arrow-right"></i></a>
                </details>
                <?php endif; ?>
                <details>
                    <summary>Shipping &amp; Returns</summary>
                    <p><?= e(setting('delivery_note')) ?></p>
                    <p><?= e(setting('returns_note')) ?></p>
                    <p><a href="<?= e(url('shipping-policy')) ?>">Shipping policy</a> · <a href="<?= e(url('returns-and-exchanges')) ?>">Returns &amp; exchanges</a></p>
                </details>
            </div>
            <div class="uc-share">
                <span>Share</span>
                <?php $shareUrl = urlencode(url('product/' . $product['slug'])); ?>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="Share on Facebook"><i class="bi bi-facebook"></i></a>
                <a href="https://wa.me/?text=<?= urlencode($product['name'] . ' ') . $shareUrl ?>" target="_blank" rel="noopener" aria-label="Share on WhatsApp"><i class="bi bi-whatsapp"></i></a>
                <a href="https://pinterest.com/pin/create/button/?url=<?= $shareUrl ?>" target="_blank" rel="noopener" aria-label="Pin on Pinterest"><i class="bi bi-pinterest"></i></a>
                <button type="button" data-copy-link="<?= e(url('product/' . $product['slug'])) ?>" aria-label="Copy link"><i class="bi bi-link-45deg"></i></button>
            </div>
        </div>
    </div>
</section>

<?php if ($fbt): $bundle = array_merge([hydrate_cards([$product])[0]], $fbt); $bundleTotal = array_sum(array_map(fn($b) => $b['price_info']['final'], $bundle)); ?>
<section class="uc-section uc-fbt-section">
    <div class="uc-container">
        <header class="uc-section-head text-left"><p class="uc-eyebrow">Complete the look</p><h2 class="uc-section-title">Frequently Bought Together</h2></header>
        <div class="uc-fbt">
            <div class="uc-fbt-items">
                <?php foreach ($bundle as $i => $b): ?>
                    <?php if ($i): ?><span class="uc-fbt-plus">+</span><?php endif; ?>
                    <a class="uc-fbt-item" href="<?= e($b['url']) ?>">
                        <img src="<?= e(img_sized($b['image'], 400)) ?>" alt="<?= e($b['name']) ?>" loading="lazy">
                        <span><?= e($b['name']) ?></span><strong><?= e(money($b['price_info']['final'])) ?></strong>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="uc-fbt-total">
                <span>Total for <?= count($bundle) ?> items</span>
                <strong><?= e(money($bundleTotal)) ?></strong>
                <p class="small text-muted">Choose sizes on each product page.</p>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($upsells): ?>
<section class="uc-section">
    <div class="uc-container">
        <header class="uc-section-head text-left"><p class="uc-eyebrow">Upgrade</p><h2 class="uc-section-title">You May Also Consider</h2></header>
        <?php partial('components/product-carousel', ['products' => $upsells]); ?>
    </div>
</section>
<?php endif; ?>

<section class="uc-section uc-reviews-section" id="reviews">
    <div class="uc-container">
        <div class="uc-reviews-grid">
            <div class="uc-reviews-summary">
                <h2 class="uc-section-title">Customer Reviews</h2>
                <?php if ((int) $product['rating_count']): ?>
                    <div class="uc-rating-big"><strong><?= number_format((float) $product['rating_avg'], 1) ?></strong><?= render_stars((float) $product['rating_avg']) ?><span><?= (int) $product['rating_count'] ?> reviews</span></div>
                    <ul class="uc-rating-bars">
                        <?php for ($r = 5; $r >= 1; $r--): $pct = $product['rating_count'] ? round($breakdown[$r] / $product['rating_count'] * 100) : 0; ?>
                            <li><span><?= $r ?> <i class="bi bi-star-fill"></i></span><span class="uc-bar"><span style="width:<?= $pct ?>%"></span></span><span><?= $breakdown[$r] ?></span></li>
                        <?php endfor; ?>
                    </ul>
                <?php else: ?>
                    <p class="text-muted">No reviews yet.</p>
                <?php endif; ?>
                <?php if ($canReview): ?>
                    <button class="uc-btn uc-btn--outline" type="button" data-bs-toggle="collapse" data-bs-target="#ucReviewForm">Write a review</button>
                <?php elseif (!$customer): ?>
                    <p class="small"><a href="<?= e(url('account/login')) ?>">Sign in</a> to write a review.</p>
                <?php endif; ?>
            </div>
            <div class="uc-reviews-list">
                <?php if ($canReview): ?>
                    <form class="collapse uc-review-form" id="ucReviewForm" data-review-form>
                        <input type="hidden" name="product_id" value="<?= $pid ?>">
                        <div class="uc-star-input" role="radiogroup" aria-label="Your rating">
                            <?php for ($r = 5; $r >= 1; $r--): ?>
                                <input type="radio" name="rating" id="rate<?= $r ?>" value="<?= $r ?>" required><label for="rate<?= $r ?>" aria-label="<?= $r ?> stars"><i class="bi bi-star-fill"></i></label>
                            <?php endfor; ?>
                        </div>
                        <label class="uc-field"><span>Title</span><input type="text" name="title" maxlength="160"></label>
                        <label class="uc-field"><span>Your review</span><textarea name="body" rows="4" maxlength="3000" required minlength="10"></textarea></label>
                        <label class="uc-field"><span>City (optional)</span><input type="text" name="city" maxlength="80"></label>
                        <button class="uc-btn" type="submit">Submit review</button>
                    </form>
                <?php endif; ?>
                <?php foreach ($reviews as $r): ?>
                    <article class="uc-review">
                        <div class="uc-review-head"><?= render_stars((float) $r['rating']) ?><time datetime="<?= e($r['created_at']) ?>"><?= e(format_date($r['created_at'])) ?></time></div>
                        <?php if ($r['title']): ?><h3><?= e($r['title']) ?></h3><?php endif; ?>
                        <p><?= nl2br(e($r['body'])) ?></p>
                        <p class="uc-review-author"><?= e($r['author_name']) ?><?= $r['author_city'] ? ', ' . e($r['author_city']) : '' ?>
                            <?php if ((int) $r['is_verified_purchase']): ?><span class="uc-verified"><i class="bi bi-patch-check-fill"></i> Verified purchase</span><?php endif; ?></p>
                        <?php if ($r['admin_reply']): ?><div class="uc-review-reply"><strong><?= e(store_name()) ?>:</strong> <?= nl2br(e($r['admin_reply'])) ?></div><?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</section>

<?php if ($related): ?>
<section class="uc-section">
    <div class="uc-container">
        <header class="uc-section-head text-left"><p class="uc-eyebrow">More to explore</p><h2 class="uc-section-title">Related Products</h2></header>
        <?php partial('components/product-carousel', ['products' => $related]); ?>
    </div>
</section>
<?php endif; ?>

<?php if ($recent): ?>
<section class="uc-section pt-0">
    <div class="uc-container">
        <header class="uc-section-head text-left"><h2 class="uc-section-title">Recently Viewed</h2></header>
        <?php partial('components/product-carousel', ['products' => $recent]); ?>
    </div>
</section>
<?php endif; ?>

<!-- Size guide -->
<div class="modal fade uc-modal" id="ucSizeGuide" tabindex="-1" aria-labelledby="ucSizeGuideTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title h4" id="ucSizeGuideTitle">Size Guide</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body uc-rich"><?= sanitize_html($sizeGuide) ?: '<p>Contact us for sizing help.</p>' ?></div>
        </div>
    </div>
</div>

<!-- Lightbox -->
<div class="uc-lightbox" data-lightbox aria-hidden="true" role="dialog" aria-label="Image viewer">
    <button type="button" class="uc-lightbox-close" data-lightbox-close aria-label="Close"><i class="bi bi-x-lg"></i></button>
    <button type="button" class="uc-lightbox-nav is-prev" data-lightbox-prev aria-label="Previous image"><i class="bi bi-chevron-left"></i></button>
    <img src="" alt="" data-lightbox-img>
    <button type="button" class="uc-lightbox-nav is-next" data-lightbox-next aria-label="Next image"><i class="bi bi-chevron-right"></i></button>
    <?php if ($video): ?><div class="uc-lightbox-video" data-lightbox-video data-src="<?= e($video) ?>"></div><?php endif; ?>
</div>

<!-- Sticky mobile add-to-bag -->
<div class="uc-sticky-buy" data-sticky-buy>
    <div><strong><?= e(str_limit($product['name'], 34)) ?></strong><span><?= e(money($pi['final'])) ?></span></div>
    <button type="button" class="uc-btn uc-btn--sm" data-scroll-to-buy>Add to Bag</button>
</div>
<?php require UC_ROOT . '/includes/footer.php';
