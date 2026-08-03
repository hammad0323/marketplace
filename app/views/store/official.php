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
        <?php View::partial('partials/product_card', ['product' => $product]); ?>
    <?php endforeach; ?>
    <?php if (!$products): ?><p>No products yet.</p><?php endif; ?>
</div>
