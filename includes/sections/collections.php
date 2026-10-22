<?php
$limit = max(1, min(20, (int) ($s['limit'] ?: 6)));
$items = $s['collection_ids'] ? collections_list(false, $limit, $s['collection_ids']) : collections_list(true, $limit);
if (!$items) {
    return;
}
$layout = $s['layout'];
?>
<section <?= section_wrapper_attrs($s, 'collections', $sid) ?>>
    <div class="<?= section_container_class($s) ?>">
        <?= section_heading($s, 'center') ?>
        <div class="uc-colls uc-colls--<?= e($layout) ?><?= $layout === 'carousel' ? ' uc-carousel' : '' ?>"<?= $layout === 'carousel' ? ' data-carousel' : '' ?>>
            <div class="uc-colls-track"<?= $layout === 'carousel' ? ' data-carousel-track' : '' ?>>
                <?php foreach ($items as $i => $c): ?>
                    <a class="uc-coll-card" href="<?= e(collection_url($c)) ?>"<?= reveal($s, ($i % 3) * 100) ?>>
                        <img src="<?= e(img_sized($c['image'], 900)) ?>" alt="<?= e($c['name']) ?>" loading="lazy" decoding="async">
                        <span class="uc-coll-info">
                            <span class="uc-coll-index"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span>
                            <span class="uc-coll-name"><?= e($c['name']) ?></span>
                            <?php if ($c['description']): ?><span class="uc-coll-desc"><?= e(str_limit($c['description'], 90)) ?></span><?php endif; ?>
                            <span class="uc-coll-cta">Explore <i class="bi bi-arrow-right"></i></span>
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
            <div class="text-center mt-5"><a class="uc-btn uc-btn--outline" href="<?= e(url($s['button_url'] ?: 'collections')) ?>"><?= e($s['button_text']) ?></a></div>
        <?php endif; ?>
    </div>
</section>
