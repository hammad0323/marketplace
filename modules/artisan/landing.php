<?php
$type = find_marketplace_type_by_slug('artisan');
if (!$type) {
    http_response_code(500);
    exit('Artisan marketplace type is not seeded. Run database/migrate.php --seed.');
}

$categories = active_categories_by_marketplace($type['id']);
$featuredArtisans = approved_vendors_by_marketplace($type['id'], 8);
$trendingProducts = published_products($type['id'], 12);

$pageTitle = 'Artisan Marketplace — Handmade with Heart';
$theme = 'artisan';
require __DIR__ . '/../../partials/header.php';
?>

<section class="artisan-hero">
    <span class="artisan-badge">🏺 Handmade &amp; Handcrafted</span>
    <h1>Where Every Piece Tells a Story</h1>
    <p>Discover one-of-a-kind creations from independent artisans — pottery, paintings,
       jewelry, and more, each carrying the maker's own journey.</p>
</section>

<h2 class="artisan-section-title">Featured Categories</h2>
<div>
    <?php foreach ($categories as $category): ?>
        <a class="artisan-category-chip" href="/artisan/category/<?= e($category['slug']) ?>"><?= e($category['name']) ?></a>
    <?php endforeach; ?>
</div>

<h2 class="artisan-section-title">Featured Artists</h2>
<div class="card-grid">
    <?php foreach ($featuredArtisans as $vendor): ?>
        <div class="vendor-card">
            <div class="vendor-card-body">
                <?php if ($vendor['is_featured']): ?><span class="artisan-badge">Featured Artist</span><?php endif; ?>
                <div><a href="/artisan/<?= e($vendor['slug']) ?>"><strong><?= e($vendor['store_name']) ?></strong></a></div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$featuredArtisans): ?><p>No approved artists yet — be the first to join!</p><?php endif; ?>
</div>

<h2 class="artisan-section-title">Trending Handmade Products</h2>
<div class="card-grid">
    <?php foreach ($trendingProducts as $product): ?>
        <?php render_product_card($product); ?>
    <?php endforeach; ?>
    <?php if (!$trendingProducts): ?><p>No products yet.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
