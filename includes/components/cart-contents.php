<?php
/**
 * Cart lines + summary. $variant: 'page' or 'drawer'. Loaded directly and via ajax/cart.php.
 */
$lines = cart_lines();
$totals = cart_totals($lines);
$variant = $variant ?? 'page';
$freeAt = $totals['shipping']['free_threshold'] ?? null;
$merch = $totals['subtotal'] + $totals['customization_total'] - $totals['discount'];
?>
<?php if (!$lines): ?>
    <div class="uc-empty uc-empty--cart">
        <i class="bi bi-bag"></i>
        <h2>Your bag is empty</h2>
        <p>Discover new arrivals and timeless essentials.</p>
        <a class="uc-btn" href="<?= e(url('new-arrivals')) ?>">Shop New Arrivals</a>
    </div>
<?php return; endif; ?>

<?php if ($freeAt && $totals['shipping_fee'] > 0): $pct = min(100, round($merch / $freeAt * 100)); ?>
    <div class="uc-freeship">
        <p>You are <strong><?= e(money(max(0, $freeAt - $merch))) ?></strong> away from free delivery.</p>
        <div class="uc-bar"><span style="width:<?= $pct ?>%"></span></div>
    </div>
<?php elseif ($freeAt): ?>
    <div class="uc-freeship is-done"><p><i class="bi bi-check-circle"></i> You have unlocked free standard delivery.</p></div>
<?php endif; ?>

<ul class="uc-cart-lines">
    <?php foreach ($lines as $l): ?>
        <li class="uc-cart-line<?= $l['error'] ? ' has-error' : '' ?>" data-line="<?= $l['id'] ?>">
            <a class="uc-cart-img" href="<?= e($l['url']) ?>"><img src="<?= e(img_sized($l['image'], 300)) ?>" alt="<?= e($l['name']) ?>" loading="lazy"></a>
            <div class="uc-cart-info">
                <a class="uc-cart-name" href="<?= e($l['url']) ?>"><?= e($l['name']) ?></a>
                <?php if ($l['options']): ?>
                    <p class="uc-cart-opts"><?php foreach ($l['options'] as $k => $v): ?><span><?= e($k === 'Color' ? 'Colour' : $k) ?>: <?= e($v) ?></span><?php endforeach; ?></p>
                <?php endif; ?>
                <?php if (cart_has_customization($l)): ?>
                    <details class="uc-cart-custom"><summary>Customisation (+<?= e(money($l['customization_fee'])) ?> each)</summary>
                        <?php if ($l['custom_measurements']): ?><p><strong>Measurements:</strong> <?= e($l['custom_measurements']) ?></p><?php endif; ?>
                        <?php if ($l['custom_color']): ?><p><strong>Colour:</strong> <?= e($l['custom_color']) ?></p><?php endif; ?>
                        <?php if ($l['personalization']): ?><p><strong>Personalisation:</strong> <?= e($l['personalization']) ?></p><?php endif; ?>
                        <?php if ($l['custom_instructions']): ?><p><strong>Instructions:</strong> <?= e($l['custom_instructions']) ?></p><?php endif; ?>
                    </details>
                <?php endif; ?>
                <?php if ($l['made_to_order']): ?><p class="uc-cart-note"><i class="bi bi-hourglass-split"></i> Made to order — <?= (int) $l['lead_time_days'] ?> days production</p><?php endif; ?>
                <?php if ($l['error']): ?><p class="uc-cart-error"><i class="bi bi-exclamation-triangle"></i> <?= e($l['error']) ?></p><?php endif; ?>
                <div class="uc-cart-row">
                    <div class="uc-qty uc-qty--sm" data-qty>
                        <button type="button" data-cart-qty="<?= $l['id'] ?>" data-delta="-1" aria-label="Decrease quantity"><i class="bi bi-dash"></i></button>
                        <input type="number" value="<?= $l['qty'] ?>" min="0" max="<?= min(UC_CART_MAX_QTY, $l['available']) ?>" data-cart-qty-input="<?= $l['id'] ?>" aria-label="Quantity">
                        <button type="button" data-cart-qty="<?= $l['id'] ?>" data-delta="1" aria-label="Increase quantity"><i class="bi bi-plus"></i></button>
                    </div>
                    <span class="uc-cart-price">
                        <?php if ($l['regular_price'] > $l['unit_price']): ?><s><?= e(money(($l['regular_price'] + $l['customization_fee']) * $l['qty'])) ?></s><?php endif; ?>
                        <?= e(money($l['line_total'])) ?>
                    </span>
                </div>
                <button type="button" class="uc-link-btn uc-cart-remove" data-cart-remove="<?= $l['id'] ?>">Remove</button>
            </div>
        </li>
    <?php endforeach; ?>
</ul>

<div class="uc-cart-summary">
    <?php if ($variant === 'page'): ?>
    <form class="uc-coupon" data-coupon-form>
        <label for="ucCoupon" class="visually-hidden">Coupon code</label>
        <input id="ucCoupon" type="text" name="code" placeholder="Coupon code" value="<?= e($totals['coupon_code'] ?? '') ?>" autocomplete="off">
        <?php if ($totals['coupon_code']): ?>
            <button type="button" class="uc-btn uc-btn--outline uc-btn--sm" data-coupon-remove>Remove</button>
        <?php else: ?>
            <button type="submit" class="uc-btn uc-btn--sm">Apply</button>
        <?php endif; ?>
    </form>
    <?php if ($totals['coupon_message']): ?><p class="uc-cart-error small"><?= e($totals['coupon_message']) ?></p><?php endif; ?>
    <?php endif; ?>
    <dl class="uc-totals">
        <dt>Subtotal (<?= $totals['item_count'] ?> items)</dt><dd><?= e(money($totals['subtotal'])) ?></dd>
        <?php if ($totals['customization_total'] > 0): ?><dt>Customisation</dt><dd><?= e(money($totals['customization_total'])) ?></dd><?php endif; ?>
        <?php if ($totals['discount'] > 0): ?><dt>Discount <small>(<?= e($totals['coupon_code']) ?>)</small></dt><dd class="is-discount">−<?= e(money($totals['discount'])) ?></dd><?php endif; ?>
        <dt>Delivery <small>(estimated)</small></dt><dd><?= $totals['shipping_fee'] > 0 ? e(money($totals['shipping_fee'])) : 'Free' ?></dd>
        <dt class="is-total">Total</dt><dd class="is-total"><?= e(money($totals['total'])) ?></dd>
    </dl>
    <?php foreach ($totals['errors'] as $err): ?><p class="uc-cart-error small"><i class="bi bi-exclamation-triangle"></i> <?= e($err) ?></p><?php endforeach; ?>
    <p class="small text-muted">Final delivery charge is calculated for your city at checkout.</p>
    <a class="uc-btn uc-btn--block<?= $totals['errors'] ? ' disabled' : '' ?>" href="<?= e(url('checkout')) ?>"<?= $totals['errors'] ? ' aria-disabled="true"' : '' ?>>Checkout Securely <i class="bi bi-lock"></i></a>
    <?php if ($variant === 'drawer'): ?>
        <a class="uc-btn uc-btn--outline uc-btn--block mt-2" href="<?= e(url('cart')) ?>">View Bag</a>
    <?php endif; ?>
</div>
