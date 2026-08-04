<?php
$images = json_decode($product['images'] ?? '[]', true) ?: [];
$thumb = $images[0] ?? null;
?>
<div class="product-card">
    <img src="<?= mp_e($thumb ?? '/assets/img/placeholder.svg') ?>" alt="<?= mp_e($product['title']) ?>">
    <div class="product-card-body">
        <?php if (!empty($product['badge_label'])): ?>
            <span class="badge"><?= mp_e($product['badge_label']) ?></span>
        <?php endif; ?>
        <div><a href="/product/<?= mp_e($product['slug']) ?>"><strong><?= mp_e($product['title']) ?></strong></a></div>
        <?php if (!empty($product['store_name'])): ?>
            <div style="color:#777;font-size:0.9rem;"><?= mp_e($product['store_name']) ?></div>
        <?php endif; ?>
        <div>$<?= number_format((float) $product['price'], 2) ?></div>
    </div>
</div>
