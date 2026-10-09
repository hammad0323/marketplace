<?php
/** Product card. Expects $p (hydrated product row). Optional $cardClass. */
if (!defined('EBAYA')) { http_response_code(403); exit; }
$wish = in_array((int)$p['id'], wishlist_ids(), true);
$link = product_url($p);
?>
<article class="product-card <?= e($cardClass ?? '') ?>" data-product-id="<?= (int)$p['id'] ?>">
  <div class="pc-media">
    <a href="<?= e($link) ?>" class="pc-img-link" tabindex="-1" aria-hidden="true">
      <img class="pc-img pc-img-main" src="<?= e(img_url($p['image'])) ?>" alt="<?= e($p['image_alt'] ?: $p['name']) ?>" loading="lazy" decoding="async" width="600" height="800">
      <?php if ($p['hover_image']): ?>
        <img class="pc-img pc-img-hover" src="<?= e(img_url($p['hover_image'])) ?>" alt="" loading="lazy" decoding="async" width="600" height="800">
      <?php endif; ?>
    </a>
    <div class="pc-badges">
      <?php if (!empty($p['is_handcrafted'])): ?><span class="pc-badge badge-craft"><i class="bi bi-flower1"></i> Handcrafted</span><?php endif; ?>
      <?php foreach ($p['badges'] as [$label, $tone]): ?><span class="pc-badge badge-<?= e($tone) ?>"><?= e($label) ?></span><?php endforeach; ?>
    </div>
    <button class="pc-wish<?= $wish ? ' is-active' : '' ?>" type="button" data-wishlist="<?= (int)$p['id'] ?>" aria-pressed="<?= $wish ? 'true' : 'false' ?>" aria-label="Add <?= e($p['name']) ?> to wishlist">
      <i class="bi bi-heart<?= $wish ? '-fill' : '' ?>"></i>
    </button>
    <div class="pc-actions">
      <button type="button" class="pc-action" data-quickview="<?= e($p['slug']) ?>"><i class="bi bi-eye"></i> Quick view</button>
      <?php if ($p['in_stock'] && $p['single_variant_id'] && !$p['allow_customization']): ?>
        <button type="button" class="pc-action" data-add-simple="<?= (int)$p['id'] ?>" data-variant="<?= (int)$p['single_variant_id'] ?>"><i class="bi bi-bag-plus"></i> Add to bag</button>
      <?php elseif ($p['in_stock']): ?>
        <a class="pc-action" href="<?= e($link) ?>"><i class="bi bi-bag-plus"></i> Choose size</a>
      <?php endif; ?>
    </div>
  </div>
  <div class="pc-body">
    <h3 class="pc-title"><a href="<?= e($link) ?>"><?= e($p['name']) ?></a></h3>
    <div class="pc-price">
      <?php if ($p['on_sale']): ?>
        <span class="price-sale"><?= money($p['price']) ?></span> <del class="price-old"><?= money($p['regular_price']) ?></del>
      <?php else: ?>
        <span><?= money($p['price']) ?></span>
      <?php endif; ?>
    </div>
    <?php if (setting('card_show_colors') && $p['colors']): ?>
      <div class="pc-swatches" aria-label="Available colours">
        <?php foreach (array_slice($p['colors'], 0, 5) as $c): ?>
          <span class="swatch" style="background:<?= e($c['swatch_hex'] ?: '#ccc') ?>" title="<?= e($c['value']) ?>"></span>
        <?php endforeach; ?>
        <?php if (count($p['colors']) > 5): ?><span class="swatch-more">+<?= count($p['colors']) - 5 ?></span><?php endif; ?>
      </div>
    <?php endif; ?>
    <div class="pc-stock">
      <?php if (!$p['in_stock']): ?><span class="text-muted">Sold out</span>
      <?php elseif ($p['fulfillment_type'] === 'made_to_order'): ?><span>Made to order</span>
      <?php elseif ($p['low_stock']): ?><span class="low">Only a few left</span>
      <?php else: ?><span>In stock</span><?php endif; ?>
    </div>
  </div>
</article>
