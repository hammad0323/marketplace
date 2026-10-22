<?php
$limit = max(1, min(30, (int) ($s['limit'] ?: 10)));
$products = $s['mode'] === 'manual' && $s['product_ids'] ? products_by_ids($s['product_ids'], $limit) : best_sellers($limit, $s['mode']);
if (!$products) {
    return;
}
?>
<section <?= section_wrapper_attrs($s, 'best_sellers', $sid) ?>>
    <div class="<?= section_container_class($s) ?>">
        <div class="uc-head-row">
            <?= section_heading($s, 'left') ?>
            <?php if ($s['button_text']): ?><a class="uc-link-arrow d-none d-md-inline-flex" href="<?= e(url($s['button_url'] ?: 'shop')) ?>"><?= e($s['button_text']) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
        </div>
        <div<?= reveal($s) ?>><?php partial('components/product-carousel', ['products' => $products]); ?></div>
    </div>
</section>
