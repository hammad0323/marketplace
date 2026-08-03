<h1><?= e($category['name']) ?></h1>
<div class="card-grid">
    <?php foreach ($products as $product): ?>
        <?php View::partial('partials/product_card', ['product' => $product]); ?>
    <?php endforeach; ?>
    <?php if (!$products): ?><p>No products in this category yet.</p><?php endif; ?>
</div>
