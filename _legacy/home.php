<?php
require __DIR__ . '/config.php';

$artisanType = mp_find_marketplace_type_by_slug('artisan');
$businessType = mp_find_marketplace_type_by_slug('business');
$officialType = mp_find_marketplace_type_by_slug('official');

$featuredArtisans = $artisanType ? mp_approved_vendors_by_marketplace($artisanType['id'], 6) : [];
$featuredBusinesses = $businessType ? mp_approved_vendors_by_marketplace($businessType['id'], 6) : [];
$officialStore = $officialType ? (mp_approved_vendors_by_marketplace($officialType['id'], 1)[0] ?? null) : null;
$trendingArtisanProducts = $artisanType ? mp_published_products($artisanType['id'], 8) : [];
$trendingBusinessProducts = $businessType ? mp_published_products($businessType['id'], 8) : [];

$pageTitle = 'Discover Handmade Artisans & Trusted Business Shops';
$theme = 'main';
require __DIR__ . '/header.php';
?>

<section class="parallax-hero home-hero">
    <div class="parallax-hero-bg"></div>
    <div class="home-hero-content reveal">
        <span class="section-eyebrow">Two Marketplaces. One Platform.</span>
        <h1>Handmade Treasures &amp; Trusted Retail, All in One Place</h1>
        <p>Discover one-of-a-kind creations from independent artisans, or shop everyday
           essentials from verified business owners — start exploring below.</p>
        <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
            <a class="btn btn-accent" href="/artisan.php">Explore Artisan Marketplace</a>
            <a class="btn btn-secondary" href="/business.php">Explore Business Shops</a>
        </div>
    </div>
</section>

<?php if ($officialStore): ?>
<section class="official-strip reveal">
    <span class="badge">⭐ Official Store</span>
    <p style="margin:0;"><a href="/official-store.php"><strong><?= mp_e($officialStore['store_name']) ?></strong></a> — verified and curated directly by the platform.</p>
</section>
<?php endif; ?>

<section class="reveal">
    <h2 class="section-title">Featured Artisans</h2>
    <div class="card-grid">
        <?php foreach ($featuredArtisans as $vendor): ?>
            <div class="vendor-card">
                <div class="vendor-card-body">
                    <strong><a href="/artisan-store.php?slug=<?= mp_e($vendor['slug']) ?>"><?= mp_e($vendor['store_name']) ?></a></strong>
                    <div><span class="badge">🏺 Handmade</span></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$featuredArtisans): ?><p>No approved artisans yet.</p><?php endif; ?>
    </div>
</section>

<section class="reveal">
    <h2 class="section-title">Featured Business Shops</h2>
    <div class="card-grid">
        <?php foreach ($featuredBusinesses as $vendor): ?>
            <div class="vendor-card">
                <div class="vendor-card-body">
                    <strong><a href="/business-store.php?slug=<?= mp_e($vendor['slug']) ?>"><?= mp_e($vendor['store_name']) ?></a></strong>
                    <div><span class="badge">🏪 Business Shop</span></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$featuredBusinesses): ?><p>No approved business shops yet.</p><?php endif; ?>
    </div>
</section>

<section class="reveal">
    <h2 class="section-title">Trending in Artisan Marketplace</h2>
    <div class="card-grid">
        <?php foreach ($trendingArtisanProducts as $product): ?>
            <?php mp_render_product_card($product); ?>
        <?php endforeach; ?>
        <?php if (!$trendingArtisanProducts): ?><p>No products yet.</p><?php endif; ?>
    </div>
</section>

<section class="reveal">
    <h2 class="section-title">Trending in Business Shops</h2>
    <div class="card-grid">
        <?php foreach ($trendingBusinessProducts as $product): ?>
            <?php mp_render_product_card($product); ?>
        <?php endforeach; ?>
        <?php if (!$trendingBusinessProducts): ?><p>No products yet.</p><?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/footer.php'; ?>
