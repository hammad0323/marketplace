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
$artistCount = mp_count_approved_vendors($type['id']);
$productCount = mp_count_published_products($type['id']);
$categoryCount = mp_count_active_categories($type['id']);

$pageTitle = 'Artisan Marketplace — Handmade with Heart';
$theme = 'artisan';
require __DIR__ . '/../templates/header.php';
?>

<section class="parallax-hero artisan-hero">
    <div class="parallax-hero-bg"></div>
    <div class="parallax-layer" data-speed="0.18" style="top:15%; left:6%; width:110px; height:110px;"><div class="floating-blob"></div></div>
    <div class="parallax-layer" data-speed="0.32" style="bottom:10%; right:8%; width:150px; height:150px;"><div class="floating-blob floating-blob-alt"></div></div>
    <div class="artisan-hero-content reveal reveal-blur">
        <span class="artisan-badge">🏺 Handmade &amp; Handcrafted</span>
        <h1>Where Every Piece Tells a Story</h1>
        <p>Discover one-of-a-kind creations from independent artisans — pottery, paintings,
           jewelry, and more, each carrying the maker's own journey.</p>
        <a class="btn btn-magnetic" href="#featured-artists">Meet the Artists</a>

        <div class="stats-row">
            <div class="stat-item">
                <strong class="stat-counter" data-target="<?= (int) $artistCount ?>">0</strong>
                <span class="stat-label">Artists</span>
            </div>
            <div class="stat-item">
                <strong class="stat-counter" data-target="<?= (int) $productCount ?>">0</strong>
                <span class="stat-label">Handmade Pieces</span>
            </div>
            <div class="stat-item">
                <strong class="stat-counter" data-target="<?= (int) $categoryCount ?>">0</strong>
                <span class="stat-label">Categories</span>
            </div>
        </div>
    </div>
</section>

<h2 class="artisan-section-title reveal">Featured Categories</h2>
<div class="reveal reveal-left">
    <?php foreach ($categories as $category): ?>
        <a class="artisan-category-chip" href="category.php?slug=<?= mp_e($category['slug']) ?>"><?= mp_e($category['name']) ?></a>
    <?php endforeach; ?>
</div>

<h2 class="artisan-section-title reveal" id="featured-artists">Featured Artists</h2>
<div class="card-grid">
    <?php foreach ($featuredArtisans as $i => $vendor): ?>
        <div class="vendor-card reveal reveal-scale reveal-delay-<?= ($i % 4) + 1 ?>">
            <div class="vendor-card-body">
                <?php if ($vendor['is_featured']): ?><span class="artisan-badge">Featured Artist</span><?php endif; ?>
                <div><a href="store.php?slug=<?= mp_e($vendor['slug']) ?>"><strong><?= mp_e($vendor['store_name']) ?></strong></a></div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$featuredArtisans): ?><p>No approved artists yet — be the first to join!</p><?php endif; ?>
</div>

<h2 class="artisan-section-title reveal">Trending Handmade Products</h2>
<div class="card-grid">
    <?php foreach ($trendingProducts as $i => $product): ?>
        <div class="reveal reveal-scale reveal-delay-<?= ($i % 4) + 1 ?>"><?php mp_render_product_card($product); ?></div>
    <?php endforeach; ?>
    <?php if (!$trendingProducts): ?><p>No products yet.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
