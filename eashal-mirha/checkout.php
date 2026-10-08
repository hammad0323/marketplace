<?php
require __DIR__ . '/includes/bootstrap.php';

$c = customer();
if (!$c && setting('guest_checkout', '1') !== '1') {
    $_SESSION['after_login'] = url('checkout');
    flash('info', 'Please log in or create an account to checkout.');
    redirect('login');
}
if (!cart_count()) {
    redirect('cart');
}

$methods = payment_methods();
$zones = rows('SELECT * FROM shipping_zones WHERE status = 1 ORDER BY city');
$errors = [];
$old = [
    'name' => $c['name'] ?? '', 'email' => $c['email'] ?? '', 'phone' => $c['phone'] ?? '',
    'address' => $c['address'] ?? '', 'city' => $c['city'] ?? '', 'postal_code' => '', 'notes' => '',
    'payment' => array_key_first($methods) ?? 'cod', 'txn_id' => '',
];

if (is_post()) {
    require_csrf();
    foreach ($old as $k => $v) {
        if ($k !== 'txn_id') {
            $old[$k] = mb_substr(is_string(post($k)) ? post($k) : '', 0, $k === 'notes' ? 1000 : 255);
        }
    }
    $tids = is_array($_POST['txn_id'] ?? null) ? $_POST['txn_id'] : [];
    $old['txn_id'] = mb_substr(trim((string)($tids[$old['payment']] ?? '')), 0, 120);
    if ($old['name'] === '') $errors['name'] = 'Please enter your full name.';
    if (!preg_match('~^[0-9+\-\s()]{10,20}$~', $old['phone'])) $errors['phone'] = 'Please enter a valid phone number.';
    if ($old['email'] !== '' && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email.';
    if (mb_strlen($old['address']) < 8) $errors['address'] = 'Please enter your complete address.';
    if ($old['city'] === '') $errors['city'] = 'Please enter your city.';
    if (!isset($methods[$old['payment']])) $errors['payment'] = 'Please choose a payment method.';

    $mode = isset($methods[$old['payment']]) ? $methods[$old['payment']]['mode'] : 'cod';
    if ($mode === 'manual' && $old['txn_id'] === '') {
        $errors['txn_id'] = 'Please enter the Transaction ID of your payment.';
    }
    $codMax = (float)setting('pay_cod_max', 0);
    $t = cart_totals($old['city'], $old['payment']);
    if ($old['payment'] === 'cod' && $codMax > 0 && $t['total'] > $codMax) {
        $errors['payment'] = 'Cash on Delivery is available for orders up to ' . money($codMax) . '. Please choose another method.';
    }

    $createAccount = !$c && post('create_account') === '1';
    if ($createAccount) {
        if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'An email is required to create an account.';
        elseif (val('SELECT COUNT(*) FROM customers WHERE email = ?', [$old['email']])) $errors['email'] = 'An account with this email already exists — please log in.';
        if (strlen((string)post('password')) < 6) $errors['password'] = 'Password must be at least 6 characters.';
    }

    // Stock check
    foreach ($t['items'] as $it) {
        if ($it['qty'] > (int)$it['product']['stock']) {
            $errors['stock'] = 'Only ' . (int)$it['product']['stock'] . ' left of “' . $it['product']['name'] . '”. Please update your bag.';
        }
    }

    if (!$errors && $t['items']) {
        $proof = null;
        $proofField = 'payment_proof_' . $old['payment'];
        if ($mode === 'manual' && !empty($_FILES[$proofField]['name'])) {
            $proof = upload_image($_FILES[$proofField], 'payments');
        }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            if ($createAccount) {
                q('INSERT INTO customers (name, email, phone, password, address, city) VALUES (?, ?, ?, ?, ?, ?)',
                    [$old['name'], $old['email'], $old['phone'], password_hash((string)post('password'), PASSWORD_DEFAULT), $old['address'], $old['city']]);
                $_SESSION['customer_id'] = (int)$pdo->lastInsertId();
                session_regenerate_id(true);
            }
            $orderNo = new_order_no();
            $key = bin2hex(random_bytes(10));
            q('INSERT INTO orders (order_no, access_key, customer_id, name, email, phone, address, city, postal_code, notes, subtotal, shipping, discount, payment_fee, total, coupon_code, payment_method, payment_status, status, txn_id, payment_proof, ip, updated_at)
               VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())', [
                $orderNo, $key, $_SESSION['customer_id'] ?? null, $old['name'], $old['email'], $old['phone'], $old['address'], $old['city'], $old['postal_code'], $old['notes'],
                $t['subtotal'], $t['shipping'], $t['discount'], $t['fee'], $t['total'], $t['coupon'] ?: null, $old['payment'],
                $mode === 'manual' ? 'pending_verification' : 'unpaid', 'pending', $mode === 'manual' ? $old['txn_id'] : null, $proof, client_ip(),
            ]);
            $orderId = (int)$pdo->lastInsertId();
            foreach ($t['items'] as $it) {
                q('INSERT INTO order_items (order_id, product_id, name, sku, size, color, price, qty, image) VALUES (?,?,?,?,?,?,?,?,?)',
                    [$orderId, $it['product']['id'], $it['product']['name'], $it['product']['sku'], $it['size'], $it['color'], $it['price'], $it['qty'], $it['product']['image']]);
                q('UPDATE products SET stock = GREATEST(stock - ?, 0), sales_count = sales_count + ? WHERE id = ?', [$it['qty'], $it['qty'], $it['product']['id']]);
            }
            if ($t['coupon']) {
                q('UPDATE coupons SET used = used + 1 WHERE code = ?', [$t['coupon']]);
            }
            order_add_history($orderId, 'pending', 'Order placed via ' . payment_label($old['payment']) . ($mode === 'manual' ? ' — TID ' . $old['txn_id'] : '') . '.');
            $pdo->commit();
        } catch (Throwable $ex) {
            $pdo->rollBack();
            if (DEBUG) throw $ex;
            $errors['stock'] = 'We could not place your order. Please try again.';
        }

        if (!$errors) {
            $_SESSION['my_orders'][] = $orderNo;
            cart_clear();
            $order = row('SELECT * FROM orders WHERE id = ?', [$orderId]);
            notify_new_order($order);
            if ($mode === 'gateway') {
                start_gateway_payment($order);
            }
            redirect('order-success/' . $orderNo . '?k=' . $key);
        }
    }
}

$t = cart_totals($old['city'], $old['payment']);
$seo = ['title' => 'Checkout | ' . setting('site_name'), 'noindex' => true];
$bodyClass = 'checkout-page';
require ROOT . '/includes/header.php';

function field_error(array $errors, string $k): string
{
    return isset($errors[$k]) ? '<small class="field-error">' . e($errors[$k]) . '</small>' : '';
}
?>
<section class="page-title">
  <div class="container"><span class="ornament">✦</span><h1 class="section-title">Checkout</h1>
    <ol class="steps"><li class="done">Bag</li><li class="active">Details & Payment</li><li>Confirmation</li></ol>
  </div>
</section>

<section class="section section--tight">
  <div class="container">
    <?php if ($errors): ?><div class="alert alert-error">Please correct the highlighted fields.<?= isset($errors['stock']) ? ' ' . e($errors['stock']) : '' ?></div><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="checkout-layout" id="checkoutForm" novalidate>
      <?= csrf_field() ?>
      <div class="checkout-main">
        <?php if (!$c): ?>
          <div class="checkout-login" data-reveal>
            Returning customer? <a class="link-underline" href="<?= url('login?next=checkout') ?>">Log in</a> for a faster checkout — or continue as a guest below.
          </div>
        <?php endif; ?>
        <fieldset class="panel" data-reveal>
          <legend>Contact & Delivery</legend>
          <div class="form-grid">
            <label class="span-2">Full Name *<input type="text" name="name" value="<?= e($old['name']) ?>" required autocomplete="name"><?= field_error($errors, 'name') ?></label>
            <label>Phone *<input type="tel" name="phone" value="<?= e($old['phone']) ?>" placeholder="03XX XXXXXXX" required autocomplete="tel"><?= field_error($errors, 'phone') ?></label>
            <label>Email<input type="email" name="email" value="<?= e($old['email']) ?>" autocomplete="email"><?= field_error($errors, 'email') ?></label>
            <label class="span-2">Complete Address *<input type="text" name="address" value="<?= e($old['address']) ?>" placeholder="House #, street, area" required autocomplete="street-address"><?= field_error($errors, 'address') ?></label>
            <label>City *
              <input type="text" name="city" list="cityList" value="<?= e($old['city']) ?>" required data-city autocomplete="address-level2">
              <datalist id="cityList"><?php foreach ($zones as $z): ?><option value="<?= e($z['city']) ?>"><?php endforeach; ?></datalist>
              <?= field_error($errors, 'city') ?>
            </label>
            <label>Postal Code<input type="text" name="postal_code" value="<?= e($old['postal_code']) ?>" autocomplete="postal-code"></label>
            <label class="span-2">Order Notes<textarea name="notes" rows="2" placeholder="Special instructions for delivery or stitching"><?= e($old['notes']) ?></textarea></label>
          </div>
          <?php if (!$c): ?>
            <label class="check"><input type="checkbox" name="create_account" value="1" data-toggle="#accPass" <?= post('create_account') === '1' ? 'checked' : '' ?>> Create an account for faster checkout next time</label>
            <div id="accPass" class="<?= post('create_account') === '1' ? '' : 'hidden' ?>">
              <label>Choose a Password<input type="password" name="password" minlength="6" autocomplete="new-password"><?= field_error($errors, 'password') ?></label>
            </div>
          <?php endif; ?>
        </fieldset>

        <fieldset class="panel" data-reveal>
          <legend>Payment Method</legend>
          <?= field_error($errors, 'payment') ?>
          <div class="pay-options">
            <?php foreach ($methods as $m): ?>
              <label class="pay-option">
                <input type="radio" name="payment" value="<?= e($m['key']) ?>" <?= $old['payment'] === $m['key'] ? 'checked' : '' ?> data-pay-mode="<?= e($m['mode']) ?>">
                <div class="pay-option__box">
                  <div class="pay-option__head"><span class="pay-option__icon"><?= icon($m['icon'], 22) ?></span><strong><?= e($m['title']) ?></strong>
                    <?php if ($m['key'] === 'cod' && (float)setting('pay_cod_fee', 0) > 0): ?><em>+ <?= money(setting('pay_cod_fee')) ?> fee</em><?php endif; ?>
                  </div>
                  <div class="pay-option__body">
                    <?php if ($m['note']): ?><p><?= e($m['note']) ?></p><?php endif; ?>
                    <?php if ($m['mode'] === 'manual'): ?>
                      <dl class="acct">
                        <?php foreach (payment_account_lines($m['key']) as $lbl => $v): ?><div><dt><?= e($lbl) ?></dt><dd><?= e($v) ?> <button type="button" class="copy" data-copy="<?= e($v) ?>">Copy</button></dd></div><?php endforeach; ?>
                        <div><dt>Amount</dt><dd><strong data-total><?= money($t['total']) ?></strong></dd></div>
                      </dl>
                      <div class="form-grid">
                        <label>Transaction ID (TID) *<input type="text" name="txn_id[<?= e($m['key']) ?>]" value="<?= $old['payment'] === $m['key'] ? e($old['txn_id']) : '' ?>" data-txn></label>
                        <label>Payment Screenshot (optional)<input type="file" name="payment_proof_<?= e($m['key']) ?>" accept="image/*" data-proof></label>
                      </div>
                      <?= $old['payment'] === $m['key'] ? field_error($errors, 'txn_id') : '' ?>
                    <?php elseif ($m['mode'] === 'gateway'): ?>
                      <p class="muted">You will be redirected to the secure <?= e($m['key'] === 'card' ? ucfirst(setting('pay_card_mode')) : $m['title']) ?> payment page to complete your payment.</p>
                    <?php endif; ?>
                  </div>
                </div>
              </label>
            <?php endforeach; ?>
          </div>
        </fieldset>
      </div>

      <aside class="summary checkout-summary" data-reveal data-delay="1">
        <h3>Your Order</h3>
        <div class="summary-items">
          <?php foreach ($t['items'] as $it): ?>
            <div class="summary-item">
              <span class="summary-item__img"><img src="<?= e(img($it['product']['image'])) ?>" alt=""><i><?= $it['qty'] ?></i></span>
              <span class="summary-item__name"><?= e($it['product']['name']) ?><small><?= e(trim($it['size'] . ($it['color'] ? ' · ' . $it['color'] : ''), ' ·')) ?></small></span>
              <strong><?= money($it['total']) ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="summary__row"><span>Subtotal</span><span><?= money($t['subtotal']) ?></span></div>
        <?php if ($t['discount'] > 0): ?><div class="summary__row text-gold"><span>Discount (<?= e($t['coupon']) ?>)</span><span>− <?= money($t['discount']) ?></span></div><?php endif; ?>
        <div class="summary__row"><span>Delivery</span><span data-shipping><?= $t['shipping'] > 0 ? money($t['shipping']) : 'Free' ?></span></div>
        <div class="summary__row<?= $t['fee'] > 0 ? '' : ' hidden' ?>" data-fee-row><span>COD Fee</span><span data-fee><?= money($t['fee']) ?></span></div>
        <div class="summary__row summary__total"><span>Total</span><span data-total><?= money($t['total']) ?></span></div>
        <?php if (!$t['coupon']): ?><p class="muted sm">Have a coupon? <a class="link-underline sm" href="<?= url('cart') ?>">Apply it in your bag</a></p><?php endif; ?>
        <button class="btn btn-gold btn-block btn-lg" type="submit">Place Order</button>
        <div class="secure-note"><?= icon('shield', 16) ?> Your information is encrypted & secure</div>
      </aside>
    </form>
  </div>
</section>
<?php require ROOT . '/includes/footer.php';
