<?php
/** @var array $p  hydrated product row */
$pricing = unit_pricing($p);
$off = $pricing['on_sale'] ? discount_percent($pricing['regular'], $pricing['price']) : 0;
$wish = wishlist_enabled() ? in_wishlist((int) $p['id']) : false;
$hasVariants = (int) ($p['variant_count'] ?? 0) > 0;
$avail = $p['availability'];
$loading = !empty($eager) ? 'eager' : 'lazy';
?>
<article class="product-card<?= $avail === 'out_of_stock' ? ' is-soldout' : '' ?>"<?= $reveal ?? '' ?>>
  <div class="product-card__media">
    <a href="<?= e(product_url($p)) ?>" class="product-card__img" tabindex="-1" aria-hidden="true">
      <img src="<?= e(media_url($p['image'])) ?>" alt="<?= e($p['image_alt'] ?: $p['name']) ?>" loading="<?= $loading ?>" width="800" height="1000" class="product-card__primary">
      <?php if ($p['hover_image']): ?>
        <img src="<?= e(media_url($p['hover_image'])) ?>" alt="" loading="lazy" width="800" height="1000" class="product-card__secondary">
      <?php endif; ?>
    </a>
    <div class="product-card__badges">
      <?php if ($off): ?><span class="badge-tag badge-tag--sale">−<?= $off ?>%</span><?php endif; ?>
      <?php if ((int) $p['is_new_arrival']): ?><span class="badge-tag">New</span><?php endif; ?>
      <?php if ($avail === 'out_of_stock'): ?><span class="badge-tag badge-tag--muted">Sold out</span><?php elseif ($avail === 'preorder'): ?><span class="badge-tag">Pre-order</span><?php endif; ?>
    </div>
    <?php if (wishlist_enabled()): ?>
      <button type="button" class="product-card__wish<?= $wish ? ' is-active' : '' ?>" data-wishlist="<?= (int) $p['id'] ?>" aria-pressed="<?= $wish ? 'true' : 'false' ?>" aria-label="<?= $wish ? 'Remove from wishlist' : 'Add to wishlist' ?>">
        <i class="bi <?= $wish ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
      </button>
    <?php endif; ?>
    <div class="product-card__actions">
      <button type="button" class="product-card__action" data-quickview="<?= e($p['slug']) ?>"><i class="bi bi-eye"></i><span>Quick view</span></button>
      <?php if (is_purchasable($avail)): ?>
        <?php if ($hasVariants): ?>
          <button type="button" class="product-card__action" data-quickview="<?= e($p['slug']) ?>"><i class="bi bi-bag-plus"></i><span>Choose options</span></button>
        <?php else: ?>
          <button type="button" class="product-card__action" data-add-to-cart="<?= (int) $p['id'] ?>"><i class="bi bi-bag-plus"></i><span>Add to bag</span></button>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="product-card__body">
    <p class="product-card__cat"><?= e($p['category_name']) ?></p>
    <h3 class="product-card__title"><a href="<?= e(product_url($p)) ?>"><?= e($p['name']) ?></a></h3>
    <div class="product-card__price">
      <?php if ($pricing['on_sale']): ?>
        <span class="price price--sale"><?= e(money($pricing['price'])) ?></span>
        <del class="price price--old"><?= e(money($pricing['regular'])) ?></del>
      <?php else: ?>
        <span class="price"><?= e(money($pricing['price'])) ?></span>
      <?php endif; ?>
    </div>
    <div class="product-card__meta">
      <?php if (!empty($p['swatches'])): ?>
        <ul class="swatches" aria-label="Available colours">
          <?php foreach (array_slice($p['swatches'], 0, 5) as $sw): ?>
            <li style="--sw: <?= e(valid_hex((string) $sw['color_hex']) ? $sw['color_hex'] : '#999999') ?>" title="<?= e($sw['color_name']) ?>"><span class="visually-hidden"><?= e($sw['color_name']) ?></span></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <span class="stock stock--<?= e($avail) ?>"><?= e(availability_label($avail)) ?></span>
    </div>
  </div>
</article>
