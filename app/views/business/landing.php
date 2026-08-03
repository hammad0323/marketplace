<section class="business-hero">
    <h1>Shop Trusted Business Stores</h1>
    <p>Fashion, electronics, home goods and more — from verified retail businesses,
       all in one convenient marketplace.</p>
</section>

<h2 class="business-section-title">Shop by Category</h2>
<div>
    <?php foreach ($categories as $category): ?>
        <a class="business-category-chip" href="/business/category/<?= e($category['slug']) ?>"><?= e($category['name']) ?></a>
    <?php endforeach; ?>
</div>

<h2 class="business-section-title">Featured Shops</h2>
<div class="card-grid">
    <?php foreach ($featuredShops as $vendor): ?>
        <div class="vendor-card">
            <div class="vendor-card-body">
                <?php if ($vendor['is_verified']): ?><span class="verified-badge">✔ Verified</span><?php endif; ?>
                <div><a href="/business/<?= e($vendor['slug']) ?>"><strong><?= e($vendor['store_name']) ?></strong></a></div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$featuredShops): ?><p>No approved shops yet — be the first to join!</p><?php endif; ?>
</div>

<h2 class="business-section-title">Best Sellers</h2>
<div class="card-grid">
    <?php foreach ($bestSellers as $product): ?>
        <?php View::partial('partials/product_card', ['product' => $product]); ?>
    <?php endforeach; ?>
    <?php if (!$bestSellers): ?><p>No best sellers yet.</p><?php endif; ?>
</div>

<h2 class="business-section-title">Trending Products</h2>
<div class="card-grid">
    <?php foreach ($trendingProducts as $product): ?>
        <?php View::partial('partials/product_card', ['product' => $product]); ?>
    <?php endforeach; ?>
    <?php if (!$trendingProducts): ?><p>No products yet.</p><?php endif; ?>
</div>
