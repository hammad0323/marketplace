<?php
/**
 * /checkout/ - guest or logged-in checkout. Delivery or store pickup.
 * Everything posted here is re-validated and re-priced in order_create().
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$customer = current_customer();
$raw = cart_raw_items();
if (!$raw) {
    flash('info', 'Your cart is empty.');
    redirect('cart/');
}
$isRashan = ($_SESSION['checkout_order_type'] ?? '') === 'rashan';
$addresses = $customer ? db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [$customer['id']]) : [];
$zones = delivery_zones();
$deliveryOn = setting('delivery_enabled', '1') === '1';
$pickupOn = setting('pickup_enabled', '1') === '1';

if (is_post()) {
    csrf_check();
    keep_old($_POST);
    $ful = input('fulfillment') === 'pickup' ? 'pickup' : 'delivery';
    $d = [
        'customer_id' => $customer['id'] ?? 0,
        'name' => input('name'), 'phone' => input('phone'), 'email' => input('email'),
        'fulfillment' => $ful, 'zone_id' => input_int('zone_id'), 'address_line' => input('address_line'),
        'instructions' => input('instructions'), 'pickup_at' => input('pickup_at'),
        'payment_method' => input('payment_method'), 'payment_reference' => input('payment_reference'),
        'notes' => input('notes'), 'coupon' => cart_coupon(), 'channel' => 'web',
        'order_type' => $isRashan ? 'rashan' : 'regular', 'grocery_list_id' => $_SESSION['checkout_list_id'] ?? null,
    ];
    // Optional account creation for guests
    if (!$customer && input('create_account') === '1') {
        $reg = customer_register($d['name'], $d['phone'], $d['email'], (string) ($_POST['password'] ?? ''));
        if (is_string($reg)) {
            flash('danger', $reg);
            redirect('checkout/');
        }
        customer_login($reg);
        $d['customer_id'] = (int) $reg['id'];
        $customer = $reg;
    }
    $r = order_create($d, $raw);
    if (!$r['ok']) {
        flash('danger', $r['error']);
        redirect('checkout/');
    }
    // Save a new address to the account
    if ($customer && $ful === 'delivery' && input('save_address') === '1' && ($z = delivery_zone($d['zone_id']))) {
        db_insert('INSERT INTO customer_addresses (customer_id, label, recipient_name, phone, address_line, city, area, delivery_zone_id, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$customer['id'], 'Home', $d['name'], normalize_phone($d['phone']), $d['address_line'], $z['city'], $z['area'], $z['id'], $addresses ? 0 : 1]);
    }
    cart_clear();
    clear_old();
    unset($_SESSION['checkout_order_type'], $_SESSION['checkout_list_id']);
    $_SESSION['last_orders'][] = $r['order_number'];
    redirect('order-success/' . $r['order_number'] . '/');
}

$zoneDefault = (int) old('zone_id', $addresses[0]['delivery_zone_id'] ?? (selected_zone()['id'] ?? 0));
$ful = old('fulfillment', $deliveryOn ? 'delivery' : 'pickup');
$s = cart_summary(['fulfillment' => $ful, 'zone_id' => $zoneDefault]);
$slots = pickup_slots();
$pmDelivery = payment_methods_for('delivery');
$pmPickup = payment_methods_for('pickup');
page_set(['title' => 'Checkout', 'robots' => 'noindex,nofollow']);
require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="page-head"><?= breadcrumbs_html([['Home', url('')], ['Cart', url('cart/')], ['Checkout', null]]) ?>
    <h1>Checkout <?php if ($isRashan): ?><span class="badge bg-success fs-6 align-middle"><i class="bi bi-basket2 me-1"></i>Monthly Rashan Order</span><?php endif; ?></h1>
  </div>
  <?= render_flashes() ?>
  <?php if (!$customer): ?>
  <div class="alert alert-light border small">Already have an account? <a href="<?= url('login/') ?>?next=<?= urlencode(url('checkout/')) ?>" class="fw-semibold">Log in</a> for faster checkout. Or continue as guest.</div>
  <?php endif; ?>
  <form method="post" id="checkoutForm" novalidate>
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-lg-7">
        <div class="box mb-3">
          <h2 class="box-title">1. How would you like to receive your order?</h2>
          <div class="option-cards">
            <?php if ($deliveryOn): ?>
            <input type="radio" class="btn-check" name="fulfillment" value="delivery" id="fulDel" <?= $ful === 'delivery' ? 'checked' : '' ?>>
            <label class="option-card" for="fulDel"><i class="bi bi-truck"></i><span><strong>Home Delivery</strong><small>Delivered to your door</small></span></label>
            <?php endif; ?>
            <?php if ($pickupOn): ?>
            <input type="radio" class="btn-check" name="fulfillment" value="pickup" id="fulPick" <?= $ful === 'pickup' ? 'checked' : '' ?>>
            <label class="option-card" for="fulPick"><i class="bi bi-shop"></i><span><strong>Store Pickup</strong><small>Free · collect from store</small></span></label>
            <?php endif; ?>
          </div>
        </div>

        <div class="box mb-3">
          <h2 class="box-title">2. Your details</h2>
          <div class="row g-2">
            <div class="col-md-6"><label class="form-label" for="coName">Full name *</label><input class="form-control" id="coName" name="name" required maxlength="120" value="<?= e(old('name', $customer['name'] ?? '')) ?>" autocomplete="name"></div>
            <div class="col-md-6"><label class="form-label" for="coPhone">Mobile number *</label><input class="form-control" id="coPhone" name="phone" required maxlength="20" placeholder="03XX-XXXXXXX" value="<?= e(old('phone', $customer['phone'] ?? '')) ?>" autocomplete="tel" inputmode="tel"></div>
            <div class="col-12"><label class="form-label" for="coEmail">Email <small class="text-muted">(optional, for order updates)</small></label><input type="email" class="form-control" id="coEmail" name="email" maxlength="190" value="<?= e(old('email', $customer['email'] ?? '')) ?>" autocomplete="email"></div>
          </div>
        </div>

        <div class="box mb-3 js-delivery-box">
          <h2 class="box-title">3. Delivery address</h2>
          <?php if ($addresses): ?>
          <div class="mb-3">
            <?php foreach ($addresses as $a): ?>
            <label class="pay-option"><input type="radio" class="form-check-input me-2 js-saved-addr" name="saved_addr" value="<?= (int) $a['id'] ?>" data-zone="<?= (int) $a['delivery_zone_id'] ?>" data-address="<?= e($a['address_line']) ?>" data-name="<?= e($a['recipient_name']) ?>" data-phone="<?= e($a['phone']) ?>">
              <strong><?= e($a['label']) ?></strong> — <?= e($a['address_line']) ?>, <?= e($a['area']) ?>, <?= e($a['city']) ?></label>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="row g-2">
            <div class="col-md-6">
              <label class="form-label" for="coZone">City / Area *</label>
              <select class="form-select" id="coZone" name="zone_id">
                <option value="">Select your area…</option>
                <?php $city = null; foreach ($zones as $z): if ($city !== $z['city']) { if ($city !== null) echo '</optgroup>'; $city = $z['city']; echo '<optgroup label="' . e($city) . '">'; } ?>
                <option value="<?= (int) $z['id'] ?>" <?= $zoneDefault === (int) $z['id'] ? 'selected' : '' ?>><?= e($z['area']) ?> — <?= money($z['delivery_fee']) ?><?= $z['estimated_time'] ? ' · ' . e($z['estimated_time']) : '' ?></option>
                <?php endforeach; if ($city !== null) echo '</optgroup>'; ?>
              </select>
            </div>
            <div class="col-md-6"><label class="form-label" for="coAddr">Complete address *</label><input class="form-control" id="coAddr" name="address_line" maxlength="255" placeholder="House #, street, block" value="<?= e(old('address_line', $addresses[0]['address_line'] ?? '')) ?>" autocomplete="street-address"></div>
            <div class="col-12"><label class="form-label" for="coInst">Delivery instructions</label><input class="form-control" id="coInst" name="instructions" maxlength="500" placeholder="Landmark, preferred time, call before arriving…" value="<?= e(old('instructions')) ?>"></div>
            <?php if ($customer): ?><div class="col-12"><div class="form-check"><input class="form-check-input" type="checkbox" name="save_address" value="1" id="saveAddr" <?= $addresses ? '' : 'checked' ?>><label class="form-check-label" for="saveAddr">Save this address to my account</label></div></div><?php endif; ?>
          </div>
          <?php if ($note = setting('delivery_note')): ?><p class="small text-muted mt-2 mb-0"><i class="bi bi-info-circle me-1"></i><?= e($note) ?></p><?php endif; ?>
        </div>

        <div class="box mb-3 js-pickup-box">
          <h2 class="box-title">3. Pickup time</h2>
          <p class="small"><i class="bi bi-geo-alt text-brand me-1"></i><?= e(setting('pickup_address')) ?></p>
          <?php if (!$slots): ?><div class="alert alert-warning small">No pickup slots available right now. Please choose home delivery or call us.</div><?php endif; ?>
          <?php foreach ($slots as $di => $day): ?>
          <div class="mb-2"><div class="small fw-semibold mb-1"><?= e($day['label']) ?></div>
            <div class="slot-grid">
              <?php foreach ($day['slots'] as $si => $slot): $sid = 'slot' . $di . '_' . $si; ?>
              <input type="radio" class="btn-check" name="pickup_at" id="<?= $sid ?>" value="<?= e($slot['value']) ?>" <?= old('pickup_at') === $slot['value'] ? 'checked' : '' ?>>
              <label class="btn btn-sm" for="<?= $sid ?>"><?= e($slot['label']) ?></label>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if ($pi = setting('pickup_instructions')): ?><p class="small text-muted mb-0"><?= e($pi) ?></p><?php endif; ?>
        </div>

        <div class="box mb-3">
          <h2 class="box-title">4. Payment method</h2>
          <?php foreach (['delivery' => $pmDelivery, 'pickup' => $pmPickup] as $mode => $methods): ?>
          <div class="js-pm-<?= $mode ?>">
            <?php foreach ($methods as $i => $m): ?>
            <label class="pay-option">
              <input type="radio" class="form-check-input me-2" name="payment_method" value="<?= e($m['code']) ?>" data-ref="<?= (int) $m['requires_reference'] ?>" <?= old('payment_method') === $m['code'] || (!old('payment_method') && $i === 0 && $mode === $ful) ? 'checked' : '' ?>>
              <strong><?= e($m['name']) ?></strong>
              <?php if ($m['instructions']): ?><div class="pay-instructions"><?= e($m['instructions']) ?></div><?php endif; ?>
            </label>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
          <div class="js-ref-box mt-2" style="display:none"><label class="form-label small" for="coRef">Transaction ID / reference</label><input class="form-control" id="coRef" name="payment_reference" maxlength="120" value="<?= e(old('payment_reference')) ?>"></div>
          <label class="form-label mt-3" for="coNotes">Order notes</label>
          <textarea class="form-control" id="coNotes" name="notes" rows="2" maxlength="1000" placeholder="Anything we should know?"><?= e(old('notes')) ?></textarea>
          <?php if (!$customer): ?>
          <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="create_account" value="1" id="createAcc"><label class="form-check-label" for="createAcc">Create an account to track orders &amp; save rashan lists</label></div>
          <div class="js-pass-box mt-2" style="display:none"><input type="password" class="form-control" name="password" placeholder="Choose a password (min 6 characters)" autocomplete="new-password" minlength="6"></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-lg-5">
        <div class="box position-sticky" style="top:calc(var(--header-h) + 12px)">
          <h2 class="box-title">Order summary</h2>
          <div style="max-height:300px;overflow:auto">
          <?php foreach ($s['lines'] as $l): ?>
            <div class="d-flex gap-2 align-items-center py-2 border-bottom small">
              <img src="<?= e(upload_url($l['thumb'])) ?>" alt="" width="44" height="44" style="object-fit:contain" loading="lazy">
              <div class="flex-grow-1"><?= e($l['name']) ?><?= $l['variation_name'] ? ' <span class="text-muted">(' . e($l['variation_name']) . ')</span>' : '' ?><br><span class="text-muted"><?= (int) $l['quantity'] ?> × <?= money($l['unit_price']) ?></span><?php if ($l['problem']): ?><br><span class="text-danger"><?= e($l['problem']) ?></span><?php endif; ?></div>
              <strong><?= money($l['line_total']) ?></strong>
            </div>
          <?php endforeach; ?>
          </div>
          <div class="mt-3">
            <div class="summary-row"><span>Subtotal (<?= (int) $s['count'] ?> items)</span><strong><?= money($s['subtotal']) ?></strong></div>
            <?php if ($s['coupon_code']): ?><div class="summary-row text-success"><span>Coupon <?= e($s['coupon_code']) ?></span><strong>- <?= money($s['discount']) ?></strong></div><?php endif; ?>
            <?php if ($s['coupon_error']): ?><div class="small text-danger"><?= e($s['coupon_error']) ?></div><?php endif; ?>
            <div class="summary-row"><span>Delivery</span><strong class="js-delivery"><?= $ful === 'pickup' ? 'Free (pickup)' : ($s['delivery_fee'] > 0 ? money($s['delivery_fee']) : 'FREE') ?></strong></div>
            <div class="small text-danger js-delivery-note"><?= e($s['delivery_note'] ?? '') ?></div>
            <div class="summary-row summary-total"><span>Total</span><span class="js-total"><?= money($s['total']) ?></span></div>
          </div>
          <?php foreach ($s['issues'] as $iss): ?><div class="alert alert-warning py-2 small mt-2"><?= e($iss) ?></div><?php endforeach; ?>
          <button type="submit" class="btn btn-brand btn-lg w-100 mt-3 js-place"><i class="bi bi-bag-check me-2"></i>Place Order</button>
          <p class="small text-muted text-center mt-2 mb-0"><i class="bi bi-shield-check me-1"></i>No online payment required to place your order.</p>
        </div>
      </div>
    </div>
  </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
  var $ = jQuery;
  function mode() { return $('input[name=fulfillment]:checked').val() || 'delivery'; }
  function sync() {
    var m = mode();
    $('.js-delivery-box').toggle(m === 'delivery');
    $('.js-pickup-box').toggle(m === 'pickup');
    $('.js-pm-delivery').toggle(m === 'delivery').find('input').prop('disabled', m !== 'delivery');
    $('.js-pm-pickup').toggle(m === 'pickup').find('input').prop('disabled', m !== 'pickup');
    var $vis = $('.js-pm-' + m + ' input[name=payment_method]');
    if (!$vis.filter(':checked').length) $vis.first().prop('checked', true);
    $('.js-ref-box').toggle(!!$('input[name=payment_method]:checked:enabled').data('ref'));
    refresh();
  }
  function refresh() {
    FS.api('cart', { action: 'summary', fulfillment: mode(), zone_id: $('#coZone').val() || 0 }).done(function (d) {
      $('.js-delivery').text(d.delivery_fee_fmt); $('.js-total').text(d.total_fmt); $('.js-delivery-note').text(mode() === 'delivery' ? (d.delivery_note || '') : '');
    });
  }
  $('input[name=fulfillment]').on('change', sync);
  $('#coZone').on('change', refresh);
  $(document).on('change', 'input[name=payment_method]', function () { $('.js-ref-box').toggle(!!$(this).data('ref')); });
  $('.js-saved-addr').on('change', function () {
    var d = $(this).data();
    $('#coZone').val(d.zone); $('#coAddr').val(d.address); if (d.name) $('#coName').val(d.name); if (d.phone) $('#coPhone').val(d.phone);
    refresh();
  });
  $('#createAcc').on('change', function () { $('.js-pass-box').toggle(this.checked); });
  $('#checkoutForm').on('submit', function (e) {
    var errs = [], m = mode();
    if ($.trim($('#coName').val()).length < 2) errs.push('Please enter your name.');
    if ($('#coPhone').val().replace(/\D/g, '').length < 10) errs.push('Please enter a valid mobile number.');
    if (m === 'delivery' && !$('#coZone').val()) errs.push('Please select your delivery area.');
    if (m === 'delivery' && $.trim($('#coAddr').val()).length < 5) errs.push('Please enter your complete address.');
    if (m === 'pickup' && !$('input[name=pickup_at]:checked').length) errs.push('Please choose a pickup time.');
    if (!$('input[name=payment_method]:checked:enabled').length) errs.push('Please choose a payment method.');
    if (errs.length) { e.preventDefault(); FS.toast(errs[0], 'error'); return; }
    $('.js-place').prop('disabled', true).html('<span class="spinner-border fs-spinner me-2"></span>Placing order…');
  });
  sync();
});
</script>
<?php clear_old(); require FS_ROOT . '/partials/footer.php';
