<?php
$limit = max(1, min(30, (int) ($s['limit'] ?: 10)));
$products = $s['mode'] === 'manual' && $s['product_ids'] ? products_by_ids($s['product_ids'], $limit) : new_arrivals($limit);
if (!$products) {
    return;
}
?>
<section <?= section_wrapper_attrs($s, 'new_arrivals', $sid) ?>>
    <div class="<?= section_container_class($s) ?>">
        <div class="uc-head-row">
            <?= section_heading($s, 'left') ?>
            <?php if ($s['button_text']): ?><a class="uc-link-arrow d-none d-md-inline-flex" href="<?= e(url($s['button_url'] ?: 'new-arrivals')) ?>"><?= e($s['button_text']) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
        </div>
        <div<?= reveal($s) ?>><?php partial('components/product-carousel', ['products' => $products, 'autoplay' => $s['autoplay'] === '1']); ?></div>
        <?php if ($s['button_text']): ?><div class="text-center mt-4 d-md-none"><a class="uc-btn uc-btn--outline" href="<?= e(url($s['button_url'] ?: 'new-arrivals')) ?>"><?= e($s['button_text']) ?></a></div><?php endif; ?>
    </div>
</section>
