<?php
require_once __DIR__ . '/includes/functions.php';

$items = get_cart_items();
if (!$items) redirect(BASE_URL . '/cart.php');

$guestCheckoutEnabled = get_setting('guest_checkout_enabled', '1') === '1';
if (!$guestCheckoutEnabled && !customer_logged_in()) {
    flash_set('danger', 'Please login to continue with checkout.');
    redirect(BASE_URL . '/login.php?redirect=checkout.php');
}

$customer = current_customer();
$shippingMethods = mysqli_query($mysqli, "SELECT * FROM shipping_methods WHERE status = 'active' ORDER BY sort_order");
$shippingList = [];
while ($s = mysqli_fetch_assoc($shippingMethods)) $shippingList[] = $s;

$paymentMethods = mysqli_query($mysqli, "SELECT * FROM payment_methods WHERE is_enabled = 1 ORDER BY sort_order");
$paymentList = [];
while ($p = mysqli_fetch_assoc($paymentMethods)) $paymentList[] = $p;

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $postalCode = trim($_POST['postal_code'] ?? '');
    $country = trim($_POST['country'] ?? 'Pakistan');
    $note = trim($_POST['note'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';

    $validPayment = array_filter($paymentList, fn($p) => $p['code'] === $paymentMethod);

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $phone === '' || $address === '' || $city === '' || !$validPayment) {
        $error = 'Please fill in all required fields and select a valid payment method.';
    } else {
        // Recompute totals server-side
        $items = get_cart_items();
        $subtotal = cart_totals($items);

        $shippingFee = 0;
        $matched = null;
        foreach ($shippingList as $s) { if (strcasecmp($s['city'], $city) === 0) { $matched = $s; break; } }
        if (!$matched) { foreach ($shippingList as $s) { if (strcasecmp($s['city'], 'Other Cities') === 0) { $matched = $s; break; } } }
        if ($matched) $shippingFee = (float)$matched['fee'];
        $freeThreshold = (float)get_setting('free_shipping_threshold', 0);
        if ($freeThreshold > 0 && $subtotal >= $freeThreshold) $shippingFee = 0;

        $discount = 0;
        $couponCode = null;
        if (!empty($_SESSION['coupon_code'])) {
            $stmt = mysqli_prepare($mysqli, "SELECT * FROM coupons WHERE code = ? AND status='active'");
            mysqli_stmt_bind_param($stmt, 's', $_SESSION['coupon_code']);
            mysqli_stmt_execute($stmt);
            $coupon = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if ($coupon && $subtotal >= $coupon['min_order']) {
                $usageCheck = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT COUNT(*) c FROM coupon_usage WHERE coupon_id = {$coupon['id']} AND customer_email = '" . mysqli_real_escape_string($mysqli, $email) . "'"));
                if (!$coupon['per_customer_limit'] || $usageCheck['c'] < $coupon['per_customer_limit']) {
                    $discount = $coupon['type'] === 'percentage' ? $subtotal * ($coupon['value'] / 100) : $coupon['value'];
                    if ($coupon['max_discount']) $discount = min($discount, $coupon['max_discount']);
                    $couponCode = $coupon['code'];
                }
            }
        }

        $taxPercent = (float)get_setting('tax_percent', 0);
        $tax = ($subtotal - $discount) * ($taxPercent / 100);
        $total = max(0, $subtotal - $discount) + $shippingFee + $tax;

        // Stock check
        $stockError = false;
        foreach ($items as $it) {
            $available = $it['variation_id'] ? $it['variation_stock'] : $it['product_stock'];
            if ($it['qty'] > $available) { $stockError = true; break; }
        }

        if ($stockError) {
            $error = 'One or more items in your cart exceed available stock. Please review your cart.';
        } else {
            $orderNumber = generate_order_number();
            $custId = customer_logged_in() ? $_SESSION['customer_id'] : null;
            $stmt = mysqli_prepare($mysqli, "INSERT INTO orders (order_number, customer_id, guest_name, guest_email, guest_phone, shipping_address, shipping_city, shipping_state, shipping_postal_code, shipping_country, subtotal, discount, shipping_fee, tax, total, coupon_code, payment_method, customer_note) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'sissssssssdddddsss', $orderNumber, $custId, $name, $email, $phone, $address, $city, $state, $postalCode, $country, $subtotal, $discount, $shippingFee, $tax, $total, $couponCode, $paymentMethod, $note);
            mysqli_stmt_execute($stmt);
            $orderId = mysqli_insert_id($mysqli);

            foreach ($items as $it) {
                $label = null;
                if ($it['variation_id']) {
                    $labelRes = mysqli_query($mysqli, "SELECT av.value FROM product_variation_values pvv JOIN attribute_values av ON av.id = pvv.attribute_value_id WHERE pvv.variation_id = {$it['variation_id']}");
                    $parts = [];
                    while ($l = mysqli_fetch_assoc($labelRes)) $parts[] = $l['value'];
                    $label = implode(' / ', $parts);
                }
                $sku = $it['sku'] ?? '';
                $stmt = mysqli_prepare($mysqli, "INSERT INTO order_items (order_id, product_id, variation_id, product_name, variation_label, sku, price, qty, subtotal, image) VALUES (?,?,?,?,?,?,?,?,?,?)");
                mysqli_stmt_bind_param($stmt, 'iiisssdids', $orderId, $it['product_id'], $it['variation_id'], $it['name'], $label, $sku, $it['unit_price'], $it['qty'], $it['line_total'], $it['image']);
                mysqli_stmt_execute($stmt);

                if ($it['variation_id']) {
                    mysqli_query($mysqli, "UPDATE product_variations SET stock_qty = GREATEST(0, stock_qty - {$it['qty']}) WHERE id = {$it['variation_id']}");
                } else {
                    mysqli_query($mysqli, "UPDATE products SET stock_qty = GREATEST(0, stock_qty - {$it['qty']}) WHERE id = {$it['product_id']}");
                }
            }

            mysqli_query($mysqli, "INSERT INTO order_status_history (order_id, status, note) VALUES ($orderId, 'pending', 'Order placed')");

            if ($couponCode) {
                mysqli_query($mysqli, "UPDATE coupons SET used_count = used_count + 1 WHERE id = {$coupon['id']}");
                $stmt = mysqli_prepare($mysqli, "INSERT INTO coupon_usage (coupon_id, customer_email, order_id) VALUES (?,?,?)");
                mysqli_stmt_bind_param($stmt, 'isi', $coupon['id'], $email, $orderId);
                mysqli_stmt_execute($stmt);
            }

            // Clear cart
            $types = ''; $params = [];
            $clause = cart_owner_clause($types, $params);
            $stmt = mysqli_prepare($mysqli, "DELETE FROM cart_items WHERE $clause");
            mysqli_stmt_bind_param($stmt, $types, ...$params);
            mysqli_stmt_execute($stmt);
            unset($_SESSION['coupon_code']);

            $_SESSION['last_order_id'] = $orderId;
            redirect(BASE_URL . '/order_success.php?id=' . $orderId);
        }
    }
}

$subtotal = cart_totals($items);
$discountPreview = 0;
if (!empty($_SESSION['coupon_code'])) {
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM coupons WHERE code = ? AND status='active'");
    mysqli_stmt_bind_param($stmt, 's', $_SESSION['coupon_code']);
    mysqli_stmt_execute($stmt);
    $c = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($c && $subtotal >= $c['min_order']) {
        $discountPreview = $c['type'] === 'percentage' ? $subtotal * ($c['value'] / 100) : $c['value'];
        if ($c['max_discount']) $discountPreview = min($discountPreview, $c['max_discount']);
    }
}

$pageTitle = 'Checkout | ' . get_setting('store_name');
require_once __DIR__ . '/includes/header.php';
?>
<div class="container section-tight">
  <h1 class="h3 font-serif mb-4">Checkout</h1>
  <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
  <?php if (!customer_logged_in()): ?>
    <div class="alert alert-light border">Already have an account? <a href="<?= BASE_URL ?>/login.php?redirect=checkout.php">Login</a> for faster checkout, or continue as guest below.</div>
  <?php endif; ?>
  <form method="post" class="row g-4">
    <?= csrf_field() ?>
    <div class="col-lg-7">
      <div class="summary-box mb-4">
        <h2 class="h6 mb-3"><span class="step-badge">1</span>Contact Information</h2>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Full Name *</label><input type="text" name="name" class="form-control" required value="<?= e($customer['name'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">Email *</label><input type="email" name="email" class="form-control" required value="<?= e($customer['email'] ?? '') ?>"></div>
          <div class="col-md-6"><label class="form-label">Phone *</label><input type="text" name="phone" class="form-control" required value="<?= e($customer['phone'] ?? '') ?>"></div>
        </div>
      </div>
      <div class="summary-box mb-4">
        <h2 class="h6 mb-3"><span class="step-badge">2</span>Shipping Address</h2>
        <div class="row g-3">
          <div class="col-12"><label class="form-label">Address *</label><input type="text" name="address" class="form-control" required></div>
          <div class="col-md-6">
            <label class="form-label">City *</label>
            <input type="text" name="city" list="cityList" class="form-control" required>
            <datalist id="cityList"><?php foreach ($shippingList as $s): ?><option value="<?= e($s['city']) ?>"><?php endforeach; ?></datalist>
          </div>
          <div class="col-md-6"><label class="form-label">Province / State</label><input type="text" name="state" class="form-control"></div>
          <div class="col-md-6"><label class="form-label">Postal Code</label><input type="text" name="postal_code" class="form-control"></div>
          <div class="col-md-6"><label class="form-label">Country</label><input type="text" name="country" class="form-control" value="Pakistan"></div>
          <div class="col-12"><label class="form-label">Order Notes (optional)</label><textarea name="note" class="form-control" rows="2"></textarea></div>
        </div>
      </div>
      <div class="summary-box">
        <h2 class="h6 mb-3"><span class="step-badge">3</span>Payment Method</h2>
        <?php foreach ($paymentList as $i => $pm): ?>
          <label class="payment-option d-block mb-2 <?= $i===0?'active':'' ?>">
            <input type="radio" name="payment_method" value="<?= e($pm['code']) ?>" <?= $i===0?'checked':'' ?> class="form-check-input me-2">
            <strong><?= e($pm['name']) ?></strong>
            <div class="small text-muted"><?= e($pm['description']) ?></div>
          </label>
        <?php endforeach; ?>
        <?php if (!$paymentList): ?><p class="text-danger small">No payment methods are currently enabled. Please contact the store.</p><?php endif; ?>
      </div>
    </div>
    <div class="col-lg-5">
      <div class="summary-box">
        <h2 class="h6 mb-3">Order Summary</h2>
        <?php foreach ($items as $it): ?>
          <div class="d-flex justify-content-between small mb-2">
            <span><?= e($it['name']) ?> &times; <?= (int)$it['qty'] ?></span>
            <span><?= format_price($it['line_total']) ?></span>
          </div>
        <?php endforeach; ?>
        <hr>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Subtotal</span><span><?= format_price($subtotal) ?></span></div>
        <?php if ($discountPreview > 0): ?><div class="d-flex justify-content-between mb-2 text-success"><span>Discount</span><span>- <?= format_price($discountPreview) ?></span></div><?php endif; ?>
        <div class="d-flex justify-content-between mb-2"><span class="text-muted">Shipping</span><span>Calculated at order placement</span></div>
        <div class="d-flex justify-content-between mb-3 fw-bold fs-5"><span>Total</span><span><?= format_price($subtotal - $discountPreview) ?>+</span></div>
        <button type="submit" class="btn-brand w-100" <?= !$paymentList?'disabled':'' ?>>Place Order</button>
      </div>
    </div>
  </form>
</div>
<script>
document.querySelectorAll('.payment-option').forEach(function(opt){
  opt.addEventListener('click', function(){
    document.querySelectorAll('.payment-option').forEach(function(o){ o.classList.remove('active'); });
    opt.classList.add('active');
  });
});
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
