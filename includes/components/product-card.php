<?php
/**
 * Product card. Expects $p (row from hydrate_cards()). Optional $cardClass, $lazy.
 */
$pi = $p['price_info'];
$lazy = $lazy ?? true;
$hoverSwap = theme('theme_card_hover_swap') === '1' && $p['hover_image'];
$colors = array_slice($p['colors'], 0, 5, true);
$extraColors = count($p['colors']) - count($colors);
?>
<article class="uc-card <?= e($cardClass ?? '') ?><?= $p['in_stock'] ? '' : ' is-soldout' ?>" data-product-id="<?= (int) $p['id'] ?>">
    <div class="uc-card-media">
        <a href="<?= e($p['url']) ?>" class="uc-card-img<?= $hoverSwap ? ' has-hover' : '' ?>" tabindex="-1" aria-hidden="true">
            <img src="<?= e(img_sized($p['image'], 700)) ?>" alt="<?= e($p['image_alt'] ?: $p['name']) ?>" width="600" height="800"<?= $lazy ? ' loading="lazy"' : '' ?> decoding="async">
            <?php if ($hoverSwap): ?>
                <img class="uc-card-hover" src="<?= e(img_sized($p['hover_image'], 700)) ?>" alt="" width="600" height="800" loading="lazy" decoding="async">
            <?php endif; ?>
        </a>
        <?php if (!$p['in_stock']): ?>
            <span class="uc-badge badge-sold-out">Sold Out</span>
        <?php elseif ($p['badge_label']): ?>
            <span class="uc-badge <?= e(badge_class($p['badge_label'])) ?>"><?= e($p['badge_label']) ?></span>
        <?php endif; ?>
        <button type="button" class="uc-wish-btn<?= $p['in_wishlist'] ? ' is-active' : '' ?>" data-wishlist="<?= (int) $p['id'] ?>" aria-pressed="<?= $p['in_wishlist'] ? 'true' : 'false' ?>" aria-label="Save <?= e($p['name']) ?> to wishlist">
            <i class="bi bi-heart"></i><i class="bi bi-heart-fill"></i>
        </button>
        <div class="uc-card-actions">
            <button type="button" class="uc-card-action" data-quickview="<?= (int) $p['id'] ?>"><i class="bi bi-eye"></i><span>Quick View</span></button>
            <?php if ($p['in_stock'] && !(int) $p['has_variants'] && !(int) $p['is_customizable']): ?>
                <button type="button" class="uc-card-action" data-add-to-cart="<?= (int) $p['id'] ?>"><i class="bi bi-bag-plus"></i><span>Add to Bag</span></button>
            <?php elseif ($p['in_stock']): ?>
                <button type="button" class="uc-card-action" data-quickview="<?= (int) $p['id'] ?>"><i class="bi bi-bag-plus"></i><span>Choose Size</span></button>
            <?php endif; ?>
        </div>
    </div>
    <div class="uc-card-body">
        <h3 class="uc-card-title"><a href="<?= e($p['url']) ?>"><?= e($p['name']) ?></a></h3>
        <div class="uc-price">
            <?php if ($pi['on_sale']): ?>
                <span class="uc-price-now is-sale"><?= e(money($pi['sale'])) ?></span>
                <s class="uc-price-was"><?= e(money($pi['regular'])) ?></s>
                <span class="uc-price-off">−<?= (int) round(100 - $pi['sale'] / max(1, $pi['regular']) * 100) ?>%</span>
            <?php else: ?>
                <span class="uc-price-now"><?= e(money($pi['final'])) ?></span>
            <?php endif; ?>
        </div>
        <div class="uc-card-meta">
            <?php if ($colors && theme('theme_card_show_colors') === '1'): ?>
                <span class="uc-swatches" aria-label="<?= count($p['colors']) ?> colours">
                    <?php foreach ($colors as $name => $hex): ?>
                        <span class="uc-swatch-dot" style="--sw:<?= e(safe_color($hex, '#cccccc')) ?>" title="<?= e($name) ?>"></span>
                    <?php endforeach; ?>
                    <?php if ($extraColors > 0): ?><small>+<?= $extraColors ?></small><?php endif; ?>
                </span>
            <?php endif; ?>
            <?php if ((int) $p['rating_count'] > 0 && theme('theme_card_show_rating') === '1'): ?>
                <span class="uc-card-rating"><?= render_stars((float) $p['rating_avg']) ?><small>(<?= (int) $p['rating_count'] ?>)</small></span>
            <?php endif; ?>
        </div>
        <?php if (!empty($p['custom_garment_id'])): ?><p class="uc-card-custom"><i class="bi bi-rulers"></i> Custom size available</p><?php endif; ?>
        <?php if ($p['low_stock']): ?><p class="uc-stock-note">Only a few left</p><?php endif; ?>
    </div>
</article>
