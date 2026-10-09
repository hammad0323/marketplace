<?php
/** Checkout — guest or registered. All totals are recalculated server-side on submit. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

$customer = current_customer();
$lines = cart_lines();
if (is_post() && preg_match('/^[a-f0-9]{64}$/', (string)post('checkout_token'))) {
    // Double-click / resubmission of an already-placed order → show that order.
    $done = db_one('SELECT order_number, lookup_key FROM orders WHERE checkout_token = ?', [post('checkout_token')]);
    if ($done) redirect('order/' . $done['order_number'] . '?key=' . $done['lookup_key']);
}
if (!$lines) {
    flash('info', 'Your bag is empty.');
    redirect('cart');
}
$addresses = $customer ? db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [(int)$customer['id']]) : [];
$def = $addresses[0] ?? null;
$errors = [];

$in = [
    'name' => old('name', $def['full_name'] ?? ($customer['name'] ?? '')),
    'email' => old('email', $customer['email'] ?? ''),
    'phone' => old('phone', $def['phone'] ?? ($customer['phone'] ?? '')),
    'address1' => old('address1', $def['address_line1'] ?? ''),
    'address2' => old('address2', $def['address_line2'] ?? ''),
    'city' => old('city', $def['city'] ?? ''),
    'province' => old('province', $def['province'] ?? ''),
    'postal_code' => old('postal_code', $def['postal_code'] ?? ''),
    'notes' => old('notes', ''),
    'method' => old('method', 'standard'),
    'payment' => old('payment', ''),
];

if (is_post()) {
    csrf_check();
    rate_limit_or_fail('checkout', 20, 600);
    foreach ($in as $k => $_) $in[$k] = mb_substr(trim((string)post($k)), 0, $k === 'notes' ? 1000 : 255);
    $in['email'] = strtolower($in['email']);
    $in['notes'] = strip_tags($in['notes']);

    if (!v_len($in['name'], 2, 120)) $errors['name'] = 'Please enter your full name.';
    if (!v_email($in['email'])) $errors['email'] = 'Please enter a valid email address.';
    if (!v_phone($in['phone'])) $errors['phone'] = 'Please enter a valid phone number.';
    if (!v_len($in['address1'], 5, 255)) $errors['address1'] = 'Please enter your full delivery address.';
    if (!v_len($in['city'], 2, 100)) $errors['city'] = 'Please enter your city.';
    if ($in['province'] !== '' && !in_array($in['province'], shipping_provinces(), true)) $errors['province'] = 'Please choose a province.';
    if ($in['postal_code'] !== '' && !preg_match('/^[0-9A-Za-z \-]{3,10}$/', $in['postal_code'])) $errors['postal_code'] = 'Please enter a valid postal code.';
    if (!post('terms')) $errors['terms'] = 'Please accept the terms to continue.';
    $in['method'] = in_list($in['method'], ['standard', 'express'], 'standard');

    $createAccount = !$customer && post('create_account') && setting('allow_checkout_registration');
    $pw = (string)($_POST['password'] ?? '');
    if ($createAccount) {
        if ($e = v_password($pw)) $errors['password'] = $e;
        elseif (db_val('SELECT id FROM customers WHERE email = ?', [$in['email']])) $errors['password'] = 'An account with this email already exists — please sign in instead, or untick "create account".';
    }

    $token = (string)post('checkout_token');
    if (!preg_match('/^[a-f0-9]{64}$/', $token) || !hash_equals(checkout_token(), $token)) {
        // A stale token is fine if it already produced an order (double-click / back button).
        $done = preg_match('/^[a-f0-9]{64}$/', $token) ? db_one('SELECT * FROM orders WHERE checkout_token = ?', [$token]) : null;
        if ($done) redirect('order/' . $done['order_number'] . '?key=' . $done['lookup_key']);
        $errors['form'] = 'This checkout form has expired. Please review your details and submit again.';
    }

    if (!$errors) {
        try {
            $customerId = $customer['id'] ?? null;
            $order = checkout_place_order($in + [
                'coupon' => cart_coupon(), 'custom_terms' => (bool)post('custom_terms'),
                'checkout_token' => $token, 'customer_id' => $customerId ? (int)$customerId : null,
            ]);
            unset($_SESSION['checkout_token']);
            clear_old();
            if ($createAccount && !db_val('SELECT id FROM customers WHERE email = ?', [$in['email']])) {
                // Account is created only after the order succeeds, then linked to it.
                $customerId = customer_create($in['name'], $in['email'], $in['phone'], $pw);
                db_exec('UPDATE orders SET customer_id = ? WHERE id = ?', [$customerId, (int)$order['id']]);
                customer_login(db_one('SELECT * FROM customers WHERE id = ?', [(int)$customerId]));
            }
            if ($customerId && post('save_address') && !db_val('SELECT id FROM customer_addresses WHERE customer_id = ? AND address_line1 = ? AND city = ?', [(int)$customerId, $in['address1'], $in['city']])) {
                db_insert('INSERT INTO customer_addresses (customer_id, label, full_name, phone, address_line1, address_line2, city, province, postal_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [(int)$customerId, 'Home', $in['name'], $in['phone'], $in['address1'], $in['address2'] ?: null, $in['city'], $in['province'] ?: null, $in['postal_code'] ?: null, $addresses ? 0 : 1]);
            }
            order_notify_placed((int)$order['id']);
            $_SESSION['last_order'] = $order['order_number'];
            if ($order['payment_method'] === 'cod') {
                redirect('order/' . $order['order_number'] . '?key=' . $order['lookup_key'] . '&placed=1');
            }
            redirect('payment/start/go/' . $order['order_number'] . '?key=' . $order['lookup_key']);
        } catch (InvalidArgumentException $e) {
            $errors['form'] = $e->getMessage();
            $lines = cart_lines();
        }
    }
}

$ctx = ['city' => $in['city'], 'province' => $in['province'], 'method' => $in['method'], 'payment' => $in['payment'], 'coupon' => cart_coupon(),
        'email' => $in['email'], 'customer_id' => customer_id(), 'estimate_default' => true];
$totals = checkout_totals($lines, $ctx);
$methods = payment_methods_for_checkout($totals['zone'], $totals['grand_total']);
$hasCustom = cart_has_made_to_order($lines);
$cities = shipping_known_cities();

seo_set(['title' => 'Checkout', 'noindex' => true]);
$bodyClass = 'page-checkout';
require ROOT_PATH . '/templates/header.php';
$err = fn($k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
$inv = fn($k) => isset($errors[$k]) ? ' is-invalid' : '';
?>
<section class="page-section checkout">
  <div class="container-eb">
    <h1 class="page-title text-center">Checkout</h1>
    <?php if (!$customer): ?><p class="text-center text-muted">Returning customer? <a href="<?= e(url('account/login?return=' . urlencode(url('checkout')))) ?>">Sign in</a> for faster checkout — or continue as a guest.</p><?php endif; ?>
    <?php if (!empty($errors['form'])): ?><div class="alert alert-danger"><?= e($errors['form']) ?></div><?php endif; ?>
    <?php if ($errors && empty($errors['form'])): ?><div class="alert alert-danger">Please check the highlighted fields.</div><?php endif; ?>

    <form method="post" class="row g-5" id="checkoutForm" data-checkout novalidate>
      <?= csrf_field() ?>
      <input type="hidden" name="checkout_token" value="<?= e(checkout_token()) ?>">
      <div class="col-lg-7">
        <div class="co-block">
          <h2 class="co-title"><span>1</span> Contact</h2>
          <div class="row g-3">
            <div class="col-md-6"><label class="form-label" for="email">Email</label><input class="form-control<?= $inv('email') ?>" type="email" id="email" name="email" value="<?= e($in['email']) ?>" required maxlength="190" autocomplete="email"><?= $err('email') ?></div>
            <div class="col-md-6"><label class="form-label" for="phone">Phone</label><input class="form-control<?= $inv('phone') ?>" type="tel" id="phone" name="phone" value="<?= e($in['phone']) ?>" required maxlength="30" autocomplete="tel" placeholder="03xx xxxxxxx"><?= $err('phone') ?></div>
          </div>
        </div>

        <div class="co-block">
          <h2 class="co-title"><span>2</span> Delivery address</h2>
          <?php if ($addresses): ?>
            <div class="saved-addresses mb-3">
              <?php foreach ($addresses as $a): ?>
                <button type="button" class="saved-address" data-fill-address='<?= e(json_encode(['name' => $a['full_name'], 'phone' => $a['phone'], 'address1' => $a['address_line1'], 'address2' => $a['address_line2'], 'city' => $a['city'], 'province' => $a['province'], 'postal_code' => $a['postal_code']])) ?>'>
                  <strong><?= e($a['label'] ?: $a['full_name']) ?></strong><span><?= e($a['address_line1']) ?>, <?= e($a['city']) ?></span>
                </button>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
          <div class="row g-3">
            <div class="col-12"><label class="form-label" for="name">Full name</label><input class="form-control<?= $inv('name') ?>" id="name" name="name" value="<?= e($in['name']) ?>" required maxlength="120" autocomplete="name"><?= $err('name') ?></div>
            <div class="col-12"><label class="form-label" for="address1">Address</label><input class="form-control<?= $inv('address1') ?>" id="address1" name="address1" value="<?= e($in['address1']) ?>" required maxlength="255" autocomplete="address-line1" placeholder="House / street / area"><?= $err('address1') ?></div>
            <div class="col-12"><label class="form-label" for="address2">Apartment, landmark (optional)</label><input class="form-control" id="address2" name="address2" value="<?= e($in['address2']) ?>" maxlength="255" autocomplete="address-line2"></div>
            <div class="col-md-5"><label class="form-label" for="city">City</label><input class="form-control<?= $inv('city') ?>" id="city" name="city" value="<?= e($in['city']) ?>" required maxlength="100" list="cityList" autocomplete="address-level2" data-shipping-input><?= $err('city') ?>
              <datalist id="cityList"><?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
            <div class="col-md-4"><label class="form-label" for="province">Province / region</label><select class="form-select<?= $inv('province') ?>" id="province" name="province" data-shipping-input><option value="">Select</option>
              <?php foreach (shipping_provinces() as $p): ?><option<?= $in['province'] === $p ? ' selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select><?= $err('province') ?></div>
            <div class="col-md-3"><label class="form-label" for="postal_code">Postal code</label><input class="form-control<?= $inv('postal_code') ?>" id="postal_code" name="postal_code" value="<?= e($in['postal_code']) ?>" maxlength="10" autocomplete="postal-code"><?= $err('postal_code') ?></div>
            <div class="col-12"><label class="form-label" for="notes">Order notes (optional)</label><textarea class="form-control" id="notes" name="notes" rows="2" maxlength="1000" placeholder="Delivery instructions, gift message…"><?= e($in['notes']) ?></textarea></div>
            <?php if ($customer): ?><div class="col-12"><label class="form-check"><input type="checkbox" class="form-check-input" name="save_address" value="1"> <span class="form-check-label">Save this address to my account</span></label></div><?php endif; ?>
          </div>
        </div>

        <div class="co-block">
          <h2 class="co-title"><span>3</span> Delivery method</h2>
          <div id="shippingOptions" class="option-list">
            <?php include ROOT_PATH . '/templates/checkout-shipping.php'; ?>
          </div>
        </div>

        <div class="co-block">
          <h2 class="co-title"><span>4</span> Payment</h2>
          <div id="paymentOptions" class="option-list">
            <?php include ROOT_PATH . '/templates/checkout-payment.php'; ?>
          </div>
          <?= $err('payment') ?>
        </div>

        <?php if (!$customer && setting('allow_checkout_registration')): ?>
        <div class="co-block">
          <label class="form-check"><input type="checkbox" class="form-check-input" name="create_account" value="1" data-toggle-target="#pwWrap"<?= post('create_account') ? ' checked' : '' ?>> <span class="form-check-label">Create an account for faster checkout and order tracking (optional)</span></label>
          <div id="pwWrap" class="mt-3"<?= post('create_account') ? '' : ' hidden' ?>><label class="form-label" for="password">Choose a password</label><input type="password" class="form-control<?= $inv('password') ?>" id="password" name="password" minlength="8" autocomplete="new-password"><?= $err('password') ?><div class="form-text">At least 8 characters with letters and numbers.</div></div>
        </div>
        <?php endif; ?>

        <div class="co-block">
          <?php if ($hasCustom): ?>
            <div class="custom-confirm">
              <h3 class="h6"><i class="bi bi-scissors"></i> Made-to-order items in your bag</h3>
              <p class="small mb-2">Production adds up to <?= cart_max_lead_days($lines) ?> days before dispatch.</p>
              <label class="form-check"><input type="checkbox" class="form-check-input" name="custom_terms" value="1" required> <span class="form-check-label"><?= e(setting('custom_order_terms')) ?></span></label>
            </div>
          <?php endif; ?>
          <label class="form-check mt-2"><input type="checkbox" class="form-check-input<?= $inv('terms') ?>" name="terms" value="1" required> <span class="form-check-label"><?= e(setting('checkout_terms_text')) ?> <a href="<?= e(url('terms-conditions')) ?>" target="_blank">Terms</a> · <a href="<?= e(url('returns-exchanges')) ?>" target="_blank">Returns</a></span></label>
          <?= $err('terms') ?>
        </div>
      </div>

      <div class="col-lg-5">
        <aside class="co-summary">
          <h2 class="co-title">Order summary</h2>
          <div class="co-lines">
            <?php foreach ($lines as $l): ?>
              <div class="co-line">
                <span class="co-img"><img src="<?= e(img_url($l['image'])) ?>" alt="" loading="lazy"><span class="co-qty"><?= (int)$l['qty'] ?></span></span>
                <span class="co-name"><?= e($l['name']) ?><small><?= e($l['variant_label']) ?><?= $l['customised'] ? ' · Customised' : '' ?></small></span>
                <span class="co-amt"><?= money($l['line_total']) ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div id="checkoutTotals"><?php include ROOT_PATH . '/templates/checkout-totals.php'; ?></div>
          <button type="submit" class="btn btn-eb btn-primary-eb w-100 btn-lg mt-3" data-place-order>Place order</button>
          <p class="secure-note"><i class="bi bi-shield-lock"></i> Prices, stock and delivery are confirmed on our server when you place the order. Card details are entered only on the payment provider's secure page.</p>
        </aside>
      </div>
    </form>
  </div>
</section>
<?php clear_old(); require ROOT_PATH . '/templates/footer.php';
