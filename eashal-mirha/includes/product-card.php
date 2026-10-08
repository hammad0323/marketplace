<?php
/** Expects $p (product row with image/hover_image) and optional $wish (array of ids). */
$wish = $wish ?? wishlist_ids();
$inWish = in_array((int)$p['id'], $wish, true);
$sizes = sizes_of($p);
?>
<article class="card" data-reveal>
  <div class="card__media">
    <a href="<?= product_url($p) ?>" class="card__img" aria-label="<?= e($p['name']) ?>">
      <img src="<?= e(img($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
      <?php if (!empty($p['hover_image'])): ?><img class="card__hover" src="<?= e(img($p['hover_image'])) ?>" alt="" loading="lazy"><?php endif; ?>
    </a>
    <div class="card__tags">
      <?php if ((int)$p['stock'] <= 0): ?><span class="tag tag--dark">Sold Out</span>
      <?php elseif (on_sale($p)): ?><span class="tag tag--gold">-<?= discount_pct($p) ?>%</span><?php endif; ?>
      <?php if ($p['is_new']): ?><span class="tag">New</span><?php endif; ?>
    </div>
    <button class="card__wish<?= $inWish ? ' active' : '' ?>" data-wish="<?= (int)$p['id'] ?>" aria-label="Add to wishlist"><?= icon('heart', 18) ?></button>
    <?php if ((int)$p['stock'] > 0): ?>
    <div class="card__actions">
      <?php if (count($sizes) > 1): ?>
        <div class="card__sizes">
          <?php foreach ($sizes as $sz): ?><button type="button" data-quick-add="<?= (int)$p['id'] ?>" data-size="<?= e($sz) ?>"><?= e($sz) ?></button><?php endforeach; ?>
        </div>
        <span class="card__quick-label">Quick Add</span>
      <?php else: ?>
        <button type="button" class="card__quick" data-quick-add="<?= (int)$p['id'] ?>" data-size="<?= e($sizes[0] ?? '') ?>">Add to Bag</button>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="card__body">
    <?php if (!empty($p['category_name'])): ?><span class="card__cat"><?= e($p['category_name']) ?></span><?php endif; ?>
    <h3 class="card__title"><a href="<?= product_url($p) ?>"><?= e($p['name']) ?></a></h3>
    <div class="price">
      <span class="price__now"><?= money(price_now($p)) ?></span>
      <?php if (on_sale($p)): ?><del><?= money($p['price']) ?></del><?php endif; ?>
    </div>
  </div>
</article>
