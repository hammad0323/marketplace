<?php
require __DIR__ . '/../config/config.php';

$type = mp_find_marketplace_type_by_slug('business');
if (!$type) {
    http_response_code(500);
    exit('Business marketplace type is not seeded. Import database.sql.');
}

$categories = mp_active_categories_by_marketplace($type['id']);
$featuredShops = mp_approved_vendors_by_marketplace($type['id'], 8);
$bestSellers = mp_best_seller_products($type['id'], 8);
$trendingProducts = mp_published_products($type['id'], 12);
$shopCount = mp_count_approved_vendors($type['id']);
$productCount = mp_count_published_products($type['id']);
$categoryCount = mp_count_active_categories($type['id']);

$pageTitle = 'Business Shops — Shop Trusted Retail Stores';
$theme = 'business';
require __DIR__ . '/../templates/header.php';
?>

<section class="parallax-hero business-hero">
    <div class="parallax-hero-bg"></div>
    <div class="parallax-layer" data-speed="0.2" style="top:18%; right:12%; width:90px; height:90px;"><div class="floating-geo"></div></div>
    <div class="parallax-layer" data-speed="0.35" style="bottom:14%; right:28%; width:56px; height:56px;"><div class="floating-geo"></div></div>
    <div class="business-hero-content reveal reveal-blur">
        <span class="section-eyebrow" style="color:#bfdbfe;">Verified Retail Businesses</span>
        <h1>Shop Trusted Business Stores</h1>
        <p>Fashion, electronics, home goods and more — from verified retail businesses,
           all in one convenient marketplace.</p>
        <div style="display:flex; gap:1rem; flex-wrap:wrap;">
            <a class="btn btn-accent btn-magnetic" href="#featured-shops">Browse Shops</a>
            <a class="btn btn-secondary btn-magnetic" href="<?= mp_e(ROUTE_VENDOR) ?>register.php">Sell With Us</a>
        </div>

        <div class="stats-row" style="justify-content:flex-start;">
            <div class="stat-item">
                <strong class="stat-counter" data-target="<?= (int) $shopCount ?>">0</strong>
                <span class="stat-label">Verified Shops</span>
            </div>
            <div class="stat-item">
                <strong class="stat-counter" data-target="<?= (int) $productCount ?>">0</strong>
                <span class="stat-label">Products</span>
            </div>
            <div class="stat-item">
                <strong class="stat-counter" data-target="<?= (int) $categoryCount ?>">0</strong>
                <span class="stat-label">Categories</span>
            </div>
        </div>
    </div>
</section>

<h2 class="business-section-title reveal">Shop by Category</h2>
<div class="reveal reveal-left">
    <?php foreach ($categories as $category): ?>
        <a class="business-category-chip" href="category.php?slug=<?= mp_e($category['slug']) ?>"><?= mp_e($category['name']) ?></a>
    <?php endforeach; ?>
</div>

<h2 class="business-section-title reveal" id="featured-shops">Featured Shops</h2>
<div class="card-grid">
    <?php foreach ($featuredShops as $i => $vendor): ?>
        <div class="vendor-card reveal reveal-scale reveal-delay-<?= ($i % 4) + 1 ?>">
            <div class="vendor-card-body">
                <?php if ($vendor['is_verified']): ?><span class="verified-badge">✔ Verified</span><?php endif; ?>
                <div><a href="store.php?slug=<?= mp_e($vendor['slug']) ?>"><strong><?= mp_e($vendor['store_name']) ?></strong></a></div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$featuredShops): ?><p>No approved shops yet — be the first to join!</p><?php endif; ?>
</div>

<h2 class="business-section-title reveal">Best Sellers</h2>
<div class="card-grid">
    <?php foreach ($bestSellers as $i => $product): ?>
        <div class="reveal reveal-scale reveal-delay-<?= ($i % 4) + 1 ?>"><?php mp_render_product_card($product); ?></div>
    <?php endforeach; ?>
    <?php if (!$bestSellers): ?><p>No best sellers yet.</p><?php endif; ?>
</div>

<h2 class="business-section-title reveal">Trending Products</h2>
<div class="card-grid">
    <?php foreach ($trendingProducts as $i => $product): ?>
        <div class="reveal reveal-scale reveal-delay-<?= ($i % 4) + 1 ?>"><?php mp_render_product_card($product); ?></div>
    <?php endforeach; ?>
    <?php if (!$trendingProducts): ?><p>No products yet.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
