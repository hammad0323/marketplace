<?php
require __DIR__ . '/../config/config.php';
require_customer();
csrf_verify();

$customer = current_customer();
$buyNowId = !empty($_POST['buy_now']) ? (int)$_POST['buy_now'] : null;
$addressId = !empty($_POST['address_id']) ? (int)$_POST['address_id'] : null;
$paymentMethod = $_POST['payment_method'] ?? '';

if (!$paymentMethod || !db_fetch_one("SELECT id FROM payment_methods WHERE method_key=? AND is_enabled=1", 's', [$paymentMethod])) {
    flash('error', 'Please select a valid payment method.');
    redirect(base_url('checkout.php' . ($buyNowId ? '?buy_now=' . $buyNowId : '')));
}

// Build the item list to purchase (buy-now single item, or full cart)
$lines = [];
if ($buyNowId) {
    $product = db_fetch_one("SELECT p.*, s.status as shop_status FROM products p JOIN shops s ON s.id=p.shop_id WHERE p.id=?", 'i', [$buyNowId]);
    if (!$product || $product['status'] !== 'active' || $product['shop_status'] !== 'active' || $product['stock_status'] !== 'in_stock') {
        flash('error', 'This product is no longer available.'); redirect(base_url());
    }
    $unitPrice = $product['sale_price'] ?? $product['regular_price'];
    $lines[] = ['product' => $product, 'quantity' => 1, 'unit_price' => $unitPrice, 'variation_id' => null];
} else {
    $grouped = get_cart_items_grouped($customer['id']);
    if (!$grouped) { flash('error', 'Your cart is empty.'); redirect(base_url('cart.php')); }
    foreach ($grouped as $shop) {
        if ($shop['shop_status'] !== 'active') { flash('error', 'One of the shops in your cart is currently unavailable.'); redirect(base_url('cart.php')); }
        foreach ($shop['items'] as $item) {
            $product = db_fetch_one("SELECT * FROM products WHERE id=?", 'i', [$item['product_id']]);
            if (!$product || $product['stock_status'] !== 'in_stock' || $product['stock_quantity'] < $item['quantity']) {
                flash('error', 'Some items in your cart are no longer available in the requested quantity.');
                redirect(base_url('cart.php'));
            }
            $lines[] = ['product' => $product, 'quantity' => $item['quantity'], 'unit_price' => $item['unit_price'], 'variation_id' => $item['variation_id']];
        }
    }
}

// Group lines by shop for splitting into shop_orders
$byShop = [];
foreach ($lines as $line) $byShop[$line['product']['shop_id']][] = $line;

$subtotal = 0;
foreach ($lines as $line) $subtotal += $line['unit_price'] * $line['quantity'];

$orderNumber = generate_order_number();
$orderId = db_insert("INSERT INTO orders (order_number, customer_id, address_id, payment_method, subtotal, grand_total, order_status, payment_status)
                       VALUES (?,?,?,?,?,?,'placed', ?)",
    'siisdds', [$orderNumber, $customer['id'], $addressId, $paymentMethod, $subtotal, $subtotal, ($paymentMethod === 'cod' ? 'pending' : 'pending')]);

$commissionDueDays = (int)get_setting('commission_due_days', 30);
$shopIndex = 0;
foreach ($byShop as $shopId => $shopLines) {
    $shopIndex++;
    $shopSubtotal = 0;
    foreach ($shopLines as $line) $shopSubtotal += $line['unit_price'] * $line['quantity'];

    $shopOrderNumber = $orderNumber . '-' . chr(64 + $shopIndex); // -A, -B, -C ...
    $shopOrderId = db_insert("INSERT INTO shop_orders (order_id, shop_order_number, shop_id, subtotal, status) VALUES (?,?,?,?,'placed')",
        'isid', [$orderId, $shopOrderNumber, $shopId, $shopSubtotal]);

    $shopCommissionTotal = 0; $shopVendorTotal = 0;
    foreach ($shopLines as $line) {
        $product = $line['product'];
        $lineTotal = $line['unit_price'] * $line['quantity'];
        $commission = calculate_commission($product['category_id'], $lineTotal);
        $vendorAmount = $lineTotal - $commission['amount'];
        $shopCommissionTotal += $commission['amount'];
        $shopVendorTotal += $vendorAmount;

        db_insert("INSERT INTO order_items (shop_order_id, product_id, product_name, category_id, quantity, unit_price, line_total, commission_percent, commission_amount, vendor_amount)
                   VALUES (?,?,?,?,?,?,?,?,?,?)",
            'iisiiddddd', [$shopOrderId, $product['id'], $product['name'], $product['category_id'], $line['quantity'],
                $line['unit_price'], $lineTotal, $commission['percent'], $commission['amount'], $vendorAmount]);

        if ($product['manage_stock']) {
            db_exec("UPDATE products SET stock_quantity = GREATEST(0, stock_quantity - ?), total_sold = total_sold + ?,
                     stock_status = IF(stock_quantity - ? <= 0, 'out_of_stock', stock_status) WHERE id=?",
                'iiii', [$line['quantity'], $line['quantity'], $line['quantity'], $product['id']]);
        }
    }

    db_exec("UPDATE shop_orders SET commission_total=?, vendor_earning=? WHERE id=?", 'ddi', [$shopCommissionTotal, $shopVendorTotal, $shopOrderId]);
    db_insert("INSERT INTO order_status_history (shop_order_id, status, note) VALUES (?, 'placed', 'Order placed by customer')", 'i', [$shopOrderId]);

    $invoiceNumber = 'INV-' . $shopOrderId . '-' . time();
    $dueDate = date('Y-m-d', strtotime("+{$commissionDueDays} days"));
    db_insert("INSERT INTO commissions (shop_id, shop_order_id, invoice_number, revenue, commission_amount, due_date, payment_status)
               VALUES (?,?,?,?,?,?,'pending')", 'iisdds', [$shopId, $shopOrderId, $invoiceNumber, $shopSubtotal, $shopCommissionTotal, $dueDate]);

    $owner = db_fetch_one("SELECT owner_id, shop_name FROM shops WHERE id=?", 'i', [$shopId]);
    if ($owner) notify('shop_owner', $owner['owner_id'], 'New Order Received', "You have received a new order $shopOrderNumber.", 'shop/orders.php');
}

db_insert("INSERT INTO payments (order_id, method, amount, status) VALUES (?,?,?, ?)",
    'isds', [$orderId, $paymentMethod, $subtotal, $paymentMethod === 'cod' ? 'pending' : 'pending']);

if (!$buyNowId) {
    $cart = db_fetch_one("SELECT id FROM carts WHERE customer_id=?", 'i', [$customer['id']]);
    if ($cart) db_exec("DELETE FROM cart_items WHERE cart_id=?", 'i', [$cart['id']]);
}

notify('customer', $customer['id'], 'Order Placed', "Your order $orderNumber has been placed successfully.", 'customer/orders.php');
audit_log('customer', $customer['id'], $customer['first_name'] . ' ' . $customer['last_name'], 'Placed order', 'orders', $orderId, "Order $orderNumber");

flash('success', "Your order $orderNumber has been placed successfully!");
redirect(customer_url('order-details.php?id=' . $orderId));
