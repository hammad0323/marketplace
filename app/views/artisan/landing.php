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
        <?php View::partial('partials/product_card', ['product' => $product]); ?>
    <?php endforeach; ?>
    <?php if (!$trendingProducts): ?><p>No products yet.</p><?php endif; ?>
</div>
