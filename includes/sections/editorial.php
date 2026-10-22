<?php
if (!$s['image'] && !$s['title']) {
    return;
}
$layout = $s['layout'];
?>
<section <?= section_wrapper_attrs($s, 'editorial', $sid) ?>>
    <?php if ($layout === 'overlay'): ?>
        <div class="uc-editorial-overlay text-<?= e($s['text_align']) ?>">
            <div class="uc-editorial-bg"<?= $s['parallax'] === '1' ? ' data-parallax="0.18"' : '' ?>>
                <picture>
                    <?php if ($s['image_mobile']): ?><source media="(max-width: 767px)" srcset="<?= e(img_url($s['image_mobile'])) ?>"><?php endif; ?>
                    <img src="<?= e(img_url($s['image'])) ?>" alt="<?= e($s['title']) ?>" loading="lazy">
                </picture>
            </div>
            <div class="uc-container uc-editorial-overlay-text"<?= reveal($s) ?>>
                <?php if ($s['eyebrow']): ?><p class="uc-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
                <h2 class="uc-display"><?= e($s['title']) ?></h2>
                <div class="uc-rich"><?= sanitize_html($s['text']) ?></div>
                <?php if ($s['button_text']): ?><a class="uc-btn uc-btn--light" href="<?= e(url($s['button_url'] ?: 'shop')) ?>"><?= e($s['button_text']) ?></a><?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="<?= section_container_class($s) ?>">
            <div class="uc-editorial <?= $layout === 'image-right' ? 'is-reversed' : '' ?>">
                <div class="uc-editorial-media"<?= reveal($s) ?>>
                    <div class="uc-editorial-main"<?= $s['parallax'] === '1' ? ' data-parallax="0.08"' : '' ?>>
                        <picture>
                            <?php if ($s['image_mobile']): ?><source media="(max-width: 767px)" srcset="<?= e(img_url($s['image_mobile'])) ?>"><?php endif; ?>
                            <img src="<?= e(img_url($s['image'])) ?>" alt="<?= e($s['title']) ?>" loading="lazy">
                        </picture>
                    </div>
                    <?php if ($s['image_secondary']): ?>
                        <div class="uc-editorial-detail"<?= $s['parallax'] === '1' ? ' data-parallax="-0.06"' : '' ?>><img src="<?= e(img_sized($s['image_secondary'], 700)) ?>" alt="" loading="lazy"></div>
                    <?php endif; ?>
                </div>
                <div class="uc-editorial-text text-<?= e($s['text_align']) ?>"<?= reveal($s, 150) ?>>
                    <?php if ($s['eyebrow']): ?><p class="uc-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
                    <h2 class="uc-display"><?= e($s['title']) ?></h2>
                    <span class="uc-rule"></span>
                    <div class="uc-rich"><?= sanitize_html($s['text']) ?></div>
                    <?php if ($s['button_text']): ?><a class="uc-btn" href="<?= e(url($s['button_url'] ?: 'shop')) ?>"><?= e($s['button_text']) ?></a><?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
