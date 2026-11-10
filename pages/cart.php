<?php
/** /cart/ */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$zone = selected_zone();
$s = cart_summary(['fulfillment' => 'delivery', 'zone_id' => $zone['id'] ?? 0]);
page_set(['title' => 'Shopping Cart', 'robots' => 'noindex,nofollow']);
require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="page-head"><?= breadcrumbs_html([['Home', url('')], ['Cart', null]]) ?><h1>Shopping Cart</h1></div>
  <?php if (!$s['lines']): ?>
    <div class="empty-state my-3"><i class="bi bi-cart-x"></i><p>Your cart is empty.</p><a href="<?= url('shop/') ?>" class="btn btn-brand me-2">Start shopping</a><a href="<?= url('monthly-rashan/') ?>" class="btn btn-outline-brand">Build monthly rashan</a></div>
  <?php else: ?>
  <div class="row g-3" id="cartPage">
    <div class="col-lg-8">
      <div class="box">
        <div class="d-flex justify-content-between align-items-center mb-2"><h2 class="box-title mb-0"><?= (int) $s['count'] ?> items</h2><button type="button" class="btn btn-link text-danger btn-sm js-cart-clear">Clear cart</button></div>
        <?php foreach ($s['issues'] as $iss): ?><div class="alert alert-warning py-2 small"><?= e($iss) ?></div><?php endforeach; ?>
        <?php foreach ($s['lines'] as $l): $key = $l['product_id'] . '-' . $l['variation_id']; ?>
        <div class="cart-line" data-pid="<?= $l['product_id'] ?>" data-vid="<?= $l['variation_id'] ?>">
          <img src="<?= e(upload_url($l['thumb'])) ?>" alt="" loading="lazy">
          <div class="flex-grow-1">
            <a href="<?= url('product/' . $l['slug'] . '/') ?>" class="cl-name"><?= e($l['name']) ?></a>
            <?php if ($l['variation_name']): ?><div class="small text-muted"><?= e($l['variation_name']) ?></div><?php endif; ?>
            <div class="small text-muted"><?= money($l['unit_price']) ?> each</div>
            <div class="small text-danger js-problem"><?= e($l['problem'] ?? '') ?></div>
            <div class="d-flex align-items-center gap-3 mt-2">
              <div class="qty-box sm">
                <button type="button" data-step="-1" aria-label="Decrease">−</button>
                <input type="number" class="js-cart-qty" value="<?= (int) $l['quantity'] ?>" min="0" max="<?= $l['track_stock'] ? max(1, $l['stock']) : 999 ?>" aria-label="Quantity">
                <button type="button" data-step="1" aria-label="Increase">+</button>
              </div>
              <button type="button" class="btn btn-link btn-sm text-danger p-0 js-cart-remove"><i class="bi bi-trash"></i> Remove</button>
            </div>
          </div>
          <strong class="js-line-total text-nowrap"><?= money($l['line_total']) ?></strong>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="box position-sticky" style="top:calc(var(--header-h) + 12px)">
        <h2 class="box-title">Order Summary</h2>
        <div class="input-group mb-1">
          <input type="text" class="form-control text-uppercase" id="couponCode" placeholder="Coupon code" value="<?= e($s['coupon_code']) ?>" aria-label="Coupon code">
          <button class="btn btn-outline-brand js-coupon-apply" type="button">Apply</button>
        </div>
        <div class="small mb-3 js-coupon-msg"><?php if ($s['coupon_code']): ?><span class="text-success">Coupon <?= e($s['coupon_code']) ?> applied.</span> <a href="#" class="js-coupon-remove">Remove</a><?php elseif ($s['coupon_error']): ?><span class="text-danger"><?= e($s['coupon_error']) ?></span><?php endif; ?></div>
        <div class="summary-row"><span>Subtotal</span><strong class="js-subtotal"><?= money($s['subtotal']) ?></strong></div>
        <div class="summary-row text-success"><span>Discount</span><strong class="js-discount">- <?= money($s['discount']) ?></strong></div>
        <div class="summary-row"><span>Delivery <?= $zone ? '<small class="text-muted">(' . e($zone['area']) . ')</small>' : '<small class="text-muted">(estimated)</small>' ?></span><strong class="js-delivery"><?= $s['delivery_fee'] > 0 ? money($s['delivery_fee']) : 'FREE' ?></strong></div>
        <div class="summary-row summary-total"><span>Total</span><span class="js-total"><?= money($s['total']) ?></span></div>
        <p class="small text-muted">Choose store pickup at checkout to skip delivery charges.</p>
        <a href="<?= url('checkout/') ?>" class="btn btn-brand btn-lg w-100">Proceed to Checkout <i class="bi bi-arrow-right"></i></a>
        <a href="<?= url('shop/') ?>" class="btn btn-link w-100 mt-1">Continue shopping</a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var $ = jQuery;
  function paint(d) {
    if (!d.ok) return;
    FS.setCartCount(d.count);
    if (!d.count) { location.reload(); return; }
    d.lines.forEach(function (l) {
      var $r = $('.cart-line[data-pid="' + l.product_id + '"][data-vid="' + l.variation_id + '"]');
      $r.find('.js-line-total').text(l.line_total_fmt); $r.find('.js-problem').text(l.problem || '');
    });
    $('.js-subtotal').text(d.subtotal_fmt); $('.js-discount').text(d.discount_fmt); $('.js-delivery').text(d.delivery_fee_fmt); $('.js-total').text(d.total_fmt);
  }
  var t;
  $(document).on('change', '.js-cart-qty', function () {
    var $r = $(this).closest('.cart-line'), q = parseInt(this.value, 10) || 0;
    clearTimeout(t);
    t = setTimeout(function () {
      FS.api('cart', { action: 'update', product_id: $r.data('pid'), variation_id: $r.data('vid'), quantity: q }).done(function (d) { if (q === 0) $r.remove(); paint(d); });
    }, 300);
  });
  $(document).on('click', '.js-cart-remove', function () {
    var $r = $(this).closest('.cart-line');
    FS.api('cart', { action: 'remove', product_id: $r.data('pid'), variation_id: $r.data('vid') }).done(function (d) { $r.remove(); paint(d); FS.toast('Item removed'); });
  });
  $('.js-cart-clear').on('click', function () { if (confirm('Remove all items from your cart?')) FS.api('cart', { action: 'clear' }).done(function () { location.reload(); }); });
  $('.js-coupon-apply').on('click', function () {
    FS.api('cart', { action: 'coupon', code: $('#couponCode').val() }).done(function (d) { paint(d); $('.js-coupon-msg').html('<span class="text-success">' + FS.esc(d.message) + '</span> <a href="#" class="js-coupon-remove">Remove</a>'); });
  });
  $(document).on('click', '.js-coupon-remove', function (e) { e.preventDefault(); FS.api('cart', { action: 'remove_coupon' }).done(function (d) { paint(d); $('#couponCode').val(''); $('.js-coupon-msg').empty(); }); });
});
</script>
<?php require FS_ROOT . '/partials/footer.php';
