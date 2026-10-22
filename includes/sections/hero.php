<?php
/** Hero banner carousel. Slides come from banner_slides. */
$preview = !empty($GLOBALS['UC_PREVIEW']);
$slides = db_all('SELECT * FROM banner_slides' . ($preview ? '' : ' WHERE is_active = 1') . ' ORDER BY sort_order, id');
if (!$slides) {
    return;
}
$posMap = ['top' => 'flex-start', 'middle' => 'center', 'bottom' => 'flex-end'];
?>
<section class="uc-hero is-<?= e($s['transition']) ?>" id="section-<?= (int) $sid ?>" data-hero
         data-autoplay="<?= $s['autoplay'] === '1' ? max(2500, (int) $s['interval']) : 0 ?>"<?= $s['parallax'] === '1' ? ' data-parallax-hero' : '' ?>>
    <?php foreach ($slides as $i => $sl):
        $hD = max(30, min(100, (int) $sl['height_desktop']));
        $hM = max(30, min(100, (int) $sl['height_mobile']));
        $overlay = safe_color($sl['overlay_color'], '#000000');
        $op = max(0, min(90, (int) $sl['overlay_opacity'])) / 100;
        $style = '--hero-h:' . $hD . 'vh;--hero-hm:' . $hM . 'vh;--hero-color:' . safe_color($sl['text_color'], '#FFFFFF')
            . ';--hero-overlay:' . $overlay . ';--hero-opacity:' . $op . ';--hero-pos:' . preg_replace('/[^a-z0-9% ]/i', '', $sl['image_position'])
            . ';--hero-size:' . ($sl['background_size'] === 'contain' ? 'contain' : 'cover') . ';--hero-justify:' . ($posMap[$sl['text_position']] ?? 'center');
    ?>
    <div class="uc-hero-slide align-<?= e($sl['text_align']) ?><?= $i === 0 ? ' is-active' : '' ?><?= !(int) $sl['is_active'] ? ' is-inactive-preview' : '' ?>" style="<?= e($style) ?>" data-hero-slide aria-roledescription="slide" aria-label="<?= ($i + 1) . ' of ' . count($slides) ?>">
        <div class="uc-hero-media" data-parallax-layer>
            <picture>
                <?php if ($sl['image_mobile']): ?><source media="(max-width: 767px)" srcset="<?= e(img_url($sl['image_mobile'])) ?>"><?php endif; ?>
                <img src="<?= e(img_url($sl['image_desktop'])) ?>" alt="<?= e($sl['heading'] ?: store_name()) ?>"<?= $i === 0 ? ' fetchpriority="high"' : ' loading="lazy"' ?> decoding="async">
            </picture>
        </div>
        <div class="uc-hero-overlay"></div>
        <div class="uc-hero-content uc-container">
            <div class="uc-hero-text">
                <?php if ($sl['eyebrow']): ?><p class="uc-hero-eyebrow"><?= e($sl['eyebrow']) ?></p><?php endif; ?>
                <?php if ($sl['heading']): ?><?= $i === 0 ? '<h1' : '<h2' ?> class="uc-hero-title"><?= e($sl['heading']) ?><?= $i === 0 ? '</h1>' : '</h2>' ?><?php endif; ?>
                <?php if ($sl['subheading']): ?><p class="uc-hero-sub"><?= e($sl['subheading']) ?></p><?php endif; ?>
                <?php if ($sl['description']): ?><p class="uc-hero-desc"><?= e($sl['description']) ?></p><?php endif; ?>
                <?php if ($sl['btn1_text'] || $sl['btn2_text']): ?>
                <div class="uc-hero-btns">
                    <?php if ($sl['btn1_text']): ?><a class="uc-btn uc-btn--light" href="<?= e(url($sl['btn1_url'] ?: 'shop')) ?>"><?= e($sl['btn1_text']) ?></a><?php endif; ?>
                    <?php if ($sl['btn2_text']): ?><a class="uc-btn uc-btn--outline-light" href="<?= e(url($sl['btn2_url'] ?: 'shop')) ?>"><?= e($sl['btn2_text']) ?></a><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (count($slides) > 1): ?>
        <?php if ($s['show_arrows'] === '1'): ?>
            <button class="uc-hero-arrow is-prev" type="button" data-hero-prev aria-label="Previous slide"><i class="bi bi-arrow-left"></i></button>
            <button class="uc-hero-arrow is-next" type="button" data-hero-next aria-label="Next slide"><i class="bi bi-arrow-right"></i></button>
        <?php endif; ?>
        <?php if ($s['show_dots'] === '1'): ?>
            <div class="uc-hero-dots" role="tablist">
                <?php foreach ($slides as $i => $sl): ?>
                    <button type="button" class="<?= $i === 0 ? 'is-active' : '' ?>" data-hero-dot="<?= $i ?>" aria-label="Go to slide <?= $i + 1 ?>"><span></span></button>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <a href="#section-<?= (int) $sid ?>-after" class="uc-hero-scroll" aria-label="Scroll down"><span></span></a>
</section>
<div id="section-<?= (int) $sid ?>-after"></div>
