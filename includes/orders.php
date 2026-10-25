<?php
/**
 * Order placement, inventory movements, status management.
 */

function order_statuses(): array
{
    return [
        'pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped',
        'delivered' => 'Delivered', 'on_hold' => 'On Hold', 'cancelled' => 'Cancelled', 'refunded' => 'Refunded',
    ];
}

function payment_statuses(): array
{
    return [
        'unpaid' => 'Unpaid', 'pending' => 'Awaiting Payment', 'paid' => 'Paid', 'failed' => 'Failed',
        'cancelled' => 'Cancelled', 'refunded' => 'Refunded', 'partially_refunded' => 'Partially Refunded',
    ];
}

function status_badge(string $status): string
{
    $map = [
        'pending' => 'warning', 'confirmed' => 'info', 'processing' => 'primary', 'shipped' => 'indigo', 'delivered' => 'success',
        'on_hold' => 'secondary', 'cancelled' => 'danger', 'refunded' => 'dark', 'unpaid' => 'secondary', 'paid' => 'success',
        'failed' => 'danger', 'partially_refunded' => 'dark',
    ];
    $label = order_statuses()[$status] ?? payment_statuses()[$status] ?? ucfirst(str_replace('_', ' ', $status));
    return '<span class="uc-status uc-status-' . e($map[$status] ?? 'secondary') . '">' . e($label) . '</span>';
}

/** SQL condition for orders that count as revenue (paid and not reversed). */
function sql_revenue_condition(string $alias = 'o'): string
{
    return "$alias.status NOT IN ('cancelled','refunded') AND $alias.payment_status IN ('paid','partially_refunded')";
}

function order_generate_number(): string
{
    $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) setting('order_prefix', 'UC'))) ?: 'UC';
    do {
        $num = $prefix . date('ymd') . random_int(10000, 99999);
    } while (db_val('SELECT 1 FROM orders WHERE order_number = ?', [$num]));
    return $num;
}

/**
 * Changes stock for a product or variant and writes the ledger row.
 * Must be called inside a transaction when part of a larger operation.
 */
function inventory_adjust(int $productId, ?int $variantId, int $change, string $reason, ?string $reference = null, ?string $note = null, ?int $adminId = null): int
{
    if ($variantId) {
        db_exec('UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ? AND product_id = ?', [$change, $variantId, $productId]);
        $balance = (int) db_val('SELECT stock_qty FROM product_variants WHERE id = ?', [$variantId]);
    } else {
        db_exec('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?', [$change, $productId]);
        $balance = (int) db_val('SELECT stock_qty FROM products WHERE id = ?', [$productId]);
    }
    db_insert('product_inventory', [
        'product_id' => $productId, 'variant_id' => $variantId, 'change_qty' => $change, 'balance_after' => $balance,
        'reason' => $reason, 'reference' => $reference, 'note' => $note, 'admin_id' => $adminId,
    ]);
    return $balance;
}

function order_history_add(int $orderId, array $d): void
{
    db_insert('order_status_history', [
        'order_id' => $orderId,
        'status' => $d['status'] ?? null,
        'payment_status' => $d['payment_status'] ?? null,
        'note' => $d['note'] ?? null,
        'is_customer_visible' => !empty($d['visible']) ? 1 : 0,
        'entry_type' => $d['type'] ?? 'status',
        'admin_id' => $d['admin_id'] ?? null,
    ]);
}

/**
 * Places an order from the current cart. Everything is recalculated and re-validated
 * under row locks inside one transaction. Returns the order row.
 * Throws RuntimeException with a customer-facing message on validation failure.
 *
 * $in: name, email, phone, address_line1, address_line2, city, region, postal_code,
 *      notes, payment_method, shipping_method, idempotency_key
 */
function order_place(array $in): array
{
    $existing = db_row('SELECT * FROM orders WHERE idempotency_key = ?', [$in['idempotency_key']]);
    if ($existing) {
        return $existing + ['_existing' => true]; // double submit: return the order that was already created
    }

    return db_transaction(function () use ($in) {
        $lines = cart_lines(true);
        if (!$lines) {
            throw new RuntimeException('Your bag is empty.');
        }
        $ctx = [
            'city' => $in['city'], 'region' => $in['region'], 'shipping_method' => $in['shipping_method'],
            'payment_method' => $in['payment_method'], 'email' => $in['email'],
        ];
        $t = cart_totals($lines, $ctx);
        if ($t['errors']) {
            throw new RuntimeException(implode(' ', $t['errors']));
        }
        if ($t['coupon_message']) {
            throw new RuntimeException($t['coupon_message'] . ' Please remove the coupon to continue.');
        }
        $pm = payment_method_get($in['payment_method']);
        $avail = payment_method_available($pm, $t['total'], $t['shipping']);
        if ($avail !== true) {
            throw new RuntimeException($avail);
        }

        $isOnline = $pm['code'] !== 'cod';
        $hasCustom = (bool) array_filter($lines, fn($l) => cart_has_customization($l) || $l['made_to_order']);
        $orderNumber = order_generate_number();
        $orderId = db_insert('orders', [
            'order_number' => $orderNumber,
            'access_token' => random_token(20),
            'idempotency_key' => $in['idempotency_key'],
            'customer_id' => customer_id(),
            'customer_name' => $in['name'],
            'email' => strtolower($in['email']),
            'phone' => $in['phone'],
            'status' => 'pending',
            'payment_status' => $isOnline ? 'pending' : 'unpaid',
            'payment_method' => $pm['code'],
            'currency' => setting('currency_code', 'PKR'),
            'subtotal' => $t['subtotal'],
            'customization_total' => $t['customization_total'],
            'discount_total' => $t['discount'],
            'shipping_total' => $t['shipping_fee'],
            'payment_fee' => $t['payment_fee'],
            'grand_total' => $t['total'],
            'coupon_id' => $t['coupon']['id'] ?? null,
            'coupon_code' => $t['coupon_code'],
            'shipping_zone' => $t['shipping']['zone'],
            'shipping_method' => $t['shipping']['method'],
            'estimated_delivery' => $t['shipping']['estimate'],
            'customer_notes' => nullable($in['notes'] ?? ''),
            'has_custom_items' => $hasCustom ? 1 : 0,
            'placed_ip' => client_ip(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);

        db_insert('order_addresses', [
            'order_id' => $orderId, 'address_type' => 'shipping', 'full_name' => $in['name'], 'phone' => $in['phone'],
            'address_line1' => $in['address_line1'], 'address_line2' => nullable($in['address_line2'] ?? ''),
            'city' => $in['city'], 'region' => $in['region'], 'postal_code' => nullable($in['postal_code'] ?? ''),
            'country' => setting('store_country', 'Pakistan'),
        ]);

        foreach ($lines as $l) {
            db_insert('order_items', [
                'order_id' => $orderId, 'product_id' => $l['product_id'], 'variant_id' => $l['variant_id'],
                'product_name' => $l['name'], 'sku' => $l['sku'], 'variant_label' => $l['variant_label'], 'image' => $l['image'],
                'unit_price' => $l['unit_price'], 'regular_price' => $l['regular_price'], 'qty' => $l['qty'],
                'customization_fee' => $l['customization_fee'], 'line_total' => $l['line_total'],
                'custom_measurements' => $l['custom_measurements'], 'custom_color' => $l['custom_color'],
                'personalization' => $l['personalization'], 'custom_instructions' => $l['custom_instructions'],
                'made_to_order' => $l['made_to_order'], 'lead_time_days' => $l['lead_time_days'],
                'is_custom' => $l['is_custom'], 'custom_spec' => $l['custom_spec'] ? json_encode($l['custom_spec'], JSON_UNESCAPED_UNICODE) : null,
            ]);
            if ((int) $l['product']['track_stock'] && !$l['is_custom']) { // custom pieces are cut to order, not taken from stock
                inventory_adjust($l['product_id'], $l['variant_id'], -$l['qty'], 'order', $orderNumber);
            }
        }

        if ($t['coupon']) {
            $ok = db_exec('UPDATE coupons SET used_count = used_count + 1 WHERE id = ? AND (usage_limit IS NULL OR used_count < usage_limit)', [(int) $t['coupon']['id']]);
            if (!$ok) {
                throw new RuntimeException('This coupon has just reached its usage limit. Please remove it to continue.');
            }
            db_insert('coupon_usage', [
                'coupon_id' => (int) $t['coupon']['id'], 'order_id' => $orderId, 'customer_id' => customer_id(),
                'email' => strtolower($in['email']), 'discount_amount' => $t['discount'],
            ]);
        }

        db_insert('payments', [
            'order_id' => $orderId, 'method' => $pm['code'], 'amount' => $t['total'], 'status' => 'pending',
            'gateway_reference' => $isOnline ? payment_reference($orderNumber) : null,
        ]);
        order_history_add($orderId, [
            'status' => 'pending',
            'payment_status' => $isOnline ? 'pending' : 'unpaid',
            'note' => 'Order placed' . ($isOnline ? ' — awaiting ' . $pm['name'] . ' payment.' : ' — Cash on Delivery.'),
            'visible' => true, 'type' => 'system',
        ]);

        db_exec('DELETE FROM cart_items WHERE cart_id = ?', [cart_id()]);
        db_exec('UPDATE carts SET coupon_code = NULL WHERE id = ?', [cart_id()]);
        return db_row('SELECT * FROM orders WHERE id = ?', [$orderId]);
    });
}

function order_items(int $orderId): array
{
    return db_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
}

function order_address(int $orderId): ?array
{
    return db_row("SELECT * FROM order_addresses WHERE order_id = ? AND address_type = 'shipping' LIMIT 1", [$orderId]);
}

function order_by_number(string $number): ?array
{
    return db_row('SELECT * FROM orders WHERE order_number = ?', [$number]);
}

/** Order visible to the current visitor: owner customer, or matching access token. */
function order_for_viewer(string $number, ?string $token = null): ?array
{
    $o = order_by_number($number);
    if (!$o) {
        return null;
    }
    if ($o['customer_id'] && (int) $o['customer_id'] === (int) customer_id()) {
        return $o;
    }
    if ($token && hash_equals($o['access_token'], $token)) {
        return $o;
    }
    if (in_array($number, $_SESSION['my_orders'] ?? [], true)) {
        return $o;
    }
    return null;
}

/** Puts stock back for every line of an order (once). */
function order_restore_stock(array $order, string $reason, ?int $adminId = null): void
{
    if ((int) $order['stock_restored']) {
        return;
    }
    foreach (order_items((int) $order['id']) as $it) {
        if (!$it['product_id'] || (int) $it['is_custom']) {
            continue;
        }
        $tracks = (int) db_val('SELECT track_stock FROM products WHERE id = ?', [(int) $it['product_id']]);
        if (!$tracks) {
            continue;
        }
        $variantExists = $it['variant_id'] ? db_val('SELECT 1 FROM product_variants WHERE id = ?', [(int) $it['variant_id']]) : false;
        inventory_adjust((int) $it['product_id'], $variantExists ? (int) $it['variant_id'] : null, (int) $it['qty'], $reason, $order['order_number'], null, $adminId);
    }
    db_exec('UPDATE orders SET stock_restored = 1 WHERE id = ?', [(int) $order['id']]);
}

/** Cancels an order: restores stock and releases the coupon use. */
function order_cancel(int $orderId, string $note = '', ?int $adminId = null, bool $visible = true): void
{
    db_transaction(function () use ($orderId, $note, $adminId, $visible) {
        $o = db_row('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$o || in_array($o['status'], ['cancelled', 'refunded'], true)) {
            return;
        }
        order_restore_stock($o, 'cancel', $adminId);
        if ($o['coupon_id']) {
            db_exec('UPDATE coupons SET used_count = GREATEST(used_count - 1, 0) WHERE id = ?', [(int) $o['coupon_id']]);
        }
        $pay = in_array($o['payment_status'], ['unpaid', 'pending', 'failed'], true) ? 'cancelled' : $o['payment_status'];
        db_exec('UPDATE orders SET status = ?, payment_status = ?, cancelled_at = NOW() WHERE id = ?', ['cancelled', $pay, $orderId]);
        db_exec("UPDATE payments SET status = 'cancelled' WHERE order_id = ? AND status = 'pending'", [$orderId]);
        order_history_add($orderId, ['status' => 'cancelled', 'payment_status' => $pay, 'note' => $note ?: 'Order cancelled.', 'visible' => $visible, 'admin_id' => $adminId]);
    });
}

/**
 * Cancels online-payment orders that were never paid after the configured number of hours,
 * releasing their reserved stock. Called from the admin dashboard and tools/cron.php.
 */
function orders_expire_unpaid(): int
{
    $hours = (int) setting('unpaid_order_timeout_hours', 48);
    if ($hours <= 0) {
        return 0;
    }
    $ids = db_col(
        "SELECT id FROM orders WHERE payment_method <> 'cod' AND payment_status IN ('pending','failed')
         AND status = 'pending' AND created_at < DATE_SUB(NOW(), INTERVAL ? HOUR)",
        [$hours]
    );
    foreach ($ids as $id) {
        order_cancel((int) $id, 'Cancelled automatically: payment not received within ' . $hours . ' hours.', null, true);
    }
    return count($ids);
}

/** Customer-facing tracking steps. */
function order_progress_steps(array $order): array
{
    $steps = ['pending' => 'Order Placed', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'delivered' => 'Delivered'];
    $keys = array_keys($steps);
    $current = array_search($order['status'], $keys, true);
    $out = [];
    foreach ($steps as $k => $label) {
        $i = array_search($k, $keys, true);
        $out[] = ['key' => $k, 'label' => $label, 'done' => $current !== false && $i <= $current, 'current' => $current === $i];
    }
    return $out;
}
