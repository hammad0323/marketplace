<?php
/**
 * Checkout — guest and registered customers share one flow.
 * All totals are recomputed server-side inside place_order().
 */
meta_set(['title' => 'Checkout', 'noindex' => true]);
header('Cache-Control: no-store');
$customer = current_customer();

if (is_post()) {
    require_csrf();
    $key = input('checkout_key');
    // A duplicate submit of the same form returns the order already created.
    if (preg_match('/^[a-f0-9]{64}$/', $key) && ($existing = db_one('SELECT * FROM orders WHERE idempotency_key = ?', [$key])) && order_visible_to_visitor($existing, null)) {
        redirect(path_url('order/' . $existing['order_number']));
    }
    if (!preg_match('/^[a-f0-9]{64}$/', $key) || empty($_SESSION['checkout_keys'][$key])) {
        flash('error', 'Your checkout session expired. Please review your order and try again.');
        redirect(path_url('checkout'));
    }
    if (!rate_limit('checkout', client_ip(), 15, 600)) {
        flash('error', 'Too many checkout attempts. Please wait a few minutes.');
        redirect(path_url('checkout'));
    }
    if ($customer && input_int('address_id') > 0) {
        $addr = db_one('SELECT * FROM customer_addresses WHERE id = ? AND customer_id = ?', [input_int('address_id'), (int) $customer['id']]);
        if ($addr) {
            foreach (['full_name', 'phone', 'address_line1', 'address_line2', 'city', 'region', 'postal_code'] as $f) {
                if (input($f) === '') {
                    $_POST[$f] = (string) $addr[$f];
                }
            }
        }
    }
    if ($customer) {
        $_POST['email'] = $customer['email'];
    }
    [$data, $errors] = checkout_validate($_POST);
    if ($errors) {
        keep_old($_POST);
        $_SESSION['checkout_errors'] = $errors;
        flash('error', 'Please check the highlighted fields.');
        redirect(path_url('checkout'));
    }
    try {
        $order = place_order($data, $key);
    } catch (RuntimeException $e) {
        keep_old($_POST);
        flash('error', $e->getMessage());
        redirect(path_url('checkout'));
    } catch (Throwable $e) {
        error_log('Checkout failure: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        keep_old($_POST);
        flash('error', 'We could not place your order due to a technical problem. You have not been charged. Please try again.');
        redirect(path_url('checkout'));
    }
    unset($_SESSION['checkout_keys'][$key], $_SESSION['checkout_errors']);
    clear_old();
    if ($order['payment_method'] === 'cod') {
        redirect(path_url('order/' . $order['order_number']));
    }
    $payment = db_one('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1', [$order['id']]);
    redirect(path_url('payment/start/' . $payment['reference']));
}

$lines = cart_lines();
if (!$lines) {
    flash('info', 'Your bag is empty.');
    redirect(path_url('cart'));
}
foreach ($lines as $l) {
    if ($l['error']) {
        flash('error', 'Some items in your bag need attention before checkout.');
        redirect(path_url('cart'));
    }
}
$key = random_token(32);
$_SESSION['checkout_keys'] = array_slice(($_SESSION['checkout_keys'] ?? []), -9, 9, true) + [$key => time()];
$errors = $_SESSION['checkout_errors'] ?? [];
unset($_SESSION['checkout_errors']);

$addresses = $customer ? db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [(int) $customer['id']]) : [];
$default = $addresses[0] ?? null;
$v = function (string $k, string $fallback = '') { return old($k, $fallback); };
$prefill = [
    'full_name' => $default['full_name'] ?? ($customer ? trim($customer['first_name'] . ' ' . $customer['last_name']) : ''),
    'phone' => $default['phone'] ?? ($customer['phone'] ?? ''),
    'address_line1' => $default['address_line1'] ?? '', 'address_line2' => $default['address_line2'] ?? '',
    'city' => $default['city'] ?? '', 'region' => $default['region'] ?? '', 'postal_code' => $default['postal_code'] ?? '',
];
$methods = checkout_payment_methods();
$quote = checkout_quote($lines, [
    'city' => $v('city', $prefill['city']), 'region' => $v('region', $prefill['region']), 'shipping_method' => $v('shipping_method', 'standard'),
    'payment_method' => $v('payment_method', $methods[0]['code'] ?? ''), 'coupon_code' => cart_coupon_code(),
    'customer_id' => customer_id(), 'email' => $customer['email'] ?? old('email'),
]);
$cities = array_values(array_unique(db_col("SELECT city FROM shipping_zone_locations WHERE city IS NOT NULL AND city <> '' ORDER BY city")));
$fieldErr = function ($k) use ($errors) { return isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : ''; };
$cls = function ($k) use ($errors) { return isset($errors[$k]) ? ' is-invalid' : ''; };
$GLOBALS['body_class'] = 'page-checkout';
partial('header');
?>
<div class="container container--wide page-pad checkout">
  <h1 class="page-title">Checkout</h1>
  <?php if (!$methods): ?>
    <div class="alert alert-warning">Online ordering is temporarily unavailable — no payment method is configured. Please contact us to order.</div>
  <?php endif; ?>
  <form method="post" action="<?= e(path_url('checkout')) ?>" class="checkout__grid" data-checkout-form novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="checkout_key" value="<?= e($key) ?>">
    <div class="checkout__main">
      <?php if (!$customer): ?>
        <div class="checkout__login">Returning customer? <a href="<?= e(path_url('account/login', ['next' => 'checkout'])) ?>">Sign in</a> for faster checkout — or continue as a guest.</div>
      <?php endif; ?>

      <fieldset class="checkout__step">
        <legend><span>1</span> Contact</legend>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label" for="email">Email address</label>
            <input type="email" class="form-control<?= $cls('email') ?>" id="email" name="email" maxlength="190" required autocomplete="email" value="<?= e($customer['email'] ?? $v('email')) ?>" <?= $customer ? 'readonly' : '' ?>>
            <?= $fieldErr('email') ?>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="phone">Mobile number</label>
            <input type="tel" class="form-control<?= $cls('phone') ?>" id="phone" name="phone" maxlength="30" required autocomplete="tel" placeholder="03xx xxxxxxx" value="<?= e($v('phone', $prefill['phone'])) ?>">
            <?= $fieldErr('phone') ?>
          </div>
        </div>
      </fieldset>

      <fieldset class="checkout__step">
        <legend><span>2</span> Delivery address</legend>
        <?php if ($addresses): ?>
          <div class="saved-addresses">
            <?php foreach ($addresses as $i => $a): ?>
              <label class="saved-address">
                <input type="radio" name="address_id" value="<?= (int) $a['id'] ?>" data-address="<?= json_attr($a) ?>" <?= $i === 0 ? 'checked' : '' ?>>
                <span><strong><?= e($a['label']) ?></strong> — <?= e($a['full_name']) ?>, <?= e($a['address_line1']) ?>, <?= e($a['city']) ?></span>
              </label>
            <?php endforeach; ?>
            <label class="saved-address"><input type="radio" name="address_id" value="0" data-address-new> <span>Use a new address</span></label>
          </div>
        <?php endif; ?>
        <div class="row g-3" data-address-fields>
          <div class="col-12">
            <label class="form-label" for="full_name">Full name</label>
            <input class="form-control<?= $cls('full_name') ?>" id="full_name" name="full_name" maxlength="150" required autocomplete="name" value="<?= e($v('full_name', $prefill['full_name'])) ?>">
            <?= $fieldErr('full_name') ?>
          </div>
          <div class="col-12">
            <label class="form-label" for="address_line1">Address</label>
            <input class="form-control<?= $cls('address_line1') ?>" id="address_line1" name="address_line1" maxlength="255" required autocomplete="address-line1" placeholder="House / street / area" value="<?= e($v('address_line1', $prefill['address_line1'])) ?>">
            <?= $fieldErr('address_line1') ?>
          </div>
          <div class="col-12">
            <label class="form-label" for="address_line2">Apartment, landmark <span class="text-muted">(optional)</span></label>
            <input class="form-control" id="address_line2" name="address_line2" maxlength="255" autocomplete="address-line2" value="<?= e($v('address_line2', $prefill['address_line2'])) ?>">
          </div>
          <div class="col-md-5">
            <label class="form-label" for="city">City</label>
            <input class="form-control<?= $cls('city') ?>" id="city" name="city" maxlength="100" required list="cityList" autocomplete="address-level2" value="<?= e($v('city', $prefill['city'])) ?>" data-quote-trigger>
            <datalist id="cityList"><?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist>
            <?= $fieldErr('city') ?>
          </div>
          <div class="col-md-4">
            <label class="form-label" for="region">Province / region</label>
            <select class="form-select<?= $cls('region') ?>" id="region" name="region" required autocomplete="address-level1" data-quote-trigger>
              <option value="">Select…</option>
              <?php foreach (pk_regions() as $r): ?><option<?= $v('region', $prefill['region']) === $r ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
            </select>
            <?= $fieldErr('region') ?>
          </div>
          <div class="col-md-3">
            <label class="form-label" for="postal_code">Postal code <span class="text-muted">(optional)</span></label>
            <input class="form-control<?= $cls('postal_code') ?>" id="postal_code" name="postal_code" maxlength="12" autocomplete="postal-code" value="<?= e($v('postal_code', $prefill['postal_code'])) ?>">
            <?= $fieldErr('postal_code') ?>
          </div>
          <?php if ($customer): ?>
            <div class="col-12"><label class="check"><input type="checkbox" name="save_address" value="1" <?= $addresses ? '' : 'checked' ?>> Save this address to my account</label></div>
          <?php endif; ?>
        </div>
      </fieldset>

      <fieldset class="checkout__step">
        <legend><span>3</span> Delivery method</legend>
        <div class="ship-options" data-ship-options>
          <?php foreach ($quote['shipping_options'] as $o): ?>
            <label class="choice">
              <input type="radio" name="shipping_method" value="<?= e($o['method']) ?>" <?= ($quote['shipping']['method'] ?? '') === $o['method'] ? 'checked' : '' ?> data-quote-trigger>
              <span class="choice__body"><strong><?= e($o['name']) ?></strong><small><?= e($o['estimate']) ?></small></span>
              <span class="choice__price"><?= $o['cost'] > 0 ? e(money($o['cost'])) : 'Free' ?></span>
            </label>
          <?php endforeach; ?>
          <?php if (!$quote['shipping_options']): ?><p class="text-muted mb-0">Enter your city to see delivery options.</p><?php endif; ?>
        </div>
      </fieldset>

      <fieldset class="checkout__step">
        <legend><span>4</span> Payment</legend>
        <?= $fieldErr('payment_method') ?>
        <div class="pay-options">
          <?php foreach ($methods as $i => $m): $checked = $v('payment_method', $methods[0]['code']) === $m['code']; ?>
            <label class="choice choice--pay" data-pay-option="<?= e($m['code']) ?>">
              <input type="radio" name="payment_method" value="<?= e($m['code']) ?>" <?= $checked ? 'checked' : '' ?> data-quote-trigger>
              <span class="choice__body">
                <strong><?= e($m['name']) ?><?= $m['sandbox'] ? ' <span class="badge text-bg-warning">Test mode</span>' : '' ?></strong>
                <small><?= e($m['description']) ?></small>
                <?php if ($m['code'] === 'cod'): ?><small class="d-block" data-cod-note><?= e($m['instructions']) ?><?= $quote['cod_fee_setting'] > 0 ? ' A ' . e(money($quote['cod_fee_setting'])) . ' cash handling fee applies.' : '' ?></small><?php endif; ?>
              </span>
              <span class="choice__icon"><i class="bi <?= ['cod' => 'bi-cash-stack', 'card' => 'bi-credit-card', 'easypaisa' => 'bi-phone', 'jazzcash' => 'bi-phone'][$m['code']] ?? 'bi-wallet2' ?>"></i></span>
            </label>
          <?php endforeach; ?>
        </div>
        <p class="small text-muted mt-2"><i class="bi bi-shield-lock"></i> Online payments are completed on the provider's secure page. We never see or store your card or wallet PIN.</p>
      </fieldset>

      <fieldset class="checkout__step">
        <legend><span>5</span> Notes &amp; account</legend>
        <label class="form-label" for="notes">Order notes <span class="text-muted">(optional)</span></label>
        <textarea class="form-control<?= $cls('notes') ?>" id="notes" name="notes" rows="3" maxlength="1000" placeholder="Delivery instructions, gift message…"><?= e($v('notes')) ?></textarea>
        <?= $fieldErr('notes') ?>
        <?php if (!$customer): ?>
          <label class="check mt-3"><input type="checkbox" name="create_account" value="1" data-toggle-target="#accountPassword" <?= old('create_account') ? 'checked' : '' ?>> Create an account for faster checkout and order history</label>
          <div id="accountPassword" class="mt-2" <?= old('create_account') ? '' : 'hidden' ?>>
            <label class="form-label" for="password">Choose a password</label>
            <input type="password" class="form-control<?= $cls('password') ?>" id="password" name="password" minlength="8" maxlength="128" autocomplete="new-password">
            <small class="text-muted">At least 8 characters with a letter and a number.</small>
            <?= $fieldErr('password') ?>
          </div>
        <?php endif; ?>
      </fieldset>
    </div>

    <aside class="checkout__summary">
      <div class="summary-card" data-checkout-summary>
        <h2 class="h5">Order summary</h2>
        <ul class="summary-lines">
          <?php foreach ($lines as $l): ?>
            <li>
              <span class="summary-lines__img"><img src="<?= e(media_url($l['image'])) ?>" alt="" width="56" height="70"><em><?= (int) $l['quantity'] ?></em></span>
              <span class="summary-lines__name"><?= e($l['product']['name']) ?><?php if ($l['variant_label']): ?><small><?= e($l['variant_label']) ?></small><?php endif; ?><?php if ($l['gift_wrap_price'] > 0 || ($l['gift_wrap'] && (int) $l['product']['gift_wrap_available'])): ?><small><i class="bi bi-gift"></i> Gift packaging</small><?php endif; ?></span>
              <span><?= e(money($l['line_total'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <dl class="totals" data-totals>
          <div><dt>Subtotal</dt><dd data-t="subtotal"><?= e(money($quote['subtotal'])) ?></dd></div>
          <div data-row="gift" <?= $quote['gift_wrap_total'] > 0 ? '' : 'hidden' ?>><dt>Gift packaging</dt><dd data-t="gift"><?= e(money($quote['gift_wrap_total'])) ?></dd></div>
          <div class="totals__discount" data-row="discount" <?= $quote['discount'] > 0 ? '' : 'hidden' ?>><dt>Discount<?= $quote['coupon'] ? ' (' . e($quote['coupon']['code']) . ')' : '' ?></dt><dd data-t="discount">− <?= e(money($quote['discount'])) ?></dd></div>
          <div><dt>Delivery</dt><dd data-t="shipping"><?= $quote['shipping'] ? ($quote['shipping_total'] > 0 ? e(money($quote['shipping_total'])) : 'Free') : '—' ?></dd></div>
          <div data-row="cod" <?= $quote['cod_fee'] > 0 ? '' : 'hidden' ?>><dt>Cash on delivery fee</dt><dd data-t="cod"><?= e(money($quote['cod_fee'])) ?></dd></div>
          <div class="totals__grand"><dt>Total to pay</dt><dd data-t="total"><?= e(money($quote['total'])) ?></dd></div>
        </dl>
        <p class="summary-estimate" data-t="estimate"><?= $quote['shipping'] ? '<i class="bi bi-truck"></i> Estimated delivery: ' . e($quote['shipping']['estimate']) : '' ?></p>
        <div class="checkout-errors" data-quote-errors><?php foreach ($quote['errors'] as $er): ?><p><?= e($er) ?></p><?php endforeach; ?></div>
        <?php if (setting_bool('checkout_require_terms', true)): ?>
          <label class="check small<?= isset($errors['accept_terms']) ? ' text-danger' : '' ?>"><input type="checkbox" name="accept_terms" value="1" required <?= old('accept_terms') ? 'checked' : '' ?>> I agree to the <a href="<?= e(path_url('terms-and-conditions')) ?>" target="_blank">terms</a> and <a href="<?= e(path_url('privacy-policy')) ?>" target="_blank">privacy policy</a>.</label>
        <?php endif; ?>
        <button type="submit" class="btn-lux btn-lux--block mt-3" data-place-order <?= $methods ? '' : 'disabled' ?>>
          <span data-place-text>Place order</span>
        </button>
        <p class="small text-muted text-center mt-2"><i class="bi bi-lock"></i> Secure checkout</p>
      </div>
    </aside>
  </form>
</div>
<?php clear_old(); partial('footer');
