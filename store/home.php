<?php
require __DIR__ . '/../config/config.php';

$artisanType = mp_find_marketplace_type_by_slug('artisan');
$businessType = mp_find_marketplace_type_by_slug('business');
$officialType = mp_find_marketplace_type_by_slug('official');

$featuredArtisans = $artisanType ? mp_approved_vendors_by_marketplace($artisanType['id'], 6) : [];
$featuredBusinesses = $businessType ? mp_approved_vendors_by_marketplace($businessType['id'], 6) : [];
$officialStore = $officialType ? (mp_approved_vendors_by_marketplace($officialType['id'], 1)[0] ?? null) : null;
$trendingArtisanProducts = $artisanType ? mp_published_products($artisanType['id'], 8) : [];
$trendingBusinessProducts = $businessType ? mp_published_products($businessType['id'], 8) : [];

$totalVendors = mp_count_approved_vendors();
$totalProducts = mp_count_published_products();
$totalCategories = ($artisanType ? mp_count_active_categories($artisanType['id']) : 0)
    + ($businessType ? mp_count_active_categories($businessType['id']) : 0);

$pageTitle = 'Discover Handmade Artisans & Trusted Business Shops';
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>

<section class="parallax-hero home-hero">
    <div class="parallax-hero-bg"></div>
    <div class="parallax-layer" data-speed="0.15" style="top:12%; left:8%; width:120px; height:120px;"><div class="floating-blob"></div></div>
    <div class="parallax-layer" data-speed="0.3" style="top:58%; right:10%; width:170px; height:170px;"><div class="floating-blob floating-blob-alt"></div></div>
    <div class="parallax-layer" data-speed="0.45" style="bottom:6%; left:20%; width:80px; height:80px;"><div class="floating-blob"></div></div>
    <div class="home-hero-content reveal reveal-blur">
        <span class="section-eyebrow">Two Marketplaces. One Platform.</span>
        <h1>Handmade Treasures &amp; Trusted Retail, All in One Place</h1>
        <p>Discover one-of-a-kind creations from independent artisans, or shop everyday
           essentials from verified business owners — start exploring below.</p>
        <div style="display:flex; gap:1rem; justify-content:center; flex-wrap:wrap;">
            <a class="btn btn-accent btn-magnetic" href="<?= mp_e(ROUTE_ARTISAN) ?>index.php">Explore Artisan Marketplace</a>
            <a class="btn btn-secondary btn-magnetic" href="<?= mp_e(ROUTE_BUSINESS) ?>index.php">Explore Business Shops</a>
        </div>

        <div class="stats-row">
            <div class="stat-item">
                <strong class="stat-counter" data-target="<?= (int) $totalVendors ?>">0</strong>
                <span class="stat-label">Approved Sellers</span>
            </div>
            <div class="stat-item">
                <strong class="stat-counter" data-target="<?= (int) $totalProducts ?>">0</strong>
                <span class="stat-label">Products Listed</span>
            </div>
            <div class="stat-item">
                <strong class="stat-counter" data-target="<?= (int) $totalCategories ?>">0</strong>
                <span class="stat-label">Categories</span>
            </div>
        </div>
    </div>
</section>

<div class="marquee" aria-hidden="true">
    <div class="marquee-track">
        <?php for ($i = 0; $i < 2; $i++): ?>
            <span>Handmade Pottery</span>
            <span>Verified Business Shops</span>
            <span>Resin Art</span>
            <span>Electronics</span>
            <span>Official Store Curated Picks</span>
            <span>Leather Goods</span>
            <span>Home Appliances</span>
            <span>Traditional Arts</span>
        <?php endfor; ?>
    </div>
</div>

<?php if ($officialStore): ?>
<section class="official-strip reveal reveal-scale">
    <span class="badge">⭐ Official Store</span>
    <p style="margin:0;"><a href="<?= mp_e(ROUTE_OFFICIAL_STORE) ?>index.php"><strong><?= mp_e($officialStore['store_name']) ?></strong></a> — verified and curated directly by the platform.</p>
</section>
<?php endif; ?>

<section>
    <h2 class="section-title reveal">Featured Artisans</h2>
    <div class="card-grid">
        <?php foreach ($featuredArtisans as $i => $vendor): ?>
            <div class="vendor-card reveal reveal-scale reveal-delay-<?= ($i % 4) + 1 ?>">
                <div class="vendor-card-body">
                    <strong><a href="<?= mp_e(ROUTE_ARTISAN) ?>store.php?slug=<?= mp_e($vendor['slug']) ?>"><?= mp_e($vendor['store_name']) ?></a></strong>
                    <div><span class="badge">🏺 Handmade</span></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$featuredArtisans): ?><p>No approved artisans yet.</p><?php endif; ?>
    </div>
</section>

<section>
    <h2 class="section-title reveal">Featured Business Shops</h2>
    <div class="card-grid">
        <?php foreach ($featuredBusinesses as $i => $vendor): ?>
            <div class="vendor-card reveal reveal-scale reveal-delay-<?= ($i % 4) + 1 ?>">
                <div class="vendor-card-body">
                    <strong><a href="<?= mp_e(ROUTE_BUSINESS) ?>store.php?slug=<?= mp_e($vendor['slug']) ?>"><?= mp_e($vendor['store_name']) ?></a></strong>
                    <div><span class="badge">🏪 Business Shop</span></div>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (!$featuredBusinesses): ?><p>No approved business shops yet.</p><?php endif; ?>
    </div>
</section>

<section>
    <h2 class="section-title reveal">Trending in Artisan Marketplace</h2>
    <div class="card-grid">
        <?php foreach ($trendingArtisanProducts as $i => $product): ?>
            <div class="reveal reveal-scale reveal-delay-<?= ($i % 4) + 1 ?>"><?php mp_render_product_card($product); ?></div>
        <?php endforeach; ?>
        <?php if (!$trendingArtisanProducts): ?><p>No products yet.</p><?php endif; ?>
    </div>
</section>

<section>
    <h2 class="section-title reveal">Trending in Business Shops</h2>
    <div class="card-grid">
        <?php foreach ($trendingBusinessProducts as $i => $product): ?>
            <div class="reveal reveal-scale reveal-delay-<?= ($i % 4) + 1 ?>"><?php mp_render_product_card($product); ?></div>
        <?php endforeach; ?>
        <?php if (!$trendingBusinessProducts): ?><p>No products yet.</p><?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../templates/footer.php'; ?>
