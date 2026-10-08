<?php
/**
 * Shared helpers used by the storefront and the admin panel.
 */

/* ------------------------------------------------------------------
 *  Database
 * ------------------------------------------------------------------ */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $ex) {
            http_response_code(500);
            exit('<div style="font-family:sans-serif;max-width:620px;margin:80px auto;padding:24px;border:1px solid #c9a24a;border-radius:8px">'
                . '<h2 style="margin-top:0">Database connection failed</h2><p>Check the DB_* values in <code>config.php</code>.</p>'
                . (DEBUG ? '<p style="color:#a33">' . htmlspecialchars($ex->getMessage()) . '</p>' : '') . '</div>');
        }
    }
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function row(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function rows(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function val(string $sql, array $params = [])
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

/* ------------------------------------------------------------------
 *  Settings
 * ------------------------------------------------------------------ */
function settings_all(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (rows('SELECT skey, svalue FROM settings') as $r) {
            $cache[$r['skey']] = $r['svalue'];
        }
    }
    return $cache;
}

function setting(string $key, $default = '')
{
    $all = settings_all();
    return (isset($all[$key]) && $all[$key] !== null && $all[$key] !== '') ? $all[$key] : $default;
}

function save_setting(string $key, $value): void
{
    q('INSERT INTO settings (skey, svalue) VALUES (?, ?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)', [$key, (string)$value]);
}

function save_settings(array $pairs): void
{
    foreach ($pairs as $k => $v) {
        save_setting($k, $v);
    }
    settings_all(true);
}

/* ------------------------------------------------------------------
 *  URLs & output
 * ------------------------------------------------------------------ */
function base_path(): string
{
    static $base = null;
    if ($base === null) {
        $script = str_replace('\\', '/', realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '');
        $root   = str_replace('\\', '/', realpath(ROOT));
        $rel    = substr($script, strlen($root));                 // e.g. /admin/index.php
        $name   = $_SERVER['SCRIPT_NAME'] ?? '';
        $base   = ($rel !== '' && substr($name, -strlen($rel)) === $rel) ? substr($name, 0, -strlen($rel)) : '';
        $base   = rtrim($base, '/');
    }
    return $base;
}

function site_url(): string
{
    if (SITE_URL !== '') {
        return rtrim(SITE_URL, '/');
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_path();
}

/** Root-relative URL for internal links:  url('product/abc') → /product/abc */
function url(string $path = ''): string
{
    if (preg_match('~^(https?:)?//|^mailto:|^tel:|^#~i', $path)) {
        return $path;
    }
    return base_path() . '/' . ltrim($path, '/');
}

/** Absolute URL (sitemaps, canonical, OG tags, gateways). */
function abs_url(string $path = ''): string
{
    if (preg_match('~^https?://~i', $path)) {
        return $path;
    }
    return site_url() . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $file = ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : '1';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

/** URL of an uploaded or bundled image, with a fallback. */
function img(?string $file, string $fallback = 'assets/images/placeholder.svg'): string
{
    $file = trim((string)$file);
    if ($file === '') {
        return url($fallback);
    }
    if (preg_match('~^https?://~i', $file)) {
        return $file;
    }
    if (strpos($file, 'assets/') === 0 || strpos($file, 'uploads/') === 0) {
        return url($file);
    }
    return url('uploads/' . $file);
}

function e($s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function money($amount): string
{
    $amount = (float)$amount;
    return setting('currency', 'Rs.') . ' ' . number_format($amount, (floor($amount) == $amount) ? 0 : 2);
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('~[^a-z0-9]+~', '-', $text);
    $text = trim($text, '-');
    return $text !== '' ? $text : 'item-' . substr(md5(uniqid('', true)), 0, 6);
}

function unique_slug(string $table, string $slug, int $ignoreId = 0): string
{
    $base = slugify($slug);
    $slug = $base;
    $i = 2;
    while (val("SELECT COUNT(*) FROM `$table` WHERE slug = ? AND id <> ?", [$slug, $ignoreId]) > 0) {
        $slug = $base . '-' . $i++;
    }
    return $slug;
}

function redirect(string $path): void
{
    header('Location: ' . (preg_match('~^https?://~', $path) ? $path : url($path)));
    exit;
}

function back(string $fallback = ''): void
{
    $ref = $_SERVER['HTTP_REFERER'] ?? '';
    if ($ref !== '' && parse_url($ref, PHP_URL_HOST) === ($_SERVER['HTTP_HOST'] ?? '')) {
        header('Location: ' . $ref);
        exit;
    }
    redirect($fallback);
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function post(string $key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function get(string $key, $default = '')
{
    $v = $_GET[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function is_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
}

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function excerpt(?string $html, int $len = 160): string
{
    $t = trim(preg_replace('~\s+~', ' ', strip_tags((string)$html)));
    return mb_strlen($t) > $len ? rtrim(mb_substr($t, 0, $len - 1)) . '…' : $t;
}

function not_found(): void
{
    http_response_code(404);
    require ROOT . '/404.php';
    exit;
}

/* ------------------------------------------------------------------
 *  Flash messages & CSRF
 * ------------------------------------------------------------------ */
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_ok(): bool
{
    $t = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($t) && hash_equals(csrf_token(), $t);
}

function require_csrf(): void
{
    if (!csrf_ok()) {
        if (is_ajax()) {
            json_out(['ok' => false, 'message' => 'Your session expired. Please refresh the page.'], 419);
        }
        flash('error', 'Your session expired. Please try again.');
        back();
    }
}

/* ------------------------------------------------------------------
 *  Auth
 * ------------------------------------------------------------------ */
function customer(): ?array
{
    static $c = false;
    if ($c === false) {
        $c = !empty($_SESSION['customer_id'])
            ? row('SELECT * FROM customers WHERE id = ? AND status = 1', [$_SESSION['customer_id']])
            : null;
    }
    return $c;
}

function require_customer(): array
{
    $c = customer();
    if (!$c) {
        $_SESSION['after_login'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('info', 'Please log in to continue.');
        redirect('login');
    }
    return $c;
}

function admin(): ?array
{
    static $a = false;
    if ($a === false) {
        $a = !empty($_SESSION['admin_id'])
            ? row('SELECT * FROM admins WHERE id = ? AND status = 1', [$_SESSION['admin_id']])
            : null;
    }
    return $a;
}

function require_admin(): array
{
    $a = admin();
    if (!$a) {
        redirect('admin/login');
    }
    return $a;
}

/* ------------------------------------------------------------------
 *  Catalogue
 * ------------------------------------------------------------------ */
function price_now(array $p): float
{
    return ($p['sale_price'] !== null && $p['sale_price'] !== '' && (float)$p['sale_price'] > 0 && (float)$p['sale_price'] < (float)$p['price'])
        ? (float)$p['sale_price'] : (float)$p['price'];
}

function on_sale(array $p): bool
{
    return price_now($p) < (float)$p['price'];
}

function discount_pct(array $p): int
{
    return on_sale($p) ? (int)round(100 - price_now($p) / (float)$p['price'] * 100) : 0;
}

function product_images(int $pid): array
{
    return rows('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort_order, id', [$pid]);
}

/** Adds image + hover_image keys to a list of product rows in one query. */
function attach_images(array $products): array
{
    if (!$products) {
        return $products;
    }
    $ids = array_map('intval', array_column($products, 'id'));
    $map = [];
    foreach (rows('SELECT product_id, image FROM product_images WHERE product_id IN (' . implode(',', $ids) . ') ORDER BY sort_order, id') as $r) {
        $map[$r['product_id']][] = $r['image'];
    }
    foreach ($products as &$p) {
        $p['image'] = $map[$p['id']][0] ?? '';
        $p['hover_image'] = $map[$p['id']][1] ?? '';
    }
    return $products;
}

function product_list(string $where = '1', array $params = [], string $order = 'p.created_at DESC', int $limit = 12, int $offset = 0): array
{
    $sql = "SELECT p.*, c.name AS category_name, c.slug AS category_slug
            FROM products p LEFT JOIN categories c ON c.id = p.category_id
            WHERE p.status = 1 AND ($where) ORDER BY $order LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset;
    return attach_images(rows($sql, $params));
}

function product_url(array $p): string
{
    return url('product/' . $p['slug']);
}

function category_url(array $c): string
{
    if (!empty($c['parent_id'])) {
        $parent = category_by_id((int)$c['parent_id']);
        if ($parent) {
            return url('category/' . $parent['slug'] . '/' . $c['slug']);
        }
    }
    return url('category/' . $c['slug']);
}

function category_by_id(int $id): ?array
{
    static $cache = [];
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = row('SELECT * FROM categories WHERE id = ?', [$id]);
    }
    return $cache[$id];
}

/** Active categories as a tree: [parent + 'children' => [...]] */
function category_tree(bool $menuOnly = false): array
{
    static $cache = [];
    $k = (int)$menuOnly;
    if (!isset($cache[$k])) {
        $all = rows('SELECT * FROM categories WHERE status = 1' . ($menuOnly ? ' AND show_menu = 1' : '') . ' ORDER BY sort_order, name');
        $tree = [];
        foreach ($all as $c) {
            if (empty($c['parent_id'])) {
                $c['children'] = [];
                $tree[$c['id']] = $c;
            }
        }
        foreach ($all as $c) {
            if (!empty($c['parent_id']) && isset($tree[$c['parent_id']])) {
                $tree[$c['parent_id']]['children'][] = $c;
            }
        }
        $cache[$k] = array_values($tree);
    }
    return $cache[$k];
}

function sizes_of(array $p): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string)$p['sizes'])), 'strlen'));
}

function colors_of(array $p): array
{
    return array_values(array_filter(array_map('trim', explode(',', (string)$p['colors'])), 'strlen'));
}

function rating_for(int $pid): array
{
    $r = row('SELECT COUNT(*) n, AVG(rating) a FROM reviews WHERE product_id = ? AND status = 1', [$pid]);
    return ['count' => (int)$r['n'], 'avg' => $r['a'] ? round((float)$r['a'], 1) : 0];
}

function stars(float $rating): string
{
    $out = '<span class="stars" aria-label="' . $rating . ' out of 5">';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<i class="' . ($rating >= $i - 0.25 ? 'on' : '') . '">★</i>';
    }
    return $out . '</span>';
}

/* ------------------------------------------------------------------
 *  Cart (session based, works for guests and customers)
 * ------------------------------------------------------------------ */
function cart_raw(): array
{
    return $_SESSION['cart'] ?? [];
}

function cart_key(int $pid, string $size, string $color): string
{
    return $pid . '|' . $size . '|' . $color;
}

function cart_add(int $pid, int $qty, string $size = '', string $color = ''): array
{
    $p = row('SELECT * FROM products WHERE id = ? AND status = 1', [$pid]);
    if (!$p) {
        return [false, 'This product is no longer available.'];
    }
    $sizes = sizes_of($p);
    if ($sizes && $size === '') {
        if (count($sizes) === 1) {
            $size = $sizes[0];
        } else {
            return [false, 'Please select a size.'];
        }
    }
    if ($sizes && !in_array($size, $sizes, true)) {
        return [false, 'Please select a valid size.'];
    }
    $colors = colors_of($p);
    if ($color !== '' && !in_array($color, $colors, true)) {
        $color = '';
    }
    $key = cart_key($pid, $size, $color);
    $current = $_SESSION['cart'][$key]['qty'] ?? 0;
    $newQty = $current + max(1, $qty);
    if ((int)$p['stock'] <= 0) {
        return [false, 'Sorry, this item is out of stock.'];
    }
    if ($newQty > (int)$p['stock']) {
        $newQty = (int)$p['stock'];
    }
    $_SESSION['cart'][$key] = ['id' => $pid, 'qty' => $newQty, 'size' => $size, 'color' => $color];
    return [true, $p['name'] . ' added to your bag.'];
}

function cart_set(string $key, int $qty): void
{
    if (!isset($_SESSION['cart'][$key])) {
        return;
    }
    if ($qty <= 0) {
        unset($_SESSION['cart'][$key]);
        return;
    }
    $stock = (int)val('SELECT stock FROM products WHERE id = ?', [$_SESSION['cart'][$key]['id']]);
    $_SESSION['cart'][$key]['qty'] = min($qty, max(1, $stock));
}

function cart_clear(): void
{
    unset($_SESSION['cart'], $_SESSION['coupon']);
}

function cart_count(): int
{
    return array_sum(array_column(cart_raw(), 'qty'));
}

/** Full cart with product data + totals. */
function cart_items(): array
{
    $raw = cart_raw();
    if (!$raw) {
        return [];
    }
    $ids = array_unique(array_map('intval', array_column($raw, 'id')));
    $products = [];
    foreach (attach_images(rows('SELECT * FROM products WHERE status = 1 AND id IN (' . implode(',', $ids) . ')')) as $p) {
        $products[$p['id']] = $p;
    }
    $items = [];
    foreach ($raw as $key => $line) {
        if (!isset($products[$line['id']])) {
            unset($_SESSION['cart'][$key]);
            continue;
        }
        $p = $products[$line['id']];
        $price = price_now($p);
        $items[] = [
            'key'     => $key,
            'product' => $p,
            'qty'     => (int)$line['qty'],
            'size'    => $line['size'],
            'color'   => $line['color'],
            'price'   => $price,
            'total'   => $price * (int)$line['qty'],
        ];
    }
    return $items;
}

function shipping_for(string $city, float $subtotal): float
{
    if ($subtotal <= 0) {
        return 0;
    }
    $zone = $city !== '' ? row('SELECT * FROM shipping_zones WHERE status = 1 AND LOWER(city) = LOWER(?)', [trim($city)]) : null;
    if ($zone) {
        if ($zone['free_above'] !== null && (float)$zone['free_above'] > 0 && $subtotal >= (float)$zone['free_above']) {
            return 0;
        }
        return (float)$zone['charge'];
    }
    $free = (float)setting('free_shipping_min', 0);
    if ($free > 0 && $subtotal >= $free) {
        return 0;
    }
    return (float)setting('shipping_flat', 0);
}

function coupon_check(string $code, float $subtotal): array
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return [false, 0, 'Enter a coupon code.'];
    }
    $c = row('SELECT * FROM coupons WHERE code = ? AND status = 1', [$code]);
    if (!$c) {
        return [false, 0, 'This coupon code is not valid.'];
    }
    if ($c['expires_at'] && strtotime($c['expires_at'] . ' 23:59:59') < time()) {
        return [false, 0, 'This coupon has expired.'];
    }
    if ((int)$c['max_uses'] > 0 && (int)$c['used'] >= (int)$c['max_uses']) {
        return [false, 0, 'This coupon has reached its usage limit.'];
    }
    if ($subtotal < (float)$c['min_order']) {
        return [false, 0, 'Minimum order of ' . money($c['min_order']) . ' required for this coupon.'];
    }
    $d = $c['type'] === 'percent' ? round($subtotal * (float)$c['value'] / 100) : (float)$c['value'];
    return [true, min($d, $subtotal), 'Coupon applied.'];
}

function cart_totals(string $city = '', string $payment = 'cod'): array
{
    $items = cart_items();
    $subtotal = array_sum(array_column($items, 'total'));
    $discount = 0;
    $coupon = $_SESSION['coupon'] ?? '';
    if ($coupon !== '') {
        [$ok, $discount] = coupon_check($coupon, $subtotal);
        if (!$ok) {
            unset($_SESSION['coupon']);
            $coupon = '';
            $discount = 0;
        }
    }
    $shipping = shipping_for($city, $subtotal);
    $fee = $payment === 'cod' ? (float)setting('pay_cod_fee', 0) : 0;
    if (!$items) {
        $fee = 0;
    }
    return [
        'items'    => $items,
        'count'    => array_sum(array_column($items, 'qty')),
        'subtotal' => $subtotal,
        'discount' => $discount,
        'coupon'   => $coupon,
        'shipping' => $shipping,
        'fee'      => $fee,
        'total'    => max(0, $subtotal - $discount + $shipping + $fee),
    ];
}

/* ------------------------------------------------------------------
 *  Wishlist (session for guests, DB for logged-in customers)
 * ------------------------------------------------------------------ */
function wishlist_ids(): array
{
    if ($c = customer()) {
        return array_map('intval', array_column(rows('SELECT product_id FROM wishlist WHERE customer_id = ?', [$c['id']]), 'product_id'));
    }
    return array_map('intval', $_SESSION['wishlist'] ?? []);
}

function wishlist_toggle(int $pid): bool
{
    if ($c = customer()) {
        if (val('SELECT COUNT(*) FROM wishlist WHERE customer_id = ? AND product_id = ?', [$c['id'], $pid])) {
            q('DELETE FROM wishlist WHERE customer_id = ? AND product_id = ?', [$c['id'], $pid]);
            return false;
        }
        q('INSERT IGNORE INTO wishlist (customer_id, product_id) VALUES (?, ?)', [$c['id'], $pid]);
        return true;
    }
    $list = $_SESSION['wishlist'] ?? [];
    if (in_array($pid, $list, true)) {
        $_SESSION['wishlist'] = array_values(array_diff($list, [$pid]));
        return false;
    }
    $_SESSION['wishlist'][] = $pid;
    return true;
}

/* ------------------------------------------------------------------
 *  Orders
 * ------------------------------------------------------------------ */
function order_statuses(): array
{
    return [
        'pending'    => 'Pending',
        'confirmed'  => 'Confirmed',
        'processing' => 'Processing',
        'shipped'    => 'Shipped',
        'delivered'  => 'Delivered',
        'cancelled'  => 'Cancelled',
        'returned'   => 'Returned',
    ];
}

function payment_statuses(): array
{
    return [
        'unpaid'               => 'Unpaid',
        'pending_verification' => 'Awaiting Verification',
        'paid'                 => 'Paid',
        'failed'               => 'Failed',
        'refunded'             => 'Refunded',
    ];
}

function status_badge(string $status): string
{
    $label = order_statuses()[$status] ?? payment_statuses()[$status] ?? ucfirst($status);
    return '<span class="badge badge-' . e($status) . '">' . e($label) . '</span>';
}

function payment_label(string $method): string
{
    return setting('pay_' . $method . '_title', ucfirst($method));
}

function order_add_history(int $orderId, string $status, string $note = ''): void
{
    q('INSERT INTO order_history (order_id, status, note) VALUES (?, ?, ?)', [$orderId, $status, $note]);
}

function new_order_no(): string
{
    $prefix = preg_replace('~[^A-Z0-9]~', '', strtoupper(setting('order_prefix', 'EM')));
    do {
        $no = $prefix . date('ymd') . random_int(1000, 9999);
    } while (val('SELECT COUNT(*) FROM orders WHERE order_no = ?', [$no]));
    return $no;
}

/** Can the current visitor view this order? (owner, or the guest who placed it) */
function can_view_order(array $o): bool
{
    if (!empty($_SESSION['my_orders']) && in_array($o['order_no'], $_SESSION['my_orders'], true)) {
        return true;
    }
    $c = customer();
    return $c && (int)$o['customer_id'] === (int)$c['id'];
}

/* ------------------------------------------------------------------
 *  Uploads
 * ------------------------------------------------------------------ */
/**
 * Saves one uploaded image into /uploads/{folder}/ and returns
 * the path relative to /uploads (e.g. "products/abc.jpg") or null.
 */
function upload_image(array $file, string $folder, array $allowExt = ['jpg', 'jpeg', 'png', 'webp', 'gif']): ?string
{
    if (empty($file['tmp_name']) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    if ($file['size'] > 8 * 1024 * 1024) {
        flash('error', 'Image "' . $file['name'] . '" is larger than 8 MB.');
        return null;
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowExt, true)) {
        flash('error', 'File type .' . $ext . ' is not allowed.');
        return null;
    }
    if ($ext === 'ico') {
        $ok = true;
    } else {
        $info = @getimagesize($file['tmp_name']);
        $ok = $info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP], true);
    }
    if (!$ok) {
        flash('error', '"' . $file['name'] . '" is not a valid image.');
        return null;
    }
    $folder = trim(preg_replace('~[^a-z0-9_-]~i', '', $folder));
    $dir = ROOT . '/uploads/' . $folder;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $name = slugify(pathinfo($file['name'], PATHINFO_FILENAME));
    $name = substr($name, 0, 40) . '-' . bin2hex(random_bytes(4)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        flash('error', 'Could not save the uploaded file. Check that /uploads is writable.');
        return null;
    }
    return $folder . '/' . $name;
}

/** Normalises $_FILES['x'] with multiple=true into a list of single files. */
function files_list(string $field): array
{
    if (empty($_FILES[$field]['name'])) {
        return [];
    }
    $f = $_FILES[$field];
    if (!is_array($f['name'])) {
        return [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $n) {
        $out[] = ['name' => $n, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i], 'error' => $f['error'][$i], 'size' => $f['size'][$i]];
    }
    return $out;
}

function delete_upload(?string $file): void
{
    $file = (string)$file;
    if ($file === '' || strpos($file, 'assets/') === 0 || preg_match('~^https?://~', $file) || strpos($file, '..') !== false) {
        return;
    }
    $path = ROOT . '/uploads/' . $file;
    if (is_file($path)) {
        @unlink($path);
    }
}

/* ------------------------------------------------------------------
 *  Pagination
 * ------------------------------------------------------------------ */
function paginate(int $total, int $perPage, int $page): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = max(1, min($page, $pages));
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'total' => $total, 'per' => $perPage];
}

function page_links(array $pg): string
{
    if ($pg['pages'] <= 1) {
        return '';
    }
    $qs = $_GET;
    $out = '<nav class="pagination">';
    for ($i = 1; $i <= $pg['pages']; $i++) {
        if ($i > 2 && $i < $pg['pages'] - 1 && abs($i - $pg['page']) > 2) {
            if (substr($out, -5) !== '<i>…</i>') {
                $out .= '<i>…</i>';
            }
            continue;
        }
        $qs['page'] = $i;
        $out .= '<a href="?' . e(http_build_query($qs)) . '"' . ($i === $pg['page'] ? ' class="active"' : '') . '>' . $i . '</a>';
    }
    return $out . '</nav>';
}

/* ------------------------------------------------------------------
 *  SEO files
 * ------------------------------------------------------------------ */
function default_robots(): string
{
    return "User-agent: *\nDisallow: /admin/\nDisallow: /cart\nDisallow: /checkout\nDisallow: /account\nDisallow: /install/\nAllow: /\n\nSitemap: " . abs_url('sitemap.xml') . "\n";
}

function robots_txt(): string
{
    $r = trim((string)setting('robots_txt', ''));
    return $r !== '' ? $r . "\n" : default_robots();
}

function sitemap_xml(): string
{
    $urls = [[abs_url(''), date('Y-m-d'), 'daily', '1.0'], [abs_url('shop'), date('Y-m-d'), 'daily', '0.9']];
    foreach (rows('SELECT * FROM categories WHERE status = 1 ORDER BY parent_id IS NOT NULL, sort_order') as $c) {
        $urls[] = [site_url() . substr(category_url($c), strlen(base_path())), substr($c['created_at'], 0, 10), 'weekly', $c['parent_id'] ? '0.7' : '0.8'];
    }
    foreach (rows('SELECT slug, COALESCE(updated_at, created_at) m FROM products WHERE status = 1 AND noindex = 0 ORDER BY id DESC') as $p) {
        $urls[] = [abs_url('product/' . $p['slug']), substr($p['m'], 0, 10), 'weekly', '0.8'];
    }
    foreach (rows('SELECT slug, updated_at FROM pages WHERE status = 1') as $pg) {
        $urls[] = [abs_url('page/' . $pg['slug']), $pg['updated_at'] ? substr($pg['updated_at'], 0, 10) : date('Y-m-d'), 'monthly', '0.5'];
    }
    $urls[] = [abs_url('contact'), date('Y-m-d'), 'monthly', '0.5'];
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ($urls as [$loc, $mod, $freq, $prio]) {
        $xml .= "  <url><loc>" . e($loc) . "</loc><lastmod>$mod</lastmod><changefreq>$freq</changefreq><priority>$prio</priority></url>\n";
    }
    return $xml . '</urlset>' . "\n";
}

/** Writes robots.txt, ads.txt and sitemap.xml as real files in the site root. */
function write_seo_files(): array
{
    $results = [];
    foreach (['robots.txt' => robots_txt(), 'ads.txt' => trim((string)setting('ads_txt', '')) . "\n", 'sitemap.xml' => sitemap_xml()] as $file => $content) {
        $results[$file] = @file_put_contents(ROOT . '/' . $file, $content) !== false;
    }
    return $results;
}

/* ------------------------------------------------------------------
 *  Misc
 * ------------------------------------------------------------------ */
function home_section(string $key): ?array
{
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (rows('SELECT * FROM home_sections') as $s) {
            $all[$s['skey']] = $s;
        }
    }
    return $all[$key] ?? null;
}

function banner(string $position): ?array
{
    static $all = null;
    if ($all === null) {
        $all = [];
        foreach (rows('SELECT * FROM banners') as $b) {
            $all[$b['position']] = $b;
        }
    }
    return $all[$position] ?? null;
}

function footer_pages(): array
{
    return rows('SELECT title, slug FROM pages WHERE status = 1 AND show_footer = 1 ORDER BY sort_order, title');
}

function client_ip(): string
{
    return substr($_SERVER['REMOTE_ADDR'] ?? '', 0, 45);
}

function icon(string $name, int $size = 20): string
{
    $paths = [
        'search'  => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'user'    => '<circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 4-6 8-6s8 2 8 6"/>',
        'heart'   => '<path d="M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z"/>',
        'bag'     => '<path d="M5 8h14l-1 12H6L5 8z"/><path d="M9 8V6a3 3 0 0 1 6 0v2"/>',
        'menu'    => '<path d="M3 6h18M3 12h18M3 18h18"/>',
        'close'   => '<path d="M6 6l12 12M18 6 6 18"/>',
        'truck'   => '<path d="M3 6h11v10H3zM14 10h4l3 3v3h-7"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/>',
        'cash'    => '<rect x="3" y="6" width="18" height="12" rx="2"/><circle cx="12" cy="12" r="3"/>',
        'needle'  => '<path d="M20 4 8 16M17 4h3v3M8 16l-4 4M14 10c-3-3-8-2-9 2"/>',
        'refresh' => '<path d="M20 11a8 8 0 0 0-14-5L4 8M4 4v4h4M4 13a8 8 0 0 0 14 5l2-2M20 20v-4h-4"/>',
        'phone'   => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'mail'    => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'pin'     => '<path d="M12 21s-7-6-7-11a7 7 0 0 1 14 0c0 5-7 11-7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'arrow'   => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'left'    => '<path d="M15 6l-6 6 6 6"/>',
        'right'   => '<path d="M9 6l6 6-6 6"/>',
        'eye'     => '<path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'star'    => '<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>',
        'shield'  => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'gift'    => '<rect x="3" y="8" width="18" height="13"/><path d="M3 12h18M12 8v13M12 8S10 3 7.5 4 9 8 12 8zm0 0s2-5 4.5-4S15 8 12 8z"/>',
        'whatsapp'=> '<path d="M4 20l1.3-4A8 8 0 1 1 8 18.7z"/><path d="M9 9.5c0 3 2.5 5.5 5.5 5.5l1-1.5-2-1-1 1c-1-.5-2-1.5-2.5-2.5l1-1-1-2z"/>',
        'facebook'=> '<path d="M14 8h3V4h-3a4 4 0 0 0-4 4v2H8v4h2v8h4v-8h3l1-4h-4V8z"/>',
        'instagram'=> '<rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".8"/>',
        'tiktok'  => '<path d="M14 3v11a3.5 3.5 0 1 1-3-3.5M14 3c.5 2.5 2.5 4.5 5 4.5"/>',
        'youtube' => '<rect x="2" y="5" width="20" height="14" rx="4"/><path d="m10 9 5 3-5 3z"/>',
        'pinterest'=> '<circle cx="12" cy="12" r="9"/><path d="M10 21l2-8m0 0a3 3 0 1 0 0-6 3 3 0 0 0-2 5"/>',
        'check'   => '<path d="m5 12 5 5L20 7"/>',
        'filter'  => '<path d="M3 5h18l-7 8v6l-4-2v-4z"/>',
        'trash'   => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/>',
        'plus'    => '<path d="M12 5v14M5 12h14"/>',
        'minus'   => '<path d="M5 12h14"/>',
    ];
    return '<svg class="ico" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($paths[$name] ?? '') . '</svg>';
}

/* ------------------------------------------------------------------
 *  E-mail (uses PHP mail(); works on most shared hosting)
 * ------------------------------------------------------------------ */
function send_mail(string $to, string $subject, string $html): bool
{
    if (!filter_var($to, FILTER_VALIDATE_EMAIL) || setting('emails_enabled', '1') !== '1') {
        return false;
    }
    $from = setting('mail_from', setting('email', 'no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'localhost')));
    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n"
        . 'From: ' . mb_encode_mimeheader(setting('site_name')) . ' <' . $from . ">\r\n";
    $body = '<div style="background:#f7f1e6;padding:30px;font-family:Georgia,serif"><div style="max-width:600px;margin:auto;background:#fff;border-top:4px solid #c9a24a">'
        . '<div style="background:#0b0b0b;color:#c9a24a;text-align:center;padding:22px;font-size:24px;letter-spacing:4px;text-transform:uppercase">' . e(setting('site_name')) . '</div>'
        . '<div style="padding:28px;color:#222;font-family:Arial,sans-serif;font-size:14px;line-height:1.6">' . $html . '</div></div></div>';
    return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, $headers);
}

function order_email_table(array $order): string
{
    $items = rows('SELECT * FROM order_items WHERE order_id = ?', [$order['id']]);
    $h = '<table width="100%" cellpadding="6" style="border-collapse:collapse">';
    foreach ($items as $it) {
        $h .= '<tr style="border-bottom:1px solid #eee"><td>' . e($it['name']) . ($it['size'] ? ' (' . e($it['size']) . ')' : '') . ' × ' . (int)$it['qty'] . '</td><td align="right">' . money($it['price'] * $it['qty']) . '</td></tr>';
    }
    $h .= '<tr><td>Delivery</td><td align="right">' . money($order['shipping']) . '</td></tr>';
    if ((float)$order['discount'] > 0) $h .= '<tr><td>Discount</td><td align="right">− ' . money($order['discount']) . '</td></tr>';
    if ((float)$order['payment_fee'] > 0) $h .= '<tr><td>COD Fee</td><td align="right">' . money($order['payment_fee']) . '</td></tr>';
    return $h . '<tr><td><b>Total</b></td><td align="right"><b>' . money($order['total']) . '</b></td></tr></table>';
}

function notify_new_order(array $order): void
{
    $table = order_email_table($order);
    $link = abs_url('order-success/' . $order['order_no'] . '?k=' . $order['access_key']);
    send_mail((string)$order['email'], 'Order ' . $order['order_no'] . ' received — ' . setting('site_name'),
        '<h2 style="font-family:Georgia,serif">Thank you, ' . e($order['name']) . '!</h2><p>We have received your order <b>' . e($order['order_no']) . '</b>. We will contact you shortly to confirm.</p>'
        . $table . '<p>Payment: ' . e(payment_label($order['payment_method'])) . '</p><p><a href="' . e($link) . '" style="color:#c9a24a">View your order</a></p>');
    send_mail(setting('order_email', setting('email')), 'New order ' . $order['order_no'] . ' — ' . money($order['total']),
        '<h2>New order ' . e($order['order_no']) . '</h2><p>' . e($order['name']) . ' · ' . e($order['phone']) . '<br>' . e($order['address']) . ', ' . e($order['city']) . '</p>'
        . $table . '<p>Payment: ' . e(payment_label($order['payment_method'])) . ($order['txn_id'] ? ' — TID ' . e($order['txn_id']) : '') . '</p><p><a href="' . e(abs_url('admin/order-view?id=' . $order['id'])) . '">Open in admin</a></p>');
}

function notify_status_change(array $order): void
{
    $msg = '<h2 style="font-family:Georgia,serif">Order update</h2><p>Dear ' . e($order['name']) . ', your order <b>' . e($order['order_no']) . '</b> is now <b>' . e(order_statuses()[$order['status']] ?? $order['status']) . '</b>.</p>';
    if ($order['tracking_no']) {
        $msg .= '<p>Courier: ' . e($order['courier']) . '<br>Tracking #: <b>' . e($order['tracking_no']) . '</b></p>';
    }
    send_mail((string)$order['email'], 'Your order ' . $order['order_no'] . ' is ' . (order_statuses()[$order['status']] ?? $order['status']), $msg);
}
