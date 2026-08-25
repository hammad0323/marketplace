<?php
/**
 * Cart + checkout + order logic for "Store" (product) providers — the
 * e-commerce counterpart to includes/booking-functions.php's date-based
 * booking flow.
 */
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

function generate_order_ref()
{
    return 'OD-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

/**
 * Same tax/fee/commission logic as calculate_booking_price(), but the
 * "units" are always a plain quantity — no date/hour arithmetic.
 */
function calculate_order_item_price($conn, array $service, $quantity)
{
    $quantity = max(1, (int) $quantity);
    $base = round((float) $service['price'] * $quantity, 2);

    $taxPercent = (float) (db_select_one($conn, 'SELECT percent FROM taxes WHERE applies_to IN ("all","booking") AND is_active = 1 ORDER BY id LIMIT 1')['percent'] ?? 0);
    $feePercent = (float) get_setting($conn, 'service_fee_percent', 0);

    $commissionRow = db_select_one($conn, 'SELECT rate_percent FROM commissions WHERE service_id = ? AND is_active = 1', [(int) $service['id']])
        ?: db_select_one($conn, 'SELECT rate_percent FROM commissions WHERE category_id = ? AND is_active = 1', [(int) $service['category_id']]);
    $commissionPercent = $commissionRow ? (float) $commissionRow['rate_percent'] : (float) get_setting($conn, 'default_commission_percent', 10);

    $tax = round($base * $taxPercent / 100, 2);
    $fee = round($base * $feePercent / 100, 2);
    $commission = round($base * $commissionPercent / 100, 2);
    $total = round($base + $tax + $fee, 2);

    return ['quantity' => $quantity, 'base' => $base, 'tax' => $tax, 'fee' => $fee, 'commission' => $commission, 'total' => $total];
}

function get_cart_count($conn, $userId)
{
    return (int) db_select_one($conn, 'SELECT COALESCE(SUM(quantity),0) AS c FROM cart_items WHERE user_id = ?', [(int) $userId])['c'];
}

/**
 * Cart rows joined with the live service/provider data needed to render
 * the cart and re-price it at checkout (never trust the price a cart row
 * was added at — always read the service's current price).
 */
function get_cart_items($conn, $userId)
{
    return db_select(
        $conn,
        'SELECT ci.id AS cart_item_id, ci.quantity, s.id AS service_id, s.category_id, s.title, s.slug, s.price, s.stock_quantity, s.status,
            p.id AS provider_id, p.business_name,
            (SELECT image_path FROM service_images WHERE service_id = s.id ORDER BY is_cover DESC, sort_order LIMIT 1) AS image
         FROM cart_items ci
         JOIN services s ON s.id = ci.service_id
         JOIN providers p ON p.id = s.provider_id
         WHERE ci.user_id = ?
         ORDER BY ci.created_at DESC',
        [(int) $userId]
    );
}

/**
 * Rolls an order's overall status up from its line items' individual
 * statuses — each provider only manages their own items, so the order's
 * status is a summary, not something set directly.
 */
function recalculate_order_status($conn, $orderId)
{
    $items = db_select($conn, 'SELECT status FROM order_items WHERE order_id = ?', [(int) $orderId]);
    if (!$items) {
        return;
    }
    $statuses = array_unique(array_column($items, 'status'));

    if (count($statuses) === 1) {
        $rolled = $statuses[0];
    } elseif (in_array('shipped', $statuses, true) || in_array('processing', $statuses, true)) {
        $rolled = in_array('shipped', $statuses, true) ? 'shipped' : 'processing';
    } elseif (in_array('delivered', $statuses, true)) {
        $rolled = 'processing';
    } else {
        $rolled = 'pending';
    }

    db_execute($conn, 'UPDATE orders SET status = ? WHERE id = ?', [$rolled, (int) $orderId]);
}

/**
 * Status transitions a provider may apply to one of their own order items.
 */
function order_item_allowed_transitions($status)
{
    $map = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['delivered'],
    ];
    return $map[$status] ?? [];
}
