<?php
$type = find_marketplace_type_by_slug('business');
if (!$type) {
    http_response_code(500);
    exit('Business marketplace type is not seeded. Run database/migrate.php --seed.');
}

$category = find_category_by_slug_in_marketplace($slug, $type['id']);
if (!$category) {
    http_response_code(404);
    require __DIR__ . '/../../partials/404.php';
    return;
}

$products = products_by_category($category['id']);

$pageTitle = $category['name'] . ' — Business Shops';
$theme = 'business';
require __DIR__ . '/../../partials/header.php';
?>

<h1><?= e($category['name']) ?></h1>
<div class="card-grid">
    <?php foreach ($products as $product): ?>
        <?php render_product_card($product); ?>
    <?php endforeach; ?>
    <?php if (!$products): ?><p>No products in this category yet.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
