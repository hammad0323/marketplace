<?php
/** Customer APIs: anonymous sessions, uploads, price quotes, print previews, orders, tracking, payments, notifications. */

function upload_payload(array $f): array
{
    $converted = (bool) $f['converted_path'];
    return ['token' => $f['token'], 'name' => $f['original_name'], 'kind' => $f['file_kind'], 'mime' => $f['mime'], 'size' => (int) $f['size_bytes'],
        'pages' => $f['page_count'] !== null ? (int) $f['page_count'] : null, 'width' => $f['width_px'] !== null ? (int) $f['width_px'] : null, 'height' => $f['height_px'] !== null ? (int) $f['height_px'] : null,
        'converted' => $converted, 'needs_page_count' => $f['file_kind'] === 'docx' && !$converted,
        'url' => signed_file_url('upload', (int) $f['id'], 3600), 'pdf_url' => $f['file_kind'] === 'pdf' ? signed_file_url('upload', (int) $f['id'], 3600) : ($converted ? signed_file_url('upload', (int) $f['id'], 3600) . '&pdf=1' : null),
        'expires_at' => $f['expires_at']];
}

function customer_order_summary(array $o): array
{
    $shop = db_row('SELECT name, shop_code FROM shops WHERE id = ?', [(int) $o['shop_id']]);
    return ['order_number' => $o['order_number'], 'status' => $o['status'], 'status_label' => order_statuses()[$o['status']] ?? $o['status'], 'amount' => order_amount($o),
        'pricing_mode' => $o['pricing_mode'], 'payment_status' => $o['payment_status'], 'created_at' => $o['created_at'], 'shop_name' => $shop['name'], 'shop_code' => $shop['shop_code'],
        'tracking_url' => url('track/' . $o['order_number'] . '?t=' . $o['tracking_token']), 'tracking_token' => $o['tracking_token']];
}

/** Collects the session's temp uploads for an item (for previews). */
function preview_sources(array $session, int $shopId, array $tokens): array
{
    $sources = [];
    foreach (array_slice($tokens, 0, 20) as $t) {
        $f = is_string($t) && preg_match('/^[a-f0-9]{32}$/', $t) ? db_row("SELECT * FROM file_uploads WHERE token = ? AND session_id = ? AND shop_id = ? AND status <> 'deleted'", [$t, (int) $session['id'], $shopId]) : null;
        if (!$f) {
            continue;
        }
        $path = $f['file_kind'] === 'docx' ? $f['converted_path'] : $f['storage_path'];
        if ($path && ($full = storage_file($path))) {
            $sources[] = ['path' => $full, 'kind' => $f['file_kind'] === 'image' ? 'image' : 'pdf', 'pages' => (int) $f['page_count']];
        }
    }
    return $sources;
}

function api_routes_customer(): array
{
    return [
        ['POST', '/customer/session', function () {
            $s = customer_session(true, ($_SERVER['HTTP_X_CLIENT'] ?? '') === 'app' ? 'app' : 'web');
            api_ok(['token' => $s['_new_token'] ?? null, 'is_new' => isset($s['_new_token']), 'expires_at' => $s['expires_at']], isset($s['_new_token']) ? 'Session created' : 'Session active');
        }],
        ['POST', '/shops/([A-Za-z0-9-]{6,20})/uploads', function ($code) {
            $shop = api_shop($code);
            $session = api_customer();
            api_rate('upload:' . $session['id'], 60, 3600);
            api_rate('upload-ip:' . client_ip(), 150, 3600);
            if (!shop_availability($shop)['open']) {
                api_fail(shop_availability($shop)['reason'], 409);
            }
            $max = min((int) shop_settings((int) $shop['id'])['max_upload_mb'], (int) setting('max_upload_mb', 25));
            try {
                [$mime, $ext, $kind] = upload_validate($_FILES['file'] ?? [], $max);
                $rel = upload_store($_FILES['file'], 'uploads', $ext);
            } catch (RuntimeException $e) {
                api_fail($e->getMessage());
            }
            $full = storage_file($rel);
            $pages = $w = $h = null;
            $converted = null;
            if ($kind === 'pdf') {
                $pages = pdf_page_count($full);
                if (!$pages) {
                    storage_delete($rel);
                    api_fail('This PDF could not be read. It may be password-protected or damaged.');
                }
            } elseif ($kind === 'image') {
                [$w, $h] = getimagesize($full);
                $pages = 1;
            } else {
                $converted = docx_convert($full);
                $pages = $converted ? pdf_page_count(storage_file($converted)) : null;
            }
            $name = mb_substr(preg_replace('/[^\pL\pN ._()\-]/u', '_', basename((string) $_FILES['file']['name'])), 0, 200) ?: 'document.' . $ext;
            $id = db_insert('file_uploads', ['token' => random_token(16), 'session_id' => (int) $session['id'], 'shop_id' => (int) $shop['id'], 'storage_path' => $rel,
                'original_name' => $name, 'mime' => $mime, 'file_kind' => $kind, 'size_bytes' => (int) $_FILES['file']['size'], 'page_count' => $pages, 'width_px' => $w, 'height_px' => $h,
                'converted_path' => $converted, 'expires_at' => date('Y-m-d H:i:s', time() + 3600 * max(1, (int) setting('temp_upload_hours', 24)))]);
            $f = db_row('SELECT * FROM file_uploads WHERE id = ?', [$id]);
            api_ok(upload_payload($f) + ($kind === 'docx' && !$converted ? ['notice' => 'Word files are printed by the shop from the original. Please enter the number of pages.'] : []), 'File uploaded', 201);
        }],
        ['DELETE', '/uploads/([a-f0-9]{32})', function ($token) {
            $session = api_customer();
            $f = db_row("SELECT * FROM file_uploads WHERE token = ? AND session_id = ? AND status = 'temp'", [$token, (int) $session['id']]);
            if ($f) {
                storage_delete($f['storage_path']);
                storage_delete($f['converted_path']);
                db_exec("UPDATE file_uploads SET status = 'deleted' WHERE id = ?", [(int) $f['id']]);
            }
            api_ok(null, 'File removed');
        }],
        ['POST', '/shops/([A-Za-z0-9-]{6,20})/price', function ($code) {
            $shop = api_shop($code);
            $session = api_customer();
            api_rate('price:' . $session['id'], 240, 300);
            $catalog = shop_catalog((int) $shop['id']);
            $items = [];
            foreach (array_slice((array) in('items', []), 0, 10) as $raw) {
                if (!is_array($raw)) continue;
                $raw['files'] = []; // pages come from the request for estimates
                [$item, , $errors] = order_item_validate((int) $shop['id'], $session, $raw + ['cv_token' => null], $catalog);
                if ($item) {
                    $item['pages'] = max(1, min(5000, (int) ($raw['pages'] ?? 1)));
                    $items[] = $item;
                }
            }
            if (!$items) api_fail('Choose a service to see the price.');
            $q = price_order((int) $shop['id'], $items);
            $s = shop_settings((int) $shop['id']);
            $q['pricing_mode'] = $s['pricing_mode'] === 'auto' && $q['priced'] ? 'auto' : 'quote';
            $q['message'] = $q['pricing_mode'] === 'quote' ? 'The shop will review your files and send you the exact price.' : 'Estimated price — the shop confirms it when accepting your order.';
            api_ok($q);
        }],
        ['POST', '/shops/([A-Za-z0-9-]{6,20})/preview', function ($code) {
            $shop = api_shop($code);
            $session = api_customer();
            api_rate('preview:' . $session['id'], 40, 600);
            $in = request_data();
            $sources = preview_sources($session, (int) $shop['id'], (array) ($in['files'] ?? []));
            if (!$sources) api_fail('Upload a PDF or image to preview the print layout. Word files are previewed after conversion only.');
            $item = ['paper_size_id' => (int) ($in['paper_size_id'] ?? 0) ?: null, 'custom_width_mm' => $in['custom_width_mm'] ?? null, 'custom_height_mm' => $in['custom_height_mm'] ?? null,
                'orientation' => ($in['orientation'] ?? '') === 'landscape' ? 'landscape' : 'portrait', 'layout' => layout_clean($in['layout'] ?? []),
                'page_selection' => count($sources) === 1 && $sources[0]['kind'] === 'pdf' ? pages_clean($in['page_selection'] ?? [], $sources[0]['pages']) : [], 'quantity' => max(1, min(200, (int) ($in['quantity'] ?? 1)))];
            try {
                $built = build_print_ready($item, $sources);
            } catch (Throwable $e) {
                api_fail($e instanceof RuntimeException ? $e->getMessage() : 'The preview could not be created. The shop will print your original file.');
            }
            $f = random_token(16);
            $dest = storage_root() . '/previews';
            if (!is_dir($dest)) @mkdir($dest, 0750, true);
            rename($built['path'], $dest . '/' . $f . '.pdf');
            api_ok(['url' => signed_file_url('preview-' . $f, 0, 1800), 'pages' => $built['pages']], 'Preview ready');
        }],
        ['POST', '/shops/([A-Za-z0-9-]{6,20})/orders', function ($code) {
            $shop = api_shop($code);
            $session = api_customer();
            api_rate('order:' . $session['id'], 15, 3600);
            api_rate('order-ip:' . client_ip(), 40, 3600);
            $in = request_data();
            $in['source'] = ($_SERVER['HTTP_X_CLIENT'] ?? '') === 'app' ? 'app' : 'web';
            $r = order_create($shop, $session, $in);
            if (isset($r['errors'])) api_fail($r['errors'][0], 422, $r['errors']);
            api_ok(order_detail($r['order'], false), 'Order submitted successfully', 201);
        }],
        ['GET', '/customer/orders', function () {
            $session = api_customer();
            $rows = db_all('SELECT * FROM orders WHERE session_id = ? ORDER BY id DESC LIMIT 50', [(int) $session['id']]);
            api_ok(['orders' => array_map('customer_order_summary', $rows)]);
        }],
        ['GET', '/orders/([A-Za-z0-9]{6,24})', function ($number) {
            $session = customer_session(false);
            api_rate('track:' . client_ip(), 60, 300);
            $o = order_for_customer($number, in_str('t', 60) ?: null, $session, in_str('phone', 30) ?: null);
            if (!$o) api_fail('Order not found. Check the order number and the mobile number used for the order.', 404);
            api_ok(order_detail($o, false));
        }],
        ['POST', '/orders/([A-Za-z0-9]{6,24})/action', function ($number) {
            $session = api_customer();
            $o = order_for_customer($number, in_str('t', 60) ?: null, $session);
            if (!$o) api_fail('Order not found.', 404);
            $err = order_customer_action($o, in_str('action', 30), $session);
            if ($err) api_fail($err);
            api_ok(order_detail(db_row('SELECT * FROM orders WHERE id = ?', [(int) $o['id']]), false), 'Order updated');
        }],
        ['POST', '/orders/([A-Za-z0-9]{6,24})/payment', function ($number) {
            $session = api_customer();
            api_rate('payment:' . $session['id'], 10, 3600);
            $o = order_for_customer($number, in_str('t', 60) ?: null, $session);
            if (!$o) api_fail('Order not found.', 404);
            try {
                $err = order_submit_payment($o, $session, in_str('method', 20), in_str('reference', 120) ?: null, $_FILES['proof'] ?? null);
            } catch (RuntimeException $e) {
                $err = $e->getMessage();
            }
            if ($err) api_fail($err);
            api_ok(order_detail(db_row('SELECT * FROM orders WHERE id = ?', [(int) $o['id']]), false), 'Thank you! The shop will confirm your payment.');
        }],
        ['GET', '/customer/notifications', function () {
            $session = api_customer();
            api_ok(notifications_for('customer', (int) $session['id'], max(1, min(50, in_int('limit', 20))), max(0, in_int('since', 0))));
        }],
        ['POST', '/customer/notifications/read', function () {
            $session = api_customer();
            notifications_mark_read('customer', (int) $session['id'], in_int('id', 0) ?: null);
            api_ok(null, 'Marked as read');
        }],
    ];
}
