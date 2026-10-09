<?php
/** @var array $p @var array $variants @var array $images @var bool $compact */
$pricing = unit_pricing($p);
$off = $pricing['on_sale'] ? discount_percent($pricing['regular'], $pricing['price']) : 0;
$variantData = [];
foreach ($variants as $v) {
    $vp = unit_pricing($p, $v);
    $vAvail = product_availability($p, (int) $p['track_stock'] ? (int) $v['stock_qty'] : null);
    $imgIndex = null;
    foreach ($images as $i => $img) {
        if ((int) $img['id'] === (int) $v['image_id']) {
            $imgIndex = $i;
        }
    }
    $variantData[] = [
        'id' => (int) $v['id'], 'label' => $v['label'], 'price' => money($vp['price']), 'regular' => $vp['on_sale'] ? money($vp['regular']) : null,
        'off' => $vp['on_sale'] ? discount_percent($vp['regular'], $vp['price']) : 0,
        'available' => is_purchasable($vAvail), 'stock_label' => availability_label($vAvail, (int) $p['track_stock'] ? (int) $v['stock_qty'] : null),
        'stock_class' => $vAvail, 'max' => (int) $p['track_stock'] ? max(0, min(CART_MAX_QTY_PER_LINE, (int) $v['stock_qty'])) : CART_MAX_QTY_PER_LINE,
        'image' => $imgIndex, 'sku' => $v['sku'],
    ];
}
$firstAvailable = null;
foreach ($variantData as $vd) {
    if ($vd['available']) { $firstAvailable = $vd; break; }
}
$selected = $firstAvailable ?? ($variantData[0] ?? null);
$avail = $selected ? $selected['stock_class'] : $p['availability'];
$maxQty = $selected ? $selected['max'] : ((int) $p['track_stock'] ? max(0, min(CART_MAX_QTY_PER_LINE, (int) $p['stock_qty'])) : CART_MAX_QTY_PER_LINE);
$wish = wishlist_enabled() && in_wishlist((int) $p['id']);
?>
<form class="buybox" data-buybox data-variants="<?= json_attr($variantData) ?>" novalidate>
  <input type="hidden" name="product_id" value="<?= (int) $p['id'] ?>">
  <p class="buybox__cat"><a href="<?= e(path_url('category/' . $p['category_slug'])) ?>"><?= e($p['category_name']) ?></a></p>
  <?php if (!empty($compact)): ?>
    <h2 class="buybox__title"><a href="<?= e(product_url($p)) ?>"><?= e($p['name']) ?></a></h2>
  <?php else: ?>
    <h1 class="buybox__title"><?= e($p['name']) ?></h1>
  <?php endif; ?>
  <?php if (!empty($rating) && $rating['count'] > 0): ?>
    <a href="#reviews" class="buybox__rating"><?php partial('stars', ['rating' => $rating['average']]); ?> <span><?= e(number_format($rating['average'], 1)) ?> · <?= (int) $rating['count'] ?> review<?= $rating['count'] === 1 ? '' : 's' ?></span></a>
  <?php endif; ?>
  <div class="buybox__price" data-price-box>
    <span class="price<?= $pricing['on_sale'] ? ' price--sale' : '' ?>" data-price><?= e($selected['price'] ?? money($pricing['price'])) ?></span>
    <del class="price price--old" data-regular <?= ($selected ? $selected['regular'] : ($pricing['on_sale'] ? 1 : null)) ? '' : 'hidden' ?>><?= e($selected['regular'] ?? money($pricing['regular'])) ?></del>
    <span class="badge-tag badge-tag--sale" data-off <?= ($selected['off'] ?? $off) ? '' : 'hidden' ?>>Save <?= (int) ($selected['off'] ?? $off) ?>%</span>
  </div>
  <?php if ($p['short_description']): ?><p class="buybox__short"><?= e($p['short_description']) ?></p><?php endif; ?>

  <?php if ($variantData): ?>
    <fieldset class="variant-picker">
      <legend>Colour: <strong data-variant-label><?= e($selected['label'] ?? '') ?></strong></legend>
      <div class="variant-picker__opts">
        <?php foreach ($variants as $i => $v): $vd = $variantData[$i]; ?>
          <label class="variant-opt<?= !$vd['available'] ? ' is-unavailable' : '' ?>" title="<?= e($v['label']) ?><?= !$vd['available'] ? ' — sold out' : '' ?>">
            <input type="radio" name="variant_id" value="<?= (int) $v['id'] ?>" <?= $selected && $selected['id'] === (int) $v['id'] ? 'checked' : '' ?> <?= !$vd['available'] ? 'data-soldout="1"' : '' ?>>
            <?php if ($v['color_hex'] && valid_hex($v['color_hex'])): ?>
              <span class="variant-opt__swatch" style="--sw: <?= e($v['color_hex']) ?>"></span>
            <?php else: ?>
              <span class="variant-opt__text"><?= e($v['label']) ?></span>
            <?php endif; ?>
            <span class="visually-hidden"><?= e($v['label']) ?></span>
          </label>
        <?php endforeach; ?>
      </div>
    </fieldset>
  <?php endif; ?>

  <p class="stock stock--<?= e($avail) ?> buybox__stock" data-stock><i class="bi bi-circle-fill"></i> <span><?= e($selected['stock_label'] ?? availability_label($avail, (int) $p['track_stock'] && $avail === 'low_stock' ? (int) $p['stock_qty'] : null)) ?></span></p>

  <?php if ((int) $p['gift_wrap_available']): ?>
    <label class="gift-opt">
      <input type="checkbox" name="gift_wrap" value="1">
      <span><i class="bi bi-gift"></i> Add gift packaging<?= (float) $p['gift_wrap_price'] > 0 ? ' (+' . e(money($p['gift_wrap_price'])) . ' each)' : ' (complimentary)' ?></span>
    </label>
  <?php endif; ?>

  <div class="buybox__actions">
    <div class="qty" data-qty>
      <button type="button" data-qty-minus aria-label="Decrease quantity"><i class="bi bi-dash"></i></button>
      <input type="number" name="quantity" value="1" min="1" max="<?= max(1, $maxQty) ?>" inputmode="numeric" aria-label="Quantity">
      <button type="button" data-qty-plus aria-label="Increase quantity"><i class="bi bi-plus"></i></button>
    </div>
    <button type="submit" class="btn-lux btn-lux--block" data-add-btn <?= is_purchasable($avail) ? '' : 'disabled' ?>>
      <span data-add-text><?= is_purchasable($avail) ? ($avail === 'preorder' ? 'Pre-order' : 'Add to bag') : 'Sold out' ?></span>
    </button>
    <?php if (wishlist_enabled()): ?>
      <button type="button" class="btn-icon-square<?= $wish ? ' is-active' : '' ?>" data-wishlist="<?= (int) $p['id'] ?>" aria-pressed="<?= $wish ? 'true' : 'false' ?>" aria-label="Wishlist"><i class="bi <?= $wish ? 'bi-heart-fill' : 'bi-heart' ?>"></i></button>
    <?php endif; ?>
  </div>
  <button type="button" class="btn-outline-lux btn-lux--block" data-buy-now <?= is_purchasable($avail) ? '' : 'disabled' ?>>Buy it now</button>

  <?php if (empty($compact)): ?>
  <ul class="buybox__assurances">
    <?php if (setting('pdp_delivery_text')): ?><li><i class="bi bi-truck"></i> <?= e(setting('pdp_delivery_text')) ?></li><?php endif; ?>
    <?php if (setting_bool('free_shipping_enabled', true) && (float) setting('free_shipping_threshold', '0') > 0): ?><li><i class="bi bi-box-seam"></i> Free delivery on orders over <?= e(money(setting('free_shipping_threshold'))) ?></li><?php endif; ?>
    <?php if (setting('pdp_returns_text')): ?><li><i class="bi bi-arrow-repeat"></i> <?= e(setting('pdp_returns_text')) ?></li><?php endif; ?>
    <?php if (gateway_is_configured('cod') && (int) (payment_gateway('cod')['is_enabled'] ?? 0)): ?><li><i class="bi bi-cash-coin"></i> Cash on delivery available</li><?php endif; ?>
  </ul>
  <?php else: ?>
    <a class="link-arrow mt-3" href="<?= e(product_url($p)) ?>">View full details <i class="bi bi-arrow-right"></i></a>
  <?php endif; ?>
</form>
