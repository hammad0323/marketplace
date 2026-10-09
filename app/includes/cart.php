<?php
/**
 * Shopping cart. Stored in MySQL: guests are identified by a random token
 * (session + 30-day HttpOnly cookie), logged-in customers by customer_id.
 * Prices are never stored in the cart — they are recalculated on every read.
 */

const CART_COOKIE = 'beglet_cart';
const CART_MAX_QTY_PER_LINE = 20;

function cart_guest_token(bool $create): ?string
{
    $token = $_SESSION['cart_token'] ?? ($_COOKIE[CART_COOKIE] ?? null);
    if ($token !== null && !preg_match('/^[a-f0-9]{64}$/', $token)) {
        $token = null;
    }
    if ($token === null && $create) {
        $token = random_token(32);
    }
    if ($token !== null) {
        $_SESSION['cart_token'] = $token;
        if (($_COOKIE[CART_COOKIE] ?? '') !== $token && !headers_sent()) {
            setcookie(CART_COOKIE, $token, [
                'expires' => time() + 86400 * 30, 'path' => (base_path() ?: '') . '/',
                'secure' => is_https(), 'httponly' => true, 'samesite' => 'Lax',
            ]);
        }
    }
    return $token;
}

/** Current cart id (optionally creating the cart). */
function cart_id(bool $create = false): ?int
{
    static $cached = null;
    if ($cached) {
        return $cached;
    }
    $cid = customer_id();
    if ($cid) {
        $id = db_val('SELECT id FROM carts WHERE customer_id = ?', [$cid]);
        if (!$id && $create) {
            $id = db_insert('carts', ['customer_id' => $cid]);
        }
    } else {
        $token = cart_guest_token($create);
        if (!$token) {
            return null;
        }
        $id = db_val('SELECT id FROM carts WHERE session_token = ? AND customer_id IS NULL', [hash('sha256', $token)]);
        if (!$id && $create) {
            $id = db_insert('carts', ['session_token' => hash('sha256', $token)]);
        }
    }
    return $cached = $id ? (int) $id : null;
}

/** Move a guest cart into the customer's cart after login. */
function cart_merge_guest(?string $guestToken, int $customerId): void
{
    if (!$guestToken || !preg_match('/^[a-f0-9]{64}$/', $guestToken)) {
        return;
    }
    $guestCart = db_one('SELECT * FROM carts WHERE session_token = ? AND customer_id IS NULL', [hash('sha256', $guestToken)]);
    if (!$guestCart) {
        return;
    }
    db_tx(function () use ($guestCart, $customerId) {
        $custCartId = db_val('SELECT id FROM carts WHERE customer_id = ? FOR UPDATE', [$customerId]);
        if (!$custCartId) {
            db_exec('UPDATE carts SET customer_id = ?, session_token = NULL WHERE id = ?', [$customerId, $guestCart['id']]);
            return;
        }
        foreach (db_all('SELECT * FROM cart_items WHERE cart_id = ?', [$guestCart['id']]) as $item) {
            db_exec(
                'INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, gift_wrap) VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE quantity = LEAST(quantity + VALUES(quantity), ?)',
                [(int) $custCartId, (int) $item['product_id'], $item['variant_id'] !== null ? (int) $item['variant_id'] : null, (int) $item['quantity'], (int) $item['gift_wrap'], CART_MAX_QTY_PER_LINE]
            );
        }
        if ($guestCart['coupon_code']) {
            db_exec('UPDATE carts SET coupon_code = COALESCE(coupon_code, ?) WHERE id = ?', [$guestCart['coupon_code'], $custCartId]);
        }
        db_exec('DELETE FROM carts WHERE id = ?', [$guestCart['id']]);
    });
    if (!headers_sent()) {
        setcookie(CART_COOKIE, '', ['expires' => time() - 3600, 'path' => (base_path() ?: '') . '/']);
    }
}

/**
 * Cart lines with live product data, pricing and stock validation.
 * Each line: id, product, variant, quantity, unit_price, regular_price, line_total,
 *            gift_wrap, gift_wrap_price, stock, error (string|null)
 */
function cart_lines(?int $cartId = null): array
{
    $cartId = $cartId ?? cart_id();
    if (!$cartId) {
        return [];
    }
    $items = db_all('SELECT * FROM cart_items WHERE cart_id = ? ORDER BY id', [$cartId]);
    if (!$items) {
        return [];
    }
    $productIds = array_unique(array_map(fn($i) => (int) $i['product_id'], $items));
    $products = [];
    foreach (db_all(product_select_sql() . ' WHERE p.id IN (' . db_in($productIds) . ')', $productIds) as $p) {
        $products[(int) $p['id']] = $p;
    }
    $products = array_column(hydrate_products(array_values($products)), null, 'id');

    $lines = [];
    foreach ($items as $item) {
        $p = $products[(int) $item['product_id']] ?? null;
        $line = [
            'id' => (int) $item['id'],
            'product_id' => (int) $item['product_id'],
            'variant_id' => $item['variant_id'] !== null ? (int) $item['variant_id'] : null,
            'quantity' => (int) $item['quantity'],
            'gift_wrap' => (int) $item['gift_wrap'],
            'product' => $p,
            'variant' => null,
            'error' => null,
        ];
        if (!$p || $p['status'] !== 'published') {
            $line['error'] = 'This product is no longer available.';
            $line += ['unit_price' => 0, 'regular_price' => 0, 'line_total' => 0, 'gift_wrap_price' => 0, 'stock' => 0, 'image' => null, 'variant_label' => null];
            $lines[] = $line;
            continue;
        }
        $variant = null;
        if ($line['variant_id']) {
            $variant = db_one(
                'SELECT v.*, COALESCE(i.quantity, 0) stock_qty, img.file_path image_path FROM product_variants v
                 LEFT JOIN product_inventory i ON i.variant_id = v.id LEFT JOIN product_images img ON img.id = v.image_id
                 WHERE v.id = ? AND v.product_id = ?',
                [$line['variant_id'], $line['product_id']]
            );
            if (!$variant || !(int) $variant['is_active']) {
                $line['error'] = 'The selected option is no longer available.';
            }
        } elseif ((int) $p['variant_count'] > 0) {
            $line['error'] = 'Please choose an option for this product.';
        }
        $pricing = unit_pricing($p, $variant);
        $stock = $variant ? (int) $variant['stock_qty'] : unit_stock($line['product_id'], null);
        $availability = $variant ? product_availability($p, $stock) : product_availability($p, (int) $p['track_stock'] ? $stock : null);
        if (!$line['error']) {
            if (!is_purchasable($availability)) {
                $line['error'] = 'Out of stock.';
            } elseif ((int) $p['track_stock'] && $line['quantity'] > $stock) {
                $line['error'] = "Only $stock available.";
            }
        }
        $giftPrice = ($line['gift_wrap'] && (int) $p['gift_wrap_available']) ? (float) $p['gift_wrap_price'] : 0.0;
        $line['variant'] = $variant;
        $line['variant_label'] = $variant['label'] ?? null;
        $line['unit_price'] = $pricing['price'];
        $line['regular_price'] = $pricing['regular'];
        $line['gift_wrap_price'] = $giftPrice;
        $line['line_total'] = round($pricing['price'] * $line['quantity'], 2);
        $line['stock'] = (int) $p['track_stock'] ? $stock : null;
        $line['image'] = ($variant['image_path'] ?? null) ?: $p['image'];
        $lines[] = $line;
    }
    return $lines;
}

function cart_count(): int
{
    $id = cart_id();
    return $id ? (int) db_val('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ?', [$id]) : 0;
}

/**
 * Add a product to the cart with full server-side validation.
 * @return array{ok:bool, message:string}
 */
function cart_add(int $productId, ?int $variantId, int $qty, bool $giftWrap = false): array
{
    $qty = max(1, min(CART_MAX_QTY_PER_LINE, $qty));
    $p = product_by_id($productId);
    if (!$p) {
        return ['ok' => false, 'message' => 'This product is not available.'];
    }
    $variants = product_variants($productId);
    $variant = null;
    if ($variants) {
        foreach ($variants as $v) {
            if ((int) $v['id'] === (int) $variantId) {
                $variant = $v;
            }
        }
        if (!$variant) {
            return ['ok' => false, 'message' => 'Please choose a colour or option first.'];
        }
    } else {
        $variantId = null;
    }
    $stock = $variant ? (int) $variant['stock_qty'] : unit_stock($productId, null);
    $availability = $variant ? product_availability($p, $stock) : $p['availability'];
    if (!is_purchasable($availability)) {
        return ['ok' => false, 'message' => 'Sorry, this item is out of stock.'];
    }
    $cartId = cart_id(true);
    $existing = (int) db_val('SELECT quantity FROM cart_items WHERE cart_id = ? AND product_id = ? AND variant_key = ?', [$cartId, $productId, (int) $variantId]);
    $newQty = min(CART_MAX_QTY_PER_LINE, $existing + $qty);
    if ((int) $p['track_stock'] && $newQty > $stock) {
        return ['ok' => false, 'message' => $stock > $existing
            ? 'Only ' . ($stock - $existing) . ' more can be added — limited stock.'
            : 'You already have all available stock of this item in your bag.'];
    }
    $gift = ($giftWrap && (int) $p['gift_wrap_available']) ? 1 : 0;
    db_exec(
        'INSERT INTO cart_items (cart_id, product_id, variant_id, quantity, gift_wrap) VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = ?, gift_wrap = GREATEST(gift_wrap, VALUES(gift_wrap))',
        [$cartId, $productId, $variantId, $newQty, $gift, $newQty]
    );
    db_exec('UPDATE carts SET updated_at = NOW() WHERE id = ?', [$cartId]);
    return ['ok' => true, 'message' => $p['name'] . ' was added to your bag.'];
}

function cart_owned_item(int $itemId): ?array
{
    $cartId = cart_id();
    return $cartId ? db_one('SELECT * FROM cart_items WHERE id = ? AND cart_id = ?', [$itemId, $cartId]) : null;
}

function cart_update_qty(int $itemId, int $qty): array
{
    $item = cart_owned_item($itemId);
    if (!$item) {
        return ['ok' => false, 'message' => 'Item not found in your bag.'];
    }
    if ($qty <= 0) {
        return cart_remove($itemId);
    }
    $qty = min(CART_MAX_QTY_PER_LINE, $qty);
    $p = product_by_id((int) $item['product_id']);
    if ($p && (int) $p['track_stock']) {
        $stock = unit_stock((int) $item['product_id'], $item['variant_id'] !== null ? (int) $item['variant_id'] : null);
        if ($qty > $stock) {
            db_exec('UPDATE cart_items SET quantity = ? WHERE id = ?', [max(1, $stock), $itemId]);
            return ['ok' => false, 'message' => $stock > 0 ? "Only $stock available — quantity adjusted." : 'This item is now out of stock.'];
        }
    }
    db_exec('UPDATE cart_items SET quantity = ? WHERE id = ?', [$qty, $itemId]);
    return ['ok' => true, 'message' => 'Bag updated.'];
}

function cart_set_gift_wrap(int $itemId, bool $on): array
{
    $item = cart_owned_item($itemId);
    if (!$item) {
        return ['ok' => false, 'message' => 'Item not found.'];
    }
    db_exec('UPDATE cart_items SET gift_wrap = ? WHERE id = ?', [$on ? 1 : 0, $itemId]);
    return ['ok' => true, 'message' => $on ? 'Gift packaging added.' : 'Gift packaging removed.'];
}

function cart_remove(int $itemId): array
{
    $cartId = cart_id();
    if (!$cartId || !db_exec('DELETE FROM cart_items WHERE id = ? AND cart_id = ?', [$itemId, $cartId])) {
        return ['ok' => false, 'message' => 'Item not found in your bag.'];
    }
    return ['ok' => true, 'message' => 'Item removed.'];
}

function cart_coupon_code(): ?string
{
    $id = cart_id();
    return $id ? db_val('SELECT coupon_code FROM carts WHERE id = ?', [$id]) : null;
}

function cart_set_coupon(?string $code): void
{
    $id = cart_id(true);
    db_exec('UPDATE carts SET coupon_code = ? WHERE id = ?', [$code, $id]);
}

function cart_clear(int $cartId): void
{
    db_exec('DELETE FROM cart_items WHERE cart_id = ?', [$cartId]);
    db_exec('UPDATE carts SET coupon_code = NULL WHERE id = ?', [$cartId]);
}
