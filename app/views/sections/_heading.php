<?php /** shared section heading: $s, optional $center, $ctaRight */ ?>
<div class="section-head<?= !empty($center) ? ' section-head--center' : '' ?>"<?= reveal_attr($s) ?>>
  <div>
    <?php if (!empty($s['eyebrow'])): ?><p class="eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
    <?php if (!empty($s['title'])): ?><h2 class="section-title"><?= e($s['title']) ?></h2><?php endif; ?>
    <?php if (!empty($s['subtitle'])): ?><p class="section-sub"><?= e($s['subtitle']) ?></p><?php endif; ?>
  </div>
  <?php if (!empty($ctaRight) && !empty($s['cta_label'])): ?>
    <a class="link-arrow" href="<?= e(safe_link($s['cta_url'] ?? '/shop', path_url('shop'))) ?>"><?= e($s['cta_label']) ?> <i class="bi bi-arrow-right"></i></a>
  <?php endif; ?>
</div>
