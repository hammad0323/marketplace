<?php
/** Genuine, approved product reviews that an admin has chosen to feature. */
$limit = max(1, min(12, (int) ($s['limit'] ?: 6)));
$reviews = db_all(
    "SELECT r.*, p.name AS product_name, p.slug AS product_slug FROM reviews r JOIN products p ON p.id = r.product_id
     WHERE r.status = 'approved' AND r.is_featured = 1 ORDER BY r.created_at DESC LIMIT " . $limit
);
if (!$reviews) {
    return;
}
?>
<section <?= section_wrapper_attrs($s, 'testimonials', $sid) ?>>
    <div class="<?= section_container_class($s) ?>">
        <?= section_heading($s, 'center') ?>
        <div class="uc-carousel uc-reviews" data-carousel>
            <div class="uc-carousel-track" data-carousel-track>
                <?php foreach ($reviews as $r): ?>
                    <figure class="uc-quote uc-carousel-slide">
                        <?= render_stars((float) $r['rating']) ?>
                        <?php if ($r['title']): ?><p class="uc-quote-title"><?= e($r['title']) ?></p><?php endif; ?>
                        <blockquote><?= e(str_limit($r['body'], 260)) ?></blockquote>
                        <figcaption>
                            <strong><?= e($r['author_name']) ?></strong><?php if ($r['author_city']): ?>, <?= e($r['author_city']) ?><?php endif; ?>
                            <?php if ((int) $r['is_verified_purchase']): ?><span class="uc-verified"><i class="bi bi-patch-check-fill"></i> Verified buyer</span><?php endif; ?>
                            <a href="<?= e(url('product/' . $r['product_slug'])) ?>"><?= e($r['product_name']) ?></a>
                        </figcaption>
                    </figure>
                <?php endforeach; ?>
            </div>
            <button class="uc-carousel-nav is-prev" type="button" data-carousel-prev aria-label="Previous"><i class="bi bi-chevron-left"></i></button>
            <button class="uc-carousel-nav is-next" type="button" data-carousel-next aria-label="Next"><i class="bi bi-chevron-right"></i></button>
        </div>
    </div>
</section>
