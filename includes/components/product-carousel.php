<?php
/** Horizontal scroll-snap carousel of product cards. Expects $products, optional $autoplay. */
if (!$products) {
    return;
}
?>
<div class="uc-carousel" data-carousel<?= !empty($autoplay) ? ' data-autoplay="5000"' : '' ?>>
    <div class="uc-carousel-track" data-carousel-track>
        <?php foreach ($products as $p): ?>
            <div class="uc-carousel-slide"><?php partial('components/product-card', ['p' => $p]); ?></div>
        <?php endforeach; ?>
    </div>
    <button class="uc-carousel-nav is-prev" type="button" data-carousel-prev aria-label="Previous"><i class="bi bi-chevron-left"></i></button>
    <button class="uc-carousel-nav is-next" type="button" data-carousel-next aria-label="Next"><i class="bi bi-chevron-right"></i></button>
    <div class="uc-carousel-progress"><span data-carousel-progress></span></div>
</div>
