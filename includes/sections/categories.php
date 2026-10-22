<?php
$limit = max(1, min(24, (int) ($s['limit'] ?: 8)));
if ($s['source'] === 'manual' && $s['category_ids']) {
    $all = categories_all();
    $cats = [];
    foreach ($s['category_ids'] as $id) {
        if (isset($all[(int) $id])) {
            $cats[] = $all[(int) $id];
        }
    }
} else {
    $cats = array_values(array_filter(categories_all(), fn($c) => (int) $c['show_on_home']));
}
$cats = array_slice($cats, 0, $limit);
if (!$cats) {
    return;
}
$layout = $s['layout'];
?>
<section <?= section_wrapper_attrs($s, 'categories', $sid) ?>>
    <div class="<?= section_container_class($s) ?>">
        <?= section_heading($s, 'center') ?>
        <div class="uc-cats uc-cats--<?= e($layout) ?><?= $layout === 'carousel' ? ' uc-carousel' : '' ?>"<?= $layout === 'carousel' ? ' data-carousel' : '' ?>>
            <div class="uc-cats-track"<?= $layout === 'carousel' ? ' data-carousel-track' : '' ?>>
            <?php foreach ($cats as $i => $c): ?>
                <a class="uc-cat-card" href="<?= e(category_url($c)) ?>"<?= reveal($s, ($i % 4) * 90) ?>>
                    <span class="uc-cat-img"><img src="<?= e(img_sized($c['image'], $layout === 'editorial' && $i === 0 ? 1100 : 700)) ?>" alt="<?= e($c['name']) ?>" loading="lazy" decoding="async"></span>
                    <span class="uc-cat-label">
                        <span class="uc-cat-name"><?= e($c['name']) ?></span>
                        <span class="uc-cat-cta">Shop now <i class="bi bi-arrow-right"></i></span>
                    </span>
                </a>
            <?php endforeach; ?>
            </div>
            <?php if ($layout === 'carousel'): ?>
                <button class="uc-carousel-nav is-prev" type="button" data-carousel-prev aria-label="Previous"><i class="bi bi-chevron-left"></i></button>
                <button class="uc-carousel-nav is-next" type="button" data-carousel-next aria-label="Next"><i class="bi bi-chevron-right"></i></button>
            <?php endif; ?>
        </div>
        <?php if ($s['button_text']): ?>
            <div class="text-center mt-5"<?= reveal($s) ?>><a class="uc-btn uc-btn--outline" href="<?= e(url($s['button_url'] ?: 'shop')) ?>"><?= e($s['button_text']) ?></a></div>
        <?php endif; ?>
    </div>
</section>
