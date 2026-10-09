<?php
/**
 * Shopping cart. Guest carts live in the database keyed by a random token in
 * a long-lived cookie (only its SHA-256 hash is stored). Logged-in customers
 * get a cart tied to their account; the guest cart merges in at login.
 * Prices are never stored in the cart — they are recalculated from the
 * catalogue every time the cart is read.
 */
if (!defined('EBAYA')) { http_response_code(403); exit; }

const CART_COOKIE = 'eb_cart';
const CART_MAX_QTY = 10;

function cart_cookie_set(string $token): void
{
    setcookie(CART_COOKIE, $token, [
        'expires' => time() + 60 * 60 * 24 * 45, 'path' => (BASE_PATH ?: '') . '/',
        'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
    ]);
    $_COOKIE[CART_COOKIE] = $token;
}

function cart_forget_cookie(): void
{
    setcookie(CART_COOKIE, '', ['expires' => time() - 3600, 'path' => (BASE_PATH ?: '') . '/']);
    unset($_COOKIE[CART_COOKIE]);
}

function cart_id(bool $create = true): ?int
{
    static $cache = [];
    $cid = customer_id();
    $ck = $cid ? 'c' . $cid : 'g';
    if (isset($cache[$ck])) return $cache[$ck];

    if ($cid) {
        $id = db_val('SELECT id FROM carts WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$cid]);
        if (!$id && $create) $id = db_insert('INSERT INTO carts (customer_id) VALUES (?)', [$cid]);
        return $cache[$ck] = $id ? (int)$id : null;
    }
    $token = $_COOKIE[CART_COOKIE] ?? '';
    if (preg_match('/^[a-f0-9]{64}$/', $token)) {
        $id = db_val('SELECT id FROM carts WHERE token_hash = ? AND customer_id IS NULL', [hash('sha256', $token)]);
        if ($id) return $cache[$ck] = (int)$id;
    }
    if (!$create) return null;
    $token = random_token(32);
    $id = db_insert('INSERT INTO carts (token_hash) VALUES (?)', [hash('sha256', $token)]);
    cart_cookie_set($token);
    return $cache[$ck] = $id;
}

/**
 * Validate customisation input for a product. Returns normalised values.
 * Throws InvalidArgumentException with a customer-facing message.
 */
function cart_customization(array $product, array $in): array
{
    $out = ['length' => null, 'sleeve' => null, 'notes' => null, 'customised' => false];
    if (!$product['allow_customization']) return $out;

    if ($product['custom_length_enabled'] && ($in['custom_length'] ?? '') !== '') {
        $len = (int)$in['custom_length'];
        $min = (int)($product['custom_length_min'] ?: 48);
        $max = (int)($product['custom_length_max'] ?: 64);
        if ($len < $min || $len > $max) {
            throw new InvalidArgumentException("Custom length must be between $min and $max inches.");
        }
        $out['length'] = $len;
    }
    if ($product['custom_sleeve_enabled'] && ($in['custom_sleeve'] ?? '') !== '') {
        $opts = array_filter(array_map('trim', explode(',', (string)$product['custom_sleeve_options'])));
        if (!in_array($in['custom_sleeve'], $opts, true)) {
            throw new InvalidArgumentException('Please choose a valid sleeve preference.');
        }
        $out['sleeve'] = $in['custom_sleeve'];
    }
    if ($product['custom_notes_enabled'] && trim((string)($in['custom_notes'] ?? '')) !== '') {
        $out['notes'] = mb_substr(trim(strip_tags((string)$in['custom_notes'])), 0, 1000);
    }
    $out['customised'] = $out['length'] !== null || $out['sleeve'] !== null || $out['notes'] !== null;
    return $out;
}

/** Add to cart with full server-side validation. Returns new line quantity. */
function cart_add(int $productId, int $variantId, int $qty, array $customInput = []): int
{
    if ($qty < 1 || $qty > CART_MAX_QTY) throw new InvalidArgumentException('Please choose a quantity between 1 and ' . CART_MAX_QTY . '.');
    $product = db_one("SELECT * FROM products WHERE id = ? AND status = 'published'", [$productId]);
    if (!$product) throw new InvalidArgumentException('This product is no longer available.');

    if ($variantId <= 0) {
        // Only allowed when the product has exactly one variant (no options to choose).
        $only = db_col("SELECT id FROM product_variants WHERE product_id = ? AND status = 'active'", [$productId]);
        if (count($only) !== 1) throw new InvalidArgumentException('Please select your size and colour.');
        $variantId = (int)$only[0];
    }
    $variant = db_one("SELECT v.*, COALESCE(i.quantity,0) AS stock FROM product_variants v LEFT JOIN product_inventory i ON i.variant_id = v.id
                       WHERE v.id = ? AND v.product_id = ? AND v.status = 'active'", [$variantId, $productId]);
    if (!$variant) throw new InvalidArgumentException('Please select an available size and colour.');

    $custom = cart_customization($product, $customInput);
    $hash = $custom['customised'] ? sha1(json_encode([$custom['length'], $custom['sleeve'], $custom['notes']])) : '';

    $cartId = cart_id(true);
    $existing = db_one('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND variant_id = ? AND custom_hash = ?', [$cartId, $variantId, $hash]);
    $newQty = ($existing ? (int)$existing['quantity'] : 0) + $qty;
    if ($newQty > CART_MAX_QTY) throw new InvalidArgumentException('You can add up to ' . CART_MAX_QTY . ' of this item.');

    if ($product['track_inventory'] && $newQty > (int)$variant['stock']) {
        $left = max(0, (int)$variant['stock'] - ($existing ? (int)$existing['quantity'] : 0));
        throw new InvalidArgumentException($left > 0 ? "Only $left more available in this size/colour." : 'This size/colour is sold out.');
    }
    if ($existing) {
        db_exec('UPDATE cart_items SET quantity = ? WHERE id = ?', [$newQty, (int)$existing['id']]);
    } else {
        db_insert('INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, custom_length, custom_sleeve, custom_notes, custom_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$cartId, $productId, $variantId, $qty, $custom['length'], $custom['sleeve'], $custom['notes'], $hash]);
    }
    db_exec('UPDATE carts SET updated_at = NOW() WHERE id = ?', [$cartId]);
    return $newQty;
}

function cart_update_qty(int $itemId, int $qty): void
{
    $cartId = cart_id(false);
    if (!$cartId) return;
    if ($qty <= 0) {
        db_exec('DELETE FROM cart_items WHERE id = ? AND cart_id = ?', [$itemId, $cartId]);
        return;
    }
    $qty = min($qty, CART_MAX_QTY);
    $row = db_one('SELECT ci.id, p.track_inventory, COALESCE(i.quantity,0) stock FROM cart_items ci JOIN products p ON p.id = ci.product_id
                   LEFT JOIN product_inventory i ON i.variant_id = ci.variant_id WHERE ci.id = ? AND ci.cart_id = ?', [$itemId, $cartId]);
    if (!$row) return;
    if ($row['track_inventory'] && $qty > (int)$row['stock']) {
        throw new InvalidArgumentException((int)$row['stock'] > 0 ? 'Only ' . (int)$row['stock'] . ' available.' : 'This item is sold out.');
    }
    db_exec('UPDATE cart_items SET quantity = ? WHERE id = ? AND cart_id = ?', [$qty, $itemId, $cartId]);
}

function cart_remove(int $itemId): void
{
    $cartId = cart_id(false);
    if ($cartId) db_exec('DELETE FROM cart_items WHERE id = ? AND cart_id = ?', [$itemId, $cartId]);
}

function cart_clear(): void
{
    $cartId = cart_id(false);
    if ($cartId) {
        db_exec('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
        db_exec('UPDATE carts SET coupon_code = NULL WHERE id = ?', [$cartId]);
    }
}

function cart_count(): int
{
    $cartId = cart_id(false);
    return $cartId ? (int)db_val('SELECT COALESCE(SUM(quantity),0) FROM cart_items WHERE cart_id = ?', [$cartId]) : 0;
}

function cart_coupon(): ?string
{
    $cartId = cart_id(false);
    return $cartId ? db_val('SELECT coupon_code FROM carts WHERE id = ?', [$cartId]) : null;
}

function cart_set_coupon(?string $code): void
{
    $cartId = cart_id(true);
    db_exec('UPDATE carts SET coupon_code = ? WHERE id = ?', [$code, $cartId]);
}

/**
 * Cart lines with live prices and stock checks.
 * Each line: item_id, product, variant_id, name, slug, image, variant_label, unit_price, regular_price,
 * qty, custom fields, custom_fee, line_total, available, max_qty, issue (string|null)
 */
function cart_lines(?int $cartId = null): array
{
    $cartId = $cartId ?? cart_id(false);
    if (!$cartId) return [];
    $rows = db_all("SELECT ci.*, p.name, p.slug, p.status AS p_status, p.regular_price, p.sale_price, p.track_inventory, p.fulfillment_type,
                    p.production_lead_days, p.customization_fee, p.allow_customization,
                    v.sku, v.price_override, v.sale_price_override, v.status AS v_status, COALESCE(i.quantity,0) AS stock,
                    (SELECT path FROM product_images im WHERE im.product_id = p.id ORDER BY is_main DESC, sort_order, id LIMIT 1) AS image
                    FROM cart_items ci JOIN products p ON p.id = ci.product_id JOIN product_variants v ON v.id = ci.variant_id
                    LEFT JOIN product_inventory i ON i.variant_id = v.id WHERE ci.cart_id = ? ORDER BY ci.id", [$cartId]);
    $lines = [];
    foreach ($rows as $r) {
        $prices = variant_prices($r, $r);
        $customised = $r['custom_length'] !== null || $r['custom_sleeve'] !== null || $r['custom_notes'] !== null;
        $fee = ($customised && $r['allow_customization']) ? (float)$r['customization_fee'] : 0.0;
        $issue = null;
        $available = true;
        if ($r['p_status'] !== 'published' || $r['v_status'] !== 'active') {
            $issue = 'No longer available — please remove it.';
            $available = false;
        } elseif ($r['track_inventory'] && (int)$r['stock'] < (int)$r['quantity']) {
            $issue = (int)$r['stock'] > 0 ? 'Only ' . (int)$r['stock'] . ' left — please reduce the quantity.' : 'Sold out — please remove it.';
            $available = false;
        }
        $lines[] = [
            'item_id' => (int)$r['id'],
            'product_id' => (int)$r['product_id'],
            'variant_id' => (int)$r['variant_id'],
            'name' => $r['name'],
            'slug' => $r['slug'],
            'sku' => $r['sku'],
            'image' => $r['image'],
            'variant_label' => variant_label((int)$r['variant_id']),
            'unit_price' => $prices['price'],
            'regular_price' => $prices['regular'],
            'qty' => (int)$r['quantity'],
            'custom_length' => $r['custom_length'] !== null ? (int)$r['custom_length'] : null,
            'custom_sleeve' => $r['custom_sleeve'],
            'custom_notes' => $r['custom_notes'],
            'customised' => $customised,
            'custom_fee' => $fee,
            'line_total' => money2(($prices['price'] + $fee) * (int)$r['quantity']),
            'fulfillment_type' => $r['fulfillment_type'],
            'lead_days' => (int)$r['production_lead_days'],
            'track_inventory' => (int)$r['track_inventory'],
            'stock' => (int)$r['stock'],
            'max_qty' => $r['track_inventory'] ? min(CART_MAX_QTY, max(1, (int)$r['stock'])) : CART_MAX_QTY,
            'available' => $available,
            'issue' => $issue,
        ];
    }
    return $lines;
}

function cart_merge_on_login(?int $guestCartId, int $customerId): void
{
    if (!$guestCartId) return;
    $customerCart = db_val('SELECT id FROM carts WHERE customer_id = ? ORDER BY id DESC LIMIT 1', [$customerId]);
    if (!$customerCart) {
        db_exec('UPDATE carts SET customer_id = ?, token_hash = NULL WHERE id = ?', [$customerId, $guestCartId]);
    } else {
        foreach (db_all('SELECT * FROM cart_items WHERE cart_id = ?', [$guestCartId]) as $it) {
            db_exec('INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, custom_length, custom_sleeve, custom_notes, custom_hash)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE quantity = LEAST(?, quantity + VALUES(quantity))',
                [(int)$customerCart, $it['product_id'], $it['variant_id'], $it['quantity'], $it['custom_length'], $it['custom_sleeve'], $it['custom_notes'], $it['custom_hash'], CART_MAX_QTY]);
        }
        $coupon = db_val('SELECT coupon_code FROM carts WHERE id = ?', [$guestCartId]);
        if ($coupon) db_exec('UPDATE carts SET coupon_code = ? WHERE id = ?', [$coupon, (int)$customerCart]);
        db_exec('DELETE FROM carts WHERE id = ?', [$guestCartId]);
    }
    cart_forget_cookie();
}
