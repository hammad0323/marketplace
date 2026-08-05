<?php
require __DIR__ . '/../config/config.php';

$vendor = mp_find_vendor_by_slug('official-store');
if (!$vendor) {
    require __DIR__ . '/../404.php';
    return;
}

$profile = mp_find_business_profile($vendor['id']) ?? [];
$products = mp_products_by_vendor($vendor['id']);

$pageTitle = $vendor['store_name'] . ' — Official Store';
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>

<section class="parallax-hero official-hero">
    <div class="parallax-hero-bg"></div>
    <div class="parallax-layer" data-speed="0.2" style="top:20%; left:10%; width:70px; height:70px;"><div class="floating-blob"></div></div>
    <div class="parallax-layer" data-speed="0.3" style="bottom:12%; right:14%; width:100px; height:100px;"><div class="floating-blob floating-blob-alt"></div></div>
    <div class="reveal reveal-blur">
        <span class="badge" style="background:rgba(255,255,255,.6);">⭐ Official Store</span>
        <h1><?= mp_e($vendor['store_name']) ?></h1>
        <?php if (!empty($profile['business_info'])): ?>
            <p><?= nl2br(mp_e($profile['business_info'])) ?></p>
        <?php endif; ?>
    </div>
</section>

<h2 class="section-title reveal">Products</h2>
<div class="card-grid">
    <?php foreach ($products as $i => $product): ?>
        <div class="reveal reveal-scale reveal-delay-<?= ($i % 4) + 1 ?>"><?php mp_render_product_card($product); ?></div>
    <?php endforeach; ?>
    <?php if (!$products): ?><p>No products yet.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
