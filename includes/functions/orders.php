<?php
/**
 * orders / order_items / order_status_history — a single checkout can
 * contain products from several vendors (one payment, split
 * fulfillment): orders holds the checkout + payment summary,
 * order_items carries its own vendor_id + status so each vendor only
 * ever touches their own items, and order_status_history is the audit
 * trail of order-level status changes.
 */

if (!defined('MP_BOOTSTRAP')) {
    exit('Direct access not permitted.');
}

function mp_generate_order_number(int $orderId): string
{
    return 'ORD-' . date('Ymd') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);
}

/**
 * Places an order from a customer's cart in one all-or-nothing MySQLi
 * transaction: validates stock, snapshots the shipping address and
 * every line item's price/title, decrements stock, records the first
 * status-history row and a payment attempt, then clears the cart.
 * Returns the new order row, or null (with $error set) if anything
 * failed — nothing is left half-written either way.
 */
function mp_create_order(int $customerId, array $shippingAddress, array $cartItems, string $paymentMethod, ?string &$error = null): ?array
{
    if (!$cartItems) {
        $error = 'Your cart is empty.';
        return null;
    }

    foreach ($cartItems as $item) {
        if ($item['product_status'] !== 'published') {
            $error = "\"{$item['title']}\" is no longer available.";
            return null;
        }
        if ((int) $item['quantity'] > (int) $item['stock_quantity']) {
            $error = "Only {$item['stock_quantity']} of \"{$item['title']}\" left in stock.";
            return null;
        }
    }

    $subtotal = mp_cart_subtotal($cartItems);
    $shippingAmount = 0.0;
    $total = $subtotal + $shippingAmount;

    mp_db_begin_transaction();

    try {
        $orderId = mp_db_insert('orders', [
            'order_number'         => 'PENDING',
            'customer_id'          => $customerId,
            'address_id'           => $shippingAddress['id'] ?? null,
            'shipping_name'        => $shippingAddress['full_name'],
            'shipping_phone'       => $shippingAddress['phone'],
            'shipping_line1'       => $shippingAddress['line1'],
            'shipping_line2'       => $shippingAddress['line2'] ?? null,
            'shipping_city'        => $shippingAddress['city'],
            'shipping_state'       => $shippingAddress['state'] ?? null,
            'shipping_postal_code' => $shippingAddress['postal_code'] ?? null,
            'shipping_country'     => $shippingAddress['country'],
            'subtotal_amount'      => $subtotal,
            'shipping_amount'      => $shippingAmount,
            'total_amount'         => $total,
            'payment_method'       => $paymentMethod,
        ]);

        mp_db_execute('UPDATE orders SET order_number = ? WHERE id = ?', [mp_generate_order_number($orderId), $orderId]);

        foreach ($cartItems as $item) {
            $lineTotal = (float) $item['price'] * (int) $item['quantity'];
            $vendorId = (int) mp_db_fetch_value('SELECT vendor_id FROM products WHERE id = ?', [$item['product_id']]);

            mp_db_insert('order_items', [
                'order_id'      => $orderId,
                'vendor_id'     => $vendorId,
                'product_id'    => $item['product_id'],
                'product_title' => $item['title'],
                'unit_price'    => $item['price'],
                'quantity'      => $item['quantity'],
                'line_total'    => $lineTotal,
            ]);

            mp_db_execute(
                'UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND stock_quantity >= ?',
                [$item['quantity'], $item['product_id'], $item['quantity']]
            );
        }

        mp_db_insert('order_status_history', [
            'order_id'        => $orderId,
            'old_status'      => null,
            'new_status'      => 'pending',
            'note'            => 'Order placed',
            'changed_by_type' => 'customer',
            'changed_by_id'   => $customerId,
        ]);

        mp_create_transaction([
            'order_id' => $orderId,
            'gateway'  => $paymentMethod,
            'amount'   => $total,
            'status'   => 'pending',
        ]);

        mp_cart_clear($customerId);
        mp_db_commit();
    } catch (Throwable $e) {
        mp_db_rollback();
        $error = 'Could not place your order. Please try again.';
        return null;
    }

    return mp_find_order($orderId);
}

function mp_find_order(int $orderId): ?array
{
    return mp_db_fetch_one('SELECT * FROM orders WHERE id = ? LIMIT 1', [$orderId]);
}

function mp_find_order_by_number(string $orderNumber): ?array
{
    return mp_db_fetch_one('SELECT * FROM orders WHERE order_number = ? LIMIT 1', [$orderNumber]);
}

function mp_orders_for_customer(int $customerId): array
{
    return mp_db_fetch_all('SELECT * FROM orders WHERE customer_id = ? ORDER BY placed_at DESC', [$customerId]);
}

function mp_all_orders(): array
{
    return mp_db_fetch_all(
        'SELECT orders.*, customers.name AS customer_name, customers.email AS customer_email
         FROM orders
         JOIN customers ON customers.id = orders.customer_id
         ORDER BY orders.placed_at DESC'
    );
}

function mp_order_items(int $orderId): array
{
    return mp_db_fetch_all(
        'SELECT order_items.*, vendors.store_name
         FROM order_items
         JOIN vendors ON vendors.id = order_items.vendor_id
         WHERE order_items.order_id = ?
         ORDER BY order_items.id ASC',
        [$orderId]
    );
}

/** A vendor's own line items across every order, newest first — their "incoming orders" queue. */
function mp_order_items_for_vendor(int $vendorId): array
{
    return mp_db_fetch_all(
        'SELECT order_items.*, orders.order_number, orders.placed_at, orders.shipping_city, orders.shipping_country
         FROM order_items
         JOIN orders ON orders.id = order_items.order_id
         WHERE order_items.vendor_id = ?
         ORDER BY orders.placed_at DESC',
        [$vendorId]
    );
}

function mp_order_status_history(int $orderId): array
{
    return mp_db_fetch_all(
        'SELECT * FROM order_status_history WHERE order_id = ? ORDER BY created_at ASC',
        [$orderId]
    );
}

/**
 * Updates one order item's fulfillment status — scoped to $vendorId so
 * a vendor can only ever change their own line items — then
 * recomputes and logs the order's own aggregate status.
 */
function mp_update_order_item_status(int $orderItemId, string $status, int $vendorId): bool
{
    $item = mp_db_fetch_one(
        'SELECT * FROM order_items WHERE id = ? AND vendor_id = ? LIMIT 1',
        [$orderItemId, $vendorId]
    );
    if (!$item) {
        return false;
    }

    mp_db_update('order_items', ['status' => $status], 'id = ?', [$orderItemId]);
    mp_recompute_order_status((int) $item['order_id'], 'vendor', $vendorId);

    return true;
}

/**
 * Rolls every order_item's status up into the order's own status:
 * all delivered/cancelled (with at least one delivered) -> completed,
 * all cancelled -> cancelled, any item past pending -> processing,
 * otherwise pending. Logs a history row only when the status actually changes.
 */
function mp_recompute_order_status(int $orderId, string $changedByType = 'system', ?int $changedById = null): void
{
    $itemStatuses = mp_db_fetch_column('SELECT status FROM order_items WHERE order_id = ?', [$orderId]);
    if (!$itemStatuses) {
        return;
    }

    if (count(array_unique($itemStatuses)) === 1 && $itemStatuses[0] === 'cancelled') {
        $newStatus = 'cancelled';
    } elseif (!array_diff($itemStatuses, ['delivered', 'cancelled']) && in_array('delivered', $itemStatuses, true)) {
        $newStatus = 'completed';
    } elseif (array_diff($itemStatuses, ['pending'])) {
        $newStatus = 'processing';
    } else {
        $newStatus = 'pending';
    }

    $order = mp_find_order($orderId);
    if (!$order || $order['status'] === $newStatus) {
        return;
    }

    mp_db_update('orders', ['status' => $newStatus], 'id = ?', [$orderId]);
    mp_db_insert('order_status_history', [
        'order_id'        => $orderId,
        'old_status'      => $order['status'],
        'new_status'      => $newStatus,
        'note'            => null,
        'changed_by_type' => $changedByType,
        'changed_by_id'   => $changedById,
    ]);
}
