<?php
/**
 * Cart contents, rendered by the cart page and returned by AJAX after every
 * change. Expects $lines, $totals; $mini = true for the slide-out bag.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }
$mini = $mini ?? false;
$hasIssue = (bool)array_filter($lines, fn($l) => !$l['available']);
if (!$lines): ?>
  <div class="empty-state small">
    <i class="bi bi-bag"></i>
    <h3>Your bag is empty</h3>
    <p>Discover pieces finished by hand.</p>
    <a class="btn btn-eb btn-primary-eb" href="<?= e(url('shop')) ?>">Start shopping</a>
  </div>
<?php return; endif; ?>
<div class="cart-lines<?= $mini ? ' is-mini' : '' ?>">
  <?php foreach ($lines as $l): ?>
    <div class="cart-line<?= $l['available'] ? '' : ' has-issue' ?>" data-item="<?= (int)$l['item_id'] ?>">
      <a href="<?= e(url('product/' . $l['slug'])) ?>" class="cl-img"><img src="<?= e(img_url($l['image'])) ?>" alt="<?= e($l['name']) ?>" loading="lazy"></a>
      <div class="cl-info">
        <a href="<?= e(url('product/' . $l['slug'])) ?>" class="cl-name"><?= e($l['name']) ?></a>
        <?php if ($l['variant_label']): ?><div class="cl-meta"><?= e($l['variant_label']) ?></div><?php endif; ?>
        <?php if ($l['customised']): ?>
          <div class="cl-meta cl-custom"><i class="bi bi-scissors"></i>
            <?= $l['custom_length'] ? 'Length ' . (int)$l['custom_length'] . '" ' : '' ?><?= $l['custom_sleeve'] ? '· ' . e($l['custom_sleeve']) . ' ' : '' ?><?= $l['custom_notes'] ? '· “' . e(str_limit($l['custom_notes'], 60)) . '”' : '' ?>
            <?= $l['custom_fee'] > 0 ? '<span class="d-block">+' . money($l['custom_fee']) . ' customisation</span>' : '' ?>
          </div>
        <?php endif; ?>
        <?php if ($l['fulfillment_type'] === 'made_to_order' || $l['customised']): ?><div class="cl-meta">Made to order · <?= (int)$l['lead_days'] ?> days production</div><?php endif; ?>
        <div class="cl-price"><?= money($l['unit_price']) ?><?php if ($l['regular_price'] > $l['unit_price']): ?> <del><?= money($l['regular_price']) ?></del><?php endif; ?></div>
        <?php if ($l['issue']): ?><div class="cl-issue"><i class="bi bi-exclamation-circle"></i> <?= e($l['issue']) ?></div><?php endif; ?>
        <div class="cl-controls">
          <div class="qty-box sm">
            <button type="button" data-cart-qty="-1" aria-label="Decrease"><i class="bi bi-dash"></i></button>
            <input type="number" value="<?= (int)$l['qty'] ?>" min="1" max="<?= (int)$l['max_qty'] ?>" data-cart-input aria-label="Quantity">
            <button type="button" data-cart-qty="1" aria-label="Increase"><i class="bi bi-plus"></i></button>
          </div>
          <button type="button" class="link-btn" data-cart-remove>Remove</button>
        </div>
      </div>
      <div class="cl-total"><?= money($l['line_total']) ?></div>
    </div>
  <?php endforeach; ?>
</div>

<div class="cart-summary">
  <?php if (!$mini): ?>
  <form class="coupon-form" data-coupon-form>
    <?php if ($totals['coupon']): ?>
      <div class="coupon-applied"><i class="bi bi-tag"></i> <strong><?= e($totals['coupon']['code']) ?></strong> applied <button type="button" class="link-btn" data-coupon-remove>Remove</button></div>
    <?php else: ?>
      <label for="couponCode" class="visually-hidden">Coupon code</label>
      <div class="input-group">
        <input type="text" class="form-control" id="couponCode" name="code" placeholder="Coupon code" maxlength="50" autocomplete="off">
        <button class="btn btn-eb btn-outline-eb" type="submit">Apply</button>
      </div>
      <?php if ($totals['coupon_error'] && cart_coupon()): ?><div class="small text-danger mt-1"><?= e($totals['coupon_error']) ?></div><?php endif; ?>
    <?php endif; ?>
  </form>
  <?php endif; ?>
  <dl class="totals">
    <dt>Subtotal</dt><dd><?= money($totals['subtotal']) ?></dd>
    <?php if ($totals['custom_total'] > 0): ?><dt>Customisation</dt><dd><?= money($totals['custom_total']) ?></dd><?php endif; ?>
    <?php if ($totals['discount'] > 0): ?><dt>Discount</dt><dd class="text-success">−<?= money($totals['discount']) ?></dd><?php endif; ?>
    <dt>Delivery</dt><dd class="text-muted"><?= $totals['free_shipping_coupon'] ? 'Free' : 'Calculated at checkout' ?></dd>
    <dt class="grand">Estimated total</dt><dd class="grand"><?= money($totals['merch_total'] - $totals['discount']) ?></dd>
  </dl>
  <?php foreach ($totals['errors'] as $er): ?><div class="alert alert-warning py-2 small"><?= e($er) ?></div><?php endforeach; ?>
  <?php if ($hasIssue): ?><div class="alert alert-warning py-2 small">Please resolve the highlighted items before checking out.</div><?php endif; ?>
  <a href="<?= e(url('checkout')) ?>" class="btn btn-eb btn-primary-eb w-100<?= $hasIssue ? ' disabled' : '' ?>"<?= $hasIssue ? ' aria-disabled="true"' : '' ?>>Checkout securely</a>
  <?php if ($mini): ?><a href="<?= e(url('cart')) ?>" class="btn btn-eb btn-outline-eb w-100 mt-2">View bag</a><?php endif; ?>
  <p class="secure-note"><i class="bi bi-shield-lock"></i> Secure checkout · Guest checkout available</p>
</div>
