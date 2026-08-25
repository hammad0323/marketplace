<?php
require_once __DIR__ . '/../config/config.php';
require_login('customer');
$user = current_user($conn);

$items = get_cart_items($conn, (int) $user['id']);
if (!$items) {
    flash_set('danger', 'Your cart is empty.');
    redirect('/customer/cart.php');
}
foreach ($items as $item) {
    $outOfStock = $item['status'] !== 'approved' || ($item['stock_quantity'] !== null && (int) $item['stock_quantity'] < 1);
    $exceedsStock = $item['stock_quantity'] !== null && (int) $item['quantity'] > (int) $item['stock_quantity'];
    if ($outOfStock || $exceedsStock) {
        flash_set('danger', 'Some items in your cart need attention before you can check out.');
        redirect('/customer/cart.php');
    }
}

$cities = db_select($conn, 'SELECT id, name FROM cities WHERE is_active = 1 ORDER BY sort_order');
$errors = [];
$old = [
    'shipping_name' => $user['name'],
    'shipping_phone' => $user['phone'] ?? '',
    'shipping_address' => '',
    'shipping_city_id' => '',
    'shipping_notes' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach (['shipping_name', 'shipping_phone', 'shipping_address', 'shipping_notes'] as $f) {
        $old[$f] = clean_input($_POST[$f] ?? '');
    }
    $old['shipping_city_id'] = (int) ($_POST['shipping_city_id'] ?? 0) ?: '';

    require_field($old['shipping_name'], 'Full name', $errors);
    require_field($old['shipping_phone'], 'Phone number', $errors);
    require_field($old['shipping_address'], 'Shipping address', $errors);

    // Re-check stock right before committing — the cart page already
    // checked once, but time may have passed since that page loaded.
    if (!$errors) {
        $freshItems = get_cart_items($conn, (int) $user['id']);
        foreach ($freshItems as $item) {
            if ($item['status'] !== 'approved' || ($item['stock_quantity'] !== null && (int) $item['quantity'] > (int) $item['stock_quantity'])) {
                $errors[] = $item['title'] . ' is no longer available in that quantity. Please update your cart.';
            }
        }
    }

    if (!$errors) {
        $orderRef = generate_order_ref();
        $subtotal = 0;
        $taxTotal = 0;
        $feeTotal = 0;
        $lineItems = [];
        foreach ($freshItems as $item) {
            $service = ['id' => $item['service_id'], 'category_id' => $item['category_id'], 'price' => $item['price']];
            $breakdown = calculate_order_item_price($conn, $service, (int) $item['quantity']);
            $subtotal += $breakdown['base'];
            $taxTotal += $breakdown['tax'];
            $feeTotal += $breakdown['fee'];
            $lineItems[] = ['item' => $item, 'breakdown' => $breakdown];
        }
        $totalAmount = round($subtotal + $taxTotal + $feeTotal, 2);

        $orderId = db_insert_get_id(
            $conn,
            'INSERT INTO orders (order_ref, customer_id, subtotal, tax_amount, service_fee, total_amount, status, shipping_name, shipping_phone, shipping_address, shipping_city_id, shipping_notes)
             VALUES (?,?,?,?,?,?, "pending", ?,?,?,?,?)',
            [
                $orderRef, (int) $user['id'], $subtotal, $taxTotal, $feeTotal, $totalAmount,
                $old['shipping_name'], $old['shipping_phone'], $old['shipping_address'], $old['shipping_city_id'] ?: null, $old['shipping_notes'],
            ]
        );

        $notifiedProviders = [];
        foreach ($lineItems as $line) {
            $item = $line['item'];
            $b = $line['breakdown'];
            db_execute(
                $conn,
                'INSERT INTO order_items (order_id, service_id, provider_id, title, unit_price, quantity, total_price, commission_amount, status)
                 VALUES (?,?,?,?,?,?,?,?, "pending")',
                [$orderId, (int) $item['service_id'], (int) $item['provider_id'], $item['title'], $item['price'], (int) $item['quantity'], $b['total'], $b['commission']]
            );
            if ($item['stock_quantity'] !== null) {
                db_execute($conn, 'UPDATE services SET stock_quantity = GREATEST(0, stock_quantity - ?) WHERE id = ?', [(int) $item['quantity'], (int) $item['service_id']]);
            }
            if (!isset($notifiedProviders[$item['provider_id']])) {
                $notifiedProviders[$item['provider_id']] = true;
                $providerUser = db_select_one($conn, 'SELECT user_id, name, email FROM users u JOIN providers p ON p.user_id = u.id WHERE p.id = ?', [(int) $item['provider_id']]);
                if ($providerUser) {
                    db_execute(
                        $conn,
                        'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, "new_order", "New order received", ?, "/provider/orders.php")',
                        [(int) $providerUser['user_id'], 'You have a new order (' . $orderRef . ').']
                    );
                }
            }
        }

        db_execute($conn, 'DELETE FROM cart_items WHERE user_id = ?', [(int) $user['id']]);

        db_execute(
            $conn,
            'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, "order_placed", "Order placed", ?, "/customer/orders.php")',
            [(int) $user['id'], 'Your order ' . $orderRef . ' has been placed.']
        );

        flash_set('success', 'Order placed! Reference ' . $orderRef . '. Track it from My Orders.');
        redirect('/customer/orders.php');
    }
}

// Recompute the summary shown on the page (also used to re-render on a
// validation error, using the cart as it stood when the page loaded).
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += round((float) $item['price'] * (int) $item['quantity'], 2);
}
$taxPercent = (float) (db_select_one($conn, 'SELECT percent FROM taxes WHERE applies_to IN ("all","booking") AND is_active = 1 ORDER BY id LIMIT 1')['percent'] ?? 0);
$feePercent = (float) get_setting($conn, 'service_fee_percent', 0);
$tax = round($subtotal * $taxPercent / 100, 2);
$fee = round($subtotal * $feePercent / 100, 2);
$total = round($subtotal + $tax + $fee, 2);

$pageTitle = 'Checkout';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl" style="max-width:960px;">
    <a href="<?php echo url('/customer/cart.php'); ?>" style="color:var(--ink-mute);font-size:13.5px;"><i class="bi bi-arrow-left"></i> Back to cart</a>
    <div class="section-head" style="margin-top:16px;">
      <span class="eyebrow"><i class="bi bi-bag-check"></i> Checkout</span>
      <h1 class="section-heading">Shipping details</h1>
    </div>

    <?php foreach ($errors as $err): ?>
      <div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($err); ?></div>
    <?php endforeach; ?>

    <div style="display:grid;grid-template-columns:2fr 1fr;gap:32px;align-items:start;">
      <form method="post" class="form-w panel">
        <?php echo csrf_field(); ?>
        <label>Full name</label>
        <input type="text" name="shipping_name" value="<?php echo e($old['shipping_name']); ?>" required>
        <label>Phone number</label>
        <input type="tel" name="shipping_phone" value="<?php echo e($old['shipping_phone']); ?>" required>
        <label>Shipping address</label>
        <textarea name="shipping_address" rows="3" required><?php echo e($old['shipping_address']); ?></textarea>
        <label>City</label>
        <select name="shipping_city_id">
          <option value="">Select city</option>
          <?php foreach ($cities as $c): ?>
            <option value="<?php echo (int) $c['id']; ?>" <?php echo (string) $c['id'] === (string) $old['shipping_city_id'] ? 'selected' : ''; ?>><?php echo e($c['name']); ?></option>
          <?php endforeach; ?>
        </select>
        <label>Delivery notes (optional)</label>
        <textarea name="shipping_notes" rows="2" placeholder="Gate code, landmark, preferred delivery time…"><?php echo e($old['shipping_notes']); ?></textarea>
        <button type="submit" class="btn-w btn-primary btn-block" style="margin-top:20px;"><i class="bi bi-lock-fill"></i> Place order</button>
        <p class="form-hint" style="text-align:center;margin-top:10px;">Payment is arranged directly with the seller(s) after your order is placed.</p>
      </form>

      <div class="panel">
        <h3 style="font-size:16px;margin-bottom:14px;">Order summary</h3>
        <?php foreach ($items as $item): ?>
          <div style="display:flex;justify-content:space-between;padding:6px 0;font-size:13.5px;">
            <span style="color:var(--ink-soft);"><?php echo e($item['title']); ?> × <?php echo (int) $item['quantity']; ?></span>
            <strong><?php echo format_price($item['price'] * $item['quantity']); ?></strong>
          </div>
        <?php endforeach; ?>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:1px solid var(--border);margin-top:8px;font-size:14px;"><span>Subtotal</span><strong><?php echo format_price($subtotal); ?></strong></div>
        <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:14px;"><span>Service fee</span><strong><?php echo format_price($fee); ?></strong></div>
        <div style="display:flex;justify-content:space-between;padding:5px 0;font-size:14px;"><span>Tax</span><strong><?php echo format_price($tax); ?></strong></div>
        <div style="display:flex;justify-content:space-between;padding:10px 0;border-top:1px solid var(--border);margin-top:6px;font-size:16px;"><span style="font-weight:700;">Total</span><strong style="color:var(--purple-600);"><?php echo format_price($total); ?></strong></div>
      </div>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
