<?php
$product = find_product_by_slug($slug);
if (!$product || $product['status'] !== 'published') {
    http_response_code(404);
    require __DIR__ . '/../../partials/404.php';
    return;
}

$vendor = find_vendor($product['vendor_id']);
$marketplaceType = find_marketplace_type($product['marketplace_type_id']);
$category = find_category($product['category_id']);

$theme = $marketplaceType['slug'] === 'artisan' ? 'artisan'
    : ($marketplaceType['slug'] === 'business' ? 'business' : 'main');

$images = json_decode($product['images'] ?? '[]', true) ?: [];
$storeUrl = $marketplaceType['slug'] === 'artisan' ? "/artisan/{$vendor['slug']}"
    : ($marketplaceType['slug'] === 'business' ? "/business/{$vendor['slug']}" : '/store/official-store');
$categoryUrl = ($marketplaceType['slug'] === 'business' ? '/business/category/' : '/artisan/category/') . $category['slug'];

$pageTitle = $product['title'];
require __DIR__ . '/../../partials/header.php';
?>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:2rem;">
    <div>
        <img src="<?= e($images[0] ?? '/assets/img/placeholder.svg') ?>" alt="<?= e($product['title']) ?>" style="border-radius:10px;">
        <?php if (count($images) > 1): ?>
        <div style="display:flex; gap:0.5rem; margin-top:0.5rem;">
            <?php foreach (array_slice($images, 1) as $img): ?>
                <img src="<?= e($img) ?>" style="width:70px;height:70px;object-fit:cover;border-radius:6px;">
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <div>
        <span class="badge"><?= e(marketplace_badge($marketplaceType['slug'])) ?></span>
        <h1><?= e($product['title']) ?></h1>
        <p style="font-size:1.4rem;font-weight:700;">$<?= number_format((float) $product['price'], 2) ?></p>
        <p><?= nl2br(e($product['description'])) ?></p>
        <p><a href="<?= e($storeUrl) ?>">Sold by <?= e($vendor['store_name']) ?></a></p>
        <p><a href="<?= e($categoryUrl) ?>">Browse more in <?= e($category['name']) ?></a></p>
    </div>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
