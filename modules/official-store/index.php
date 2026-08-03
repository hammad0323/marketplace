<?php
$vendor = find_vendor_by_slug('official-store');
if (!$vendor) {
    http_response_code(404);
    require __DIR__ . '/../../partials/404.php';
    return;
}

$profile = find_business_profile($vendor['id']) ?? [];
$products = products_by_vendor($vendor['id']);

$pageTitle = $vendor['store_name'] . ' — Official Store';
$theme = 'main';
require __DIR__ . '/../../partials/header.php';
?>

<section class="content-panel" style="text-align:center;">
    <span class="badge">⭐ Official Store</span>
    <h1><?= e($vendor['store_name']) ?></h1>
    <?php if (!empty($profile['business_info'])): ?>
        <p><?= nl2br(e($profile['business_info'])) ?></p>
    <?php endif; ?>
</section>

<h2>Products</h2>
<div class="card-grid">
    <?php foreach ($products as $product): ?>
        <?php render_product_card($product); ?>
    <?php endforeach; ?>
    <?php if (!$products): ?><p>No products yet.</p><?php endif; ?>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
