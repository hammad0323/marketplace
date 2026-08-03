<?php
$vendor = require_vendor();
$products = products_by_vendor($vendor['id']);

$pageTitle = 'My Products';
$theme = 'main';
require __DIR__ . '/../../partials/header.php';
?>

<h1>My Products</h1>
<?php if ($vendor['status'] === 'approved'): ?>
    <a class="btn" href="/vendor/dashboard/products/create">Add Product</a>
<?php endif; ?>

<div class="card-grid" style="margin-top:1.5rem;">
    <?php foreach ($products as $product): ?>
        <?php render_product_card($product); ?>
    <?php endforeach; ?>
    <?php if (!$products): ?><p>No products yet.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
