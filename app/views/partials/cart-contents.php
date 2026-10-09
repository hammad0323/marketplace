<?php
/** @var array $lines @var string $mode 'page'|'mini' */
$mode = $mode ?? 'page';
$quote = checkout_quote($lines, ['coupon_code' => cart_coupon_code(), 'customer_id' => customer_id(), 'email' => current_customer()['email'] ?? null]);
$threshold = setting_bool('free_shipping_enabled', true) ? (float) setting('free_shipping_threshold', '0') : 0;
$hasErrors = (bool) array_filter($lines, fn($l) => $l['error']);
?>
<?php if (!$lines): ?>
  <div class="empty-state empty-state--compact">
    <i class="bi bi-bag"></i>
    <h2>Your bag is empty</h2>
    <p>Discover pieces made to be carried every day.</p>
    <a class="btn-lux" href="<?= e(path_url('shop')) ?>">Start shopping</a>
  </div>
<?php else: ?>
  <?php if ($threshold > 0): $remaining = max(0, $threshold - ($quote['subtotal'] - $quote['discount'])); $pct = min(100, round(($quote['subtotal'] - $quote['discount']) / $threshold * 100)); ?>
    <div class="ship-progress">
      <p><?= $remaining > 0 ? 'You are <strong>' . e(money($remaining)) . '</strong> away from free delivery.' : '<i class="bi bi-check2-circle"></i> Your order qualifies for free standard delivery.' ?></p>
      <div class="ship-progress__bar"><span style="width: <?= (int) $pct ?>%"></span></div>
    </div>
  <?php endif; ?>
  <ul class="cart-lines cart-lines--<?= e($mode) ?>">
    <?php foreach ($lines as $l): $p = $l['product']; ?>
      <li class="cart-line<?= $l['error'] ? ' has-error' : '' ?>" data-line="<?= (int) $l['id'] ?>">
        <a class="cart-line__img" href="<?= $p ? e(product_url($p)) : '#' ?>"><img src="<?= e(media_url($l['image'])) ?>" alt="<?= e($p['name'] ?? '') ?>" width="96" height="120" loading="lazy"></a>
        <div class="cart-line__info">
          <h3><a href="<?= $p ? e(product_url($p)) : '#' ?>"><?= e($p['name'] ?? 'Unavailable product') ?></a></h3>
          <?php if ($l['variant_label']): ?><p class="cart-line__variant"><?= e($l['variant_label']) ?></p><?php endif; ?>
          <p class="cart-line__price"><?= e(money($l['unit_price'])) ?><?php if ($l['regular_price'] > $l['unit_price']): ?> <del><?= e(money($l['regular_price'])) ?></del><?php endif; ?></p>
          <?php if ($p && (int) $p['gift_wrap_available'] && $mode === 'page'): ?>
            <label class="gift-opt gift-opt--sm"><input type="checkbox" data-gift-toggle="<?= (int) $l['id'] ?>" <?= $l['gift_wrap'] ? 'checked' : '' ?>> Gift packaging<?= (float) $p['gift_wrap_price'] > 0 ? ' (+' . e(money($p['gift_wrap_price'])) . ' each)' : '' ?></label>
          <?php elseif ($l['gift_wrap'] && $l['gift_wrap_price'] >= 0 && $p && (int) $p['gift_wrap_available']): ?>
            <p class="cart-line__variant"><i class="bi bi-gift"></i> Gift packaging</p>
          <?php endif; ?>
          <?php if ($l['error']): ?><p class="cart-line__error"><i class="bi bi-exclamation-circle"></i> <?= e($l['error']) ?></p><?php endif; ?>
          <div class="cart-line__controls">
            <div class="qty qty--sm">
              <button type="button" data-line-qty="<?= (int) $l['id'] ?>" data-delta="-1" aria-label="Decrease"><i class="bi bi-dash"></i></button>
              <input type="number" value="<?= (int) $l['quantity'] ?>" min="1" max="<?= CART_MAX_QTY_PER_LINE ?>" data-line-input="<?= (int) $l['id'] ?>" aria-label="Quantity">
              <button type="button" data-line-qty="<?= (int) $l['id'] ?>" data-delta="1" aria-label="Increase"><i class="bi bi-plus"></i></button>
            </div>
            <button type="button" class="link-muted" data-line-remove="<?= (int) $l['id'] ?>">Remove</button>
          </div>
        </div>
        <div class="cart-line__total"><?= e(money($l['line_total'])) ?></div>
      </li>
    <?php endforeach; ?>
  </ul>

  <div class="cart-summary">
    <?php if ($mode === 'page'): ?>
      <form class="coupon-form" data-coupon-form>
        <?php if ($quote['coupon']): ?>
          <div class="coupon-applied"><span><i class="bi bi-tag"></i> <strong><?= e($quote['coupon']['code']) ?></strong> — <?= e(coupon_summary($quote['coupon'])) ?></span><button type="button" class="link-muted" data-coupon-remove>Remove</button></div>
        <?php else: ?>
          <label for="coupon" class="visually-hidden">Coupon code</label>
          <input type="text" id="coupon" name="code" placeholder="Coupon code" maxlength="40" autocomplete="off">
          <button type="submit" class="btn-outline-lux">Apply</button>
          <?php if (cart_coupon_code() && $quote['coupon_error']): ?><p class="cart-line__error w-100 mb-0"><?= e(cart_coupon_code()) ?>: <?= e($quote['coupon_error']) ?></p><?php endif; ?>
        <?php endif; ?>
      </form>
    <?php endif; ?>
    <dl class="totals">
      <div><dt>Subtotal (<?= (int) $quote['item_count'] ?> item<?= $quote['item_count'] === 1 ? '' : 's' ?>)</dt><dd><?= e(money($quote['subtotal'])) ?></dd></div>
      <?php if ($quote['gift_wrap_total'] > 0): ?><div><dt>Gift packaging</dt><dd><?= e(money($quote['gift_wrap_total'])) ?></dd></div><?php endif; ?>
      <?php if ($quote['discount'] > 0): ?><div class="totals__discount"><dt>Discount</dt><dd>− <?= e(money($quote['discount'])) ?></dd></div><?php endif; ?>
      <div><dt>Delivery</dt><dd class="text-muted">Calculated at checkout</dd></div>
      <div class="totals__grand"><dt>Estimated total</dt><dd><?= e(money($quote['subtotal'] - $quote['discount'] + $quote['gift_wrap_total'])) ?></dd></div>
    </dl>
    <?php $minOrder = (float) setting('minimum_order_amount', '0'); if ($minOrder > 0 && $quote['subtotal'] < $minOrder): ?>
      <p class="cart-line__error">Minimum order value is <?= e(money($minOrder)) ?>.</p>
    <?php endif; ?>
    <a class="btn-lux btn-lux--block<?= $hasErrors ? ' disabled' : '' ?>" href="<?= e(path_url('checkout')) ?>" <?= $hasErrors ? 'aria-disabled="true"' : '' ?>>Secure checkout <i class="bi bi-lock"></i></a>
    <?php if ($mode === 'mini'): ?><a class="btn-outline-lux btn-lux--block" href="<?= e(path_url('cart')) ?>">View bag</a><?php endif; ?>
    <?php if ($hasErrors): ?><p class="small text-muted mt-2">Please resolve the highlighted items to continue.</p><?php endif; ?>
  </div>
<?php endif; ?>
