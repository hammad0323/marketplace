<?php
/**
 * Checkout (guest or signed-in). All totals are recalculated server-side in order_place().
 */
seo_set(['title' => 'Checkout', 'noindex' => true]);
$customer = current_customer();
$errors = [];

if (is_post()) {
    csrf_check();
    if (!rate_limit('checkout:' . client_ip(), 15, 600)) {
        $errors[] = 'Too many attempts. Please wait a few minutes and try again.';
    }
    $in = [
        'name' => mb_substr(input('name', '', 'post'), 0, 120),
        'email' => strtolower(mb_substr(input('email', '', 'post'), 0, 190)),
        'phone' => mb_substr(input('phone', '', 'post'), 0, 30),
        'address_line1' => mb_substr(input('address_line1', '', 'post'), 0, 255),
        'address_line2' => mb_substr(input('address_line2', '', 'post'), 0, 255),
        'city' => mb_substr(input('city', '', 'post'), 0, 100),
        'region' => mb_substr(input('region', '', 'post'), 0, 100),
        'postal_code' => mb_substr(input('postal_code', '', 'post'), 0, 20),
        'notes' => mb_substr(input('notes', '', 'post'), 0, 1000),
        'payment_method' => input('payment_method', '', 'post'),
        'shipping_method' => in_array(input('shipping_method', 'standard', 'post'), ['express', 'urgent'], true) ? input('shipping_method', 'standard', 'post') : 'standard',
        'idempotency_key' => input('idempotency_key', '', 'post'),
    ];
    remember_input($_POST);
    if (mb_strlen($in['name']) < 3) $errors[] = 'Please enter your full name.';
    if (!valid_email($in['email'])) $errors[] = 'Please enter a valid email address.';
    if (!valid_phone($in['phone'])) $errors[] = 'Please enter a valid phone number.';
    if (mb_strlen($in['address_line1']) < 5) $errors[] = 'Please enter your complete delivery address.';
    if (mb_strlen($in['city']) < 2) $errors[] = 'Please enter your city.';
    if ($in['region'] === '' || !in_array($in['region'], pakistan_regions(), true)) $errors[] = 'Please choose your province / region.';
    if (!preg_match('/^[a-f0-9]{40}$/', $in['idempotency_key'])) $errors[] = 'Your checkout session expired. Please try again.';

    $createAccount = !$customer && input_bool('create_account');
    $password = (string) ($_POST['password'] ?? '');
    if ($createAccount) {
        if ($err = password_strong_enough($password)) {
            $errors[] = $err;
        } elseif (db_val('SELECT id FROM customers WHERE email = ?', [$in['email']])) {
            $errors[] = 'An account with this email already exists — please sign in, or untick "create an account".';
        }
    }
    if (!$customer && !setting_bool('guest_checkout', true)) {
        $errors[] = 'Please sign in or create an account to place an order.';
    }

    if (!$errors) {
        try {
            if ($createAccount) {
                [$first, $last] = array_pad(explode(' ', $in['name'], 2), 2, '');
                $cid = customer_create(['first_name' => $first, 'last_name' => $last, 'email' => $in['email'], 'phone' => $in['phone'], 'password' => $password, 'newsletter' => input_bool('newsletter')]);
                customer_login(['id' => $cid]);
            }
            $order = order_place($in);
            $_SESSION['my_orders'][] = $order['order_number'];
            $_SESSION['my_orders'] = array_slice(array_unique($_SESSION['my_orders']), -20);
            unset($_SESSION['checkout_keys']);
            clear_old();

            if ($customer || $createAccount) {
                $cid = customer_id();
                if (input_bool('save_address') && $cid && !db_val('SELECT 1 FROM customer_addresses WHERE customer_id = ? AND address_line1 = ? AND city = ?', [$cid, $in['address_line1'], $in['city']])) {
                    $hasDefault = db_val('SELECT 1 FROM customer_addresses WHERE customer_id = ? AND is_default = 1', [$cid]);
                    db_insert('customer_addresses', ['customer_id' => $cid, 'label' => 'Home', 'full_name' => $in['name'], 'phone' => $in['phone'], 'address_line1' => $in['address_line1'],
                        'address_line2' => nullable($in['address_line2']), 'city' => $in['city'], 'region' => $in['region'], 'postal_code' => nullable($in['postal_code']), 'is_default' => $hasDefault ? 0 : 1]);
                }
                db_exec('UPDATE orders SET customer_id = ? WHERE id = ? AND customer_id IS NULL', [$cid, (int) $order['id']]);
            }
            if (input_bool('newsletter')) {
                newsletter_subscribe($in['email'], 'checkout', 'Opted in at checkout');
            }
            if ($order['payment_method'] === 'cod') {
                if (empty($order['_existing'])) {
                    mail_order_confirmation($order);
                }
                redirect('order/' . $order['order_number'] . '?t=' . $order['access_token'] . '&placed=1');
            }
            redirect('checkout/pay/' . $order['order_number'] . '?t=' . $order['access_token']);
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$lines = cart_lines();
if (!$lines) {
    flash('info', 'Your bag is empty.');
    redirect('cart');
}
// One idempotency key per checkout page view; reused on resubmission of the same form.
$idemKey = old('idempotency_key') ?: random_token(20);
$addresses = $customer ? db_all('SELECT * FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC, id DESC', [(int) $customer['id']]) : [];
$def = $addresses[0] ?? null;
$val = fn(string $k, $fallback = '') => old($k, $fallback);
$city = $val('city', $def['city'] ?? '');
$region = $val('region', $def['region'] ?? '');
$pmCode = $val('payment_method', 'cod');
$methods = array_filter(payment_methods_all(), fn($m) => (int) $m['is_enabled'] || payment_method_state($m) !== 'disabled');
$cities = db_col("SELECT DISTINCT name FROM shipping_zone_locations WHERE location_type = 'city' ORDER BY name");

$bodyClass = 'page-checkout';
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad">
    <?= breadcrumbs_html([['Bag', url('cart')], ['Checkout', null]]) ?>
    <h1 class="uc-page-title">Checkout</h1>
    <?php if ($errors): ?>
        <div class="uc-alert uc-alert--error" role="alert"><ul class="mb-0"><?php foreach ($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <form method="post" class="uc-checkout" data-checkout-form novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="idempotency_key" value="<?= e($idemKey) ?>">
        <div class="uc-checkout-main">
            <fieldset class="uc-step">
                <legend><span>1</span> Contact</legend>
                <?php if (!$customer): ?>
                    <p class="small">Have an account? <a href="<?= e(url('account/login')) ?>?next=checkout">Sign in</a> for faster checkout. Otherwise continue as a guest.</p>
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-md-6"><label class="uc-field"><span>Email *</span><input type="email" name="email" required autocomplete="email" value="<?= e($val('email', $customer['email'] ?? '')) ?>"></label></div>
                    <div class="col-md-6"><label class="uc-field"><span>Phone *</span><input type="tel" name="phone" required autocomplete="tel" placeholder="03XX XXXXXXX" value="<?= e($val('phone', $def['phone'] ?? ($customer['phone'] ?? ''))) ?>"></label></div>
                </div>
            </fieldset>

            <fieldset class="uc-step">
                <legend><span>2</span> Delivery address</legend>
                <?php if ($addresses): ?>
                    <label class="uc-field"><span>Saved addresses</span>
                        <select data-saved-address class="form-select">
                            <option value="">Enter a new address</option>
                            <?php foreach ($addresses as $a): ?>
                                <option value="<?= e(json_encode(['name' => $a['full_name'], 'phone' => $a['phone'], 'address_line1' => $a['address_line1'], 'address_line2' => $a['address_line2'], 'city' => $a['city'], 'region' => $a['region'], 'postal_code' => $a['postal_code']])) ?>"<?= $a === $def && !old('address_line1') ? ' selected' : '' ?>>
                                    <?= e(($a['label'] ? $a['label'] . ' — ' : '') . $a['address_line1'] . ', ' . $a['city']) ?></option>
                            <?php endforeach; ?>
                        </select></label>
                <?php endif; ?>
                <div class="row g-3">
                    <div class="col-12"><label class="uc-field"><span>Full name *</span><input type="text" name="name" required autocomplete="name" value="<?= e($val('name', $def['full_name'] ?? ($customer ? trim($customer['first_name'] . ' ' . $customer['last_name']) : ''))) ?>"></label></div>
                    <div class="col-12"><label class="uc-field"><span>Address *</span><input type="text" name="address_line1" required autocomplete="address-line1" placeholder="House / flat no., street, area" value="<?= e($val('address_line1', $def['address_line1'] ?? '')) ?>"></label></div>
                    <div class="col-12"><label class="uc-field"><span>Apartment, landmark (optional)</span><input type="text" name="address_line2" autocomplete="address-line2" value="<?= e($val('address_line2', $def['address_line2'] ?? '')) ?>"></label></div>
                    <div class="col-md-5"><label class="uc-field"><span>City *</span><input type="text" name="city" required autocomplete="address-level2" list="ucCities" value="<?= e($city) ?>" data-quote-trigger></label>
                        <datalist id="ucCities"><?php foreach ($cities as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
                    <div class="col-md-4"><label class="uc-field"><span>Province / region *</span>
                        <select name="region" required class="form-select" data-quote-trigger>
                            <option value="">Select…</option>
                            <?php foreach (pakistan_regions() as $r): ?><option<?= $region === $r ? ' selected' : '' ?>><?= e($r) ?></option><?php endforeach; ?>
                        </select></label></div>
                    <div class="col-md-3"><label class="uc-field"><span>Postal code</span><input type="text" name="postal_code" autocomplete="postal-code" inputmode="numeric" value="<?= e($val('postal_code', $def['postal_code'] ?? '')) ?>"></label></div>
                </div>
                <?php if ($customer): ?>
                    <label class="uc-check mt-3"><input type="checkbox" name="save_address" value="1"<?= $addresses ? '' : ' checked' ?>> <span>Save this address to my account</span></label>
                <?php endif; ?>
            </fieldset>

            <fieldset class="uc-step">
                <legend><span>3</span> Delivery method</legend>
                <div data-shipping-methods><div class="uc-skeleton-line"></div></div>
            </fieldset>

            <fieldset class="uc-step">
                <legend><span>4</span> Payment</legend>
                <div class="uc-pay-list">
                    <?php foreach ($methods as $m):
                        $state = payment_method_state($m);
                        $usable = $state === 'ready' || ($state === 'testing' && current_admin());
                    ?>
                        <label class="uc-pay-opt<?= $usable ? '' : ' is-disabled' ?>" data-pay-opt="<?= e($m['code']) ?>">
                            <input type="radio" name="payment_method" value="<?= e($m['code']) ?>"<?= $usable ? '' : ' disabled' ?><?= $usable && $pmCode === $m['code'] ? ' checked' : '' ?> required>
                            <span class="uc-pay-body">
                                <strong><?= e($m['name']) ?><?php if ((float) $m['fee'] > 0): ?> <small>(+<?= e(money($m['fee'])) ?>)</small><?php endif; ?>
                                    <?php if ($state === 'testing' && $usable): ?><em class="uc-tag">Sandbox — admin only</em><?php endif; ?></strong>
                                <span><?= $usable ? e($m['description']) : 'Currently unavailable' ?></span>
                                <?php if ($m['code'] === 'cod'): ?><span class="uc-cod-note" data-cod-note hidden></span><?php endif; ?>
                            </span>
                            <i class="bi bi-<?= $m['code'] === 'cod' ? 'cash-stack' : ($m['code'] === 'card' ? 'credit-card' : 'phone') ?>"></i>
                        </label>
                    <?php endforeach; ?>
                </div>
                <p class="small text-muted mt-2"><i class="bi bi-shield-lock"></i> Online payments are completed on the provider's secure page. We never see or store your card number or PIN.</p>
            </fieldset>

            <fieldset class="uc-step">
                <legend><span>5</span> Additional information</legend>
                <label class="uc-field"><span>Order notes (optional)</span><textarea name="notes" rows="3" maxlength="1000" placeholder="Delivery instructions, gift message, or notes about custom items"><?= e($val('notes')) ?></textarea></label>
                <?php if (!$customer): ?>
                    <label class="uc-check"><input type="checkbox" name="create_account" value="1" data-toggle-target="#ucNewPassword"<?= old('create_account') ? ' checked' : '' ?>> <span>Create an account for faster checkout and order tracking</span></label>
                    <div id="ucNewPassword" class="mt-2"<?= old('create_account') ? '' : ' hidden' ?>>
                        <label class="uc-field"><span>Choose a password (8+ characters, letters &amp; numbers)</span><input type="password" name="password" autocomplete="new-password" minlength="8"></label>
                    </div>
                <?php endif; ?>
                <label class="uc-check"><input type="checkbox" name="newsletter" value="1"<?= old('newsletter') ? ' checked' : '' ?>> <span>Email me new arrivals and offers (unsubscribe anytime)</span></label>
            </fieldset>
        </div>

        <aside class="uc-checkout-side">
            <div class="uc-summary-card">
                <h2 class="h5">Order summary</h2>
                <ul class="uc-summary-items">
                    <?php foreach ($lines as $l): ?>
                        <li>
                            <span class="uc-summary-img"><img src="<?= e(img_sized($l['image'], 200)) ?>" alt="" loading="lazy"><em><?= $l['qty'] ?></em></span>
                            <span class="uc-summary-name"><?= e($l['name']) ?><?php if ($l['variant_label']): ?><small><?= e($l['variant_label']) ?></small><?php endif; ?>
                                <?php if (cart_has_customization($l)): ?><small>+ customisation</small><?php endif; ?>
                                <?php if ($l['error']): ?><small class="text-danger"><?= e($l['error']) ?></small><?php endif; ?></span>
                            <span><?= e(money($l['line_total'])) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="uc-coupon" data-coupon-form>
                    <input type="text" name="coupon_code" placeholder="Coupon code" value="<?= e(cart_coupon_code() ?? '') ?>" aria-label="Coupon code" data-coupon-input>
                    <button type="button" class="uc-btn uc-btn--outline uc-btn--sm" data-coupon-apply>Apply</button>
                </div>
                <div data-checkout-totals><div class="uc-skeleton-list"><span></span><span></span></div></div>
                <button type="submit" class="uc-btn uc-btn--block uc-btn--lg" data-place-order><span>Place Order</span></button>
                <p class="small text-muted mt-2 mb-0">By placing your order you agree to our <a href="<?= e(url('terms-and-conditions')) ?>">Terms</a> and <a href="<?= e(url('privacy-policy')) ?>">Privacy Policy</a>.</p>
            </div>
        </aside>
    </form>
</section>
<?php require UC_ROOT . '/includes/footer.php';
