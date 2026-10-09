<?php if (empty($s['title']) && empty($s['body'])) { return; } ?>
<section <?= section_attrs($s, 'section--story story--img-' . ($s['image_position'] === 'right' ? 'right' : 'left')) ?>>
  <div class="<?= e(section_container_class($s)) ?>">
    <div class="story">
      <div class="story__media"<?= reveal_attr($s) ?>>
        <?php if ($s['image']): ?>
          <div class="story__main"<?= !empty($s['parallax']) ? ' data-parallax-box' : '' ?>>
            <img src="<?= e(media_url($s['image'])) ?>" alt="<?= e($s['image_alt']) ?>" loading="lazy" width="1200" height="1500"<?= !empty($s['parallax']) ? ' data-parallax="0.12"' : '' ?>>
          </div>
        <?php endif; ?>
        <?php if ($s['secondary_image']): ?>
          <div class="story__secondary"<?= reveal_attr($s, 250) ?>><img src="<?= e(media_url($s['secondary_image'])) ?>" alt="<?= e($s['secondary_alt']) ?>" loading="lazy" width="700" height="700"></div>
        <?php endif; ?>
      </div>
      <div class="story__text"<?= reveal_attr($s, 150) ?>>
        <?php if ($s['eyebrow']): ?><p class="eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
        <h2 class="section-title"><?= e($s['title']) ?></h2>
        <div class="story__body"><?= rich_text($s['body']) ?></div>
        <?php if ($s['button_label']): ?><a class="btn-lux" href="<?= e(safe_link($s['button_url'], path_url('about-us'))) ?>"><?= e($s['button_label']) ?></a><?php endif; ?>
      </div>
    </div>
  </div>
</section>
