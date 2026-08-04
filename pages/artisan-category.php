<?php
$type = mp_find_marketplace_type_by_slug('artisan');
if (!$type) {
    http_response_code(500);
    exit('Artisan marketplace type is not seeded. Run database/migrate.php --seed.');
}

$category = mp_find_category_by_slug_in_marketplace($slug, $type['id']);
if (!$category) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    return;
}

$products = mp_products_by_category($category['id']);

$pageTitle = $category['name'] . ' — Artisan Marketplace';
$theme = 'artisan';
require __DIR__ . '/../includes/header.php';
?>

<h1><?= mp_e($category['name']) ?></h1>
<div class="card-grid">
    <?php foreach ($products as $product): ?>
        <?php mp_render_product_card($product); ?>
    <?php endforeach; ?>
    <?php if (!$products): ?><p>No products in this category yet.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
