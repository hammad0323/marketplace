<?php
/**
 * Print orders: creation, the status workflow, quotes, payments, tracking and file retention.
 *
 * Automatic pricing:  awaiting_approval → accepted → (awaiting_payment → payment_confirmed) → printing → ready → completed
 * Quote pricing:      awaiting_approval → quoted → (customer accepts) accepted … same as above
 * The owner can reject and the customer can cancel while the order has not started printing.
 */

function order_statuses(): array
{
    return [
        'submitted' => 'Order submitted', 'awaiting_approval' => 'Awaiting shop approval', 'accepted' => 'Accepted', 'quoted' => 'Price quotation sent',
        'awaiting_payment' => 'Awaiting payment', 'payment_confirmed' => 'Payment confirmed', 'printing' => 'Printing in progress',
        'ready' => 'Ready for collection', 'completed' => 'Completed', 'rejected' => 'Rejected', 'cancelled' => 'Cancelled',
    ];
}

/** Steps shown in the customer's progress bar, in order. */
function order_progress_steps(array $order): array
{
    $steps = ['submitted', 'awaiting_approval'];
    if ($order['pricing_mode'] === 'quote' || $order['quoted_amount'] !== null) {
        $steps[] = 'quoted';
    }
    $steps[] = 'accepted';
    if ($order['payment_mode'] === 'advance_required') {
        $steps[] = 'awaiting_payment';
        $steps[] = 'payment_confirmed';
    }
    return array_merge($steps, ['printing', 'ready', 'completed']);
}

function order_is_open(array $o): bool
{
    return !in_array($o['status'], ['completed', 'rejected', 'cancelled'], true);
}

function order_number_generate(): string
{
    do {
        $n = 'PO' . date('ymd') . random_int(10000, 99999);
    } while (db_val('SELECT 1 FROM orders WHERE order_number = ?', [$n]));
    return $n;
}

function order_history(int $orderId, string $status, ?string $note, string $actorType, ?int $actorId): void
{
    db_insert('order_status_history', ['order_id' => $orderId, 'status' => $status, 'note' => $note !== null ? mb_substr($note, 0, 500) : null,
        'actor_type' => $actorType, 'actor_id' => $actorId]);
}

function order_amount(array $o): ?float
{
    foreach (['final_amount', 'quoted_amount', 'estimated_amount'] as $k) {
        if ($o[$k] !== null) {
            return (float) $o[$k];
        }
    }
    return null;
}

// ---------------------------------------------------------------- creating orders
/**
 * Validates one requested item against the shop's catalogue and the customer's uploads.
 * Returns [clean item, files (upload rows), errors].
 */
function order_item_validate(int $shopId, array $session, array $in, array $catalog): array
{
    $errors = [];
    $cat = null;
    foreach ($catalog as $c) {
        if ($c['id'] === (int) ($in['category_id'] ?? 0)) {
            $cat = $c;
        }
    }
    if (!$cat) {
        return [null, [], ['Please choose a printing service offered by this shop.']];
    }
    $opts = (array) $cat['options'];
    $allowed = function (string $group, $id) use ($opts): ?int {
        foreach ($opts[$group] ?? [] as $o) {
            if ($o['id'] === (int) $id) {
                return $o['id'];
            }
        }
        return null;
    };
    $code = function (string $group, ?int $id) use ($opts): ?string {
        foreach ($opts[$group] ?? [] as $o) {
            if ($o['id'] === $id) {
                return $o['code'];
            }
        }
        return null;
    };
    $sub = null;
    if (!empty($in['subcategory_id'])) {
        foreach ($cat['subcategories'] as $s) {
            if ($s['id'] === (int) $in['subcategory_id']) {
                $sub = $s;
            }
        }
        if (!$sub) {
            $errors[] = 'Please choose a valid type of ' . strtolower($cat['name']) . '.';
        }
    }
    $item = ['category_id' => $cat['id'], 'subcategory_id' => $sub['id'] ?? null, 'item_type' => !empty($in['cv_token']) ? 'cv' : ($cat['code'] === 'custom' ? 'custom' : 'document')];
    foreach (['paper_size' => 'paper_size_id', 'paper_type' => 'paper_type_id', 'gsm' => 'gsm_id'] as $g => $k) {
        $v = $in[$k] ?? null;
        $item[$k] = $v ? $allowed($g, $v) : null;
        if ($v && !$item[$k]) {
            $errors[] = 'One of the selected options is not available for this service.';
        }
    }
    if (!$item['paper_size_id'] && !empty($opts['paper_size'])) {
        $errors[] = 'Please choose a paper size.';
    }
    $item['custom_width_mm'] = $item['custom_height_mm'] = null;
    if ($code('paper_size', $item['paper_size_id']) === 'custom') {
        $w = (float) ($in['custom_width_mm'] ?? 0);
        $h = (float) ($in['custom_height_mm'] ?? 0);
        if ($w < 20 || $h < 20 || $w > 1200 || $h > 1200) {
            $errors[] = 'Enter the custom paper size in millimetres (20–1200 mm).';
        }
        $item['custom_width_mm'] = $w;
        $item['custom_height_mm'] = $h;
    }
    $item['color'] = in_array($in['color'] ?? '', ['bw', 'color'], true) && $allowed('color', db_val("SELECT id FROM printing_options WHERE option_group = 'color' AND code = ?", [$in['color']])) ? $in['color'] : null;
    $item['sides'] = in_array($in['sides'] ?? '', ['single', 'double'], true) && $allowed('sides', db_val("SELECT id FROM printing_options WHERE option_group = 'sides' AND code = ?", [$in['sides']])) ? $in['sides'] : null;
    if (!empty($opts['color']) && !$item['color']) $errors[] = 'Please choose black & white or colour.';
    if (!empty($opts['sides']) && !$item['sides']) $item['sides'] = 'single';
    $item['orientation'] = ($in['orientation'] ?? '') === 'landscape' ? 'landscape' : 'portrait';
    $item['finishing'] = [];
    foreach (array_slice((array) ($in['finishing'] ?? []), 0, 10) as $f) {
        if ($id = $allowed('finishing', $f)) {
            $item['finishing'][] = $id;
        } else {
            $errors[] = 'One of the selected finishing services is not available.';
        }
    }
    $item['copies'] = max(1, min(1000, (int) ($in['copies'] ?? 1)));
    $item['quantity'] = max(1, min(100000, (int) ($in['quantity'] ?? 1)));
    $item['notes'] = mb_substr(trim((string) ($in['notes'] ?? '')), 0, 500) ?: null;
    $item['layout'] = layout_clean($in['layout'] ?? []);
    $item['cv_id'] = null;

    // files
    $files = [];
    $tokens = array_slice(array_values(array_filter((array) ($in['files'] ?? []), 'is_string')), 0, 20);
    foreach ($tokens as $t) {
        $f = preg_match('/^[a-f0-9]{32}$/', $t) ? db_row("SELECT * FROM file_uploads WHERE token = ? AND session_id = ? AND shop_id = ? AND status = 'temp' AND expires_at > NOW()", [$t, (int) $session['id'], $shopId]) : null;
        if (!$f) {
            $errors[] = 'One of your files has expired. Please upload it again.';
            continue;
        }
        $files[] = $f;
    }
    if (!empty($in['cv_token'])) {
        $cv = db_row('SELECT id FROM customer_cvs WHERE public_token = ? AND session_id = ? AND deleted_at IS NULL', [(string) $in['cv_token'], (int) $session['id']]);
        if (!$cv) {
            $errors[] = 'Your CV could not be found. Please open it again.';
        } else {
            $item['cv_id'] = (int) $cv['id'];
        }
    }
    if (!$files && !$item['cv_id'] && $cat['accepts_upload'] && $cat['code'] !== 'custom') {
        $errors[] = 'Please upload the file you want to print.';
    }
    // pages per copy are counted on the server
    $pages = 0;
    $selectionCount = null;
    foreach ($files as $f) {
        if ($f['file_kind'] === 'image') {
            $pages++;
        } elseif ($f['page_count']) {
            $sel = pages_clean($in['page_selection'] ?? [], (int) $f['page_count']);
            $selectionCount = count($sel);
            $pages += count($files) === 1 ? count($sel) : (int) $f['page_count'];
        } else {
            $pages += max(1, min(2000, (int) ($in['pages'] ?? 1))); // DOCX the server could not convert: customer's estimate
        }
    }
    if ($item['cv_id']) {
        $pages += max(1, min(10, (int) ($in['pages'] ?? 1)));
    }
    $item['pages'] = max(1, $pages ?: (int) ($in['pages'] ?? 1));
    $item['page_selection'] = count($files) === 1 && $files[0]['page_count'] ? pages_clean($in['page_selection'] ?? [], (int) $files[0]['page_count']) : [];
    $item['title'] = $files ? mb_substr($files[0]['original_name'] . (count($files) > 1 ? ' + ' . (count($files) - 1) . ' more' : ''), 0, 255) : ($item['cv_id'] ? 'CV' : $cat['name']);
    return [$item, $files, $errors];
}

/**
 * Creates an order for the current customer session. $in: customer_name, customer_phone, instructions, items[], source.
 * Returns ['order' => row] or ['errors' => [...]].
 */
function order_create(array $shop, array $session, array $in): array
{
    $avail = shop_availability($shop);
    if (!$avail['open']) {
        return ['errors' => [$avail['reason']]];
    }
    $name = mb_substr(trim((string) ($in['customer_name'] ?? '')), 0, 120);
    $phone = clean_phone((string) ($in['customer_phone'] ?? ''));
    $errors = [];
    if (mb_strlen($name) < 2) $errors[] = 'Please enter your name.';
    if (!valid_phone($phone)) $errors[] = 'Please enter a valid mobile number so the shop can contact you.';
    $rawItems = array_slice(array_values(array_filter((array) ($in['items'] ?? []), 'is_array')), 0, 10);
    if (!$rawItems) $errors[] = 'Your order is empty.';
    $catalog = shop_catalog((int) $shop['id']);
    $items = [];
    $seen = [];
    foreach ($rawItems as $raw) {
        [$item, $files, $err] = order_item_validate((int) $shop['id'], $session, $raw, $catalog);
        foreach ($files as $f) {
            if (isset($seen[$f['id']])) {
                $err[] = 'The same file was added twice.';
            }
            $seen[$f['id']] = true;
        }
        $errors = array_merge($errors, $err);
        if ($item) {
            $items[] = [$item, $files];
        }
    }
    if ($errors) {
        return ['errors' => array_values(array_unique($errors))];
    }
    $settings = shop_settings((int) $shop['id']);
    $quote = price_order((int) $shop['id'], array_map(fn($x) => $x[0], $items));
    $mode = $settings['pricing_mode'] === 'auto' && $quote['priced'] ? 'auto' : 'quote';

    $orderId = db_tx(function () use ($shop, $session, $in, $name, $phone, $items, $quote, $mode, $settings) {
        $customerId = $session['customer_id'] ? (int) $session['customer_id'] : null;
        if (!$customerId) {
            $customerId = db_insert('customers', ['name' => $name, 'phone' => $phone]);
            db_exec('UPDATE customer_sessions SET customer_id = ? WHERE id = ?', [$customerId, (int) $session['id']]);
        } else {
            db_exec('UPDATE customers SET name = ?, phone = ? WHERE id = ?', [$name, $phone, $customerId]);
        }
        db_exec('UPDATE customer_sessions SET last_shop_id = ? WHERE id = ?', [(int) $shop['id'], (int) $session['id']]);
        $orderId = db_insert('orders', [
            'order_number' => order_number_generate(), 'tracking_token' => random_token(20), 'shop_id' => (int) $shop['id'], 'customer_id' => $customerId,
            'session_id' => (int) $session['id'], 'customer_name' => $name, 'customer_phone' => $phone, 'status' => 'awaiting_approval',
            'pricing_mode' => $mode, 'payment_mode' => $settings['payment_mode'], 'estimated_amount' => $quote['priced'] ? $quote['total'] : null,
            'special_instructions' => mb_substr(trim((string) ($in['instructions'] ?? '')), 0, 2000) ?: null, 'source' => ($in['source'] ?? 'web') === 'app' ? 'app' : 'web',
        ]);
        foreach ($items as $i => [$item, $files]) {
            $p = $quote['items'][$i];
            $itemId = db_insert('order_items', [
                'order_id' => $orderId, 'item_type' => $item['item_type'], 'category_id' => $item['category_id'], 'subcategory_id' => $item['subcategory_id'],
                'title' => $item['title'], 'paper_size_id' => $item['paper_size_id'], 'custom_width_mm' => $item['custom_width_mm'], 'custom_height_mm' => $item['custom_height_mm'],
                'color' => $item['color'], 'sides' => $item['sides'], 'paper_type_id' => $item['paper_type_id'], 'gsm_id' => $item['gsm_id'], 'orientation' => $item['orientation'],
                'pages' => $item['pages'], 'copies' => $item['copies'], 'quantity' => $item['quantity'], 'layout' => json_encode($item['layout']),
                'page_selection' => $item['page_selection'] ? json_encode($item['page_selection']) : null, 'estimated_price' => $p['priced'] ? $p['total'] : null,
                'price_breakdown' => json_encode($p['lines'], JSON_UNESCAPED_UNICODE), 'cv_id' => $item['cv_id'], 'notes' => $item['notes'],
            ]);
            $labels = pricing_option_labels();
            foreach ($item['finishing'] as $fid) {
                db_insert('order_item_finishings', ['order_item_id' => $itemId, 'option_id' => $fid, 'label' => $labels[$fid] ?? 'Finishing', 'price' => null]);
            }
            foreach ($files as $f) {
                db_insert('order_files', ['order_id' => $orderId, 'order_item_id' => $itemId, 'kind' => 'original', 'storage_path' => $f['storage_path'],
                    'original_name' => $f['original_name'], 'mime' => $f['mime'], 'size_bytes' => (int) $f['size_bytes'], 'page_count' => $f['page_count']]);
                if ($f['converted_path']) {
                    db_insert('order_files', ['order_id' => $orderId, 'order_item_id' => $itemId, 'kind' => 'converted', 'storage_path' => $f['converted_path'],
                        'original_name' => pathinfo($f['original_name'], PATHINFO_FILENAME) . '.pdf', 'mime' => 'application/pdf', 'size_bytes' => (int) @filesize(storage_file($f['converted_path']) ?: ''), 'page_count' => $f['page_count']]);
                }
                db_exec("UPDATE file_uploads SET status = 'attached' WHERE id = ?", [(int) $f['id']]);
            }
        }
        order_history($orderId, 'submitted', null, 'customer', (int) $session['id']);
        order_history($orderId, 'awaiting_approval', $mode === 'quote' ? 'The shop will review your files and send a price.' : null, 'system', null);
        return $orderId;
    });

    order_build_print_files($orderId);
    $order = db_row('SELECT * FROM orders WHERE id = ?', [$orderId]);
    notify('shop', (int) $shop['id'], (int) $shop['id'], $orderId, 'new_order', 'New order ' . $order['order_number'],
        $name . ' · ' . count($items) . ' item' . (count($items) > 1 ? 's' : '') . ($order['estimated_amount'] !== null ? ' · ' . money($order['estimated_amount']) : ' · needs a quote'), 'owner/order.php?id=' . $orderId);
    notify('customer', (int) $session['id'], (int) $shop['id'], $orderId, 'order_submitted', 'Order ' . $order['order_number'] . ' sent to ' . $shop['name'],
        $mode === 'quote' ? 'The shop will review your files and send you a price.' : 'The shop will confirm your order shortly.', 'track/' . $order['order_number'] . '?t=' . $order['tracking_token']);
    activity('customer', (int) $session['id'], 'order_created', (int) $shop['id'], 'order', $orderId);
    return ['order' => $order];
}

/** Builds print-ready PDFs for every item; failures are recorded as notes, never block the order. */
function order_build_print_files(int $orderId): void
{
    foreach (db_all('SELECT * FROM order_items WHERE order_id = ?', [$orderId]) as $item) {
        $files = db_all("SELECT * FROM order_files WHERE order_item_id = ? AND kind IN ('original','converted') AND deleted_at IS NULL ORDER BY id", [(int) $item['id']]);
        if (!$files) {
            continue;
        }
        $sources = [];
        $note = null;
        foreach ($files as $f) {
            if ($f['kind'] === 'original' && str_contains($f['mime'], 'wordprocessingml')) {
                $conv = array_values(array_filter($files, fn($x) => $x['kind'] === 'converted'));
                if (!$conv) {
                    $note = 'Word file: open the original in Word / LibreOffice and print with the selected options.';
                }
                continue;
            }
            $full = storage_file($f['storage_path']);
            if ($full) {
                $sources[] = ['path' => $full, 'kind' => str_starts_with($f['mime'], 'image/') ? 'image' : 'pdf'];
            }
        }
        if ($sources) {
            try {
                $built = build_print_ready($item, $sources);
                [$rel, $dest] = storage_new_path('orders', 'pdf');
                rename($built['path'], $dest);
                db_insert('order_files', ['order_id' => $orderId, 'order_item_id' => (int) $item['id'], 'kind' => 'print_ready', 'storage_path' => $rel,
                    'original_name' => 'print-ready-' . $item['id'] . '.pdf', 'mime' => 'application/pdf', 'size_bytes' => (int) filesize($dest),
                    'page_count' => $built['pages'], 'sha256' => hash_file('sha256', $dest), 'note' => $note]);
                continue;
            } catch (Throwable $e) {
                $note = $e instanceof RuntimeException ? $e->getMessage() : 'The print-ready file could not be generated; print the original with the selected options.';
                error_log('print-ready failed for item ' . $item['id'] . ': ' . $e->getMessage());
            }
        }
        if ($note) {
            db_update('order_items', ['notes' => trim(($item['notes'] ? $item['notes'] . ' · ' : '') . 'Shop note: ' . $note)], 'id = ?', [(int) $item['id']]);
        }
    }
}

// ---------------------------------------------------------------- reading orders
function order_detail(array $o, bool $forOwner): array
{
    $labels = pricing_option_labels();
    $items = [];
    foreach (db_all('SELECT oi.*, c.name category_name, s.name subcategory_name FROM order_items oi LEFT JOIN categories c ON c.id = oi.category_id LEFT JOIN subcategories s ON s.id = oi.subcategory_id WHERE oi.order_id = ? ORDER BY oi.id', [(int) $o['id']]) as $it) {
        $fin = db_all('SELECT option_id, label FROM order_item_finishings WHERE order_item_id = ?', [(int) $it['id']]);
        $files = [];
        foreach (db_all('SELECT * FROM order_files WHERE order_item_id = ? ORDER BY id', [(int) $it['id']]) as $f) {
            $available = !$f['deleted_at'] && storage_file($f['storage_path']);
            $files[] = ['id' => (int) $f['id'], 'kind' => $f['kind'], 'name' => $f['original_name'], 'mime' => $f['mime'], 'size' => (int) $f['size_bytes'],
                'pages' => $f['page_count'] !== null ? (int) $f['page_count'] : null, 'note' => $f['note'], 'deleted' => !$available,
                'url' => $available ? signed_file_url('order', (int) $f['id'], $forOwner ? 3600 : 900) : null,
                'download_url' => $available && $forOwner ? signed_file_url('order', (int) $f['id'], 3600, 'attachment') : null];
        }
        $items[] = [
            'id' => (int) $it['id'], 'title' => $it['title'], 'item_type' => $it['item_type'], 'category' => $it['category_name'], 'subcategory' => $it['subcategory_name'],
            'paper_size' => $it['paper_size_id'] ? ($labels[(int) $it['paper_size_id']] ?? null) : null,
            'custom_size' => $it['custom_width_mm'] ? (float) $it['custom_width_mm'] . ' × ' . (float) $it['custom_height_mm'] . ' mm' : null,
            'color' => $it['color'], 'sides' => $it['sides'], 'paper_type' => $it['paper_type_id'] ? ($labels[(int) $it['paper_type_id']] ?? null) : null,
            'gsm' => $it['gsm_id'] ? ($labels[(int) $it['gsm_id']] ?? null) : null, 'orientation' => $it['orientation'],
            'pages' => (int) $it['pages'], 'copies' => (int) $it['copies'], 'quantity' => (int) $it['quantity'],
            'finishing' => array_column($fin, 'label'), 'layout' => json_decode((string) $it['layout'], true), 'page_selection' => json_decode((string) $it['page_selection'], true),
            'estimated_price' => $it['estimated_price'] !== null ? (float) $it['estimated_price'] : null, 'price_breakdown' => json_decode((string) $it['price_breakdown'], true) ?: [],
            'notes' => $it['notes'], 'files' => $files,
        ];
    }
    $history = db_all('SELECT status, note, actor_type, created_at FROM order_status_history WHERE order_id = ? ORDER BY id', [(int) $o['id']]);
    $payments = db_all('SELECT id, amount, method, reference, status, submitted_by, created_at, confirmed_at, proof_file_id FROM payments WHERE order_id = ? ORDER BY id', [(int) $o['id']]);
    foreach ($payments as &$p) {
        $p['amount'] = (float) $p['amount'];
        $p['proof_url'] = $p['proof_file_id'] ? signed_file_url('order', (int) $p['proof_file_id'], 900) : null;
        unset($p['proof_file_id']);
    }
    $shop = shop_by_id((int) $o['shop_id']);
    $settings = shop_settings((int) $o['shop_id']);
    $out = [
        'id' => (int) $o['id'], 'order_number' => $o['order_number'], 'status' => $o['status'], 'status_label' => order_statuses()[$o['status']] ?? $o['status'],
        'pricing_mode' => $o['pricing_mode'], 'payment_mode' => $o['payment_mode'], 'payment_status' => $o['payment_status'],
        'estimated_amount' => $o['estimated_amount'] !== null ? (float) $o['estimated_amount'] : null, 'quoted_amount' => $o['quoted_amount'] !== null ? (float) $o['quoted_amount'] : null,
        'final_amount' => $o['final_amount'] !== null ? (float) $o['final_amount'] : null, 'amount' => order_amount($o),
        'customer_name' => $o['customer_name'], 'customer_phone' => $o['customer_phone'], 'special_instructions' => $o['special_instructions'],
        'rejection_reason' => $o['rejection_reason'], 'source' => $o['source'], 'created_at' => $o['created_at'], 'updated_at' => $o['updated_at'], 'closed_at' => $o['closed_at'],
        'files_deleted' => (bool) $o['files_deleted_at'], 'items' => $items, 'history' => $history, 'payments' => $payments,
        'steps' => array_map(fn($s) => ['key' => $s, 'label' => order_statuses()[$s]], order_progress_steps($o)),
        'shop' => ['name' => $shop['name'], 'shop_code' => $shop['shop_code'], 'phone' => $shop['phone'], 'whatsapp' => $shop['whatsapp'], 'address' => $shop['address'],
            'payment_instructions' => $o['payment_mode'] !== 'pay_at_shop' ? $settings['payment_instructions'] : null],
        'tracking_url' => url('track/' . $o['order_number'] . '?t=' . $o['tracking_token']),
        'can_cancel' => in_array($o['status'], ['submitted', 'awaiting_approval', 'quoted', 'accepted', 'awaiting_payment'], true),
        'can_accept_quote' => $o['status'] === 'quoted',
        'can_submit_payment' => in_array($o['status'], ['accepted', 'awaiting_payment', 'quoted'], true) && $o['payment_mode'] !== 'pay_at_shop' && $o['payment_status'] !== 'paid',
    ];
    if ($forOwner) {
        $out['owner_actions'] = order_owner_actions($o);
    }
    return $out;
}

/** Orders the current customer may see: their session, or the private tracking token, or number + phone. */
function order_for_customer(string $number, ?string $token, ?array $session, ?string $phone = null): ?array
{
    $o = db_row('SELECT * FROM orders WHERE order_number = ?', [strtoupper(trim($number))]);
    if (!$o) {
        return null;
    }
    if (($session && (int) $o['session_id'] === (int) $session['id']) || ($token && hash_equals($o['tracking_token'], $token))
        || ($phone && preg_replace('/\D/', '', $phone) !== '' && substr(preg_replace('/\D/', '', $phone), -10) === substr(preg_replace('/\D/', '', $o['customer_phone']), -10))) {
        return $o;
    }
    return null;
}

// ---------------------------------------------------------------- owner actions
/** Actions the owner may take now: list of [action, label]. */
function order_owner_actions(array $o): array
{
    $a = [];
    switch ($o['status']) {
        case 'submitted':
        case 'awaiting_approval':
            if ($o['estimated_amount'] !== null) $a[] = ['accept', 'Accept order'];
            $a[] = ['quote', $o['estimated_amount'] !== null ? 'Change price & send quote' : 'Send price quote'];
            $a[] = ['reject', 'Reject'];
            break;
        case 'quoted':
            $a[] = ['quote', 'Update quote'];
            $a[] = ['accept', 'Mark accepted by customer'];
            $a[] = ['reject', 'Reject'];
            break;
        case 'accepted':
        case 'awaiting_payment':
            if ($o['payment_status'] !== 'paid' && $o['payment_mode'] !== 'pay_at_shop') $a[] = ['confirm_payment', 'Confirm payment received'];
            if ($o['payment_mode'] !== 'advance_required' || $o['payment_status'] === 'paid') $a[] = ['printing', 'Start printing'];
            $a[] = ['reject', 'Cancel order'];
            break;
        case 'payment_confirmed':
            $a[] = ['printing', 'Start printing'];
            break;
        case 'printing':
            $a[] = ['ready', 'Mark ready for collection'];
            break;
        case 'ready':
            $a[] = ['complete', 'Complete (handed over)'];
            break;
    }
    return $a;
}

/**
 * Applies an owner action. $p: amount (quote/override), note, reason, method (payment).
 * Returns null on success or an error message.
 */
function order_owner_action(array $o, string $action, array $p, array $owner): ?string
{
    if (!subscription_allows_work((int) $o['shop_id'])) {
        return 'Your subscription has expired. Renew it to manage orders.';
    }
    if (!in_array($action, array_column(order_owner_actions($o), 0), true)) {
        return 'This action is not available for the order right now.';
    }
    $uid = (int) $owner['user']['id'];
    $id = (int) $o['id'];
    $note = mb_substr(trim((string) ($p['note'] ?? '')), 0, 500) ?: null;
    $customer = $o['session_id'] ? (int) $o['session_id'] : null;
    $link = 'track/' . $o['order_number'] . '?t=' . $o['tracking_token'];
    $tell = function (string $kind, string $title, ?string $body = null) use ($customer, $o, $link, $id) {
        if ($customer) {
            notify('customer', $customer, (int) $o['shop_id'], $id, $kind, $title, $body, $link);
        }
    };
    switch ($action) {
        case 'accept':
            $amount = isset($p['amount']) && $p['amount'] !== '' && $p['amount'] !== null ? round(max(0, (float) $p['amount']), 2) : null;
            if ($amount === null && order_amount($o) === null) {
                return 'Enter the price for this order.';
            }
            $final = $amount ?? order_amount($o);
            $next = $o['payment_mode'] === 'advance_required' && $o['payment_status'] !== 'paid' ? 'awaiting_payment' : 'accepted';
            db_update('orders', ['status' => $next, 'final_amount' => $final, 'quoted_amount' => $amount ?? $o['quoted_amount']], 'id = ?', [$id]);
            order_history($id, 'accepted', $note, 'owner', $uid);
            if ($next === 'awaiting_payment') {
                order_history($id, 'awaiting_payment', 'Advance payment of ' . money($final) . ' required.', 'system', null);
            }
            $tell('order_accepted', 'Order ' . $o['order_number'] . ' accepted', 'Total ' . money($final) . ($next === 'awaiting_payment' ? ' — please pay in advance using the shop’s instructions.' : '.'));
            break;
        case 'quote':
            $amount = round((float) ($p['amount'] ?? -1), 2);
            if ($amount <= 0) {
                return 'Enter the quoted price in PKR.';
            }
            db_update('orders', ['status' => 'quoted', 'quoted_amount' => $amount], 'id = ?', [$id]);
            order_history($id, 'quoted', 'Price: ' . money($amount) . ($note ? ' — ' . $note : ''), 'owner', $uid);
            $tell('order_quoted', 'Price for order ' . $o['order_number'] . ': ' . money($amount), $note ?: 'Open your order to accept the price.');
            break;
        case 'reject':
            $reason = mb_substr(trim((string) ($p['reason'] ?? $note ?? '')), 0, 255);
            if ($reason === '') {
                return 'Please give the customer a reason.';
            }
            db_update('orders', ['status' => $o['status'] === 'awaiting_approval' || $o['status'] === 'quoted' ? 'rejected' : 'cancelled', 'rejection_reason' => $reason, 'closed_at' => now()], 'id = ?', [$id]);
            order_history($id, $o['status'] === 'awaiting_approval' || $o['status'] === 'quoted' ? 'rejected' : 'cancelled', $reason, 'owner', $uid);
            $tell('order_rejected', 'Order ' . $o['order_number'] . ' was not accepted', $reason);
            break;
        case 'confirm_payment':
            $amount = isset($p['amount']) && $p['amount'] !== '' ? round(max(0, (float) $p['amount']), 2) : (order_amount($o) ?? 0);
            $method = array_key_exists($p['method'] ?? '', payment_methods()) ? $p['method'] : 'cash';
            db_exec("UPDATE payments SET status = 'confirmed', confirmed_by = ?, confirmed_at = NOW() WHERE order_id = ? AND status = 'pending'", [$uid, $id]);
            if (!db_val("SELECT 1 FROM payments WHERE order_id = ? AND status = 'confirmed'", [$id])) {
                db_insert('payments', ['order_id' => $id, 'shop_id' => (int) $o['shop_id'], 'amount' => $amount, 'method' => $method, 'status' => 'confirmed', 'submitted_by' => 'owner', 'confirmed_by' => $uid, 'confirmed_at' => now()]);
            }
            db_update('orders', ['status' => 'payment_confirmed', 'payment_status' => 'paid', 'final_amount' => $o['final_amount'] ?? $amount], 'id = ?', [$id]);
            order_history($id, 'payment_confirmed', $note, 'owner', $uid);
            $tell('payment_confirmed', 'Payment received for ' . $o['order_number'], 'Your order will be printed shortly.');
            break;
        case 'printing':
            db_update('orders', ['status' => 'printing'], 'id = ?', [$id]);
            order_history($id, 'printing', $note, 'owner', $uid);
            $tell('order_printing', 'Order ' . $o['order_number'] . ' is being printed');
            break;
        case 'ready':
            db_update('orders', ['status' => 'ready'], 'id = ?', [$id]);
            order_history($id, 'ready', $note, 'owner', $uid);
            $tell('order_ready', 'Order ' . $o['order_number'] . ' is ready for collection', 'Please collect it from ' . (string) db_val('SELECT name FROM shops WHERE id = ?', [(int) $o['shop_id']]) . '.');
            break;
        case 'complete':
            $amount = isset($p['amount']) && $p['amount'] !== '' ? round(max(0, (float) $p['amount']), 2) : order_amount($o);
            db_update('orders', ['status' => 'completed', 'closed_at' => now(), 'final_amount' => $amount, 'payment_status' => 'paid'], 'id = ?', [$id]);
            if ($o['payment_status'] !== 'paid' && $amount) {
                db_insert('payments', ['order_id' => $id, 'shop_id' => (int) $o['shop_id'], 'amount' => $amount, 'method' => array_key_exists($p['method'] ?? '', payment_methods()) ? $p['method'] : 'cash',
                    'status' => 'confirmed', 'submitted_by' => 'owner', 'confirmed_by' => $uid, 'confirmed_at' => now()]);
            }
            order_history($id, 'completed', $note, 'owner', $uid);
            $tell('order_completed', 'Order ' . $o['order_number'] . ' completed', 'Thank you for printing with us!');
            break;
    }
    activity('owner', $uid, 'order_' . $action, (int) $o['shop_id'], 'order', $id);
    return null;
}

// ---------------------------------------------------------------- customer actions
function order_customer_action(array $o, string $action, array $session): ?string
{
    $id = (int) $o['id'];
    $sid = (int) $session['id'];
    if ($action === 'cancel') {
        if (!in_array($o['status'], ['submitted', 'awaiting_approval', 'quoted', 'accepted', 'awaiting_payment'], true)) {
            return 'This order can no longer be cancelled. Please contact the shop.';
        }
        db_update('orders', ['status' => 'cancelled', 'closed_at' => now()], 'id = ?', [$id]);
        order_history($id, 'cancelled', 'Cancelled by the customer.', 'customer', $sid);
        notify('shop', (int) $o['shop_id'], (int) $o['shop_id'], $id, 'order_cancelled', 'Order ' . $o['order_number'] . ' cancelled by the customer', null, 'owner/order.php?id=' . $id);
        return null;
    }
    if ($action === 'accept_quote' || $action === 'decline_quote') {
        if ($o['status'] !== 'quoted') {
            return 'There is no price waiting for your approval.';
        }
        if ($action === 'decline_quote') {
            db_update('orders', ['status' => 'cancelled', 'closed_at' => now()], 'id = ?', [$id]);
            order_history($id, 'cancelled', 'Customer declined the quoted price.', 'customer', $sid);
            notify('shop', (int) $o['shop_id'], (int) $o['shop_id'], $id, 'quote_declined', 'Quote declined for ' . $o['order_number'], null, 'owner/order.php?id=' . $id);
            return null;
        }
        $next = $o['payment_mode'] === 'advance_required' && $o['payment_status'] !== 'paid' ? 'awaiting_payment' : 'accepted';
        db_update('orders', ['status' => $next, 'final_amount' => $o['quoted_amount']], 'id = ?', [$id]);
        order_history($id, 'accepted', 'Customer accepted the price of ' . money($o['quoted_amount']) . '.', 'customer', $sid);
        if ($next === 'awaiting_payment') {
            order_history($id, 'awaiting_payment', null, 'system', null);
        }
        notify('shop', (int) $o['shop_id'], (int) $o['shop_id'], $id, 'quote_accepted', 'Quote accepted for ' . $o['order_number'], money($o['quoted_amount']), 'owner/order.php?id=' . $id);
        return null;
    }
    return 'Unknown action.';
}

/** Customer reports an advance payment (with optional proof screenshot). */
function order_submit_payment(array $o, array $session, string $method, ?string $reference, ?array $proofFile): ?string
{
    if ($o['payment_mode'] === 'pay_at_shop' || $o['payment_status'] === 'paid' || !in_array($o['status'], ['accepted', 'awaiting_payment', 'quoted'], true)) {
        return 'Payment cannot be submitted for this order.';
    }
    if (!in_array($method, ['bank_transfer', 'jazzcash', 'easypaisa', 'other'], true)) {
        return 'Choose how you paid.';
    }
    $fileId = null;
    if ($proofFile && ($proofFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        [$mime, $ext, $kind] = upload_validate($proofFile, 8);
        if ($kind === 'docx') {
            return 'Please upload a screenshot (image) or PDF receipt.';
        }
        $rel = upload_store($proofFile, 'orders', $ext);
        $fileId = db_insert('order_files', ['order_id' => (int) $o['id'], 'kind' => 'payment_proof', 'storage_path' => $rel, 'original_name' => 'payment-proof.' . $ext,
            'mime' => $mime, 'size_bytes' => (int) $proofFile['size']]);
    }
    db_insert('payments', ['order_id' => (int) $o['id'], 'shop_id' => (int) $o['shop_id'], 'amount' => order_amount($o) ?? 0, 'method' => $method,
        'reference' => $reference ? mb_substr($reference, 0, 120) : null, 'proof_file_id' => $fileId, 'status' => 'pending', 'submitted_by' => 'customer']);
    db_update('orders', ['payment_status' => 'pending_verification'], 'id = ?', [(int) $o['id']]);
    order_history((int) $o['id'], $o['status'], 'Customer reported a ' . str_replace('_', ' ', $method) . ' payment' . ($reference ? ' (ref ' . $reference . ')' : '') . '.', 'customer', (int) $session['id']);
    notify('shop', (int) $o['shop_id'], (int) $o['shop_id'], (int) $o['id'], 'payment_submitted', 'Payment submitted for ' . $o['order_number'], 'Please verify and confirm it.', 'owner/order.php?id=' . $o['id']);
    return null;
}

// ---------------------------------------------------------------- retention
/** Deletes customer files after each shop's retention period. Returns counts. */
function files_cleanup(): array
{
    $deletedOrders = $deletedTemp = 0;
    $maxDays = (int) setting('max_retention_days', 30);
    $orders = db_all("SELECT o.id, o.shop_id FROM orders o JOIN shop_settings s ON s.shop_id = o.shop_id
        WHERE o.files_deleted_at IS NULL AND (
            (o.closed_at IS NOT NULL AND o.closed_at < DATE_SUB(NOW(), INTERVAL LEAST(s.retention_days, ?) DAY))
            OR o.created_at < DATE_SUB(NOW(), INTERVAL ? DAY))", [$maxDays, $maxDays + 30]);
    foreach ($orders as $o) {
        foreach (db_all('SELECT id, storage_path FROM order_files WHERE order_id = ? AND deleted_at IS NULL', [(int) $o['id']]) as $f) {
            storage_delete($f['storage_path']);
            db_exec('UPDATE order_files SET deleted_at = NOW() WHERE id = ?', [(int) $f['id']]);
        }
        db_exec('UPDATE orders SET files_deleted_at = NOW() WHERE id = ?', [(int) $o['id']]);
        $deletedOrders++;
    }
    foreach (db_all("SELECT id, storage_path, converted_path FROM file_uploads WHERE status = 'temp' AND expires_at < NOW()") as $f) {
        storage_delete($f['storage_path']);
        storage_delete($f['converted_path']);
        db_exec("UPDATE file_uploads SET status = 'deleted' WHERE id = ?", [(int) $f['id']]);
        $deletedTemp++;
    }
    foreach (glob(storage_root() . '/previews/*.pdf') ?: [] as $pv) {
        if (filemtime($pv) < time() - 7200) {
            @unlink($pv);
        }
    }
    // CV photos of drafts nobody touched for 180 days
    foreach (db_all('SELECT id, photo_path FROM customer_cvs WHERE photo_path IS NOT NULL AND COALESCE(updated_at, created_at) < DATE_SUB(NOW(), INTERVAL 180 DAY)') as $cv) {
        storage_delete($cv['photo_path']);
        db_exec('UPDATE customer_cvs SET photo_path = NULL WHERE id = ?', [(int) $cv['id']]);
    }
    db_exec('DELETE FROM auth_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)');
    db_exec('DELETE FROM customer_sessions WHERE expires_at < NOW() AND id NOT IN (SELECT session_id FROM orders WHERE session_id IS NOT NULL)');
    db_exec('DELETE FROM rate_limits WHERE window_start < ?', [time() - 86400]);
    return ['orders' => $deletedOrders, 'temp' => $deletedTemp];
}
