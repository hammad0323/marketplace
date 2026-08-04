<?php
require __DIR__ . '/config.php';

$type = mp_find_marketplace_type_by_slug('business');
if (!$type) {
    http_response_code(500);
    exit('Business marketplace type is not seeded. Import database.sql.');
}

$categories = mp_active_categories_by_marketplace($type['id']);
$featuredShops = mp_approved_vendors_by_marketplace($type['id'], 8);
$bestSellers = mp_best_seller_products($type['id'], 8);
$trendingProducts = mp_published_products($type['id'], 12);

$pageTitle = 'Business Shops — Shop Trusted Retail Stores';
$theme = 'business';
require __DIR__ . '/header.php';
?>

<section class="business-hero">
    <h1>Shop Trusted Business Stores</h1>
    <p>Fashion, electronics, home goods and more — from verified retail businesses,
       all in one convenient marketplace.</p>
</section>

<h2 class="business-section-title">Shop by Category</h2>
<div>
    <?php foreach ($categories as $category): ?>
        <a class="business-category-chip" href="/business-category.php?slug=<?= mp_e($category['slug']) ?>"><?= mp_e($category['name']) ?></a>
    <?php endforeach; ?>
</div>

<h2 class="business-section-title">Featured Shops</h2>
<div class="card-grid">
    <?php foreach ($featuredShops as $vendor): ?>
        <div class="vendor-card">
            <div class="vendor-card-body">
                <?php if ($vendor['is_verified']): ?><span class="verified-badge">✔ Verified</span><?php endif; ?>
                <div><a href="/business-store.php?slug=<?= mp_e($vendor['slug']) ?>"><strong><?= mp_e($vendor['store_name']) ?></strong></a></div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$featuredShops): ?><p>No approved shops yet — be the first to join!</p><?php endif; ?>
</div>

<h2 class="business-section-title">Best Sellers</h2>
<div class="card-grid">
    <?php foreach ($bestSellers as $product): ?>
        <?php mp_render_product_card($product); ?>
    <?php endforeach; ?>
    <?php if (!$bestSellers): ?><p>No best sellers yet.</p><?php endif; ?>
</div>

<h2 class="business-section-title">Trending Products</h2>
<div class="card-grid">
    <?php foreach ($trendingProducts as $product): ?>
        <?php mp_render_product_card($product); ?>
    <?php endforeach; ?>
    <?php if (!$trendingProducts): ?><p>No products yet.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/footer.php'; ?>
