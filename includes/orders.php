<?php
/**
 * Orders: creation (web checkout, rashan builder, POS), status workflow and
 * the stock policy that keeps inventory correct.
 *
 * Stock policy (setting `stock_policy`):
 *   placed    - stock is deducted as soon as an order is placed (default)
 *   confirmed - deducted when the order reaches Confirmed (or any later stage)
 *   completed - deducted only when the order is Completed
 * orders.stock_deducted guards against double deduction; cancelling a
 * deducted order restocks it exactly once. Drafts never touch stock.
 */
if (!defined('FS_BOOT')) { http_response_code(403); exit; }

function order_status_flow(): array
{
    return ['pending', 'confirmed', 'processing', 'ready_for_pickup', 'out_for_delivery', 'completed'];
}

/** Does an order in $status need its stock deducted under the current policy? */
function status_requires_stock(string $status, ?string $policy = null): bool
{
    $policy = $policy ?: setting('stock_policy', 'placed');
    if (in_array($status, ['draft', 'cancelled'], true)) return false;
    if ($policy === 'completed') return $status === 'completed';
    if ($policy === 'confirmed') return $status !== 'pending';
    return true; // placed
}

function order_generate_number(string $prefix = 'FS'): string
{
    $prefix = setting('order_prefix', $prefix);
    do {
        $n = strtoupper($prefix) . date('ymd') . '-' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
    } while (db_val('SELECT id FROM orders WHERE order_number = ?', [$n]));
    return $n;
}

/** Deduct stock for every line (inside a transaction, row-locked). */
function order_deduct_stock(int $orderId): void
{
    $o = db_row('SELECT id, order_number, channel, stock_deducted FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
    if (!$o || (int) $o['stock_deducted'] === 1) return;
    $type = $o['channel'] === 'pos' ? 'pos_sale' : 'sale';
    foreach (db_all('SELECT oi.*, p.track_stock FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?', [$orderId]) as $it) {
        if (!$it['product_id']) continue;
        if ((int) $it['track_stock'] === 1) {
            stock_change((int) $it['product_id'], (int) $it['variation_id'], -(int) $it['quantity'], $type,
                ['ref_type' => 'order', 'ref_id' => $orderId, 'reason' => 'Order ' . $o['order_number'], 'unit_cost' => $it['cost_price']]);
        }
        db_exec('UPDATE products SET sales_count = sales_count + ? WHERE id = ?', [(int) $it['quantity'], $it['product_id']]);
    }
    db_exec('UPDATE orders SET stock_deducted = 1 WHERE id = ?', [$orderId]);
}

/** Put stock back (cancellation). */
function order_restock(int $orderId): void
{
    $o = db_row('SELECT id, order_number, stock_deducted FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
    if (!$o || (int) $o['stock_deducted'] !== 1) return;
    foreach (db_all('SELECT oi.*, p.track_stock FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?', [$orderId]) as $it) {
        if (!$it['product_id']) continue;
        if ((int) $it['track_stock'] === 1) {
            stock_change((int) $it['product_id'], (int) $it['variation_id'], (int) $it['quantity'], 'cancel_restock',
                ['ref_type' => 'order', 'ref_id' => $orderId, 'reason' => 'Order ' . $o['order_number'] . ' cancelled'], true);
        }
        db_exec('UPDATE products SET sales_count = GREATEST(0, sales_count - ?) WHERE id = ?', [(int) $it['quantity'], $it['product_id']]);
    }
    db_exec('UPDATE orders SET stock_deducted = 0 WHERE id = ?', [$orderId]);
}

/**
 * Create an order. All prices, discounts, fees and stock are validated here.
 *
 * $d keys: customer_id, name, phone, email, fulfillment (delivery|pickup|walkin),
 *   address_line, city, area, zone_id, instructions, pickup_at, payment_method,
 *   payment_reference, notes, coupon, channel (web|pos), order_type (regular|rashan),
 *   status, manual_discount (POS only), grocery_list_id, admin_id, payment_status
 * $lines: [['product_id'=>, 'variation_id'=>, 'quantity'=>], ...]
 *
 * @return array ['ok'=>true,'order_id'=>..,'order_number'=>..] or ['ok'=>false,'error'=>..]
 */
function order_create(array $d, array $lines): array
{
    $channel = ($d['channel'] ?? 'web') === 'pos' ? 'pos' : 'web';
    $fulfillment = in_array($d['fulfillment'] ?? '', ['delivery', 'pickup', 'walkin'], true) ? $d['fulfillment'] : 'delivery';
    if ($channel === 'web' && $fulfillment === 'walkin') $fulfillment = 'pickup';
    $status = $d['status'] ?? 'pending';
    if (!in_array($status, ['draft', 'pending', 'confirmed', 'processing', 'completed'], true)) $status = 'pending';
    if ($channel === 'web') $status = 'pending';

    $name = trim((string) ($d['name'] ?? ''));
    $phone = normalize_phone((string) ($d['phone'] ?? ''));
    $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
    $errors = [];
    if ($channel === 'web' || $status !== 'completed' || $phone !== '') {
        if (mb_strlen($name) < 2) $errors[] = 'Please enter the customer name.';
        if (!valid_phone($phone)) $errors[] = 'Please enter a valid phone number.';
    }
    if ($channel === 'pos' && $status === 'completed' && $phone === '') {
        $name = $name ?: 'Walk-in Customer';
        $phone = '00000000000';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';

    $zone = null;
    if ($fulfillment === 'delivery') {
        if (mb_strlen(trim((string) ($d['address_line'] ?? ''))) < 5) $errors[] = 'Please enter the complete delivery address.';
        if (!empty($d['zone_id'])) {
            $zone = delivery_zone((int) $d['zone_id']);
            if (!$zone) $errors[] = 'Please select a valid delivery area.';
        } elseif ($channel === 'web' && delivery_zones()) {
            $errors[] = 'Please select your delivery area.';
        }
        if ($zone) {
            $d['city'] = $zone['city'];
            $d['area'] = $zone['area'];
        }
        if (setting('delivery_enabled', '1') !== '1' && $channel === 'web') $errors[] = 'Home delivery is currently unavailable.';
    }
    $pickupAt = null;
    if ($fulfillment === 'pickup') {
        if (setting('pickup_enabled', '1') !== '1' && $channel === 'web') $errors[] = 'Store pickup is currently unavailable.';
        if (!empty($d['pickup_at'])) {
            $ts = strtotime((string) $d['pickup_at']);
            if (!$ts) {
                $errors[] = 'Please choose a valid pickup time.';
            } elseif ($channel === 'web' && $ts < time() + ((int) setting('pickup_lead_hours', 2)) * 3600 - 300) {
                $errors[] = 'Please choose a later pickup time (we need at least ' . (int) setting('pickup_lead_hours', 2) . ' hours to prepare).';
            } else {
                $pickupAt = date('Y-m-d H:i:s', $ts);
            }
        } elseif ($channel === 'web') {
            $errors[] = 'Please choose a pickup date and time.';
        }
    }
    // Payment method
    $pmCode = (string) ($d['payment_method'] ?? '');
    $pm = db_row('SELECT * FROM payment_methods WHERE code = ? AND is_active = 1', [$pmCode]);
    if (!$pm) {
        $errors[] = 'Please choose a payment method.';
    } elseif ($channel === 'web') {
        if ($pm['applies_to'] === 'pos' || ($pm['applies_to'] === 'delivery' && $fulfillment !== 'delivery') || ($pm['applies_to'] === 'pickup' && $fulfillment !== 'pickup')) {
            $errors[] = $pm['name'] . ' is not available for this order type.';
        }
        if ($pm['requires_reference'] && trim((string) ($d['payment_reference'] ?? '')) === '' && setting('require_payment_reference', '0') === '1') {
            $errors[] = 'Please enter your payment transaction ID.';
        }
    }
    if (!$lines) $errors[] = 'No items in the order.';
    if ($errors) return ['ok' => false, 'error' => implode(' ', $errors)];

    try {
        $result = db_tx(function () use ($d, $lines, $channel, $fulfillment, $status, $name, $phone, $email, $zone, $pickupAt, $pm) {
            // Lock inventory rows for every line before validating stock
            $priced = price_lines($lines);
            if (!$priced['lines']) throw new RuntimeException('No valid items in the order.');
            if ($status !== 'draft') {
                foreach ($priced['lines'] as $l) {
                    if ($l['track_stock']) {
                        stock_ensure_row($l['product_id'], $l['variation_id']);
                        $avail = (int) db_val('SELECT quantity FROM inventory WHERE product_id = ? AND variation_id = ? FOR UPDATE', [$l['product_id'], $l['variation_id']]);
                        if ($l['quantity'] > $avail) {
                            throw new RuntimeException('Sorry, only ' . max(0, $avail) . ' of "' . $l['name'] . ($l['variation_name'] ? ' - ' . $l['variation_name'] : '') . '" left in stock.');
                        }
                    }
                    if ($l['quantity'] < $l['min_qty']) throw new RuntimeException('Minimum order quantity for "' . $l['name'] . '" is ' . $l['min_qty'] . '.');
                    if ($l['max_qty'] && $l['quantity'] > $l['max_qty']) throw new RuntimeException('Maximum order quantity for "' . $l['name'] . '" is ' . $l['max_qty'] . '.');
                }
            }
            $subtotal = $priced['subtotal'];
            // Coupon
            $discount = 0.0;
            $coupon = null;
            $freeDelivery = false;
            if (!empty($d['coupon'])) {
                $res = coupon_evaluate((string) $d['coupon'], $subtotal, (int) ($d['customer_id'] ?? 0), $phone);
                if (is_string($res)) throw new RuntimeException($res);
                $coupon = $res['coupon'];
                $discount = $res['discount'];
                $freeDelivery = $res['free_delivery'];
            }
            // POS manual discount (admin only)
            if ($channel === 'pos' && !empty($d['manual_discount'])) {
                $discount += max(0, min((float) $d['manual_discount'], $subtotal - $discount));
            }
            $discount = round(min($discount, $subtotal), 2);
            // Delivery fee
            $fee = 0.0;
            if ($fulfillment === 'delivery') {
                if ($channel === 'pos' && isset($d['delivery_fee']) && $d['delivery_fee'] !== '' && $d['delivery_fee'] !== null) {
                    $fee = max(0, (float) $d['delivery_fee']);
                } else {
                    $fee = delivery_fee_for($zone, $subtotal - $discount);
                }
                if ($freeDelivery) $fee = 0.0;
                if ($zone && $channel === 'web' && $subtotal < (float) $zone['min_order_amount']) {
                    throw new RuntimeException('Minimum order for ' . $zone['area'] . ' is ' . money($zone['min_order_amount']) . '.');
                }
            }
            $minOrder = (float) setting('min_order_amount', 0);
            if ($channel === 'web' && $minOrder > 0 && $subtotal < $minOrder) {
                throw new RuntimeException('Minimum order amount is ' . money($minOrder) . '.');
            }
            $total = round($subtotal - $discount + $fee, 2);

            // Customer record (guest orders still get a customer row for history)
            $customerId = (int) ($d['customer_id'] ?? 0);
            if (!$customerId && $phone !== '00000000000') {
                $customerId = customer_find_or_create($name, $phone, $email ?: null, $channel === 'pos' ? 'pos' : 'guest');
            }
            $orderType = ($d['order_type'] ?? 'regular') === 'rashan' ? 'rashan' : 'regular';
            $number = order_generate_number();
            $paymentStatus = in_array($d['payment_status'] ?? '', ['unpaid', 'paid', 'partial'], true) ? $d['payment_status'] : 'unpaid';

            $orderId = db_insert_row('orders', [
                'order_number' => $number,
                'customer_id' => $customerId ?: null,
                'channel' => $channel,
                'order_type' => $orderType,
                'fulfillment' => $fulfillment,
                'status' => $status,
                'customer_name' => mb_substr($name, 0, 120),
                'customer_phone' => $phone,
                'customer_email' => $email ?: null,
                'address_line' => $fulfillment === 'delivery' ? mb_substr(trim((string) $d['address_line']), 0, 255) : null,
                'city' => $fulfillment === 'delivery' ? mb_substr((string) ($d['city'] ?? ''), 0, 80) : null,
                'area' => $fulfillment === 'delivery' ? mb_substr((string) ($d['area'] ?? ''), 0, 120) : null,
                'delivery_zone_id' => $zone ? (int) $zone['id'] : null,
                'delivery_instructions' => mb_substr(trim((string) ($d['instructions'] ?? '')), 0, 500) ?: null,
                'pickup_at' => $pickupAt,
                'subtotal' => $subtotal,
                'discount_amount' => $discount,
                'coupon_code' => $coupon ? $coupon['code'] : null,
                'delivery_fee' => $fee,
                'total' => $total,
                'payment_method' => $pm['code'],
                'payment_status' => $paymentStatus,
                'payment_reference' => mb_substr(trim((string) ($d['payment_reference'] ?? '')), 0, 120) ?: null,
                'customer_notes' => trim((string) ($d['notes'] ?? '')) ?: null,
                'grocery_list_id' => !empty($d['grocery_list_id']) ? (int) $d['grocery_list_id'] : null,
                'created_by_admin' => !empty($d['admin_id']) ? (int) $d['admin_id'] : null,
                'ip_address' => client_ip(),
                'completed_at' => $status === 'completed' ? date('Y-m-d H:i:s') : null,
            ]);
            foreach ($priced['lines'] as $l) {
                db_insert_row('order_items', [
                    'order_id' => $orderId, 'product_id' => $l['product_id'], 'variation_id' => $l['variation_id'],
                    'product_name' => $l['name'], 'variation_name' => $l['variation_name'], 'sku' => $l['sku'], 'unit' => $l['unit'],
                    'unit_price' => $l['unit_price'], 'cost_price' => $l['cost_price'], 'quantity' => $l['quantity'], 'line_total' => $l['line_total'],
                ]);
            }
            db_insert('INSERT INTO order_status_history (order_id, from_status, to_status, note, admin_id) VALUES (?, NULL, ?, ?, ?)',
                [$orderId, $status, $channel === 'pos' ? 'Created at POS' : 'Order placed online', !empty($d['admin_id']) ? (int) $d['admin_id'] : null]);
            db_insert('INSERT INTO payments (order_id, method, amount, reference, status, received_by) VALUES (?, ?, ?, ?, ?, ?)',
                [$orderId, $pm['code'], $total, trim((string) ($d['payment_reference'] ?? '')) ?: null, $paymentStatus === 'paid' ? 'received' : 'pending', !empty($d['admin_id']) ? (int) $d['admin_id'] : null]);
            if ($coupon) {
                db_insert('INSERT INTO coupon_usage (coupon_id, order_id, customer_id, customer_phone, discount_amount) VALUES (?, ?, ?, ?, ?)',
                    [$coupon['id'], $orderId, $customerId ?: null, $phone, $discount]);
                db_exec('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [$coupon['id']]);
            }
            if (status_requires_stock($status)) {
                order_deduct_stock($orderId);
            }
            return ['ok' => true, 'order_id' => $orderId, 'order_number' => $number, 'total' => $total];
        });
    } catch (RuntimeException $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    } catch (Throwable $e) {
        log_error('order_create: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not place the order. Please try again.'];
    }

    // Notifications (outside the transaction - failures never lose an order)
    if ($status !== 'draft') {
        order_notify_new((int) $result['order_id']);
    }
    return $result;
}

/**
 * Change order status with history, stock policy and customer notification.
 * @return string|null error message
 */
function order_update_status(int $orderId, string $newStatus, string $note = '', bool $notifyCustomer = true): ?string
{
    if (!isset(order_statuses()[$newStatus])) return 'Invalid status.';
    try {
        $changed = db_tx(function () use ($orderId, $newStatus, $note) {
            $o = db_row('SELECT * FROM orders WHERE id = ? FOR UPDATE', [$orderId]);
            if (!$o) throw new RuntimeException('Order not found.');
            if ($o['status'] === $newStatus) return false;
            if ($newStatus === 'draft' && $o['status'] !== 'draft') throw new RuntimeException('An order cannot go back to draft.');
            if ($newStatus === 'cancelled') {
                order_restock($orderId);
                // release coupon usage
                if ($o['coupon_code']) {
                    db_exec('UPDATE coupons SET used_count = GREATEST(0, used_count - 1) WHERE code = ?', [$o['coupon_code']]);
                }
            } elseif (status_requires_stock($newStatus)) {
                order_deduct_stock($orderId);
            }
            if ($o['status'] === 'cancelled' && $newStatus !== 'cancelled' && $o['coupon_code']) {
                db_exec('UPDATE coupons SET used_count = used_count + 1 WHERE code = ?', [$o['coupon_code']]);
            }
            $extra = '';
            $params = [$newStatus];
            if ($newStatus === 'completed') {
                $extra = ', completed_at = NOW()';
                if (in_array($o['payment_method'], ['cod', 'cash_pickup', 'cash'], true) && $o['payment_status'] === 'unpaid') {
                    $extra .= ", payment_status = 'paid'";
                    db_exec("UPDATE payments SET status = 'received', received_by = ? WHERE order_id = ? AND status = 'pending'", [admin_id() ?: null, $orderId]);
                }
            }
            $params[] = $orderId;
            db_exec('UPDATE orders SET status = ?' . $extra . ' WHERE id = ?', $params);
            db_insert('INSERT INTO order_status_history (order_id, from_status, to_status, note, admin_id) VALUES (?, ?, ?, ?, ?)',
                [$orderId, $o['status'], $newStatus, $note ?: null, admin_id() ?: null]);
            return true;
        });
    } catch (RuntimeException $e) {
        return $e->getMessage();
    }
    if ($changed && $notifyCustomer && $newStatus !== 'pending') {
        order_notify_status($orderId);
    }
    return null;
}

function order_add_note(int $orderId, string $note, bool $internal = true): void
{
    $status = db_val('SELECT status FROM orders WHERE id = ?', [$orderId]);
    db_insert('INSERT INTO order_status_history (order_id, from_status, to_status, note, is_internal, admin_id) VALUES (?, ?, ?, ?, ?, ?)',
        [$orderId, $status, $status, mb_substr($note, 0, 500), $internal ? 1 : 0, admin_id() ?: null]);
}

function order_items(int $orderId): array
{
    return db_all('SELECT oi.*, p.slug, (SELECT pi.thumb FROM product_images pi WHERE pi.product_id = oi.product_id ORDER BY pi.is_primary DESC, pi.sort_order LIMIT 1) AS thumb,
        (SELECT c.name FROM categories c WHERE c.id = p.primary_category_id) AS category_name
        FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ? ORDER BY oi.id', [$orderId]);
}

function payment_methods_for(string $fulfillment): array
{
    $rows = db_all("SELECT * FROM payment_methods WHERE is_active = 1 AND applies_to <> 'pos' ORDER BY sort_order, id");
    return array_values(array_filter($rows, function ($m) use ($fulfillment) {
        return $m['applies_to'] === 'both' || $m['applies_to'] === $fulfillment;
    }));
}

function payment_method_name(string $code): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        foreach (db_all('SELECT code, name FROM payment_methods') as $r) $map[$r['code']] = $r['name'];
    }
    return $map[$code] ?? ucwords(str_replace('_', ' ', $code));
}

/** Available pickup slots for the next N days based on pickup settings. */
function pickup_slots(int $days = 0): array
{
    $days = $days ?: (int) setting('pickup_days_ahead', 5);
    $open = setting('pickup_open', '10:00');
    $close = setting('pickup_close', '21:00');
    $interval = max(15, (int) setting('pickup_interval', 60));
    $lead = (int) setting('pickup_lead_hours', 2) * 3600;
    $closedDays = array_filter(explode(',', (string) setting('pickup_closed_days', '')), 'strlen'); // e.g. "5" = Friday (date('w'))
    $out = [];
    for ($d = 0; $d <= $days; $d++) {
        $date = date('Y-m-d', strtotime("+$d day"));
        if (in_array(date('w', strtotime($date)), $closedDays, true)) continue;
        $slots = [];
        for ($t = strtotime("$date $open"); $t + $interval * 60 <= strtotime("$date $close"); $t += $interval * 60) {
            if ($t >= time() + $lead) {
                $slots[] = ['value' => date('Y-m-d H:i', $t), 'label' => date('h:i A', $t) . ' - ' . date('h:i A', $t + $interval * 60)];
            }
        }
        if ($slots) {
            $label = $d === 0 ? 'Today' : ($d === 1 ? 'Tomorrow' : date('D, d M', strtotime($date)));
            $out[] = ['date' => $date, 'label' => $label, 'slots' => $slots];
        }
    }
    return $out;
}
