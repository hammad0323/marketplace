<?php
/**
 * Shopping cart (DB backed). Guests are identified by a random cookie token;
 * logged-in customers keep their cart across devices (merged on login).
 * Prices are NEVER taken from the client - cart_summary() recalculates.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

const CART_COOKIE = 'fs_cart';

function cart_id(bool $create = false): int
{
    static $id = null;
    if ($id) return $id;
    $cid = customer_id();
    if ($cid) {
        $id = (int) db_val('SELECT id FROM carts WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$cid]);
        if (!$id && $create) {
            $id = db_insert('INSERT INTO carts (customer_id, session_token) VALUES (?, ?)', [$cid, bin2hex(random_bytes(20))]);
        }
        return (int) $id;
    }
    $token = $_COOKIE[CART_COOKIE] ?? '';
    if (preg_match('/^[a-f0-9]{40}$/', $token)) {
        $id = (int) db_val('SELECT id FROM carts WHERE session_token = ? AND customer_id IS NULL', [$token]);
    }
    if (!$id && $create) {
        $token = bin2hex(random_bytes(20));
        $id = db_insert('INSERT INTO carts (session_token) VALUES (?)', [$token]);
        if (!headers_sent()) {
            setcookie(CART_COOKIE, $token, ['expires' => time() + 86400 * 30, 'path' => base_path() ?: '/', 'httponly' => true, 'samesite' => 'Lax',
                'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
        }
    }
    return (int) $id;
}

/** Merge the guest cookie cart into the customer's cart after login. */
function cart_merge_on_login(int $customerId): void
{
    $token = $_COOKIE[CART_COOKIE] ?? '';
    if (!preg_match('/^[a-f0-9]{40}$/', $token)) return;
    $guest = (int) db_val('SELECT id FROM carts WHERE session_token = ? AND customer_id IS NULL', [$token]);
    if (!$guest) return;
    $mine = (int) db_val('SELECT id FROM carts WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$customerId]);
    if (!$mine) {
        db_exec('UPDATE carts SET customer_id = ? WHERE id = ?', [$customerId, $guest]);
        return;
    }
    foreach (db_all('SELECT * FROM cart_items WHERE cart_id = ?', [$guest]) as $it) {
        db_exec('INSERT INTO cart_items (cart_id, product_id, variation_id, quantity) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = GREATEST(quantity, VALUES(quantity))', [$mine, $it['product_id'], $it['variation_id'], $it['quantity']]);
    }
    db_exec('DELETE FROM carts WHERE id = ?', [$guest]);
}

/**
 * Add / set quantity. Validates product, variation, stock and min/max limits.
 * @return string|null error message
 */
function cart_set(int $productId, int $variationId, int $qty, bool $increment = true): ?string
{
    $item = sellable_item($productId, $variationId);
    if (!$item) return $variationId || !db_val('SELECT has_variations FROM products WHERE id = ?', [$productId]) ? 'This product is not available.' : 'Please select an option (size/weight) first.';
    $cart = cart_id(true);
    $current = (int) db_val('SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND variation_id = ?', [$cart, $productId, $item['variation_id']]);
    $new = $increment ? $current + $qty : $qty;
    if ($new <= 0) {
        db_exec('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ? AND variation_id = ?', [$cart, $productId, $item['variation_id']]);
        return null;
    }
    if ($new < $item['min_qty']) $new = $item['min_qty'];
    if ($item['max_qty'] && $new > $item['max_qty']) {
        return 'You can order a maximum of ' . $item['max_qty'] . ' of this item.';
    }
    if ($item['track_stock'] && $new > $item['stock']) {
        return $item['stock'] > 0 ? 'Only ' . $item['stock'] . ' available in stock.' : 'Sorry, this item is out of stock.';
    }
    db_exec('INSERT INTO cart_items (cart_id, product_id, variation_id, quantity) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)',
        [$cart, $productId, $item['variation_id'], $new]);
    return null;
}

function cart_remove(int $productId, int $variationId): void
{
    $cart = cart_id();
    if ($cart) db_exec('DELETE FROM cart_items WHERE cart_id = ? AND product_id = ? AND variation_id = ?', [$cart, $productId, $variationId]);
}

function cart_clear(): void
{
    $cart = cart_id();
    if ($cart) {
        db_exec('DELETE FROM cart_items WHERE cart_id = ?', [$cart]);
        db_exec('UPDATE carts SET coupon_code = NULL WHERE id = ?', [$cart]);
    }
}

function cart_raw_items(): array
{
    $cart = cart_id();
    return $cart ? db_all('SELECT product_id, variation_id, quantity FROM cart_items WHERE cart_id = ? ORDER BY id', [$cart]) : [];
}

function cart_count(): int
{
    $cart = cart_id();
    return $cart ? (int) db_val('SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE cart_id = ?', [$cart]) : 0;
}

function cart_coupon(): ?string
{
    $cart = cart_id();
    return $cart ? db_val('SELECT coupon_code FROM carts WHERE id = ?', [$cart]) : null;
}

function cart_set_coupon(?string $code): void
{
    $cart = cart_id(true);
    db_exec('UPDATE carts SET coupon_code = ? WHERE id = ?', [$code ?: null, $cart]);
}

/**
 * Price a list of [product_id, variation_id, quantity] lines on the server.
 * Returns lines with live prices plus any problems (out of stock etc).
 */
function price_lines(array $raw): array
{
    $lines = [];
    $issues = [];
    $subtotal = 0.0;
    $count = 0;
    foreach ($raw as $r) {
        $qty = max(0, (int) $r['quantity']);
        if ($qty === 0) continue;
        $item = sellable_item((int) $r['product_id'], (int) $r['variation_id']);
        if (!$item) {
            $issues[] = 'An item in your cart is no longer available and was skipped.';
            continue;
        }
        $problem = null;
        if ($item['track_stock'] && $qty > $item['stock']) {
            $problem = $item['stock'] > 0 ? 'Only ' . $item['stock'] . ' left' : 'Out of stock';
            $issues[] = $item['name'] . ': ' . $problem;
        }
        if ($qty < $item['min_qty']) {
            $problem = 'Minimum ' . $item['min_qty'];
            $issues[] = $item['name'] . ': minimum order quantity is ' . $item['min_qty'];
        }
        if ($item['max_qty'] && $qty > $item['max_qty']) {
            $problem = 'Maximum ' . $item['max_qty'];
            $issues[] = $item['name'] . ': maximum order quantity is ' . $item['max_qty'];
        }
        $item['quantity'] = $qty;
        $item['line_total'] = round($item['unit_price'] * $qty, 2);
        $item['problem'] = $problem;
        $subtotal += $item['line_total'];
        $count += $qty;
        $lines[] = $item;
    }
    return ['lines' => $lines, 'subtotal' => round($subtotal, 2), 'count' => $count, 'issues' => $issues];
}

/**
 * Full cart summary: lines, subtotal, coupon discount, delivery fee, total.
 * $ctx: fulfillment (delivery|pickup), zone_id, coupon, phone
 */
function cart_summary(array $ctx = [], ?array $rawLines = null): array
{
    $priced = price_lines($rawLines ?? cart_raw_items());
    $fulfillment = $ctx['fulfillment'] ?? 'delivery';
    $zone = !empty($ctx['zone_id']) ? delivery_zone((int) $ctx['zone_id']) : null;
    $couponCode = array_key_exists('coupon', $ctx) ? $ctx['coupon'] : cart_coupon();

    $discount = 0.0;
    $coupon = null;
    $couponError = null;
    $freeDelivery = false;
    if ($couponCode) {
        $res = coupon_evaluate($couponCode, $priced['subtotal'], customer_id(), $ctx['phone'] ?? null);
        if (is_string($res)) {
            $couponError = $res;
        } else {
            $coupon = $res['coupon'];
            $discount = $res['discount'];
            $freeDelivery = $res['free_delivery'];
        }
    }
    $deliveryFee = 0.0;
    $deliveryNote = null;
    if ($fulfillment === 'delivery') {
        $deliveryFee = delivery_fee_for($zone, $priced['subtotal'] - $discount);
        if ($freeDelivery) $deliveryFee = 0.0;
        if ($zone && $priced['subtotal'] < (float) $zone['min_order_amount']) {
            $deliveryNote = 'Minimum order for ' . $zone['area'] . ' is ' . money($zone['min_order_amount']) . '.';
        }
    }
    $total = max(0, round($priced['subtotal'] - $discount + $deliveryFee, 2));
    return $priced + [
        'discount' => round($discount, 2), 'coupon' => $coupon, 'coupon_code' => $coupon ? $coupon['code'] : null, 'coupon_error' => $couponError,
        'delivery_fee' => round($deliveryFee, 2), 'delivery_note' => $deliveryNote, 'zone' => $zone, 'fulfillment' => $fulfillment, 'total' => $total,
    ];
}

/* ==================================================================
 * DELIVERY
 * ================================================================== */
function delivery_zones(bool $activeOnly = true): array
{
    return db_all('SELECT * FROM delivery_zones' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY city, sort_order, area');
}

function delivery_zone(int $id): ?array
{
    return db_row('SELECT * FROM delivery_zones WHERE id = ? AND is_active = 1', [$id]);
}

function delivery_fee_for(?array $zone, float $amount): float
{
    if ($zone) {
        if ($zone['free_delivery_over'] !== null && (float) $zone['free_delivery_over'] > 0 && $amount >= (float) $zone['free_delivery_over']) return 0.0;
        return (float) $zone['delivery_fee'];
    }
    $free = (float) setting('free_delivery_over', 0);
    if ($free > 0 && $amount >= $free) return 0.0;
    return (float) setting('default_delivery_fee', 150);
}

/** Selected delivery area (header location picker), stored in a cookie. */
function selected_zone(): ?array
{
    $id = (int) ($_COOKIE['fs_zone'] ?? 0);
    return $id ? delivery_zone($id) : null;
}

/* ==================================================================
 * COUPONS
 * ================================================================== */
/**
 * @return array|string ['coupon'=>row,'discount'=>float,'free_delivery'=>bool] or error message
 */
function coupon_evaluate(string $code, float $subtotal, int $customerId = 0, ?string $phone = null)
{
    $code = strtoupper(trim($code));
    $c = db_row('SELECT * FROM coupons WHERE code = ?', [$code]);
    if (!$c || !$c['is_active']) return 'This coupon code is not valid.';
    $now = time();
    if ($c['starts_at'] && strtotime($c['starts_at']) > $now) return 'This coupon is not active yet.';
    if ($c['ends_at'] && strtotime($c['ends_at']) < $now) return 'This coupon has expired.';
    if ($c['usage_limit'] !== null && (int) $c['used_count'] >= (int) $c['usage_limit']) return 'This coupon has reached its usage limit.';
    if ($subtotal < (float) $c['min_order_amount']) return 'Add items worth ' . money((float) $c['min_order_amount'] - $subtotal) . ' more to use this coupon.';
    if ($c['per_customer_limit'] !== null && ($customerId || $phone)) {
        $used = (int) db_val('SELECT COUNT(*) FROM coupon_usage cu JOIN orders o ON o.id = cu.order_id WHERE cu.coupon_id = ? AND o.status <> \'cancelled\' AND (cu.customer_id = ? OR cu.customer_phone = ?)',
            [$c['id'], $customerId ?: -1, $phone ? normalize_phone($phone) : '-']);
        if ($used >= (int) $c['per_customer_limit']) return 'You have already used this coupon.';
    }
    $discount = 0.0;
    $free = false;
    if ($c['discount_type'] === 'percent') {
        $discount = $subtotal * ((float) $c['discount_value'] / 100);
        if ($c['max_discount'] !== null && (float) $c['max_discount'] > 0) $discount = min($discount, (float) $c['max_discount']);
    } elseif ($c['discount_type'] === 'fixed') {
        $discount = min((float) $c['discount_value'], $subtotal);
    } else {
        $free = true;
    }
    return ['coupon' => $c, 'discount' => round($discount, 2), 'free_delivery' => $free];
}
