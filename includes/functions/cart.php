<?php
/**
 * cart_items — one persisted cart per customer (survives across
 * sessions/devices), one row per product with a quantity.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

/** Cart rows joined with the product data the cart page needs to render itself. */
function mp_cart_items_for_customer(int $customerId): array
{
    return mp_db_fetch_all(
        'SELECT cart_items.id AS cart_item_id, cart_items.quantity,
                products.id AS product_id, products.title, products.slug, products.price,
                products.images, products.stock_quantity, products.status AS product_status,
                vendors.store_name
         FROM cart_items
         JOIN products ON products.id = cart_items.product_id
         JOIN vendors ON vendors.id = products.vendor_id
         WHERE cart_items.customer_id = ?
         ORDER BY cart_items.created_at DESC',
        [$customerId]
    );
}

function mp_cart_add_item(int $customerId, int $productId, int $quantity = 1): void
{
    mp_db_execute(
        'INSERT INTO cart_items (customer_id, product_id, quantity) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)',
        [$customerId, $productId, $quantity]
    );
}

function mp_cart_update_quantity(int $customerId, int $productId, int $quantity): void
{
    if ($quantity <= 0) {
        mp_cart_remove_item($customerId, $productId);
        return;
    }

    mp_db_execute(
        'UPDATE cart_items SET quantity = ? WHERE customer_id = ? AND product_id = ?',
        [$quantity, $customerId, $productId]
    );
}

function mp_cart_remove_item(int $customerId, int $productId): void
{
    mp_db_execute('DELETE FROM cart_items WHERE customer_id = ? AND product_id = ?', [$customerId, $productId]);
}

function mp_cart_clear(int $customerId): void
{
    mp_db_execute('DELETE FROM cart_items WHERE customer_id = ?', [$customerId]);
}

/** Total item count (sum of quantities) for the cart badge in the nav. */
function mp_cart_item_count(int $customerId): int
{
    return (int) mp_db_fetch_value('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE customer_id = ?', [$customerId]);
}

/** Pure calculation, no DB — subtotal across a set of cart rows from mp_cart_items_for_customer(). */
function mp_cart_subtotal(array $cartItems): float
{
    $subtotal = 0.0;
    foreach ($cartItems as $item) {
        $subtotal += (float) $item['price'] * (int) $item['quantity'];
    }
    return $subtotal;
}
