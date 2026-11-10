<?php
/**
 * POS backend. All prices/stock come from the server (same inventory as the
 * website). Actions: open_session, close_session, customers, customer_create,
 * checkout, drafts, load_draft, delete_draft
 */
require dirname(__DIR__) . '/includes/init.php';
admin_require('pos');
if (!is_post()) json_error('Method not allowed', 405);
csrf_check();
$action = input('action');
$aid = admin_id();

function pos_session(int $aid): ?array
{
    return db_row("SELECT * FROM pos_sessions WHERE admin_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1", [$aid]);
}

function pos_session_summary(array $s): array
{
    $r = db_row("SELECT COUNT(*) AS sales, COALESCE(SUM(o.total),0) AS total,
        COALESCE(SUM(CASE WHEN o.payment_method = 'cash' THEN o.total ELSE 0 END),0) AS cash
        FROM pos_sales ps JOIN orders o ON o.id = ps.order_id WHERE ps.pos_session_id = ? AND o.status <> 'cancelled'", [$s['id']]);
    $r['expected_cash'] = round((float) $s['opening_cash'] + (float) $r['cash'], 2);
    return $r;
}

switch ($action) {
    case 'open_session':
        if (pos_session($aid)) json_error('A register session is already open.');
        $id = db_insert('INSERT INTO pos_sessions (admin_id, opening_cash) VALUES (?, ?)', [$aid, max(0, input_float('opening_cash', 0.0))]);
        json_out(['ok' => true, 'session_id' => $id]);

    case 'close_session':
        $s = pos_session($aid);
        if (!$s) json_error('No open register session.');
        $sum = pos_session_summary($s);
        db_exec("UPDATE pos_sessions SET status = 'closed', closed_at = NOW(), closing_cash = ?, expected_cash = ?, notes = ? WHERE id = ?",
            [input_float('closing_cash', 0.0), $sum['expected_cash'], mb_substr(input('notes'), 0, 255) ?: null, $s['id']]);
        json_out(['ok' => true, 'summary' => $sum, 'difference' => round(input_float('closing_cash', 0.0) - $sum['expected_cash'], 2)]);

    case 'session_summary':
        $s = pos_session($aid);
        if (!$s) json_error('No open register session.');
        json_out(['ok' => true, 'session' => $s, 'summary' => pos_session_summary($s)]);

    case 'customers':
        $q = input('q');
        $like = '%' . db_like($q) . '%';
        $rows = db_all("SELECT id, name, phone, email FROM customers WHERE deleted_at IS NULL AND phone <> '00000000000' AND (name LIKE ? OR phone LIKE ?) ORDER BY name LIMIT 10", [$like, '%' . db_like(preg_replace('/\D/', '', $q) ?: $q) . '%']);
        foreach ($rows as &$r) {
            $r['addresses'] = db_all('SELECT id, label, address_line, area, city, delivery_zone_id FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC', [$r['id']]);
            $r['last_order'] = db_val("SELECT order_number FROM orders WHERE customer_id = ? AND status <> 'draft' ORDER BY id DESC LIMIT 1", [$r['id']]);
        }
        json_out(['ok' => true, 'customers' => $rows]);

    case 'customer_create':
        $name = input('name'); $phone = normalize_phone(input('phone')); $email = mb_strtolower(input('email'));
        if (mb_strlen($name) < 2 || !valid_phone($phone)) json_error('Enter the customer name and a valid phone number.');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Invalid email address.');
        $id = customer_find_or_create($name, $phone, $email ?: null, 'pos');
        if (($line = input('address_line')) !== '' && ($z = delivery_zone(input_int('zone_id')))) {
            db_insert('INSERT INTO customer_addresses (customer_id, label, recipient_name, phone, address_line, city, area, delivery_zone_id, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [$id, 'Home', $name, $phone, mb_substr($line, 0, 255), $z['city'], $z['area'], $z['id'], db_val('SELECT COUNT(*) FROM customer_addresses WHERE customer_id = ?', [$id]) ? 0 : 1]);
        }
        $c = db_row('SELECT id, name, phone, email FROM customers WHERE id = ?', [$id]);
        $c['addresses'] = db_all('SELECT id, label, address_line, area, city, delivery_zone_id FROM customer_addresses WHERE customer_id = ? ORDER BY is_default DESC', [$id]);
        json_out(['ok' => true, 'customer' => $c, 'message' => 'Customer saved.']);

    case 'checkout':
        $mode = in_array(input('mode'), ['complete', 'pending', 'draft'], true) ? input('mode') : 'complete';
        $items = json_decode((string) ($_POST['items'] ?? '[]'), true);
        $lines = [];
        foreach (is_array($items) ? $items : [] as $it) {
            $q = max(0, min(9999, (int) ($it['q'] ?? 0)));
            if (!empty($it['p']) && $q) $lines[] = ['product_id' => (int) $it['p'], 'variation_id' => (int) ($it['v'] ?? 0), 'quantity' => $q];
        }
        if (!$lines) json_error('Cart is empty.');
        $session = pos_session($aid);
        if ($mode === 'complete' && !$session) json_error('Please open the register first.');
        $fulfillment = in_array(input('fulfillment'), ['walkin', 'pickup', 'delivery'], true) ? input('fulfillment') : 'walkin';
        // server-side subtotal for % discount
        $priced = price_lines($lines);
        $discount = 0.0;
        $dv = input_float('discount_value', 0.0);
        if ($dv > 0) $discount = input('discount_type') === 'percent' ? round($priced['subtotal'] * min(100, $dv) / 100, 2) : $dv;
        $customerId = input_int('customer_id');
        $name = input('name'); $phone = input('phone');
        if ($customerId && ($c = db_row('SELECT name, phone, email FROM customers WHERE id = ?', [$customerId]))) {
            $name = $name ?: $c['name']; $phone = $phone ?: $c['phone'];
        }
        $status = $mode === 'draft' ? 'draft' : ($mode === 'pending' ? 'pending' : ($fulfillment === 'walkin' ? 'completed' : 'confirmed'));
        $tendered = input_float('tendered', 0.0);
        $draftId = input_int('draft_id');
        $d = [
            'channel' => 'pos', 'status' => $status, 'admin_id' => $aid, 'customer_id' => $customerId,
            'name' => $name, 'phone' => $phone, 'email' => input('email'),
            'fulfillment' => $fulfillment, 'zone_id' => input_int('zone_id'), 'address_line' => input('address_line'), 'delivery_fee' => input('delivery_fee'),
            'pickup_at' => input('pickup_at'), 'payment_method' => input('payment_method') ?: 'cash',
            'payment_status' => $mode === 'complete' ? 'paid' : 'unpaid', 'payment_reference' => input('payment_reference'),
            'notes' => input('notes'), 'order_type' => input('order_type') === 'rashan' ? 'rashan' : 'regular', 'manual_discount' => $discount,
        ];
        if ($fulfillment === 'delivery' && !$d['zone_id']) json_error('Select the delivery area.');
        if ($mode === 'complete' && $d['payment_method'] === 'cash') {
            $expected = round($priced['subtotal'] - min($discount, $priced['subtotal']) + ($fulfillment === 'delivery' ? (float) $d['delivery_fee'] : 0), 2);
            if ($tendered > 0 && $tendered + 0.001 < $expected) json_error('Cash received is less than the total (' . money($expected) . ').');
        }
        // Replace a previously saved draft (drafts never touched stock)
        if ($draftId) {
            $old = db_row("SELECT id FROM orders WHERE id = ? AND status = 'draft' AND stock_deducted = 0", [$draftId]);
            if ($old) db_exec('DELETE FROM orders WHERE id = ?', [$draftId]);
        }
        $r = order_create($d, $lines);
        if (!$r['ok']) json_error($r['error']);
        if ($mode === 'complete') {
            $total = (float) $r['total'];
            $paid = $d['payment_method'] === 'cash' ? max($tendered, $total) : $total;
            db_insert('INSERT INTO pos_sales (pos_session_id, order_id, admin_id, amount_tendered, change_given) VALUES (?, ?, ?, ?, ?)',
                [$session['id'], $r['order_id'], $aid, $paid, max(0, round($paid - $total, 2))]);
        }
        $msg = ['complete' => 'Sale completed', 'pending' => 'Order saved as pending', 'draft' => 'Draft saved'][$mode];
        json_out(['ok' => true, 'order_id' => $r['order_id'], 'order_number' => $r['order_number'], 'total' => $r['total'], 'message' => $msg . ' (' . $r['order_number'] . ')',
            'change' => $mode === 'complete' && $d['payment_method'] === 'cash' && $tendered > $r['total'] ? round($tendered - $r['total'], 2) : 0]);

    case 'drafts':
        $rows = db_all("SELECT o.id, o.order_number, o.customer_name, o.customer_phone, o.total, o.created_at, o.order_type, (SELECT COUNT(*) FROM order_items WHERE order_id = o.id) AS items
            FROM orders o WHERE o.status = 'draft' ORDER BY o.id DESC LIMIT 50");
        foreach ($rows as &$r) { $r['ago'] = time_ago($r['created_at']); $r['total_fmt'] = money($r['total']); }
        json_out(['ok' => true, 'drafts' => $rows]);

    case 'load_draft':
        $o = db_row("SELECT * FROM orders WHERE id = ? AND status = 'draft'", [input_int('id')]);
        if (!$o) json_error('Draft not found.');
        $items = [];
        foreach (db_all('SELECT product_id, variation_id, quantity FROM order_items WHERE order_id = ?', [$o['id']]) as $it) {
            $s = sellable_item((int) $it['product_id'], (int) $it['variation_id']);
            if ($s) $items[] = ['p' => $s['product_id'], 'v' => $s['variation_id'], 'q' => (int) $it['quantity'], 'name' => $s['name'] . ($s['variation_name'] ? ' - ' . $s['variation_name'] : ''),
                'price' => $s['unit_price'], 'stock' => $s['track_stock'] ? $s['stock'] : 99999, 'sku' => $s['sku']];
        }
        json_out(['ok' => true, 'order' => ['id' => (int) $o['id'], 'customer_id' => (int) $o['customer_id'], 'name' => $o['customer_name'], 'phone' => $o['customer_phone'],
            'fulfillment' => $o['fulfillment'], 'zone_id' => (int) $o['delivery_zone_id'], 'address_line' => $o['address_line'], 'delivery_fee' => (float) $o['delivery_fee'],
            'pickup_at' => $o['pickup_at'] ? date('Y-m-d\TH:i', strtotime($o['pickup_at'])) : '', 'notes' => $o['customer_notes'], 'order_type' => $o['order_type'],
            'discount' => (float) $o['discount_amount'], 'items' => $items]]);

    case 'delete_draft':
        db_exec("DELETE FROM orders WHERE id = ? AND status = 'draft' AND stock_deducted = 0", [input_int('id')]);
        json_out(['ok' => true]);

    default:
        json_error('Unknown action.');
}
