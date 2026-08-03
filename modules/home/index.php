<?php
$artisanType = find_marketplace_type_by_slug('artisan');
$businessType = find_marketplace_type_by_slug('business');
$officialType = find_marketplace_type_by_slug('official');

$featuredArtisans = $artisanType ? approved_vendors_by_marketplace($artisanType['id'], 6) : [];
$featuredBusinesses = $businessType ? approved_vendors_by_marketplace($businessType['id'], 6) : [];
$officialStore = $officialType ? (approved_vendors_by_marketplace($officialType['id'], 1)[0] ?? null) : null;
$trendingArtisanProducts = $artisanType ? published_products($artisanType['id'], 8) : [];
$trendingBusinessProducts = $businessType ? published_products($businessType['id'], 8) : [];

$pageTitle = 'Discover Handmade Artisans & Trusted Business Shops';
$theme = 'main';
require __DIR__ . '/../../partials/header.php';
?>

<section style="text-align:center; padding: 3rem 0;">
    <h1>One Platform. Two Worlds of Shopping.</h1>
    <p style="max-width:640px;margin:0 auto;color:#555;">
        Discover handmade treasures from independent artisans, or shop trusted
        everyday retail businesses — all in one place.
    </p>
    <div style="display:flex; gap:1rem; justify-content:center; margin-top:2rem; flex-wrap:wrap;">
        <a class="btn" href="/artisan">Explore Artisan Marketplace</a>
        <a class="btn btn-secondary" href="/business">Explore Business Shops</a>
    </div>
</section>

<?php if ($officialStore): ?>
<section style="text-align:center; margin: 3rem 0;">
    <span class="badge">⭐ Official Store</span>
    <p><a href="/store/official-store"><?= e($officialStore['store_name']) ?></a> — verified and curated directly by the platform.</p>
</section>
<?php endif; ?>

<section>
    <h2>Featured Artisans</h2>
    <div class="card-grid">
        <?php foreach ($featuredArtisans as $vendor): ?>
            <div class="vendor-card">
                <div class="vendor-card-body">
                    <strong><a href="/artisan/<?= e($vendor['slug']) ?>"><?= e($vendor['store_name']) ?></a></strong>
                    <div><span class="badge">🏺 Handmade</span></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$featuredArtisans): ?><p>No approved artisans yet.</p><?php endif; ?>
    </div>
</section>

<section>
    <h2>Featured Business Shops</h2>
    <div class="card-grid">
        <?php foreach ($featuredBusinesses as $vendor): ?>
            <div class="vendor-card">
                <div class="vendor-card-body">
                    <strong><a href="/business/<?= e($vendor['slug']) ?>"><?= e($vendor['store_name']) ?></a></strong>
                    <div><span class="badge">🏪 Business Shop</span></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$featuredBusinesses): ?><p>No approved business shops yet.</p><?php endif; ?>
    </div>
</section>

<section>
    <h2>Trending in Artisan Marketplace</h2>
    <div class="card-grid">
        <?php foreach ($trendingArtisanProducts as $product): ?>
            <?php render_product_card($product); ?>
        <?php endforeach; ?>
        <?php if (!$trendingArtisanProducts): ?><p>No products yet.</p><?php endif; ?>
    </div>
</section>

<section>
    <h2>Trending in Business Shops</h2>
    <div class="card-grid">
        <?php foreach ($trendingBusinessProducts as $product): ?>
            <?php render_product_card($product); ?>
        <?php endforeach; ?>
        <?php if (!$trendingBusinessProducts): ?><p>No products yet.</p><?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
