<h1>My Products</h1>
<?php if ($vendor['status'] === 'approved'): ?>
    <a class="btn" href="/vendor/dashboard/products/create">Add Product</a>
<?php endif; ?>

<div class="card-grid" style="margin-top:1.5rem;">
    <?php foreach ($products as $product): ?>
        <?php View::partial('partials/product_card', ['product' => $product]); ?>
    <?php endforeach; ?>
    <?php if (!$products): ?><p>No products yet.</p><?php endif; ?>
</div>
