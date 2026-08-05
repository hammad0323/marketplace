<?php
require __DIR__ . '/../config/config.php';

$slug = $_GET['slug'] ?? '';
$product = mp_find_product_by_slug($slug);
if (!$product || $product['status'] !== 'published') {
    require __DIR__ . '/../404.php';
    return;
}

$vendor = mp_find_vendor($product['vendor_id']);
$marketplaceType = mp_find_marketplace_type($product['marketplace_type_id']);
$category = mp_find_category($product['category_id']);

$theme = $marketplaceType['slug'] === 'artisan' ? 'artisan'
    : ($marketplaceType['slug'] === 'business' ? 'business' : 'main');

$images = json_decode($product['images'] ?? '[]', true) ?: [];
$mainImage = $images[0] ?? ROUTE_ASSETS . 'images/placeholder.svg';
$storeUrl = $marketplaceType['slug'] === 'artisan' ? ROUTE_ARTISAN . "store.php?slug={$vendor['slug']}"
    : ($marketplaceType['slug'] === 'business' ? ROUTE_BUSINESS . "store.php?slug={$vendor['slug']}" : ROUTE_OFFICIAL_STORE . 'index.php');
$categoryUrl = ($marketplaceType['slug'] === 'business' ? ROUTE_BUSINESS : ROUTE_ARTISAN) . 'category.php?slug=' . $category['slug'];

$pageTitle = $product['title'];
require __DIR__ . '/../templates/header.php';
?>

<div class="product-detail reveal">
    <div>
        <img class="product-detail-main-img" id="product-main-img" src="<?= mp_e($mainImage) ?>" alt="<?= mp_e($product['title']) ?>">
        <?php if (count($images) > 1): ?>
        <div class="product-detail-thumbs">
            <?php foreach ($images as $img): ?>
                <img src="<?= mp_e($img) ?>" alt="" onclick="document.getElementById('product-main-img').src=this.src">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div>
        <span class="badge"><?= mp_e(mp_marketplace_badge($marketplaceType['slug'])) ?></span>
        <h1><?= mp_e($product['title']) ?></h1>
        <p class="product-detail-price">$<?= number_format((float) $product['price'], 2) ?></p>
        <p><?= nl2br(mp_e($product['description'])) ?></p>
        <div class="product-detail-meta">
            <a href="<?= mp_e($storeUrl) ?>">Sold by <?= mp_e($vendor['store_name']) ?> &rarr;</a>
            <a href="<?= mp_e($categoryUrl) ?>">Browse more in <?= mp_e($category['name']) ?> &rarr;</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
