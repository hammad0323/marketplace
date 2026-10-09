<?php
/** Order lifecycle: statuses, payment status, cancellations with restock, notifications. */
if (!defined('EBAYA')) { http_response_code(403); exit; }

function order_statuses(): array
{
    return [
        'pending' => 'Pending', 'confirmed' => 'Confirmed', 'processing' => 'Processing', 'in_production' => 'In production',
        'shipped' => 'Shipped', 'delivered' => 'Delivered', 'cancelled' => 'Cancelled', 'returned' => 'Returned',
    ];
}

function payment_statuses(): array
{
    return [
        'unpaid' => 'Unpaid', 'pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed',
        'refunded' => 'Refunded', 'partially_refunded' => 'Partially refunded', 'cancelled' => 'Cancelled',
    ];
}

/** Allowed status transitions (prevents e.g. delivered → pending by mistake). */
function order_allowed_transitions(string $from): array
{
    return [
        'pending' => ['confirmed', 'processing', 'in_production', 'cancelled'],
        'confirmed' => ['processing', 'in_production', 'shipped', 'cancelled'],
        'processing' => ['in_production', 'shipped', 'cancelled'],
        'in_production' => ['processing', 'shipped', 'cancelled'],
        'shipped' => ['delivered', 'returned'],
        'delivered' => ['returned'],
        'cancelled' => [],
        'returned' => [],
    ][$from] ?? [];
}

function order_items(int $orderId): array
{
    return db_all('SELECT * FROM order_items WHERE order_id = ? ORDER BY id', [$orderId]);
}

function order_address(int $orderId): ?array
{
    return db_one("SELECT * FROM order_addresses WHERE order_id = ? AND type = 'shipping'", [$orderId]);
}

function order_public_history(int $orderId): array
{
    $h = db_all('SELECT status, payment_status, note, created_at FROM order_status_history WHERE order_id = ? AND is_customer_visible = 1 ORDER BY id', [$orderId]);
    $n = db_all('SELECT NULL AS status, NULL AS payment_status, note, created_at FROM order_notes WHERE order_id = ? AND is_customer_visible = 1', [$orderId]);
    $all = array_merge($h, $n);
    usort($all, fn($a, $b) => strcmp($a['created_at'], $b['created_at']));
    return $all;
}

/** Guest access: order number + lookup key (from confirmation link) or + email/phone (track form). */
function order_find_public(string $number, string $keyOrContact): ?array
{
    $o = db_one('SELECT * FROM orders WHERE order_number = ?', [strtoupper(trim($number))]);
    if (!$o) return null;
    $k = trim($keyOrContact);
    if (strlen($k) === 32 && hash_equals($o['lookup_key'], $k)) return $o;
    if (strcasecmp($o['email'], $k) === 0) return $o;
    $digits = fn($s) => substr(preg_replace('/\D/', '', $s), -10);
    if (strlen($digits($k)) >= 7 && $digits($k) === $digits($o['phone'])) return $o;
    return null;
}

function order_history_add(int $orderId, ?string $status, ?string $payStatus, string $note, bool $visible = false): void
{
    db_insert('INSERT INTO order_status_history (order_id, status, payment_status, note, is_customer_visible, admin_id) VALUES (?, ?, ?, ?, ?, ?)',
        [$orderId, $status, $payStatus, $note, $visible ? 1 : 0, admin_id()]);
}

/** Put deducted stock back (cancel / return). Idempotent per order item. */
function order_restock(int $orderId, string $reason): void
{
    foreach (db_all('SELECT * FROM order_items WHERE order_id = ? AND stock_deducted = 1 AND variant_id IS NOT NULL FOR UPDATE', [$orderId]) as $it) {
        inventory_change((int)$it['variant_id'], (int)$it['quantity'], $reason, 'order', $orderId, 'Restocked from order');
        db_exec('UPDATE order_items SET stock_deducted = 0 WHERE id = ?', [(int)$it['id']]);
    }
}

/**
 * Change fulfilment status. Throws InvalidArgumentException for disallowed transitions.
 */
function order_set_status(int $orderId, string $new, string $note = '', bool $notify = true, bool $restockOnReturn = true): void
{
    db_tx(function () use ($orderId, $new, $note, $restockOnReturn) {
        $o = db_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
        if (!$o) throw new InvalidArgumentException('Order not found.');
        if ($o['status'] === $new) return;
        if (!in_array($new, order_allowed_transitions($o['status']), true)) {
            throw new InvalidArgumentException('Cannot change status from ' . order_statuses()[$o['status']] . ' to ' . (order_statuses()[$new] ?? $new) . '.');
        }
        $pay = null;
        if ($new === 'cancelled') {
            order_restock($orderId, 'order_cancel');
            if (in_array($o['payment_status'], ['unpaid', 'pending', 'failed'], true)) {
                $pay = 'cancelled';
                db_exec("UPDATE payments SET status = 'cancelled' WHERE order_id = ? AND status = 'pending'", [$orderId]);
            }
            db_exec('UPDATE orders SET cancelled_at = NOW() WHERE id = ?', [$orderId]);
            if ($o['coupon_code']) {
                db_exec('UPDATE coupons SET used_count = GREATEST(used_count - 1, 0) WHERE code = ?', [$o['coupon_code']]);
            }
        }
        if ($new === 'returned' && $restockOnReturn) {
            order_restock($orderId, 'return');
        }
        db_exec('UPDATE orders SET status = ?' . ($pay ? ', payment_status = ?' : '') . ' WHERE id = ?', $pay ? [$new, $pay, $orderId] : [$new, $orderId]);
        $default = ['confirmed' => 'Your order has been confirmed.', 'processing' => 'Your order is being prepared.', 'in_production' => 'Your piece is being handcrafted.',
                    'shipped' => 'Your order is on its way.', 'delivered' => 'Your order has been delivered.', 'cancelled' => 'Your order has been cancelled.', 'returned' => 'Your return has been processed.'];
        order_history_add($orderId, $new, $pay, $note !== '' ? $note : ($default[$new] ?? 'Status updated.'), true);
    });
    if ($notify) order_notify_status($orderId);
}

/**
 * Mark a payment successful. Safe to call repeatedly — only the first call
 * changes anything (guards against duplicate callbacks).
 */
function order_mark_paid(int $paymentId, ?string $providerTxn, string $source, ?float $amount = null): bool
{
    $changed = db_tx(function () use ($paymentId, $providerTxn, $source, $amount) {
        $p = db_one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
        if (!$p || $p['status'] === 'success') return false;
        if ($amount !== null && abs($amount - (float)$p['amount']) > 0.01) {
            db_exec("UPDATE payments SET failure_reason = ? WHERE id = ?", ['Amount mismatch: received ' . $amount, $paymentId]);
            log_message('payments', "Amount mismatch on payment $paymentId: expected {$p['amount']} got $amount");
            return false;
        }
        db_exec("UPDATE payments SET status = 'success', provider_txn_id = COALESCE(?, provider_txn_id), verified_at = NOW(), failure_reason = NULL WHERE id = ?", [$providerTxn, $paymentId]);
        $o = db_one('SELECT * FROM orders WHERE id = ? FOR UPDATE', [(int)$p['order_id']]);
        $newStatus = $o['status'] === 'pending' ? 'confirmed' : $o['status'];
        db_exec("UPDATE orders SET payment_status = 'paid', paid_at = NOW(), status = ? WHERE id = ?", [$newStatus, (int)$o['id']]);
        order_history_add((int)$o['id'], $newStatus !== $o['status'] ? $newStatus : null, 'paid', 'Payment received (' . $source . ').', true);
        return true;
    });
    if ($changed) {
        $orderId = (int)db_val('SELECT order_id FROM payments WHERE id = ?', [$paymentId]);
        order_notify_status($orderId);
    }
    return $changed;
}

function order_mark_payment_failed(int $paymentId, string $reason, string $status = 'failed'): void
{
    db_tx(function () use ($paymentId, $reason, $status) {
        $p = db_one('SELECT * FROM payments WHERE id = ? FOR UPDATE', [$paymentId]);
        if (!$p || $p['status'] !== 'pending') return;
        db_exec('UPDATE payments SET status = ?, failure_reason = ? WHERE id = ?', [$status, mb_substr($reason, 0, 255), $paymentId]);
        db_exec("UPDATE orders SET payment_status = 'failed' WHERE id = ? AND payment_status IN ('pending','unpaid')", [(int)$p['order_id']]);
        order_history_add((int)$p['order_id'], null, 'failed', 'Payment ' . $status . ': ' . $reason, true);
    });
}

// ---------------------------------------------------------------------
// Notifications
// ---------------------------------------------------------------------
function order_email_body(array $o, string $intro): string
{
    $items = order_items((int)$o['id']);
    $addr = order_address((int)$o['id']);
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr><td style="padding:8px 0;border-bottom:1px solid #eee">' . e($it['product_name']) . '<br><small style="color:#777">' . e($it['variant_label']) .
            ($it['custom_length'] ? ' · Length ' . (int)$it['custom_length'] . '"' : '') . ($it['custom_sleeve'] ? ' · ' . e($it['custom_sleeve']) : '') .
            '</small></td><td style="text-align:center">×' . (int)$it['quantity'] . '</td><td style="text-align:right">' . money($it['line_total']) . '</td></tr>';
    }
    $link = abs_url('order/' . $o['order_number'] . '?key=' . $o['lookup_key']);
    $tot = fn($label, $v) => '<tr><td colspan="2" style="text-align:right;padding:4px 8px">' . $label . '</td><td style="text-align:right">' . $v . '</td></tr>';
    return '<p>' . e($intro) . '</p>'
        . '<p><strong>Order ' . e($o['order_number']) . '</strong> · Status: ' . e(order_statuses()[$o['status']] ?? $o['status']) . ' · Payment: ' . e(payment_statuses()[$o['payment_status']] ?? $o['payment_status']) . '</p>'
        . '<table width="100%" cellspacing="0" style="border-collapse:collapse;font-size:14px">' . $rows
        . $tot('Subtotal', money($o['subtotal']))
        . ((float)$o['customization_total'] > 0 ? $tot('Customisation', money($o['customization_total'])) : '')
        . ((float)$o['discount_total'] > 0 ? $tot('Discount', '−' . money($o['discount_total'])) : '')
        . $tot('Delivery', (float)$o['shipping_total'] > 0 ? money($o['shipping_total']) : 'Free')
        . ((float)$o['cod_fee'] > 0 ? $tot('COD fee', money($o['cod_fee'])) : '')
        . $tot('<strong>Total</strong>', '<strong>' . money($o['grand_total']) . '</strong>') . '</table>'
        . ($addr ? '<p style="margin-top:16px"><strong>Delivering to</strong><br>' . e($addr['full_name']) . '<br>' . e($addr['address_line1']) . ($addr['address_line2'] ? ', ' . e($addr['address_line2']) : '') . '<br>' . e($addr['city']) . ($addr['province'] ? ', ' . e($addr['province']) : '') . '</p>' : '')
        . ($o['estimated_delivery'] ? '<p>Estimated delivery: ' . e($o['estimated_delivery']) . '</p>' : '')
        . ($o['tracking_number'] ? '<p>Courier: ' . e($o['courier_name']) . ' · Tracking: ' . e($o['tracking_number']) . '</p>' : '')
        . '<p><a href="' . e($link) . '" style="color:#354638">View your order</a></p>';
}

function order_notify_placed(int $orderId): void
{
    $o = db_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$o) return;
    send_mail($o['email'], 'Thank you — order ' . $o['order_number'] . ' received', order_email_body($o, 'Dear ' . $o['customer_name'] . ', thank you for your order. We will be in touch as it progresses.'));
    $admin = setting('admin_notify_email', '');
    if ($admin && v_email($admin)) {
        send_mail($admin, 'New order ' . $o['order_number'] . ' — ' . money($o['grand_total']), order_email_body($o, 'A new order has been placed (' . $o['payment_method'] . ').'));
    }
}

function order_notify_status(int $orderId): void
{
    $o = db_one('SELECT * FROM orders WHERE id = ?', [$orderId]);
    if (!$o) return;
    $label = order_statuses()[$o['status']] ?? $o['status'];
    send_mail($o['email'], 'Order ' . $o['order_number'] . ' update: ' . $label, order_email_body($o, 'Dear ' . $o['customer_name'] . ', there is an update on your order.'));
}
