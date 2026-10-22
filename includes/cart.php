<?php
/**
 * Shopping cart. Guests are identified by a random token cookie (persistent guest cart);
 * signed-in customers by customer_id (cart follows them across devices).
 * Prices are never stored in the cart — they are recalculated from the catalogue every time.
 */

const UC_CART_MAX_QTY = 20;

function cart_cookie_set(string $token): void
{
    setcookie('uc_cart', $token, ['expires' => time() + 86400 * 30, 'path' => (BASE_PATH ?: '') . '/', 'secure' => uc_is_https(), 'httponly' => true, 'samesite' => 'Lax']);
    $_COOKIE['uc_cart'] = $token;
}

function cart_forget_cookie(): void
{
    setcookie('uc_cart', '', ['expires' => time() - 3600, 'path' => (BASE_PATH ?: '') . '/']);
    unset($_COOKIE['uc_cart']);
}

function cart_id(bool $create = false): ?int
{
    static $resolved = null;
    if ($resolved) {
        return $resolved;
    }
    $cid = customer_id();
    if ($cid) {
        $id = db_val('SELECT id FROM carts WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$cid]);
        if (!$id && $create) {
            $id = db_insert('carts', ['token' => random_token(24), 'customer_id' => $cid]);
        }
        return $resolved = ($id ? (int) $id : null);
    }
    $token = $_COOKIE['uc_cart'] ?? '';
    if ($token && preg_match('/^[a-f0-9]{48}$/', $token)) {
        $id = db_val('SELECT id FROM carts WHERE token = ? AND customer_id IS NULL', [$token]);
        if ($id) {
            return $resolved = (int) $id;
        }
    }
    if (!$create) {
        return null;
    }
    $token = random_token(24);
    $id = db_insert('carts', ['token' => $token]);
    cart_cookie_set($token);
    return $resolved = $id;
}

/** After login: move guest cart lines into the customer's cart. */
function cart_merge_guest(int $customerId, ?string $token): void
{
    if (!$token || !preg_match('/^[a-f0-9]{48}$/', $token)) {
        return;
    }
    $guest = db_row('SELECT * FROM carts WHERE token = ? AND customer_id IS NULL', [$token]);
    if (!$guest) {
        return;
    }
    $mine = db_val('SELECT id FROM carts WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$customerId]);
    if (!$mine) {
        db_exec('UPDATE carts SET customer_id = ? WHERE id = ?', [$customerId, (int) $guest['id']]);
        return;
    }
    foreach (db_all('SELECT * FROM cart_items WHERE cart_id = ?', [(int) $guest['id']]) as $line) {
        $existing = db_row(
            'SELECT id, qty FROM cart_items WHERE cart_id = ? AND product_id = ? AND variant_id <=> ? AND custom_hash = ?',
            [(int) $mine, (int) $line['product_id'], $line['variant_id'] !== null ? (int) $line['variant_id'] : null, $line['custom_hash']]
        );
        if ($existing) {
            db_exec('UPDATE cart_items SET qty = LEAST(?, qty + ?) WHERE id = ?', [UC_CART_MAX_QTY, (int) $line['qty'], (int) $existing['id']]);
        } else {
            db_exec('UPDATE cart_items SET cart_id = ? WHERE id = ?', [(int) $mine, (int) $line['id']]);
        }
    }
    if ($guest['coupon_code']) {
        db_exec('UPDATE carts SET coupon_code = ? WHERE id = ?', [$guest['coupon_code'], (int) $mine]);
    }
    db_exec('DELETE FROM carts WHERE id = ?', [(int) $guest['id']]);
}

/** Validates and normalises customisation input for a product. Returns [data, error]. */
function cart_customization(array $product, array $in): array
{
    $data = ['custom_measurements' => null, 'custom_color' => null, 'personalization' => null, 'custom_instructions' => null];
    if (!(int) $product['is_customizable']) {
        return [$data, null];
    }
    $allowed = array_filter(explode(',', (string) $product['custom_fields']));
    $map = ['measurements' => ['custom_measurements', 600], 'color' => ['custom_color', 80], 'personalization' => ['personalization', 40], 'instructions' => ['custom_instructions', 1000]];
    foreach ($map as $field => [$col, $max]) {
        $val = trim((string) ($in[$col] ?? ''));
        if ($val === '') {
            continue;
        }
        if (!in_array($field, $allowed, true)) {
            continue;
        }
        if (mb_strlen($val) > $max) {
            return [$data, ucfirst($field) . ' is too long (max ' . $max . ' characters).'];
        }
        $data[$col] = $val;
    }
    return [$data, null];
}

function cart_has_customization(array $line): bool
{
    return ($line['custom_measurements'] ?? null) || ($line['custom_color'] ?? null) || ($line['personalization'] ?? null) || ($line['custom_instructions'] ?? null);
}

/** Adds an item. Returns ['ok' => bool, 'message' => string]. */
function cart_add(int $productId, ?int $variantId, int $qty, array $customIn = []): array
{
    $qty = max(1, min(UC_CART_MAX_QTY, $qty));
    $product = db_row("SELECT * FROM products WHERE id = ? AND status = 'published'", [$productId]);
    if (!$product) {
        return ['ok' => false, 'message' => 'This product is no longer available.'];
    }
    $variant = null;
    if ((int) $product['has_variants']) {
        if (!$variantId) {
            return ['ok' => false, 'message' => 'Please select your size and options.'];
        }
        $variant = db_row('SELECT * FROM product_variants WHERE id = ? AND product_id = ? AND is_active = 1', [$variantId, $productId]);
        if (!$variant) {
            return ['ok' => false, 'message' => 'The selected option is not available.'];
        }
    } else {
        $variantId = null;
    }
    [$custom, $err] = cart_customization($product, $customIn);
    if ($err) {
        return ['ok' => false, 'message' => $err];
    }
    $hash = md5(json_encode($custom));
    $cartId = cart_id(true);
    $existing = db_row('SELECT id, qty FROM cart_items WHERE cart_id = ? AND product_id = ? AND variant_id <=> ? AND custom_hash = ?', [$cartId, $productId, $variantId, $hash]);
    $newQty = min(UC_CART_MAX_QTY, ($existing ? (int) $existing['qty'] : 0) + $qty);
    $available = available_qty($product, $variant);
    if ($available <= 0) {
        return ['ok' => false, 'message' => 'Sorry, this item is out of stock.'];
    }
    if ($newQty > $available) {
        return ['ok' => false, 'message' => 'Only ' . $available . ' left in stock' . ($existing ? ' (you already have ' . (int) $existing['qty'] . ' in your bag).' : '.')];
    }
    if ($existing) {
        db_exec('UPDATE cart_items SET qty = ? WHERE id = ?', [$newQty, (int) $existing['id']]);
    } else {
        db_insert('cart_items', array_merge($custom, [
            'cart_id' => $cartId, 'product_id' => $productId, 'variant_id' => $variantId, 'qty' => $newQty, 'custom_hash' => $hash,
        ]));
    }
    return ['ok' => true, 'message' => $product['name'] . ' added to your bag.'];
}

function cart_update_qty(int $itemId, int $qty): array
{
    $cartId = cart_id();
    $line = $cartId ? db_row('SELECT * FROM cart_items WHERE id = ? AND cart_id = ?', [$itemId, $cartId]) : null;
    if (!$line) {
        return ['ok' => false, 'message' => 'Item not found in your bag.'];
    }
    if ($qty <= 0) {
        db_exec('DELETE FROM cart_items WHERE id = ?', [$itemId]);
        return ['ok' => true, 'message' => 'Item removed.'];
    }
    $qty = min(UC_CART_MAX_QTY, $qty);
    $product = db_row('SELECT * FROM products WHERE id = ?', [(int) $line['product_id']]);
    $variant = $line['variant_id'] ? db_row('SELECT * FROM product_variants WHERE id = ?', [(int) $line['variant_id']]) : null;
    $available = $product ? available_qty($product, $variant) : 0;
    if ($qty > $available) {
        db_exec('UPDATE cart_items SET qty = ? WHERE id = ?', [max(1, min($available, UC_CART_MAX_QTY)), $itemId]);
        return ['ok' => false, 'message' => 'Only ' . $available . ' available.'];
    }
    db_exec('UPDATE cart_items SET qty = ? WHERE id = ?', [$qty, $itemId]);
    return ['ok' => true, 'message' => 'Bag updated.'];
}

function cart_remove(int $itemId): void
{
    $cartId = cart_id();
    if ($cartId) {
        db_exec('DELETE FROM cart_items WHERE id = ? AND cart_id = ?', [$itemId, $cartId]);
    }
}

function cart_clear(): void
{
    $cartId = cart_id();
    if ($cartId) {
        db_exec('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
        db_exec('UPDATE carts SET coupon_code = NULL WHERE id = ?', [$cartId]);
    }
}

function cart_count(): int
{
    $cartId = cart_id();
    return $cartId ? (int) db_val('SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE cart_id = ?', [$cartId]) : 0;
}

/**
 * Cart lines joined with live catalogue data. Every line carries its current price,
 * availability and an 'error' when it cannot be purchased as is.
 * Pass $lock = true inside a transaction to lock stock rows (SELECT … FOR UPDATE).
 */
function cart_lines(bool $lock = false): array
{
    $cartId = cart_id();
    if (!$cartId) {
        return [];
    }
    $lines = db_all('SELECT * FROM cart_items WHERE cart_id = ? ORDER BY id', [$cartId]);
    $out = [];
    $forUpdate = $lock ? ' FOR UPDATE' : '';
    foreach ($lines as $l) {
        $product = db_row('SELECT * FROM products WHERE id = ?' . $forUpdate, [(int) $l['product_id']]);
        if (!$product) {
            continue;
        }
        $variant = $l['variant_id'] ? db_row('SELECT * FROM product_variants WHERE id = ?' . $forUpdate, [(int) $l['variant_id']]) : null;
        $optValues = [];
        if ($variant) {
            foreach (db_all('SELECT option_name, option_value FROM product_variant_values WHERE variant_id = ? ORDER BY sort_order, id', [(int) $variant['id']]) as $o) {
                $optValues[$o['option_name']] = $o['option_value'];
            }
        }
        $price = price_info($product, $variant);
        $available = available_qty($product, $variant);
        $error = null;
        if ($product['status'] !== 'published') {
            $error = 'No longer available';
        } elseif ((int) $product['has_variants'] && (!$variant || !(int) $variant['is_active'] || (int) $variant['product_id'] !== (int) $product['id'])) {
            $error = 'Selected option unavailable';
        } elseif ($available <= 0) {
            $error = 'Out of stock';
        } elseif ((int) $l['qty'] > $available) {
            $error = 'Only ' . $available . ' left';
        }
        $customFee = cart_has_customization($l) ? (float) $product['customization_fee'] : 0.0;
        $img = db_row('SELECT image FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order, id LIMIT 1', [(int) $product['id']]);
        if (!empty($optValues['Color'])) {
            $colorImg = db_row('SELECT image FROM product_images WHERE product_id = ? AND color_name = ? ORDER BY is_main DESC, sort_order LIMIT 1', [(int) $product['id'], $optValues['Color']]);
            $img = $colorImg ?: $img;
        }
        $out[] = [
            'id' => (int) $l['id'],
            'product' => $product,
            'variant' => $variant,
            'product_id' => (int) $product['id'],
            'variant_id' => $variant ? (int) $variant['id'] : null,
            'name' => $product['name'],
            'slug' => $product['slug'],
            'url' => url('product/' . $product['slug']),
            'sku' => $variant['sku'] ?? $product['sku'],
            'options' => $optValues,
            'variant_label' => $optValues ? variant_label($optValues) : null,
            'image' => $img['image'] ?? null,
            'qty' => (int) $l['qty'],
            'unit_price' => $price['final'],
            'regular_price' => $price['regular'],
            'customization_fee' => $customFee,
            'line_total' => round(($price['final'] + $customFee) * (int) $l['qty'], 2),
            'available' => $available,
            'error' => $error,
            'made_to_order' => (int) $product['made_to_order'],
            'lead_time_days' => (int) $product['lead_time_days'],
            'custom_measurements' => $l['custom_measurements'],
            'custom_color' => $l['custom_color'],
            'personalization' => $l['personalization'],
            'custom_instructions' => $l['custom_instructions'],
        ];
    }
    return $out;
}

function cart_coupon_code(): ?string
{
    $cartId = cart_id();
    return $cartId ? db_val('SELECT coupon_code FROM carts WHERE id = ?', [$cartId]) : null;
}

function cart_set_coupon(?string $code): void
{
    $cartId = cart_id(true);
    db_exec('UPDATE carts SET coupon_code = ? WHERE id = ?', [$code, $cartId]);
}

/**
 * Full server-side calculation of the order totals.
 * $ctx: city, region, shipping_method, payment_method, email (for coupon per-customer limits)
 */
function cart_totals(array $lines, array $ctx = []): array
{
    $subtotal = 0.0;
    $customTotal = 0.0;
    $errors = [];
    $maxLead = 0;
    foreach ($lines as $l) {
        $subtotal += $l['unit_price'] * $l['qty'];
        $customTotal += $l['customization_fee'] * $l['qty'];
        if ($l['error']) {
            $errors[] = $l['name'] . ': ' . $l['error'];
        }
        if ($l['made_to_order']) {
            $maxLead = max($maxLead, $l['lead_time_days']);
        }
    }
    $subtotal = round($subtotal, 2);
    $merch = $subtotal + $customTotal;

    // Coupon
    $discount = 0.0;
    $coupon = null;
    $couponMessage = null;
    $freeShipCoupon = false;
    $code = $ctx['coupon_code'] ?? cart_coupon_code();
    if ($code) {
        $res = coupon_evaluate($code, $merch, $ctx['email'] ?? (current_customer()['email'] ?? null), customer_id());
        if ($res['ok']) {
            $coupon = $res['coupon'];
            $discount = $res['discount'];
            $freeShipCoupon = $res['free_shipping'];
        } else {
            $couponMessage = $res['message'];
        }
    }

    // Delivery
    $shipping = shipping_quote($ctx['city'] ?? '', $ctx['region'] ?? '', $merch - $discount, $ctx['shipping_method'] ?? 'standard', $maxLead);
    $shippingFee = $freeShipCoupon ? 0.0 : $shipping['fee'];

    // Minimum order
    $minOrder = (float) setting('min_order_value', 0);
    if ($minOrder > 0 && $merch < $minOrder) {
        $errors[] = 'The minimum order value is ' . money($minOrder) . '.';
    }
    if ($shipping['min_order'] && $merch < $shipping['min_order']) {
        $errors[] = 'The minimum order for delivery to this area is ' . money($shipping['min_order']) . '.';
    }

    // Payment fee
    $paymentFee = 0.0;
    if (!empty($ctx['payment_method'])) {
        $pm = payment_method_get($ctx['payment_method']);
        $paymentFee = $pm ? (float) $pm['fee'] : 0.0;
    }

    $total = round(max(0, $merch - $discount) + $shippingFee + $paymentFee, 2);
    return [
        'subtotal' => $subtotal,
        'customization_total' => round($customTotal, 2),
        'discount' => round($discount, 2),
        'coupon' => $coupon,
        'coupon_code' => $coupon['code'] ?? null,
        'coupon_message' => $couponMessage,
        'shipping' => $shipping,
        'shipping_fee' => round($shippingFee, 2),
        'payment_fee' => round($paymentFee, 2),
        'total' => $total,
        'errors' => $errors,
        'max_lead_days' => $maxLead,
        'item_count' => array_sum(array_column($lines, 'qty')),
    ];
}
