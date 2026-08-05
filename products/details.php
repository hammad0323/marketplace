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

        <?php if ((int) $product['stock_quantity'] > 0): ?>
            <p class="stock-note stock-in">✓ In stock (<?= (int) $product['stock_quantity'] ?> available)</p>
        <?php else: ?>
            <p class="stock-note stock-out">Out of stock</p>
        <?php endif; ?>

        <p><?= nl2br(mp_e($product['description'])) ?></p>

        <?php if ((int) $product['stock_quantity'] > 0): ?>
        <form method="post" action="<?= mp_e(ROUTE_CART) ?>add.php" class="add-to-cart-form">
            <?= mp_csrf_field() ?>
            <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
            <input type="hidden" name="redirect_to" value="<?= mp_e(ROUTE_PRODUCTS . 'details.php?slug=' . $product['slug']) ?>">
            <input type="number" name="quantity" value="1" min="1" max="<?= (int) $product['stock_quantity'] ?>">
            <button type="submit" class="btn">Add to Cart</button>
        </form>
        <?php endif; ?>

        <div class="product-detail-meta">
            <a href="<?= mp_e($storeUrl) ?>">Sold by <?= mp_e($vendor['store_name']) ?> &rarr;</a>
            <a href="<?= mp_e($categoryUrl) ?>">Browse more in <?= mp_e($category['name']) ?> &rarr;</a>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
