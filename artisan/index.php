<?php
require __DIR__ . '/../config/config.php';

$type = mp_find_marketplace_type_by_slug('artisan');
if (!$type) {
    http_response_code(500);
    exit('Artisan marketplace type is not seeded. Import database.sql.');
}

$categories = mp_active_categories_by_marketplace($type['id']);
$featuredArtisans = mp_approved_vendors_by_marketplace($type['id'], 8);
$trendingProducts = mp_published_products($type['id'], 12);

$pageTitle = 'Artisan Marketplace — Handmade with Heart';
$theme = 'artisan';
require __DIR__ . '/../templates/header.php';
?>

<section class="parallax-hero artisan-hero">
    <div class="parallax-hero-bg"></div>
    <div class="artisan-hero-content reveal">
        <span class="artisan-badge">🏺 Handmade &amp; Handcrafted</span>
        <h1>Where Every Piece Tells a Story</h1>
        <p>Discover one-of-a-kind creations from independent artisans — pottery, paintings,
           jewelry, and more, each carrying the maker's own journey.</p>
        <a class="btn" href="#featured-artists">Meet the Artists</a>
    </div>
</section>

<h2 class="artisan-section-title reveal">Featured Categories</h2>
<div class="reveal">
    <?php foreach ($categories as $category): ?>
        <a class="artisan-category-chip" href="category.php?slug=<?= mp_e($category['slug']) ?>"><?= mp_e($category['name']) ?></a>
    <?php endforeach; ?>
</div>

<h2 class="artisan-section-title reveal" id="featured-artists">Featured Artists</h2>
<div class="card-grid reveal">
    <?php foreach ($featuredArtisans as $vendor): ?>
        <div class="vendor-card">
            <div class="vendor-card-body">
                <?php if ($vendor['is_featured']): ?><span class="artisan-badge">Featured Artist</span><?php endif; ?>
                <div><a href="store.php?slug=<?= mp_e($vendor['slug']) ?>"><strong><?= mp_e($vendor['store_name']) ?></strong></a></div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$featuredArtisans): ?><p>No approved artists yet — be the first to join!</p><?php endif; ?>
</div>

<h2 class="artisan-section-title reveal">Trending Handmade Products</h2>
<div class="card-grid reveal">
    <?php foreach ($trendingProducts as $product): ?>
        <?php mp_render_product_card($product); ?>
    <?php endforeach; ?>
    <?php if (!$trendingProducts): ?><p>No products yet.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
