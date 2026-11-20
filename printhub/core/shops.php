<?php
/** Shops: creation (by the Super Admin only), profile, services, availability. */

function shop_code_generate(): string
{
    $alpha = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    do {
        $code = 'PH-';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alpha[random_int(0, strlen($alpha) - 1)];
        }
    } while (db_val('SELECT 1 FROM shops WHERE shop_code = ?', [$code]));
    return $code;
}

function shop_by_code(string $code): ?array
{
    $code = strtoupper(trim($code));
    if (!preg_match('/^PH-[A-Z0-9]{6}$/', $code)) {
        return null;
    }
    return db_row('SELECT * FROM shops WHERE shop_code = ? AND deleted_at IS NULL', [$code]);
}

function shop_by_id(int $id): ?array
{
    return db_row('SELECT * FROM shops WHERE id = ? AND deleted_at IS NULL', [$id]);
}

function shop_settings(int $shopId): array
{
    $s = db_row('SELECT * FROM shop_settings WHERE shop_id = ?', [$shopId]);
    if (!$s) {
        db_exec('INSERT IGNORE INTO shop_settings (shop_id, retention_days) VALUES (?, ?)', [$shopId, (int) setting('default_retention_days', 7)]);
        $s = db_row('SELECT * FROM shop_settings WHERE shop_id = ?', [$shopId]);
    }
    return $s;
}

function shop_qr_settings(int $shopId): array
{
    db_exec('INSERT IGNORE INTO shop_qr_settings (shop_id) VALUES (?)', [$shopId]);
    return db_row('SELECT * FROM shop_qr_settings WHERE shop_id = ?', [$shopId]);
}

/** Wi-Fi settings; the password is decrypted only when $withPassword is true. */
function shop_wifi(int $shopId, bool $withPassword = false): array
{
    db_exec('INSERT IGNORE INTO shop_wifi_settings (shop_id) VALUES (?)', [$shopId]);
    $w = db_row('SELECT * FROM shop_wifi_settings WHERE shop_id = ?', [$shopId]);
    $w['password'] = $withPassword ? decrypt_secret($w['password_enc']) : null;
    unset($w['password_enc']);
    return $w;
}

/** Wi-Fi details customers may see: only when the owner enabled public sharing. */
function shop_public_wifi(int $shopId): ?array
{
    $w = shop_wifi($shopId, true);
    if (!(int) $w['is_public'] || !$w['ssid']) {
        return null;
    }
    return ['ssid' => $w['ssid'], 'password' => $w['security'] === 'nopass' ? '' : $w['password'], 'security' => $w['security'],
        'hidden' => (bool) $w['is_hidden_network'], 'qr_payload' => wifi_qr_payload($w['ssid'], (string) $w['password'], $w['security'], (bool) $w['is_hidden_network'])];
}

/** Standard Wi-Fi QR format understood by Android and iOS cameras. */
function wifi_qr_payload(string $ssid, string $password, string $security, bool $hidden): string
{
    $esc = fn($s) => preg_replace('/([\\\\;,:"])/', '\\\\$1', $s);
    return 'WIFI:T:' . ($security === 'nopass' ? 'nopass' : $security) . ';S:' . $esc($ssid) . ';' . ($security === 'nopass' ? '' : 'P:' . $esc($password) . ';') . ($hidden ? 'H:true;' : '') . ';';
}

/**
 * Creates a shop with its owner login, settings and first subscription period.
 * $d: name, owner_name, phone, email, address, city, area, latitude, longitude, username, password, months, price (optional custom)
 */
function shop_create(array $d, int $adminId): array
{
    return db_tx(function () use ($d, $adminId) {
        $code = shop_code_generate();
        $shopId = db_insert('shops', [
            'shop_code' => $code, 'name' => $d['name'], 'owner_name' => $d['owner_name'], 'phone' => $d['phone'], 'whatsapp' => $d['whatsapp'] ?? null,
            'email' => $d['email'] ?: null, 'address' => $d['address'], 'area' => $d['area'] ?? null, 'city' => $d['city'],
            'latitude' => $d['latitude'] ?? null, 'longitude' => $d['longitude'] ?? null, 'logo_path' => $d['logo_path'] ?? null,
            'subscription_price' => $d['price'] ?? null, 'created_by' => $adminId,
        ]);
        db_insert('shop_users', ['shop_id' => $shopId, 'name' => $d['owner_name'], 'username' => $d['username'], 'email' => $d['email'] ?: null,
            'phone' => $d['phone'], 'password_hash' => password_hash($d['password'], PASSWORD_DEFAULT), 'role' => 'owner', 'must_change_password' => 1]);
        db_insert('shop_settings', ['shop_id' => $shopId, 'retention_days' => (int) setting('default_retention_days', 7), 'max_upload_mb' => (int) setting('max_upload_mb', 25)]);
        db_insert('shop_wifi_settings', ['shop_id' => $shopId]);
        db_insert('shop_qr_settings', ['shop_id' => $shopId, 'subheading' => 'Upload your files and collect your prints']);
        foreach (db_all('SELECT id FROM categories WHERE is_active = 1 AND deleted_at IS NULL') as $i => $c) {
            db_insert('shop_services', ['shop_id' => $shopId, 'category_id' => (int) $c['id'], 'is_enabled' => 1, 'sort_order' => $i]);
        }
        $months = max(0, (int) ($d['months'] ?? 1));
        if ($months > 0) {
            subscription_add($shopId, $months, date('Y-m-d'), $adminId, 'Initial subscription');
        }
        activity('admin', $adminId, 'shop_created', $shopId, 'shop', $shopId, ['code' => $code]);
        return shop_by_id($shopId);
    });
}

/** Validates the admin's shop form. Returns [data, errors]. */
function shop_validate(array $in, ?array $existing = null): array
{
    $e = [];
    $d = [
        'name' => mb_substr(trim((string) ($in['name'] ?? '')), 0, 150), 'owner_name' => mb_substr(trim((string) ($in['owner_name'] ?? '')), 0, 120),
        'phone' => clean_phone((string) ($in['phone'] ?? '')), 'whatsapp' => nullable(clean_phone((string) ($in['whatsapp'] ?? ''))),
        'email' => mb_substr(trim((string) ($in['email'] ?? '')), 0, 190), 'address' => mb_substr(trim((string) ($in['address'] ?? '')), 0, 255),
        'area' => nullable(mb_substr(trim((string) ($in['area'] ?? '')), 0, 120)), 'city' => mb_substr(trim((string) ($in['city'] ?? '')), 0, 100),
        'latitude' => is_numeric($in['latitude'] ?? null) ? max(-90, min(90, (float) $in['latitude'])) : null,
        'longitude' => is_numeric($in['longitude'] ?? null) ? max(-180, min(180, (float) $in['longitude'])) : null,
    ];
    if (mb_strlen($d['name']) < 2) $e[] = 'Enter the shop name.';
    if (mb_strlen($d['owner_name']) < 2) $e[] = 'Enter the owner name.';
    if (!valid_phone($d['phone'])) $e[] = 'Enter a valid contact number.';
    if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $e[] = 'Enter a valid email address.';
    if (mb_strlen($d['address']) < 5) $e[] = 'Enter the complete shop address.';
    if ($d['city'] === '') $e[] = 'Enter the city.';
    if (!$existing) {
        $d['username'] = strtolower(trim((string) ($in['username'] ?? '')));
        $d['password'] = (string) ($in['password'] ?? '');
        if (!preg_match('/^[a-z0-9._-]{4,40}$/', $d['username'])) $e[] = 'Username: 4–40 characters, letters, numbers, dot, dash or underscore.';
        elseif (db_val('SELECT 1 FROM shop_users WHERE username = ?', [$d['username']])) $e[] = 'That username is already taken.';
        if ($err = password_ok($d['password'])) $e[] = $err;
        $d['months'] = max(0, min(24, (int) ($in['months'] ?? 1)));
    }
    $price = trim((string) ($in['subscription_price'] ?? ''));
    $d['price'] = $price === '' ? null : max(0, round((float) $price, 2));
    return [$d, $e];
}

/** Can customers order right now? Returns ['open' => bool, 'reason' => string|null, 'label' => string]. */
function shop_availability(array $shop): array
{
    $sub = subscription_state((int) $shop['id'], $shop);
    if ($shop['status'] !== 'active') {
        return ['open' => false, 'reason' => 'This shop is not taking online orders at the moment.', 'label' => 'Unavailable'];
    }
    if (!in_array($sub['state'], ['active', 'grace'], true)) {
        return ['open' => false, 'reason' => 'This shop is not taking online orders at the moment.', 'label' => 'Unavailable'];
    }
    if (!(int) $shop['accepting_orders']) {
        return ['open' => false, 'reason' => 'The shop has paused online orders for now. Please visit the counter.', 'label' => 'Closed'];
    }
    return ['open' => true, 'reason' => null, 'label' => 'Open'];
}

/** Enabled categories (with subcategories and the options this shop offers) for the customer flow. */
function shop_catalog(int $shopId): array
{
    $cats = db_all("SELECT c.* FROM categories c JOIN shop_services s ON s.category_id = c.id AND s.shop_id = ? AND s.subcategory_id IS NULL AND s.is_enabled = 1
        WHERE c.is_active = 1 AND c.deleted_at IS NULL ORDER BY s.sort_order, c.sort_order", [$shopId]);
    if (!$cats) {
        return [];
    }
    $ids = array_column($cats, 'id');
    $disabledSubs = array_column(db_all('SELECT subcategory_id FROM shop_services WHERE shop_id = ? AND subcategory_id IS NOT NULL AND is_enabled = 0', [$shopId]), 'subcategory_id');
    $subs = [];
    foreach (db_all('SELECT * FROM subcategories WHERE is_active = 1 AND deleted_at IS NULL AND category_id IN (' . db_in($ids) . ') ORDER BY sort_order, id', $ids) as $s) {
        if (!in_array($s['id'], $disabledSubs)) {
            $subs[(int) $s['category_id']][] = ['id' => (int) $s['id'], 'code' => $s['code'], 'name' => $s['name'], 'name_ur' => $s['name_ur'],
                'pricing_basis' => $s['pricing_basis']];
        }
    }
    $disabledOpts = array_column(db_all('SELECT option_id FROM shop_option_settings WHERE shop_id = ? AND is_enabled = 0', [$shopId]), 'option_id');
    $opts = [];
    foreach (db_all('SELECT co.category_id, co.is_default, o.* FROM category_options co JOIN printing_options o ON o.id = co.option_id
        WHERE o.is_active = 1 AND co.category_id IN (' . db_in($ids) . ') ORDER BY o.option_group, o.sort_order', $ids) as $o) {
        if (in_array($o['id'], $disabledOpts)) {
            continue;
        }
        $opts[(int) $o['category_id']][$o['option_group']][] = ['id' => (int) $o['id'], 'code' => $o['code'], 'label' => $o['label'],
            'width_mm' => $o['width_mm'] !== null ? (float) $o['width_mm'] : null, 'height_mm' => $o['height_mm'] !== null ? (float) $o['height_mm'] : null,
            'is_default' => (bool) $o['is_default']];
    }
    $out = [];
    foreach ($cats as $c) {
        $out[] = ['id' => (int) $c['id'], 'code' => $c['code'], 'name' => $c['name'], 'name_ur' => $c['name_ur'], 'description' => $c['description'],
            'icon' => $c['icon'], 'icon_url' => $c['icon_path'] ? url('media/' . $c['icon_path']) : null, 'pricing_basis' => $c['pricing_basis'],
            'accepts_upload' => (bool) $c['accepts_upload'], 'subcategories' => $subs[(int) $c['id']] ?? [], 'options' => $opts[(int) $c['id']] ?? (object) []];
    }
    return $out;
}

/** Public shop profile for the landing page and the app. */
function shop_public(array $shop): array
{
    $s = shop_settings((int) $shop['id']);
    $avail = shop_availability($shop);
    return [
        'shop_code' => $shop['shop_code'], 'name' => $shop['name'], 'address' => $shop['address'], 'area' => $shop['area'], 'city' => $shop['city'],
        'phone' => $shop['phone'], 'whatsapp' => $shop['whatsapp'], 'email' => $shop['email'],
        'latitude' => $shop['latitude'] !== null ? (float) $shop['latitude'] : null, 'longitude' => $shop['longitude'] !== null ? (float) $shop['longitude'] : null,
        'logo_url' => $shop['logo_path'] ? url('media/' . $shop['logo_path']) : null, 'url' => shop_url($shop),
        'is_open' => $avail['open'], 'status_label' => $avail['label'], 'status_message' => $avail['reason'],
        'estimated_minutes' => (int) $s['estimated_minutes'], 'opening_hours' => $s['opening_hours'],
        'heading' => $s['landing_heading'] ?: 'Print anything in minutes', 'message' => $s['landing_message'], 'theme_color' => $s['theme_color'],
        'pricing_mode' => $s['pricing_mode'], 'payment_mode' => $s['payment_mode'],
        'payment_instructions' => $s['payment_mode'] !== 'pay_at_shop' ? $s['payment_instructions'] : null,
        'min_order_charge' => (float) $s['min_order_charge'], 'max_upload_mb' => min((int) $s['max_upload_mb'], (int) setting('max_upload_mb', 25)),
        'retention_days' => (int) $s['retention_days'], 'wifi' => shop_public_wifi((int) $shop['id']),
    ];
}
