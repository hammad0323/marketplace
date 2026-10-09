<?php
/**
 * Checkout: totals, coupons and order placement. Everything is recalculated
 * from the database on the server; nothing price-related is trusted from
 * the browser.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

/** Returns [coupon|null, error|null, discount, free_shipping]. */
function coupon_evaluate(?string $code, float $merchTotal, string $email = '', ?int $customerId = null): array
{
    $code = strtoupper(trim((string)$code));
    if ($code === '') return [null, null, 0.0, false];
    $c = db_one("SELECT * FROM coupons WHERE code = ? AND status = 'active'", [$code]);
    if (!$c) return [null, 'This coupon code is not valid.', 0.0, false];
    $now = time();
    if ($c['starts_at'] && strtotime($c['starts_at']) > $now) return [null, 'This coupon is not active yet.', 0.0, false];
    if ($c['ends_at'] && strtotime($c['ends_at']) < $now) return [null, 'This coupon has expired.', 0.0, false];
    if ($c['usage_limit'] !== null && (int)$c['used_count'] >= (int)$c['usage_limit']) return [null, 'This coupon has reached its usage limit.', 0.0, false];
    if ($c['min_order'] !== null && $merchTotal < (float)$c['min_order']) {
        return [null, 'This coupon needs a minimum order of ' . money($c['min_order']) . '.', 0.0, false];
    }
    if ($c['per_customer_limit'] !== null && ($email !== '' || $customerId)) {
        $used = (int)db_val('SELECT COUNT(*) FROM coupon_usage cu JOIN orders o ON o.id = cu.order_id
                             WHERE cu.coupon_id = ? AND o.status NOT IN (\'cancelled\') AND (cu.email = ? OR (cu.customer_id IS NOT NULL AND cu.customer_id = ?))',
            [(int)$c['id'], strtolower($email), (int)$customerId]);
        if ($used >= (int)$c['per_customer_limit']) return [null, 'You have already used this coupon.', 0.0, false];
    }
    $discount = 0.0;
    $free = false;
    if ($c['type'] === 'percent') {
        $discount = $merchTotal * min(100, (float)$c['value']) / 100;
    } elseif ($c['type'] === 'fixed') {
        $discount = (float)$c['value'];
    } else {
        $free = true;
    }
    if ($c['max_discount'] !== null && (float)$c['max_discount'] > 0) $discount = min($discount, (float)$c['max_discount']);
    $discount = money2(min($discount, $merchTotal));
    return [$c, null, $discount, $free];
}

/**
 * Compute totals for cart lines.
 * $ctx: city, province, method (standard|express), payment, coupon, email, customer_id
 */
function checkout_totals(array $lines, array $ctx = []): array
{
    $subtotal = 0.0;
    $custom = 0.0;
    foreach ($lines as $l) {
        $subtotal += $l['unit_price'] * $l['qty'];
        $custom += $l['custom_fee'] * $l['qty'];
    }
    $subtotal = money2($subtotal);
    $custom = money2($custom);
    $merch = $subtotal + $custom;

    [$coupon, $couponError, $discount, $freeShip] = coupon_evaluate($ctx['coupon'] ?? null, $merch, $ctx['email'] ?? '', $ctx['customer_id'] ?? null);

    $zone = null;
    $options = [];
    $selected = null;
    if (!empty($ctx['city']) || !empty($ctx['province']) || !empty($ctx['estimate_default'])) {
        $zone = shipping_zone_for((string)($ctx['city'] ?? ''), (string)($ctx['province'] ?? ''));
        $options = shipping_options($zone, $lines, $merch - $discount);
        $method = $ctx['method'] ?? 'standard';
        $selected = $options[$method] ?? ($options['standard'] ?? (reset($options) ?: null));
    }
    $shipping = $selected ? (float)$selected['cost'] : 0.0;
    if ($freeShip && $selected) $shipping = 0.0;

    $preCod = money2($merch - $discount + $shipping);
    $codAvail = $zone ? cod_available($zone, $preCod) : payment_gateway_available('cod');
    $codFee = (($ctx['payment'] ?? '') === 'cod' && $zone && $codAvail) ? (float)$zone['cod_fee'] : 0.0;

    $errors = [];
    $minOrder = (float)setting('min_order_value', 0);
    if ($selected && $selected['min_order'] !== null) $minOrder = max($minOrder, (float)$selected['min_order']);
    if ($minOrder > 0 && $merch < $minOrder) {
        $errors[] = 'The minimum order value is ' . money($minOrder) . '.';
    }
    if ($zone === null && (!empty($ctx['city']) || !empty($ctx['province']))) {
        $errors[] = 'Sorry, we do not deliver to this location yet.';
    }

    return [
        'subtotal' => $subtotal,
        'custom_total' => $custom,
        'merch_total' => money2($merch),
        'discount' => $discount,
        'coupon' => $coupon,
        'coupon_error' => $couponError,
        'free_shipping_coupon' => $freeShip,
        'zone' => $zone,
        'shipping_options' => $options,
        'shipping' => $selected,
        'shipping_total' => money2($shipping),
        'cod_available' => $codAvail,
        'cod_fee' => money2($codFee),
        'grand_total' => money2($preCod + $codFee),
        'errors' => $errors,
    ];
}

function order_number_generate(): string
{
    $prefix = preg_replace('/[^A-Z0-9]/', '', strtoupper((string)setting('order_prefix', 'EB'))) ?: 'EB';
    for ($i = 0; $i < 10; $i++) {
        $n = $prefix . date('ymd') . random_int(1000, 9999);
        if (!db_val('SELECT id FROM orders WHERE order_number = ?', [$n])) return $n;
    }
    return $prefix . date('ymdHis') . random_int(10, 99);
}

/** A one-time token rendered into the checkout form; prevents double submission. */
function checkout_token(): string
{
    if (empty($_SESSION['checkout_token'])) $_SESSION['checkout_token'] = random_token(32);
    return $_SESSION['checkout_token'];
}

/**
 * Place an order. $d keys: name, email, phone, address1, address2, city, province, postal_code,
 * notes, method, payment, coupon, custom_terms (bool), checkout_token, customer_id
 * Returns the order row. Throws InvalidArgumentException for customer-facing errors.
 */
function checkout_place_order(array $d): array
{
    $existing = db_one('SELECT * FROM orders WHERE checkout_token = ?', [$d['checkout_token']]);
    if ($existing) return $existing; // duplicate submit → same order

    $cartId = cart_id(false);
    if (!$cartId) throw new InvalidArgumentException('Your bag is empty.');

    return db_tx(function () use ($d, $cartId) {
        // Lock the cart's inventory rows so two shoppers cannot buy the last piece.
        $variantIds = db_col('SELECT variant_id FROM cart_items WHERE cart_id = ? ORDER BY variant_id', [$cartId]);
        if (!$variantIds) throw new InvalidArgumentException('Your bag is empty.');
        db_all('SELECT variant_id, quantity FROM product_inventory WHERE variant_id IN (' . db_in($variantIds) . ') FOR UPDATE', $variantIds);

        $lines = cart_lines($cartId);
        foreach ($lines as $l) {
            if (!$l['available']) throw new InvalidArgumentException($l['name'] . ': ' . $l['issue']);
        }
        // Per-variant total across lines (same variant with different customisations).
        $need = [];
        foreach ($lines as $l) {
            if ($l['track_inventory']) $need[$l['variant_id']] = ($need[$l['variant_id']] ?? 0) + $l['qty'];
        }
        foreach ($lines as $l) {
            if ($l['track_inventory'] && $need[$l['variant_id']] > $l['stock']) {
                throw new InvalidArgumentException($l['name'] . ': only ' . $l['stock'] . ' available.');
            }
        }

        $t = checkout_totals($lines, [
            'city' => $d['city'], 'province' => $d['province'], 'method' => $d['method'], 'payment' => $d['payment'],
            'coupon' => $d['coupon'], 'email' => $d['email'], 'customer_id' => $d['customer_id'],
        ]);
        if ($t['errors']) throw new InvalidArgumentException($t['errors'][0]);
        if (!$t['shipping']) throw new InvalidArgumentException('Delivery is not available for this address.');
        if ($d['coupon'] && $t['coupon_error']) throw new InvalidArgumentException($t['coupon_error']);

        $methods = payment_methods_for_checkout($t['zone'], $t['grand_total']);
        if (!isset($methods[$d['payment']])) throw new InvalidArgumentException('Please choose an available payment method.');

        $hasCustom = cart_has_made_to_order($lines);
        if ($hasCustom && empty($d['custom_terms'])) {
            throw new InvalidArgumentException('Please confirm the made-to-order / customisation terms.');
        }

        if ($t['coupon']) {
            $c = db_one('SELECT * FROM coupons WHERE id = ? FOR UPDATE', [(int)$t['coupon']['id']]);
            if ($c['usage_limit'] !== null && (int)$c['used_count'] >= (int)$c['usage_limit']) {
                throw new InvalidArgumentException('This coupon has reached its usage limit.');
            }
        }

        $number = order_number_generate();
        $orderId = db_insert('INSERT INTO orders (order_number, lookup_key, checkout_token, customer_id, customer_name, email, phone, status, payment_status, payment_method,
                currency, subtotal, customization_total, discount_total, shipping_total, cod_fee, grand_total, coupon_code, shipping_zone_id, shipping_method,
                shipping_label, estimated_delivery, has_custom_items, custom_terms_accepted, customer_note, ip, user_agent)
                VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            $number, random_token(16), $d['checkout_token'], $d['customer_id'], $d['name'], strtolower($d['email']), $d['phone'],
            $d['payment'] === 'cod' ? 'unpaid' : 'pending', $d['payment'], setting('currency_code', 'PKR'),
            $t['subtotal'], $t['custom_total'], $t['discount'], $t['shipping_total'], $t['cod_fee'], $t['grand_total'],
            $t['coupon']['code'] ?? null, (int)$t['zone']['id'], $t['shipping']['method'], $t['shipping']['label'], $t['shipping']['est_text'],
            $hasCustom ? 1 : 0, $hasCustom ? 1 : 0, $d['notes'] !== '' ? $d['notes'] : null, client_ip(), mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);

        foreach ($lines as $l) {
            db_insert('INSERT INTO order_items (order_id, product_id, variant_id, product_name, sku, variant_label, image, unit_price, regular_price, quantity,
                       customization_fee, line_total, custom_length, custom_sleeve, custom_notes, fulfillment_type, lead_days, stock_deducted)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
                $orderId, $l['product_id'], $l['variant_id'], $l['name'], $l['sku'], $l['variant_label'], $l['image'], $l['unit_price'], $l['regular_price'],
                $l['qty'], $l['custom_fee'], $l['line_total'], $l['custom_length'], $l['custom_sleeve'], $l['custom_notes'],
                $l['customised'] ? 'made_to_order' : $l['fulfillment_type'], $l['lead_days'], $l['track_inventory'] ? 1 : 0,
            ]);
            if ($l['track_inventory']) {
                inventory_change($l['variant_id'], -$l['qty'], 'order', 'order', $orderId, 'Order ' . $number);
            }
        }

        db_insert('INSERT INTO order_addresses (order_id, type, full_name, phone, address_line1, address_line2, city, province, postal_code) VALUES (?, \'shipping\', ?, ?, ?, ?, ?, ?, ?)',
            [$orderId, $d['name'], $d['phone'], $d['address1'], $d['address2'] ?: null, $d['city'], $d['province'] ?: null, $d['postal_code'] ?: null]);

        db_insert('INSERT INTO order_status_history (order_id, status, payment_status, note, is_customer_visible) VALUES (?, \'pending\', ?, ?, 1)',
            [$orderId, $d['payment'] === 'cod' ? 'unpaid' : 'pending', 'Order placed.']);

        if ($t['coupon']) {
            db_insert('INSERT INTO coupon_usage (coupon_id, order_id, customer_id, email, discount) VALUES (?, ?, ?, ?, ?)',
                [(int)$t['coupon']['id'], $orderId, $d['customer_id'], strtolower($d['email']), $t['discount']]);
            db_exec('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [(int)$t['coupon']['id']]);
        }

        db_insert('INSERT INTO payments (order_id, method, amount, currency, status, mode) VALUES (?, ?, ?, ?, \'pending\', ?)',
            [$orderId, $d['payment'], $t['grand_total'], setting('currency_code', 'PKR'), $d['payment'] === 'cod' ? null : payment_gateway($d['payment'])['mode'] ?? null]);

        db_exec('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
        db_exec('UPDATE carts SET coupon_code = NULL WHERE id = ?', [$cartId]);

        return db_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    });
}

/**
 * Change stock for a variant and record the movement. Call inside a
 * transaction when part of a larger operation.
 */
function inventory_change(int $variantId, int $delta, string $reason, ?string $refType = null, ?int $refId = null, ?string $note = null): int
{
    db_exec('INSERT INTO product_inventory (variant_id, quantity) VALUES (?, 0) ON DUPLICATE KEY UPDATE variant_id = variant_id', [$variantId]);
    db_exec('UPDATE product_inventory SET quantity = quantity + ? WHERE variant_id = ?', [$delta, $variantId]);
    $bal = (int)db_val('SELECT quantity FROM product_inventory WHERE variant_id = ?', [$variantId]);
    db_insert('INSERT INTO inventory_movements (variant_id, change_qty, balance_after, reason, reference_type, reference_id, note, admin_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$variantId, $delta, $bal, $reason, $refType, $refId, $note, admin_id()]);
    return $bal;
}
