<?php
/** Shop owner APIs. Every query is scoped to the shop of the authenticated token. */

function owner_settings_payload(int $shopId): array
{
    $s = shop_settings($shopId);
    return ['pricing_mode' => $s['pricing_mode'], 'payment_mode' => $s['payment_mode'], 'payment_instructions' => $s['payment_instructions'],
        'min_order_charge' => (float) $s['min_order_charge'], 'estimated_minutes' => (int) $s['estimated_minutes'], 'opening_hours' => $s['opening_hours'],
        'landing_heading' => $s['landing_heading'], 'landing_message' => $s['landing_message'], 'theme_color' => $s['theme_color'],
        'retention_days' => (int) $s['retention_days'], 'max_retention_days' => (int) setting('max_retention_days', 30),
        'max_upload_mb' => (int) $s['max_upload_mb'], 'platform_max_upload_mb' => (int) setting('max_upload_mb', 25)];
}

/** Applies owner shop-info updates (shared by the API and the owner web dashboard). Returns error or null. */
function owner_update_shop(array $shop, array $in): ?string
{
    $d = [
        'phone' => clean_phone((string) ($in['phone'] ?? $shop['phone'])), 'whatsapp' => nullable(clean_phone((string) ($in['whatsapp'] ?? $shop['whatsapp'] ?? ''))),
        'email' => nullable(mb_substr(trim((string) ($in['email'] ?? $shop['email'] ?? '')), 0, 190)), 'address' => mb_substr(trim((string) ($in['address'] ?? $shop['address'])), 0, 255),
        'area' => nullable(mb_substr(trim((string) ($in['area'] ?? $shop['area'] ?? '')), 0, 120)), 'city' => mb_substr(trim((string) ($in['city'] ?? $shop['city'])), 0, 100),
        'latitude' => is_numeric($in['latitude'] ?? null) ? max(-90, min(90, (float) $in['latitude'])) : (array_key_exists('latitude', $in) ? null : $shop['latitude']),
        'longitude' => is_numeric($in['longitude'] ?? null) ? max(-180, min(180, (float) $in['longitude'])) : (array_key_exists('longitude', $in) ? null : $shop['longitude']),
    ];
    if (array_key_exists('accepting_orders', $in)) {
        $d['accepting_orders'] = in_array($in['accepting_orders'], [1, '1', true, 'true', 'on'], true) ? 1 : 0;
    }
    if (isset($in['name']) && mb_strlen(trim((string) $in['name'])) >= 2) {
        $d['name'] = mb_substr(trim((string) $in['name']), 0, 150);
    }
    if (!valid_phone($d['phone'])) return 'Enter a valid contact number.';
    if ($d['email'] && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) return 'Enter a valid email address.';
    if (mb_strlen($d['address']) < 5) return 'Enter the complete address.';
    if ($d['city'] === '') return 'Enter the city.';
    db_update('shops', $d, 'id = ?', [(int) $shop['id']]);
    return null;
}

function owner_update_settings(int $shopId, array $in): ?string
{
    $cur = shop_settings($shopId);
    $d = [
        'pricing_mode' => in_array($in['pricing_mode'] ?? '', ['auto', 'quote'], true) ? $in['pricing_mode'] : $cur['pricing_mode'],
        'payment_mode' => in_array($in['payment_mode'] ?? '', ['pay_at_shop', 'advance_required', 'advance_optional'], true) ? $in['payment_mode'] : $cur['payment_mode'],
        'payment_instructions' => array_key_exists('payment_instructions', $in) ? nullable(mb_substr(trim((string) $in['payment_instructions']), 0, 1000)) : $cur['payment_instructions'],
        'min_order_charge' => array_key_exists('min_order_charge', $in) ? max(0, min(100000, round((float) $in['min_order_charge'], 2))) : $cur['min_order_charge'],
        'estimated_minutes' => array_key_exists('estimated_minutes', $in) ? max(1, min(10080, (int) $in['estimated_minutes'])) : $cur['estimated_minutes'],
        'opening_hours' => array_key_exists('opening_hours', $in) ? nullable(mb_substr(trim((string) $in['opening_hours']), 0, 255)) : $cur['opening_hours'],
        'landing_heading' => array_key_exists('landing_heading', $in) ? nullable(mb_substr(trim((string) $in['landing_heading']), 0, 120)) : $cur['landing_heading'],
        'landing_message' => array_key_exists('landing_message', $in) ? nullable(mb_substr(trim((string) $in['landing_message']), 0, 500)) : $cur['landing_message'],
        'theme_color' => valid_color($in['theme_color'] ?? null, $cur['theme_color']),
        'retention_days' => array_key_exists('retention_days', $in) ? max(1, min((int) setting('max_retention_days', 30), (int) $in['retention_days'])) : $cur['retention_days'],
        'max_upload_mb' => array_key_exists('max_upload_mb', $in) ? max(1, min((int) setting('max_upload_mb', 25), (int) $in['max_upload_mb'])) : $cur['max_upload_mb'],
    ];
    if ($d['payment_mode'] !== 'pay_at_shop' && !$d['payment_instructions']) {
        return 'Add payment instructions (e.g. your JazzCash / Easypaisa number or bank account) for advance payments.';
    }
    db_update('shop_settings', $d, 'shop_id = ?', [$shopId]);
    return null;
}

function owner_update_wifi(int $shopId, array $in): ?string
{
    $cur = shop_wifi($shopId, true);
    $ssid = array_key_exists('ssid', $in) ? mb_substr(trim((string) $in['ssid']), 0, 32) : (string) $cur['ssid'];
    $security = in_array($in['security'] ?? '', ['WPA', 'WEP', 'nopass'], true) ? $in['security'] : $cur['security'];
    $password = array_key_exists('password', $in) ? (string) $in['password'] : (string) $cur['password'];
    $public = array_key_exists('is_public', $in) ? (in_array($in['is_public'], [1, '1', true, 'true', 'on'], true) ? 1 : 0) : (int) $cur['is_public'];
    if ($public && $ssid === '') return 'Enter the Wi-Fi name before sharing it with customers.';
    if ($security === 'WPA' && $password !== '' && (strlen($password) < 8 || strlen($password) > 63)) return 'WPA passwords are 8–63 characters.';
    if ($security !== 'nopass' && $public && $password === '') return 'Enter the Wi-Fi password, or choose “Open network”.';
    db_update('shop_wifi_settings', ['ssid' => $ssid ?: null, 'password_enc' => $security === 'nopass' ? null : encrypt_secret($password), 'security' => $security,
        'is_hidden_network' => in_array($in['is_hidden_network'] ?? (int) $cur['is_hidden_network'], [1, '1', true, 'true', 'on'], true) ? 1 : 0, 'is_public' => $public], 'shop_id = ?', [$shopId]);
    return null;
}

function owner_update_qr(int $shopId, array $in): void
{
    $cur = shop_qr_settings($shopId);
    db_update('shop_qr_settings', [
        'fg_color' => valid_color($in['fg_color'] ?? null, $cur['fg_color']), 'bg_color' => valid_color($in['bg_color'] ?? null, $cur['bg_color']),
        'accent_color' => valid_color($in['accent_color'] ?? null, $cur['accent_color']),
        'show_logo' => array_key_exists('show_logo', $in) ? (in_array($in['show_logo'], [1, '1', true, 'true', 'on'], true) ? 1 : 0) : (int) $cur['show_logo'],
        'heading' => array_key_exists('heading', $in) ? (mb_substr(trim((string) $in['heading']), 0, 80) ?: 'Scan to print') : $cur['heading'],
        'subheading' => array_key_exists('subheading', $in) ? nullable(mb_substr(trim((string) $in['subheading']), 0, 160)) : $cur['subheading'],
    ], 'shop_id = ?', [$shopId]);
}

/** Services overview for the owner: every global category with this shop's switches and option availability. */
function owner_services(int $shopId): array
{
    $svc = [];
    foreach (db_all('SELECT * FROM shop_services WHERE shop_id = ?', [$shopId]) as $s) {
        $svc[(int) $s['category_id']][(int) ($s['subcategory_id'] ?? 0)] = $s;
    }
    $disabledOpts = array_map('intval', array_column(db_all('SELECT option_id FROM shop_option_settings WHERE shop_id = ? AND is_enabled = 0', [$shopId]), 'option_id'));
    $cats = [];
    foreach (db_all('SELECT * FROM categories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY sort_order, id') as $c) {
        $cid = (int) $c['id'];
        $subs = array_map(fn($s) => ['id' => (int) $s['id'], 'name' => $s['name'], 'enabled' => !isset($svc[$cid][(int) $s['id']]) || (int) $svc[$cid][(int) $s['id']]['is_enabled'] === 1],
            db_all('SELECT id, name FROM subcategories WHERE category_id = ? AND is_active = 1 AND deleted_at IS NULL ORDER BY sort_order, id', [$cid]));
        $cats[] = ['id' => $cid, 'code' => $c['code'], 'name' => $c['name'], 'icon' => $c['icon'], 'description' => $c['description'],
            'enabled' => isset($svc[$cid][0]) && (int) $svc[$cid][0]['is_enabled'] === 1, 'note' => $svc[$cid][0]['note'] ?? null, 'subcategories' => $subs];
    }
    $opts = [];
    foreach (db_all("SELECT * FROM printing_options WHERE is_active = 1 AND option_group IN ('paper_size','paper_type','gsm','finishing') ORDER BY option_group, sort_order") as $o) {
        $opts[$o['option_group']][] = ['id' => (int) $o['id'], 'label' => $o['label'], 'enabled' => !in_array((int) $o['id'], $disabledOpts, true)];
    }
    return ['categories' => $cats, 'options' => $opts];
}

/** $in: categories {id: bool}, subcategories {id: bool}, notes {id: text}, options {id: bool} */
function owner_save_services(int $shopId, array $in): void
{
    foreach ((array) ($in['categories'] ?? []) as $cid => $on) {
        $cid = (int) $cid;
        if (!db_val('SELECT 1 FROM categories WHERE id = ? AND deleted_at IS NULL', [$cid])) continue;
        $note = isset($in['notes'][$cid]) ? nullable(mb_substr(trim((string) $in['notes'][$cid]), 0, 160)) : null;
        db_exec('INSERT INTO shop_services (shop_id, category_id, subcategory_id, is_enabled, note) VALUES (?, ?, NULL, ?, ?)
            ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), note = COALESCE(VALUES(note), note)', [$shopId, $cid, $on ? 1 : 0, $note]);
        // the unique key treats NULL subcategory as distinct, so keep exactly one category row
        $rows = db_all('SELECT id FROM shop_services WHERE shop_id = ? AND category_id = ? AND subcategory_id IS NULL ORDER BY id', [$shopId, $cid]);
        if (count($rows) > 1) {
            $keep = (int) array_pop($rows)['id'];
            db_exec('DELETE FROM shop_services WHERE shop_id = ? AND category_id = ? AND subcategory_id IS NULL AND id <> ?', [$shopId, $cid, $keep]);
        }
    }
    foreach ((array) ($in['subcategories'] ?? []) as $sid => $on) {
        $sub = db_row('SELECT id, category_id FROM subcategories WHERE id = ?', [(int) $sid]);
        if ($sub) {
            db_exec('INSERT INTO shop_services (shop_id, category_id, subcategory_id, is_enabled) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)',
                [$shopId, (int) $sub['category_id'], (int) $sub['id'], $on ? 1 : 0]);
        }
    }
    foreach ((array) ($in['options'] ?? []) as $oid => $on) {
        if (db_val('SELECT 1 FROM printing_options WHERE id = ?', [(int) $oid])) {
            db_exec('INSERT INTO shop_option_settings (shop_id, option_id, is_enabled) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)', [$shopId, (int) $oid, $on ? 1 : 0]);
        }
    }
}

function owner_pricing_list(int $shopId): array
{
    return array_map(fn($r) => ['id' => (int) $r['id'], 'rule_type' => $r['rule_type'], 'category_id' => $r['category_id'] !== null ? (int) $r['category_id'] : null,
        'paper_size_id' => $r['paper_size_id'] !== null ? (int) $r['paper_size_id'] : null, 'color' => $r['color'], 'sides' => $r['sides'],
        'paper_type_id' => $r['paper_type_id'] !== null ? (int) $r['paper_type_id'] : null, 'gsm_id' => $r['gsm_id'] !== null ? (int) $r['gsm_id'] : null,
        'finishing_id' => $r['finishing_id'] !== null ? (int) $r['finishing_id'] : null, 'unit' => $r['unit'], 'price' => (float) $r['price'], 'min_qty' => (int) $r['min_qty'],
        'is_active' => (bool) $r['is_active'], 'applies_to' => pricing_rule_text($r)],
        db_all("SELECT * FROM shop_pricing WHERE shop_id = ? ORDER BY FIELD(rule_type, 'print','item','paper','finishing','discount'), category_id IS NULL DESC, id", [$shopId]));
}

function owner_order_list(int $shopId, string $status, string $q, int $page, int $per = 20): array
{
    $where = ['o.shop_id = ?'];
    $params = [$shopId];
    $groups = ['new' => ['submitted', 'awaiting_approval'], 'active' => ['accepted', 'quoted', 'awaiting_payment', 'payment_confirmed', 'printing', 'ready'],
        'printing' => ['printing'], 'ready' => ['ready'], 'completed' => ['completed'], 'closed' => ['rejected', 'cancelled']];
    if (isset($groups[$status])) {
        $where[] = 'o.status IN (' . db_in($groups[$status]) . ')';
        $params = array_merge($params, $groups[$status]);
    } elseif (isset(order_statuses()[$status])) {
        $where[] = 'o.status = ?';
        $params[] = $status;
    }
    if ($status === 'today') {
        $where[] = 'DATE(o.created_at) = CURDATE()';
    }
    if ($q !== '') {
        $where[] = '(o.order_number LIKE ? OR o.customer_name LIKE ? OR o.customer_phone LIKE ?)';
        array_push($params, "%$q%", "%$q%", "%$q%");
    }
    $w = implode(' AND ', $where);
    $total = (int) db_val("SELECT COUNT(*) FROM orders o WHERE $w", $params);
    $rows = db_all("SELECT o.*, (SELECT COUNT(*) FROM order_items i WHERE i.order_id = o.id) items, (SELECT GROUP_CONCAT(DISTINCT c.name SEPARATOR ', ') FROM order_items i LEFT JOIN categories c ON c.id = i.category_id WHERE i.order_id = o.id) categories
        FROM orders o WHERE $w ORDER BY o.status IN ('submitted','awaiting_approval') DESC, o.id DESC LIMIT " . (int) $per . ' OFFSET ' . (int) (($page - 1) * $per), $params);
    return ['total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $per)), 'items' => array_map(fn($o) => [
        'id' => (int) $o['id'], 'order_number' => $o['order_number'], 'status' => $o['status'], 'status_label' => order_statuses()[$o['status']] ?? $o['status'],
        'customer_name' => $o['customer_name'], 'customer_phone' => $o['customer_phone'], 'items' => (int) $o['items'], 'categories' => $o['categories'],
        'amount' => order_amount($o), 'pricing_mode' => $o['pricing_mode'], 'payment_status' => $o['payment_status'], 'source' => $o['source'], 'created_at' => $o['created_at']], $rows)];
}

function api_routes_owner(): array
{
    return [
        ['GET', '/owner/dashboard', function () {
            $o = api_owner();
            $sid = (int) $o['shop']['id'];
            $st = subscription_state($sid, $o['shop']);
            api_ok(['counts' => shop_dashboard($sid), 'subscription' => ['state' => $st['state'], 'label' => $st['label'], 'end_date' => $st['end_date'], 'days_left' => $st['days_left'], 'price' => $st['price']],
                'availability' => shop_availability($o['shop']), 'recent' => owner_order_list($sid, 'active', '', 1, 8)['items'], 'new' => owner_order_list($sid, 'new', '', 1, 20)['items'],
                'unread_notifications' => notifications_for('shop', $sid, 1)['unread']]);
        }],
        ['GET', '/owner/shop', function () {
            $o = api_owner();
            api_ok(['shop' => owner_shop_payload($o['shop']), 'settings' => owner_settings_payload((int) $o['shop']['id'])]);
        }],
        ['POST', '/owner/shop', function () {
            $o = api_owner_write();
            $err = owner_update_shop($o['shop'], request_data());
            if ($err) api_fail($err);
            activity('owner', (int) $o['user']['id'], 'shop_info_updated', (int) $o['shop']['id']);
            api_ok(['shop' => owner_shop_payload(shop_by_id((int) $o['shop']['id']))], 'Shop details saved');
        }],
        ['POST', '/owner/shop/logo', function () {
            $o = api_owner_write();
            try {
                $path = media_store_image($_FILES['logo'] ?? [], 'logos', 600);
            } catch (RuntimeException $e) {
                api_fail($e->getMessage());
            }
            storage_delete($o['shop']['logo_path']);
            db_update('shops', ['logo_path' => $path], 'id = ?', [(int) $o['shop']['id']]);
            api_ok(['logo_url' => url('media/' . $path)], 'Logo updated');
        }],
        ['POST', '/owner/settings', function () {
            $o = api_owner_write();
            $err = owner_update_settings((int) $o['shop']['id'], request_data());
            if ($err) api_fail($err);
            activity('owner', (int) $o['user']['id'], 'settings_updated', (int) $o['shop']['id']);
            api_ok(['settings' => owner_settings_payload((int) $o['shop']['id'])], 'Settings saved');
        }],
        ['GET', '/owner/subscription', function () {
            $o = api_owner();
            $sid = (int) $o['shop']['id'];
            $st = subscription_state($sid, $o['shop']);
            api_ok(['state' => $st['state'], 'label' => $st['label'], 'price' => $st['price'], 'end_date' => $st['end_date'], 'grace_until' => $st['grace_until'], 'days_left' => $st['days_left'],
                'support_phone' => setting('support_phone'), 'support_email' => setting('support_email'),
                'periods' => db_all('SELECT start_date, end_date, months, price, status FROM subscriptions WHERE shop_id = ? ORDER BY end_date DESC LIMIT 24', [$sid]),
                'payments' => db_all("SELECT amount, method, reference, paid_on, months_covered, status FROM subscription_payments WHERE shop_id = ? ORDER BY paid_on DESC LIMIT 24", [$sid])]);
        }],
        ['GET', '/owner/wifi', function () {
            $o = api_owner();
            $w = shop_wifi((int) $o['shop']['id'], true);
            api_ok(['ssid' => $w['ssid'], 'password' => $w['password'], 'security' => $w['security'], 'is_hidden_network' => (bool) $w['is_hidden_network'], 'is_public' => (bool) $w['is_public'],
                'qr_payload' => $w['ssid'] ? wifi_qr_payload($w['ssid'], (string) $w['password'], $w['security'], (bool) $w['is_hidden_network']) : null]);
        }],
        ['POST', '/owner/wifi', function () {
            $o = api_owner_write();
            $err = owner_update_wifi((int) $o['shop']['id'], request_data());
            if ($err) api_fail($err);
            activity('owner', (int) $o['user']['id'], 'wifi_updated', (int) $o['shop']['id']);
            api_ok(null, 'Wi-Fi details saved');
        }],
        ['GET', '/owner/wifi/qr\.png', function () {
            $o = api_owner();
            $png = shop_wifi_qr_png($o['shop']);
            if (!$png) api_fail('Add your Wi-Fi name first.', 404);
            header('Content-Type: image/png');
            header('Content-Disposition: inline; filename="wifi-qr.png"');
            echo $png;
            exit;
        }],
        ['GET', '/owner/qr', function () {
            $o = api_owner();
            $q = shop_qr_settings((int) $o['shop']['id']);
            api_ok(['url' => shop_url($o['shop']), 'shop_code' => $o['shop']['shop_code'], 'fg_color' => $q['fg_color'], 'bg_color' => $q['bg_color'], 'accent_color' => $q['accent_color'],
                'show_logo' => (bool) $q['show_logo'], 'heading' => $q['heading'], 'subheading' => $q['subheading'], 'version' => (int) $q['version'], 'has_logo' => (bool) $o['shop']['logo_path']]);
        }],
        ['POST', '/owner/qr', function () {
            $o = api_owner_write();
            owner_update_qr((int) $o['shop']['id'], request_data());
            api_ok(null, 'QR design saved');
        }],
        ['POST', '/owner/qr/regenerate', function () {
            $o = api_owner_write();
            db_exec("UPDATE shop_qr_settings SET version = version + 1, fg_color = '#0F172A', bg_color = '#FFFFFF', accent_color = '#0F766E' WHERE shop_id = ?", [(int) $o['shop']['id']]);
            activity('owner', (int) $o['user']['id'], 'qr_regenerated', (int) $o['shop']['id']);
            api_ok(['url' => shop_url($o['shop'])], 'QR code regenerated. Your shop link (' . $o['shop']['shop_code'] . ') stays the same, so printed codes keep working.');
        }],
        ['GET', '/owner/qr\.png', function () {
            $o = api_owner();
            header('Content-Type: image/png');
            header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . $o['shop']['shop_code'] . '-qr.png"');
            echo shop_qr_png($o['shop'], max(6, min(30, (int) ($_GET['scale'] ?? 16))));
            exit;
        }],
        ['GET', '/owner/qr\.pdf', function () {
            $o = api_owner();
            header('Content-Type: application/pdf');
            header('Content-Disposition: ' . (isset($_GET['download']) ? 'attachment' : 'inline') . '; filename="' . $o['shop']['shop_code'] . '-poster.pdf"');
            echo shop_qr_poster_pdf($o['shop'], !isset($_GET['no_wifi']));
            exit;
        }],
        ['GET', '/owner/services', function () {
            $o = api_owner();
            api_ok(owner_services((int) $o['shop']['id']));
        }],
        ['POST', '/owner/services', function () {
            $o = api_owner_write();
            owner_save_services((int) $o['shop']['id'], request_data());
            api_ok(owner_services((int) $o['shop']['id']), 'Services saved');
        }],
        ['GET', '/owner/pricing', function () {
            $o = api_owner();
            api_ok(['rules' => owner_pricing_list((int) $o['shop']['id']), 'units' => pricing_units(),
                'options' => db_all('SELECT id, option_group, code, label FROM printing_options WHERE is_active = 1 ORDER BY option_group, sort_order'),
                'categories' => db_all('SELECT id, name, pricing_basis FROM categories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY sort_order'),
                'min_order_charge' => (float) shop_settings((int) $o['shop']['id'])['min_order_charge']]);
        }],
        ['POST', '/owner/pricing', function () {
            $o = api_owner_write();
            $err = pricing_rule_save((int) $o['shop']['id'], request_data());
            if ($err) api_fail($err);
            api_ok(['rules' => owner_pricing_list((int) $o['shop']['id'])], 'Price added', 201);
        }],
        ['POST', '/owner/pricing/(\d+)', function ($id) {
            $o = api_owner_write();
            if (!db_val('SELECT 1 FROM shop_pricing WHERE id = ? AND shop_id = ?', [(int) $id, (int) $o['shop']['id']])) api_fail('Price not found.', 404);
            $err = pricing_rule_save((int) $o['shop']['id'], request_data(), (int) $id);
            if ($err) api_fail($err);
            api_ok(['rules' => owner_pricing_list((int) $o['shop']['id'])], 'Price updated');
        }],
        ['DELETE', '/owner/pricing/(\d+)', function ($id) {
            $o = api_owner_write();
            db_exec('DELETE FROM shop_pricing WHERE id = ? AND shop_id = ?', [(int) $id, (int) $o['shop']['id']]);
            api_ok(['rules' => owner_pricing_list((int) $o['shop']['id'])], 'Price removed');
        }],
        ['POST', '/owner/pricing/test', function () {
            $o = api_owner();
            $item = request_data();
            api_ok(price_order((int) $o['shop']['id'], [$item]));
        }],
        ['GET', '/owner/orders', function () {
            $o = api_owner();
            api_ok(owner_order_list((int) $o['shop']['id'], in_str('status', 30), in_str('q', 60), max(1, in_int('page', 1)), max(5, min(100, in_int('per_page', 20)))));
        }],
        ['GET', '/owner/orders/(\d+)', function ($id) {
            $o = api_owner();
            $order = db_row('SELECT * FROM orders WHERE id = ? AND shop_id = ?', [(int) $id, (int) $o['shop']['id']]);
            if (!$order) api_fail('Order not found.', 404);
            api_ok(order_detail($order, true));
        }],
        ['POST', '/owner/orders/(\d+)/action', function ($id) {
            $o = api_owner();
            $order = db_row('SELECT * FROM orders WHERE id = ? AND shop_id = ?', [(int) $id, (int) $o['shop']['id']]);
            if (!$order) api_fail('Order not found.', 404);
            $err = order_owner_action($order, in_str('action', 30), request_data(), $o);
            if ($err) api_fail($err, str_contains($err, 'subscription') ? 402 : 422);
            api_ok(order_detail(db_row('SELECT * FROM orders WHERE id = ?', [(int) $id]), true), 'Order updated');
        }],
        ['GET', '/owner/reports', function () {
            $o = api_owner();
            api_ok(shop_report((int) $o['shop']['id'], in_str('period', 10) ?: 'month'));
        }],
        ['GET', '/owner/notifications', function () {
            $o = api_owner();
            api_ok(notifications_for('shop', (int) $o['shop']['id'], max(1, min(100, in_int('limit', 30))), max(0, in_int('since', 0))));
        }],
        ['POST', '/owner/notifications/read', function () {
            $o = api_owner();
            notifications_mark_read('shop', (int) $o['shop']['id'], in_int('id', 0) ?: null);
            api_ok(null, 'Marked as read');
        }],
    ];
}
