<?php
$slides = active_slides();
if (!$slides) {
    return;
}
$posMap = ['top' => 'flex-start', 'middle' => 'center', 'bottom' => 'flex-end'];
?>
<section class="hero<?= ($s['transition'] ?? 'fade') === 'slide' ? ' hero--slide' : ' hero--fade' ?><?= !empty($s['ken_burns']) ? ' hero--kenburns' : '' ?>"
         data-hero data-autoplay="<?= !empty($s['autoplay']) ? '1' : '0' ?>" data-interval="<?= max(2500, (int) $s['interval']) ?>"
         style="--hero-speed: <?= max(200, min(4000, (int) $s['transition_speed'])) ?>ms" aria-roledescription="carousel" aria-label="Featured">
  <div class="hero__slides">
    <?php foreach ($slides as $i => $sl):
        $style = sprintf('--h-desktop:%dpx;--h-mobile:%dpx;--overlay:%s;--overlay-opacity:%s;--text:%s;--accent:%s;--btn-bg:%s;--btn-text:%s;--bg-pos:%s;--bg-size:%s;--justify:%s;',
            max(320, min(1200, (int) $sl['height_desktop'])), max(320, min(1200, (int) $sl['height_mobile'])),
            valid_hex($sl['overlay_color']) ? $sl['overlay_color'] : '#0A1426', max(0, min(90, (int) $sl['overlay_opacity'])) / 100,
            valid_hex($sl['text_color']) ? $sl['text_color'] : '#FFFFFF', valid_hex($sl['accent_color']) ? $sl['accent_color'] : '#B99A5B',
            valid_hex($sl['btn_bg_color']) ? $sl['btn_bg_color'] : '#214E9B', valid_hex($sl['btn_text_color']) ? $sl['btn_text_color'] : '#FFFFFF',
            preg_match('/^[a-z0-9% .]+$/i', $sl['bg_position']) ? $sl['bg_position'] : 'center center', $sl['bg_size'],
            $posMap[$sl['content_position']] ?? 'center');
    ?>
    <div class="hero__slide<?= $i === 0 ? ' is-active' : '' ?> align-<?= e($sl['text_align']) ?>" style="<?= e($style) ?>" role="group" aria-roledescription="slide" aria-label="<?= $i + 1 ?> of <?= count($slides) ?>">
      <picture class="hero__media" data-parallax="0.25">
        <?php if ($sl['image_mobile']): ?><source media="(max-width: 767px)" srcset="<?= e(media_url($sl['image_mobile'])) ?>"><?php endif; ?>
        <img src="<?= e(media_url($sl['image_desktop'])) ?>" alt="<?= e($sl['image_alt'] ?: $sl['title']) ?>" <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> width="2400" height="1200">
      </picture>
      <div class="hero__overlay"></div>
      <div class="container container--wide hero__content">
        <div class="hero__text">
          <?php if ($sl['subtitle']): ?><p class="hero__eyebrow"><?= e($sl['subtitle']) ?></p><?php endif; ?>
          <<?= $i === 0 ? 'h1' : 'h2' ?> class="hero__title"><?= e($sl['title']) ?></<?= $i === 0 ? 'h1' : 'h2' ?>>
          <?php if ($sl['description']): ?><p class="hero__desc"><?= e($sl['description']) ?></p><?php endif; ?>
          <div class="hero__ctas">
            <?php if ($sl['primary_btn_text']): ?><a class="btn-lux btn-lux--hero" href="<?= e(safe_link($sl['primary_btn_url'], path_url('shop'))) ?>"><?= e($sl['primary_btn_text']) ?></a><?php endif; ?>
            <?php if ($sl['secondary_btn_text']): ?><a class="btn-ghost" href="<?= e(safe_link($sl['secondary_btn_url'], path_url('shop'))) ?>"><?= e($sl['secondary_btn_text']) ?> <i class="bi bi-arrow-right"></i></a><?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php if (count($slides) > 1): ?>
    <?php if (!empty($s['show_arrows'])): ?>
      <button class="hero__arrow hero__arrow--prev" type="button" data-hero-prev aria-label="Previous slide"><i class="bi bi-chevron-left"></i></button>
      <button class="hero__arrow hero__arrow--next" type="button" data-hero-next aria-label="Next slide"><i class="bi bi-chevron-right"></i></button>
    <?php endif; ?>
    <?php if (!empty($s['show_dots'])): ?>
      <div class="hero__dots" role="tablist">
        <?php foreach ($slides as $i => $sl): ?>
          <button type="button" class="hero__dot<?= $i === 0 ? ' is-active' : '' ?>" data-hero-dot="<?= $i ?>" aria-label="Go to slide <?= $i + 1 ?>"><span></span></button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  <?php endif; ?>
  <a href="#after-hero" class="hero__scroll" aria-label="Scroll to content"><span></span></a>
</section>
<div id="after-hero"></div>
