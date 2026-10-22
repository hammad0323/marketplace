<?php
$imgs = array_values(array_filter([$s['image_1'], $s['image_2'], $s['image_3'], $s['image_4'], $s['image_5'], $s['image_6']]));
if (!$imgs) {
    return;
}
$link = $s['profile_url'] ?: setting('social_instagram');
?>
<section <?= section_wrapper_attrs($s, 'instagram', $sid) ?>>
    <div class="<?= section_container_class($s) ?>">
        <header class="uc-section-head text-center"<?= reveal($s) ?>>
            <?php if ($s['eyebrow']): ?><p class="uc-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
            <?php if ($s['title']): ?><h2 class="uc-section-title"><?= e($s['title']) ?></h2><?php endif; ?>
            <?php if ($s['handle'] && $link): ?><a class="uc-link-arrow" href="<?= e($link) ?>" target="_blank" rel="noopener"><i class="bi bi-instagram"></i> <?= e($s['handle']) ?></a><?php endif; ?>
        </header>
        <div class="uc-insta">
            <?php foreach ($imgs as $i => $im): ?>
                <a href="<?= e($link ?: '#') ?>" target="_blank" rel="noopener" class="uc-insta-item"<?= reveal($s, $i * 60) ?> aria-label="View on Instagram">
                    <img src="<?= e(img_sized($im, 600)) ?>" alt="" loading="lazy" decoding="async"><i class="bi bi-instagram"></i>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
