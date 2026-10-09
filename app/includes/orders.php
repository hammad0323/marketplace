<?php
/**
 * Checkout quoting, order placement (transactional), status workflow,
 * stock reservation/restoration and refunds.
 */

const ORDER_STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled', 'refunded', 'on_hold'];
const PAYMENT_STATUSES = ['unpaid', 'pending', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded'];

/** Allowed manual status transitions (refunds go through order_record_refund). */
function order_transitions(): array
{
    return [
        'pending'    => ['confirmed', 'processing', 'on_hold', 'cancelled'],
        'confirmed'  => ['processing', 'shipped', 'on_hold', 'cancelled'],
        'processing' => ['shipped', 'on_hold', 'cancelled'],
        'on_hold'    => ['pending', 'confirmed', 'processing', 'cancelled'],
        'shipped'    => ['delivered', 'on_hold'],
        'delivered'  => [],
        'cancelled'  => [],
        'refunded'   => [],
    ];
}

function order_can_cancel(array $order): bool
{
    return in_array('cancelled', order_transitions()[$order['status']] ?? [], true);
}

/**
 * Price a cart for checkout. Everything is recomputed from the database.
 * $opt: city, region, shipping_method, payment_method, coupon_code, customer_id, email
 */
function checkout_quote(array $lines, array $opt): array
{
    $errors = [];
    $subtotal = 0.0;
    $gift = 0.0;
    $count = 0;
    foreach ($lines as $l) {
        if ($l['error']) {
            $errors[] = ($l['product']['name'] ?? 'An item') . ': ' . $l['error'];
            continue;
        }
        $subtotal += $l['line_total'];
        $gift += $l['gift_wrap_price'] * $l['quantity'];
        $count += $l['quantity'];
    }
    if (!$lines) {
        $errors[] = 'Your bag is empty.';
    }
    $subtotal = round($subtotal, 2);
    $minOrder = (float) setting('minimum_order_amount', '0');
    if ($minOrder > 0 && $subtotal < $minOrder) {
        $errors[] = 'The minimum order value is ' . money($minOrder) . '.';
    }

    $coupon = null;
    $couponError = null;
    $discount = 0.0;
    $freeShipCoupon = false;
    if (!empty($opt['coupon_code'])) {
        [$coupon, $couponError] = coupon_validate($opt['coupon_code'], $subtotal, $opt['customer_id'] ?? null, $opt['email'] ?? null);
        if ($coupon) {
            $discount = coupon_discount($coupon, $subtotal);
            $freeShipCoupon = $coupon['discount_type'] === 'free_shipping';
        }
    }

    $ship = shipping_options((string) ($opt['city'] ?? ''), (string) ($opt['region'] ?? ''), $subtotal - $discount, $freeShipCoupon);
    $selected = null;
    foreach ($ship['options'] as $o) {
        if ($o['method'] === ($opt['shipping_method'] ?? 'standard')) {
            $selected = $o;
        }
    }
    $selected = $selected ?? ($ship['options'][0] ?? null);
    if ($ship['error']) {
        $errors[] = $ship['error'];
    }
    $shipping = $selected ? (float) $selected['cost'] : 0.0;

    $codFee = 0.0;
    $pre = $subtotal - $discount + $gift + $shipping;
    [$codOk, $codMsg] = cod_available_for($ship['zone'], $pre);
    if (($opt['payment_method'] ?? '') === 'cod') {
        if (!$codOk) {
            $errors[] = $codMsg;
        } else {
            $codFee = cod_fee();
        }
    }
    $total = round(max(0, $subtotal - $discount) + $gift + $shipping + $codFee, 2);

    return [
        'lines' => $lines,
        'item_count' => $count,
        'subtotal' => $subtotal,
        'gift_wrap_total' => round($gift, 2),
        'discount' => $discount,
        'coupon' => $coupon,
        'coupon_error' => $couponError,
        'shipping_zone' => $ship['zone'],
        'shipping_options' => $ship['options'],
        'shipping' => $selected,
        'shipping_total' => $shipping,
        'cod_available' => $codOk,
        'cod_message' => $codMsg,
        'cod_fee' => $codFee,
        'cod_fee_setting' => cod_fee(),
        'total' => $total,
        'errors' => $errors,
    ];
}

function generate_order_number(): string
{
    $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) setting('order_prefix', 'BG'))) ?: 'BG';
    do {
        $num = $prefix . date('ymd') . random_int(10000, 99999);
    } while (db_val('SELECT COUNT(*) FROM orders WHERE order_number = ?', [$num]));
    return $num;
}

function generate_payment_reference(): string
{
    do {
        $ref = 'T' . date('YmdHis') . random_int(1000, 9999);
    } while (db_val('SELECT COUNT(*) FROM payments WHERE reference = ?', [$ref]));
    return $ref;
}

/** Validate checkout form data. Returns [clean data, errors]. */
function checkout_validate(array $in): array
{
    $d = [
        'full_name' => trim($in['full_name'] ?? ''),
        'email' => mb_strtolower(trim($in['email'] ?? '')),
        'phone' => trim($in['phone'] ?? ''),
        'address_line1' => trim($in['address_line1'] ?? ''),
        'address_line2' => trim($in['address_line2'] ?? ''),
        'city' => trim($in['city'] ?? ''),
        'region' => trim($in['region'] ?? ''),
        'postal_code' => trim($in['postal_code'] ?? ''),
        'notes' => trim($in['notes'] ?? ''),
        'shipping_method' => in_array($in['shipping_method'] ?? '', ['standard', 'express'], true) ? $in['shipping_method'] : 'standard',
        'payment_method' => (string) ($in['payment_method'] ?? ''),
        'create_account' => !empty($in['create_account']),
        'password' => (string) ($in['password'] ?? ''),
        'save_address' => !empty($in['save_address']),
        'accept_terms' => !empty($in['accept_terms']),
    ];
    $e = [];
    if (mb_strlen($d['full_name']) < 2 || mb_strlen($d['full_name']) > 150) {
        $e['full_name'] = 'Please enter your full name.';
    }
    if (!valid_email($d['email'])) {
        $e['email'] = 'Please enter a valid email address.';
    }
    if (!valid_phone($d['phone'])) {
        $e['phone'] = 'Please enter a valid phone number.';
    }
    if (mb_strlen($d['address_line1']) < 5 || mb_strlen($d['address_line1']) > 255) {
        $e['address_line1'] = 'Please enter your full delivery address.';
    }
    if (mb_strlen($d['address_line2']) > 255) {
        $e['address_line2'] = 'Address line 2 is too long.';
    }
    if (mb_strlen($d['city']) < 2 || mb_strlen($d['city']) > 100) {
        $e['city'] = 'Please enter your city.';
    }
    if (mb_strlen($d['region']) < 2 || mb_strlen($d['region']) > 100) {
        $e['region'] = 'Please select your province or region.';
    }
    if ($d['postal_code'] !== '' && !preg_match('/^[A-Za-z0-9 \-]{3,12}$/', $d['postal_code'])) {
        $e['postal_code'] = 'Please enter a valid postal code.';
    }
    if (mb_strlen($d['notes']) > 1000) {
        $e['notes'] = 'Order notes are limited to 1000 characters.';
    }
    $available = array_column(checkout_payment_methods(), 'code');
    if (!in_array($d['payment_method'], $available, true)) {
        $e['payment_method'] = 'Please choose a payment method.';
    }
    if ($d['create_account'] && !customer_id()) {
        if ($err = password_policy_error($d['password'])) {
            $e['password'] = $err;
        } elseif (db_val('SELECT COUNT(*) FROM customers WHERE email = ?', [$d['email']])) {
            $e['email'] = 'An account already exists for this email. Sign in, or untick "create an account".';
        }
    }
    if (setting_bool('checkout_require_terms', true) && !$d['accept_terms']) {
        $e['accept_terms'] = 'Please accept the terms and conditions.';
    }
    return [$d, $e];
}

/**
 * Place an order atomically. Throws RuntimeException with a customer-safe
 * message when the order cannot be placed.
 * @return array the order row
 */
function place_order(array $d, string $idempotencyKey): array
{
    $existing = db_one('SELECT * FROM orders WHERE idempotency_key = ?', [$idempotencyKey]);
    if ($existing) {
        return $existing;
    }
    $cartId = cart_id();
    if (!$cartId) {
        throw new RuntimeException('Your bag is empty.');
    }
    $accessToken = random_token(24);

    $order = db_tx(function () use ($d, $idempotencyKey, $cartId, $accessToken) {
        // 1. Lock every inventory row involved, then price the cart under the lock.
        $items = db_all('SELECT * FROM cart_items WHERE cart_id = ? ORDER BY product_id, variant_key FOR UPDATE', [$cartId]);
        if (!$items) {
            throw new RuntimeException('Your bag is empty.');
        }
        foreach ($items as $it) {
            db_one('SELECT quantity FROM product_inventory WHERE product_id = ? AND variant_key = ? FOR UPDATE', [(int) $it['product_id'], (int) $it['variant_key']]);
        }
        $lines = cart_lines($cartId);
        $customerId = customer_id();

        // 2. Optional account creation (inside the transaction).
        if ($d['create_account'] && !$customerId) {
            $parts = preg_split('/\s+/', $d['full_name'], 2);
            [$newId, $errs] = customer_register([
                'first_name' => $parts[0], 'last_name' => $parts[1] ?? '', 'email' => $d['email'],
                'phone' => $d['phone'], 'password' => $d['password'],
            ]);
            if (!$newId) {
                throw new RuntimeException(reset($errs) ?: 'Could not create your account.');
            }
            $customerId = $newId;
        }

        $couponCode = db_val('SELECT coupon_code FROM carts WHERE id = ?', [$cartId]);
        if ($couponCode) {
            db_one('SELECT id FROM coupons WHERE code = ? FOR UPDATE', [$couponCode]);
        }
        $quote = checkout_quote($lines, [
            'city' => $d['city'], 'region' => $d['region'], 'shipping_method' => $d['shipping_method'],
            'payment_method' => $d['payment_method'], 'coupon_code' => $couponCode,
            'customer_id' => $customerId, 'email' => $d['email'],
        ]);
        if ($quote['errors']) {
            throw new RuntimeException(implode(' ', $quote['errors']));
        }
        if ($couponCode && !$quote['coupon']) {
            throw new RuntimeException('Coupon: ' . $quote['coupon_error'] . ' Please remove it to continue.');
        }

        // 3. Order header.
        $orderNumber = generate_order_number();
        $orderId = db_insert('orders', [
            'order_number' => $orderNumber,
            'customer_id' => $customerId,
            'customer_name' => $d['full_name'],
            'email' => $d['email'],
            'phone' => $d['phone'],
            'status' => 'pending',
            'payment_method' => $d['payment_method'],
            'payment_status' => $d['payment_method'] === 'cod' ? 'unpaid' : 'pending',
            'currency' => currency_code(),
            'subtotal' => $quote['subtotal'],
            'discount_total' => $quote['discount'],
            'shipping_total' => $quote['shipping_total'],
            'cod_fee' => $quote['cod_fee'],
            'gift_wrap_total' => $quote['gift_wrap_total'],
            'grand_total' => $quote['total'],
            'coupon_code' => $quote['coupon']['code'] ?? null,
            'shipping_zone_id' => $quote['shipping_zone']['id'] ?? null,
            'shipping_method' => $quote['shipping']['method'] ?? 'standard',
            'shipping_label' => $quote['shipping']['name'] ?? null,
            'delivery_estimate' => $quote['shipping']['estimate'] ?? null,
            'customer_note' => $d['notes'] !== '' ? $d['notes'] : null,
            'idempotency_key' => $idempotencyKey,
            'access_token_hash' => token_hash($accessToken),
            'ip_address' => client_ip(),
            'user_agent' => user_agent(),
        ]);

        db_insert('order_addresses', [
            'order_id' => $orderId, 'address_type' => 'shipping', 'full_name' => $d['full_name'], 'phone' => $d['phone'],
            'address_line1' => $d['address_line1'], 'address_line2' => $d['address_line2'] ?: null,
            'city' => $d['city'], 'region' => $d['region'], 'postal_code' => $d['postal_code'] ?: null,
            'country' => setting('store_country', 'Pakistan'),
        ]);

        // 4. Line snapshot + stock decrement.
        foreach ($quote['lines'] as $l) {
            $p = $l['product'];
            db_insert('order_items', [
                'order_id' => $orderId,
                'product_id' => $l['product_id'],
                'variant_id' => $l['variant_id'],
                'product_name' => $p['name'],
                'sku' => $l['variant']['sku'] ?? $p['sku'],
                'variant_label' => $l['variant_label'],
                'image_path' => $l['image'],
                'unit_price' => $l['unit_price'],
                'regular_price' => $l['regular_price'],
                'quantity' => $l['quantity'],
                'gift_wrap' => $l['gift_wrap_price'] > 0 ? 1 : 0,
                'gift_wrap_price' => $l['gift_wrap_price'],
                'line_total' => $l['line_total'],
            ]);
            if ((int) $p['track_stock']) {
                inventory_adjust($l['product_id'], $l['variant_id'], -$l['quantity'], 'order', $orderId);
            }
        }

        // 5. Coupon usage.
        if ($quote['coupon']) {
            db_insert('coupon_usage', [
                'coupon_id' => (int) $quote['coupon']['id'], 'order_id' => $orderId, 'customer_id' => $customerId,
                'email' => $d['email'], 'discount_amount' => $quote['discount'],
            ]);
            db_exec('UPDATE coupons SET times_used = times_used + 1 WHERE id = ?', [(int) $quote['coupon']['id']]);
        }

        // 6. Payment record.
        $provider = $d['payment_method'] === 'card' ? card_provider() : $d['payment_method'];
        db_insert('payments', [
            'order_id' => $orderId, 'method' => $d['payment_method'], 'provider' => $provider,
            'reference' => generate_payment_reference(), 'amount' => $quote['total'],
            'currency' => currency_code(), 'status' => 'pending',
        ]);

        db_insert('order_status_history', [
            'order_id' => $orderId, 'status' => 'pending',
            'payment_status' => $d['payment_method'] === 'cod' ? 'unpaid' : 'pending',
            'note' => $d['payment_method'] === 'cod' ? 'Order placed — cash on delivery.' : 'Order placed — awaiting online payment.',
            'is_customer_visible' => 1,
        ]);

        // 7. Save address for registered customers.
        if ($customerId && ($d['save_address'] || $d['create_account'])) {
            $has = (int) db_val('SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?', [$customerId]);
            $dup = db_val('SELECT id FROM customer_addresses WHERE customer_id = ? AND address_line1 = ? AND city = ?', [$customerId, $d['address_line1'], $d['city']]);
            if (!$dup) {
                db_insert('customer_addresses', [
                    'customer_id' => $customerId, 'label' => 'Home', 'full_name' => $d['full_name'], 'phone' => $d['phone'],
                    'address_line1' => $d['address_line1'], 'address_line2' => $d['address_line2'] ?: null, 'city' => $d['city'],
                    'region' => $d['region'], 'postal_code' => $d['postal_code'] ?: null, 'is_default' => $has ? 0 : 1,
                ]);
            }
        }

        cart_clear($cartId);
        return ['id' => $orderId, 'customer_id' => $customerId];
    });

    if ($order['customer_id'] && !customer_id()) {
        customer_start_session((int) $order['customer_id']);
    }
    $row = db_one('SELECT * FROM orders WHERE id = ?', [$order['id']]);
    $_SESSION['order_access'][$row['order_number']] = $accessToken;

    if ($row['payment_method'] === 'cod') {
        order_send_placed_emails($row, $accessToken);
    }
    low_stock_check_order((int) $row['id']);
    return $row;
}

/**
 * Change stock for a sellable unit and log the movement. Must be called inside a transaction.
 */
function inventory_adjust(int $productId, ?int $variantId, int $change, string $reason, ?int $orderId = null, ?string $note = null): int
{
    $row = db_one('SELECT id, quantity FROM product_inventory WHERE product_id = ? AND variant_key = ? FOR UPDATE', [$productId, (int) $variantId]);
    if (!$row) {
        db_insert('product_inventory', ['product_id' => $productId, 'variant_id' => $variantId, 'quantity' => 0]);
        $row = ['quantity' => 0];
    }
    $balance = (int) $row['quantity'] + $change;
    if ($balance < 0 && $reason === 'order') {
        throw new RuntimeException('Sorry, one of the items in your bag just sold out. Please review your bag.');
    }
    db_exec('UPDATE product_inventory SET quantity = ? WHERE product_id = ? AND variant_key = ?', [$balance, $productId, (int) $variantId]);
    db_insert('inventory_movements', [
        'product_id' => $productId, 'variant_id' => $variantId, 'change_qty' => $change, 'balance' => $balance,
        'reason' => $reason, 'order_id' => $orderId, 'admin_id' => admin_id(), 'note' => $note,
    ]);
    return $balance;
}

/** Return reserved stock for an order (idempotent per order). */
function order_restore_stock(int $orderId, string $reason = 'order_cancel'): void
{
    $already = (int) db_val("SELECT COUNT(*) FROM inventory_movements WHERE order_id = ? AND reason IN ('order_cancel','refund')", [$orderId]);
    if ($already) {
        return;
    }
    foreach (db_all('SELECT oi.*, p.track_stock FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?', [$orderId]) as $it) {
        if ((int) $it['track_stock']) {
            inventory_adjust((int) $it['product_id'], $it['variant_id'] !== null ? (int) $it['variant_id'] : null, (int) $it['quantity'], $reason, $orderId);
        }
    }
}

function order_add_history(int $orderId, string $status, ?string $paymentStatus, ?string $note, bool $visible = true): void
{
    db_insert('order_status_history', [
        'order_id' => $orderId, 'status' => $status, 'payment_status' => $paymentStatus,
        'note' => $note ? mb_substr($note, 0, 500) : null, 'is_customer_visible' => $visible ? 1 : 0, 'admin_id' => admin_id(),
    ]);
}

/**
 * Change an order's fulfilment status with validation and side effects.
 * Returns null on success, or an error string.
 */
function order_set_status(int $orderId, string $new, ?string $note = null, bool $notify = true): ?string
{
    $result = db_tx(function () use ($orderId, $new, $note) {
        $o = db_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$o) {
            return 'Order not found.';
        }
        if ($o['status'] === $new) {
            return null;
        }
        if (!in_array($new, order_transitions()[$o['status']] ?? [], true)) {
            return 'An order cannot move from "' . status_label($o['status']) . '" to "' . status_label($new) . '".';
        }
        $paymentStatus = $o['payment_status'];
        if ($new === 'cancelled') {
            order_restore_stock($orderId);
            db_exec('UPDATE coupons c JOIN coupon_usage cu ON cu.coupon_id = c.id SET c.times_used = GREATEST(c.times_used - 1, 0) WHERE cu.order_id = ?', [$orderId]);
            if (in_array($o['payment_status'], ['pending', 'unpaid'], true)) {
                $paymentStatus = 'cancelled';
                db_exec("UPDATE payments SET status = 'cancelled' WHERE order_id = ? AND status IN ('pending','processing')", [$orderId]);
            }
        }
        db_exec('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?', [$new, $paymentStatus, $orderId]);
        order_add_history($orderId, $new, $paymentStatus, $note);
        return null;
    });
    if ($result === null && $notify) {
        $o = db_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
        if (in_array($new, ['confirmed', 'shipped', 'delivered', 'cancelled'], true)) {
            order_send_status_email($o, $note);
        }
    }
    return $result;
}

/**
 * Mark a payment as paid after SERVER-SIDE verification. Idempotent:
 * a payment already marked paid is never processed twice.
 */
function payment_mark_paid(int $paymentId, float $amount, ?string $providerRef, string $note): bool
{
    $done = db_tx(function () use ($paymentId, $amount, $providerRef, $note) {
        $pay = db_one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
        if (!$pay || $pay['status'] === 'paid') {
            return false;
        }
        if (abs((float) $pay['amount'] - $amount) > 0.01) {
            app_log('payments', 'Amount mismatch — not marking paid', ['payment' => $paymentId, 'expected' => $pay['amount'], 'got' => $amount]);
            db_exec("UPDATE payments SET status = 'failed' WHERE id = ?", [$paymentId]);
            return false;
        }
        $order = db_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$pay['order_id']]);
        if ($order['status'] === 'cancelled') {
            // Paid after cancellation (e.g. late callback): re-reserve stock is not safe — flag for manual review.
            db_exec("UPDATE payments SET status = 'paid', paid_at = NOW(), provider_reference = ? WHERE id = ?", [$providerRef, $paymentId]);
            db_exec("UPDATE orders SET payment_status = 'paid', status = 'on_hold' WHERE id = ?", [$order['id']]);
            order_add_history((int) $order['id'], 'on_hold', 'paid', 'Payment received after the order was cancelled — needs manual review.', false);
            return true;
        }
        db_exec("UPDATE payments SET status = 'paid', paid_at = NOW(), provider_reference = ? WHERE id = ?", [$providerRef, $paymentId]);
        $newStatus = $order['status'] === 'pending' ? 'confirmed' : $order['status'];
        db_exec("UPDATE orders SET payment_status = 'paid', status = ? WHERE id = ?", [$newStatus, $order['id']]);
        order_add_history((int) $order['id'], $newStatus, 'paid', $note);
        return true;
    });
    if ($done) {
        $order = db_one('SELECT o.* FROM orders o JOIN payments p ON p.order_id = o.id WHERE p.id = ?', [$paymentId]);
        if ($order['payment_method'] !== 'cod') {
            order_send_placed_emails($order, null);
        }
    }
    return $done;
}

/** Mark an online payment failed/cancelled and release the order's stock. */
function payment_mark_failed(int $paymentId, string $status, string $note): void
{
    db_tx(function () use ($paymentId, $status, $note) {
        $pay = db_one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
        if (!$pay || in_array($pay['status'], ['paid', 'refunded', 'partially_refunded'], true)) {
            return;
        }
        $status = in_array($status, ['failed', 'cancelled'], true) ? $status : 'failed';
        db_exec('UPDATE payments SET status = ? WHERE id = ?', [$status, $paymentId]);
        $order = db_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$pay['order_id']]);
        if ($order && $order['status'] === 'pending') {
            order_restore_stock((int) $order['id']);
            db_exec('UPDATE coupons c JOIN coupon_usage cu ON cu.coupon_id = c.id SET c.times_used = GREATEST(c.times_used - 1, 0) WHERE cu.order_id = ?', [$order['id']]);
            db_exec("UPDATE orders SET status = 'cancelled', payment_status = ? WHERE id = ?", [$status, $order['id']]);
            order_add_history((int) $order['id'], 'cancelled', $status, $note);
        }
    });
}

/** Record COD cash collected (admin action). */
function order_mark_cod_collected(int $orderId, ?string $note): ?string
{
    return db_tx(function () use ($orderId, $note) {
        $o = db_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$o || $o['payment_method'] !== 'cod') {
            return 'This is not a cash-on-delivery order.';
        }
        if ($o['payment_status'] === 'paid') {
            return 'Payment is already recorded.';
        }
        if (in_array($o['status'], ['cancelled', 'refunded'], true)) {
            return 'Cancelled orders cannot be marked as collected.';
        }
        $pay = db_one("SELECT * FROM payments WHERE order_id = ? AND method = 'cod' ORDER BY id DESC LIMIT 1 FOR UPDATE", [$orderId]);
        db_exec("UPDATE payments SET status = 'paid', paid_at = NOW() WHERE id = ?", [$pay['id']]);
        db_exec("UPDATE orders SET payment_status = 'paid', cod_collected_at = NOW(), cod_collected_by = ? WHERE id = ?", [admin_id(), $orderId]);
        db_insert('payment_transactions', [
            'payment_id' => (int) $pay['id'], 'order_id' => $orderId, 'txn_type' => 'cod_collection', 'status' => 'paid',
            'amount' => (float) $o['grand_total'], 'provider_message' => $note ? mb_substr($note, 0, 255) : 'Cash collected', 'admin_id' => admin_id(), 'ip_address' => client_ip(),
        ]);
        order_add_history($orderId, $o['status'], 'paid', 'Cash on delivery payment collected.' . ($note ? ' ' . $note : ''), false);
        return null;
    });
}

/**
 * Record a refund. Provider refunds must be executed in the provider's merchant
 * portal/API; this records and reconciles it. Returns error or null.
 */
function order_record_refund(int $orderId, float $amount, string $reason, bool $restock): ?string
{
    return db_tx(function () use ($orderId, $amount, $reason, $restock) {
        $o = db_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$o) {
            return 'Order not found.';
        }
        if (!in_array($o['payment_status'], ['paid', 'partially_refunded'], true)) {
            return 'Only paid orders can be refunded. Cancel unpaid orders instead.';
        }
        $remaining = round((float) $o['grand_total'] - (float) $o['refunded_total'], 2);
        $amount = round($amount, 2);
        if ($amount <= 0 || $amount > $remaining + 0.001) {
            return 'Refund amount must be between 0 and ' . money($remaining) . '.';
        }
        $pay = db_one("SELECT * FROM payments WHERE order_id = ? AND status IN ('paid','partially_refunded') ORDER BY id DESC LIMIT 1 FOR UPDATE", [$orderId]);
        $full = abs($amount - $remaining) < 0.01;
        $newPay = $full ? 'refunded' : 'partially_refunded';
        db_exec('UPDATE payments SET refunded_amount = refunded_amount + ?, status = ? WHERE id = ?', [$amount, $newPay, $pay['id']]);
        db_insert('payment_transactions', [
            'payment_id' => (int) $pay['id'], 'order_id' => $orderId, 'txn_type' => 'refund', 'status' => $newPay,
            'amount' => $amount, 'provider_message' => mb_substr($reason, 0, 255), 'admin_id' => admin_id(), 'ip_address' => client_ip(),
        ]);
        $newStatus = $full ? 'refunded' : $o['status'];
        db_exec('UPDATE orders SET refunded_total = refunded_total + ?, payment_status = ?, status = ? WHERE id = ?', [$amount, $newPay, $newStatus, $orderId]);
        if ($restock) {
            order_restore_stock($orderId, 'refund');
        }
        order_add_history($orderId, $newStatus, $newPay, 'Refund of ' . money($amount) . ' recorded. ' . $reason, true);
        return null;
    });
}

// ---- Access to orders by guests -----------------------------------------------

function order_access_link(array $order, string $token): string
{
    return url('order/' . $order['order_number'], ['t' => $token]);
}

/** May the current visitor view this order? */
function order_visible_to_visitor(array $order, ?string $token): bool
{
    if ($order['customer_id'] && (int) $order['customer_id'] === (int) customer_id()) {
        return true;
    }
    $verifiedAt = (int) ($_SESSION['order_verified'][$order['order_number']] ?? 0);
    if ($verifiedAt && $verifiedAt > time() - 86400) {
        return true;
    }
    $sessionToken = $_SESSION['order_access'][$order['order_number']] ?? null;
    foreach ([$token, $sessionToken] as $t) {
        if ($t && hash_equals($order['access_token_hash'], token_hash($t))) {
            return true;
        }
    }
    return false;
}

function order_items(int $orderId): array
{
    return db_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
}

function order_address(int $orderId): ?array
{
    return db_one("SELECT * FROM order_addresses WHERE order_id = ? AND address_type = 'shipping'", [$orderId]);
}

function order_public_history(int $orderId): array
{
    return db_all('SELECT * FROM order_status_history WHERE order_id = ? AND is_customer_visible = 1 ORDER BY created_at, id', [$orderId]);
}

/** Notify admins when an order pushes stock to/below its threshold. */
function low_stock_check_order(int $orderId): void
{
    if (!setting_bool('low_stock_email', true)) {
        return;
    }
    $rows = db_all(
        'SELECT DISTINCT p.name, p.sku, i.quantity, p.low_stock_threshold, v.label
         FROM order_items oi JOIN products p ON p.id = oi.product_id
         JOIN product_inventory i ON i.product_id = oi.product_id AND i.variant_key = IFNULL(oi.variant_id, 0)
         LEFT JOIN product_variants v ON v.id = oi.variant_id
         WHERE oi.order_id = ? AND p.track_stock = 1 AND i.quantity <= p.low_stock_threshold',
        [$orderId]
    );
    if ($rows) {
        $to = setting('notification_email', setting('support_email', ''));
        if ($to) {
            send_template_email($to, 'Low stock alert — ' . setting('site_name', 'Beglet'), 'low_stock', ['rows' => $rows]);
        }
    }
}

function order_send_placed_emails(array $order, ?string $accessToken): void
{
    $items = order_items((int) $order['id']);
    $address = order_address((int) $order['id']);
    $link = $accessToken ? order_access_link($order, $accessToken) : url('track-order');
    send_template_email($order['email'], 'Order ' . $order['order_number'] . ' confirmed — ' . setting('site_name', 'Beglet'), 'order_placed', [
        'order' => $order, 'items' => $items, 'address' => $address, 'link' => $link,
    ]);
    $admin = setting('notification_email', setting('support_email', ''));
    if ($admin) {
        send_template_email($admin, 'New order ' . $order['order_number'] . ' (' . money($order['grand_total']) . ')', 'admin_new_order', [
            'order' => $order, 'items' => $items, 'address' => $address,
        ]);
    }
}

function order_send_status_email(array $order, ?string $note): void
{
    if (!setting_bool('email_status_updates', true)) {
        return;
    }
    send_template_email($order['email'], 'Your order ' . $order['order_number'] . ' is ' . strtolower(status_label($order['status'])), 'order_status', [
        'order' => $order, 'note' => $note, 'link' => url('track-order'),
    ]);
}

/** Cancel online-payment orders left unpaid longer than the configured window (cron). */
function cancel_stale_unpaid_orders(): int
{
    $hours = max(1, (int) setting('unpaid_order_timeout_hours', '2'));
    $ids = db_col(
        "SELECT p.id FROM payments p JOIN orders o ON o.id = p.order_id
         WHERE p.method <> 'cod' AND p.status IN ('pending','processing') AND o.status = 'pending' AND p.created_at < ?",
        [date('Y-m-d H:i:s', time() - $hours * 3600)]
    );
    foreach ($ids as $pid) {
        payment_mark_failed((int) $pid, 'cancelled', 'Payment not completed within ' . $hours . ' hours — order released automatically.');
    }
    return count($ids);
}
