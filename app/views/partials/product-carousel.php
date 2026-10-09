<?php /** @var array $products */ ?>
<div class="pcarousel" data-carousel>
  <div class="pcarousel__track" data-carousel-track tabindex="0" aria-label="<?= e($label ?? 'Products') ?>">
    <?php foreach ($products as $i => $p): ?>
      <div class="pcarousel__slide"><?php partial('product-card', ['p' => $p]); ?></div>
    <?php endforeach; ?>
  </div>
  <div class="pcarousel__nav">
    <button type="button" class="pcarousel__btn" data-carousel-prev aria-label="Previous"><i class="bi bi-arrow-left"></i></button>
    <div class="pcarousel__progress"><span data-carousel-progress></span></div>
    <button type="button" class="pcarousel__btn" data-carousel-next aria-label="Next"><i class="bi bi-arrow-right"></i></button>
  </div>
</div>
