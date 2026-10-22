<?php
if (!$s['image']) {
    return;
}
$h = max(30, min(100, (int) ($s['height'] ?: 70)));
$op = max(0, min(90, (int) $s['overlay_opacity'])) / 100;
?>
<section <?= section_wrapper_attrs($s, 'promo_banner', $sid) ?>>
    <div class="<?= section_container_class($s) ?>">
        <div class="uc-promo text-<?= e($s['text_align']) ?>" style="--promo-h:<?= $h ?>vh;--promo-op:<?= $op ?>">
            <div class="uc-promo-bg"<?= $s['parallax'] === '1' ? ' data-parallax="0.22"' : '' ?>>
                <picture>
                    <?php if ($s['image_mobile']): ?><source media="(max-width: 767px)" srcset="<?= e(img_url($s['image_mobile'])) ?>"><?php endif; ?>
                    <img src="<?= e(img_url($s['image'])) ?>" alt="<?= e($s['title']) ?>" loading="lazy">
                </picture>
            </div>
            <div class="uc-promo-content uc-container"<?= reveal($s) ?>>
                <?php if ($s['eyebrow']): ?><p class="uc-eyebrow is-light"><?= e($s['eyebrow']) ?></p><?php endif; ?>
                <?php if ($s['title']): ?><h2 class="uc-display"><?= e($s['title']) ?></h2><?php endif; ?>
                <?php if ($s['text']): ?><p class="uc-promo-text"><?= e($s['text']) ?></p><?php endif; ?>
                <?php if ($s['button_text']): ?><a class="uc-btn uc-btn--light" href="<?= e(url($s['button_url'] ?: 'shop')) ?>"><?= e($s['button_text']) ?></a><?php endif; ?>
            </div>
        </div>
    </div>
</section>
