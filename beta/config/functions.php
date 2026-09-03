<?php
/**
 * All shared helper functions for Beglet. Procedural, MySQLi-backed.
 */

// ------------------------------------------------------------------
// URL HELPERS — every internal link in the app must go through these
// ------------------------------------------------------------------
function base_url($path = '') {
    return rtrim(defined('BASE_URL_RUNTIME') ? BASE_URL_RUNTIME : BASE_URL, '/') . '/' . ltrim($path, '/');
}
function asset_url($path = '') {
    return base_url('assets/' . ltrim($path, '/'));
}
function upload_url($path = '') {
    return base_url('uploads/' . ltrim($path, '/'));
}
function admin_url($path = '') {
    return base_url('admin/' . ltrim($path, '/'));
}
function shop_url($path = '') {
    return base_url('shop/' . ltrim($path, '/'));
}
function employee_url($path = '') {
    return base_url('employee/' . ltrim($path, '/'));
}
function customer_url($path = '') {
    return base_url('customer/' . ltrim($path, '/'));
}
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// ------------------------------------------------------------------
// DATABASE HELPERS (MySQLi + prepared statements)
// ------------------------------------------------------------------
function db_query($sql, $types = '', $params = []) {
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('SQL prepare failed: ' . mysqli_error($conn) . ' | ' . $sql);
        return false;
    }
    if ($types !== '' && !empty($params)) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return $result !== false ? $result : $stmt;
}
function db_fetch_one($sql, $types = '', $params = []) {
    $result = db_query($sql, $types, $params);
    if (!$result instanceof mysqli_result) return null;
    return mysqli_fetch_assoc($result) ?: null;
}
function db_fetch_all($sql, $types = '', $params = []) {
    $result = db_query($sql, $types, $params);
    $rows = [];
    if ($result instanceof mysqli_result) {
        while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    }
    return $rows;
}
function db_insert($sql, $types = '', $params = []) {
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { error_log('SQL prepare failed: ' . mysqli_error($conn) . ' | ' . $sql); return 0; }
    if ($types !== '' && !empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}
function db_exec($sql, $types = '', $params = []) {
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) { error_log('SQL prepare failed: ' . mysqli_error($conn) . ' | ' . $sql); return false; }
    if ($types !== '' && !empty($params)) mysqli_stmt_bind_param($stmt, $types, ...$params);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $ok ? $affected : false;
}
function db_esc($value) {
    global $conn;
    return mysqli_real_escape_string($conn, $value);
}

// ------------------------------------------------------------------
// SETTINGS
// ------------------------------------------------------------------
function get_setting($key, $default = '') {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_fetch_all("SELECT setting_key, setting_value FROM settings") as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}
function set_setting($key, $value) {
    db_exec("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)", 'ss', [$key, $value]);
}
function site_name() { return get_setting('site_name', 'Beglet'); }
function currency_symbol() { return get_setting('currency_symbol', 'Rs.'); }
function format_price($amount) {
    return currency_symbol() . ' ' . number_format((float)$amount, 0);
}

// ------------------------------------------------------------------
// SANITIZATION / VALIDATION
// ------------------------------------------------------------------
function clean($value) {
    return htmlspecialchars(trim($value ?? ''), ENT_QUOTES, 'UTF-8');
}
function clean_html($html) {
    // Strip script/style/event-handlers/javascript: URIs; keep basic rich-text tags.
    $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html ?? '');
    $html = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $html);
    $html = preg_replace('/\son\w+\s*=\s*"[^"]*"/i', '', $html);
    $html = preg_replace("/\son\w+\s*=\s*'[^']*'/i", '', $html);
    $html = preg_replace('/javascript\s*:/i', '', $html);
    return strip_tags($html, '<p><br><b><strong><i><em><ul><ol><li><h1><h2><h3><h4><a><img><table><thead><tbody><tr><td><th><blockquote><span>');
}
function slugify($text) {
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}
function unique_slug($table, $slug, $idColumn = 'id', $excludeId = null) {
    $base = $slug; $i = 1;
    while (true) {
        $sql = "SELECT $idColumn FROM $table WHERE slug = ?" . ($excludeId ? " AND $idColumn != ?" : "");
        $params = $excludeId ? [$slug, $excludeId] : [$slug];
        $types = $excludeId ? 'si' : 's';
        if (!db_fetch_one($sql, $types, $params)) return $slug;
        $slug = $base . '-' . (++$i);
    }
}

// ------------------------------------------------------------------
// CSRF PROTECTION
// ------------------------------------------------------------------
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}
function csrf_verify() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid or expired form submission (CSRF check failed). Please go back and try again.');
    }
}

// ------------------------------------------------------------------
// FLASH MESSAGES
// ------------------------------------------------------------------
function flash($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}
function get_flashes() {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

// ------------------------------------------------------------------
// AUTH — four independent role sessions can coexist
// ------------------------------------------------------------------
function current_admin() { return $_SESSION['admin'] ?? null; }
function current_shop_owner() { return $_SESSION['shop_owner'] ?? null; }
function current_shop_staff() { return $_SESSION['shop_staff'] ?? null; }
function current_customer() { return $_SESSION['customer'] ?? null; }

function require_admin() {
    if (!current_admin()) redirect(admin_url('login.php'));
}
function require_shop_owner() {
    if (!current_shop_owner()) redirect(shop_url('login.php'));
}
function require_shop_staff() {
    if (!current_shop_staff()) redirect(shop_url('login.php'));
}
function require_shop_owner_or_staff() {
    if (!current_shop_owner() && !current_shop_staff()) redirect(shop_url('login.php'));
}
function require_customer() {
    if (!current_customer()) redirect(base_url('login.php?redirect=' . urlencode($_SERVER['REQUEST_URI'] ?? '')));
}

// The shop_id currently in scope for a shop-owner/staff session.
function active_shop_id() {
    if ($owner = current_shop_owner()) return $owner['shop_id'];
    if ($staff = current_shop_staff()) return $staff['shop_id'];
    return null;
}
function staff_has_permission($key) {
    if (current_shop_owner()) return true; // owner has full access
    $staff = current_shop_staff();
    if (!$staff) return false;
    return !empty($_SESSION['staff_permissions'][$key]);
}
function require_permission($key) {
    require_shop_owner_or_staff();
    if (!staff_has_permission($key)) {
        http_response_code(403);
        die('<div style="font-family:sans-serif;max-width:520px;margin:80px auto;text-align:center;">
             <h2>Access Restricted</h2><p>You do not have permission to access this module. Contact your shop owner.</p>
             <a href="' . shop_url('dashboard.php') . '">Back to Dashboard</a></div>');
    }
}

// ------------------------------------------------------------------
// FILE UPLOADS
// ------------------------------------------------------------------
function handle_image_upload($fileField, $subfolder) {
    if (empty($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fileField];
    if ($file['error'] !== UPLOAD_ERR_OK) return ['error' => 'Upload failed.'];
    if ($file['size'] > MAX_UPLOAD_SIZE) return ['error' => 'File too large (max 2MB).'];

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_IMAGE_TYPES, true)) return ['error' => 'Only JPG, JPEG, PNG, WEBP allowed.'];

    $mime = mime_content_type($file['tmp_name']);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes, true)) return ['error' => 'Invalid image file.'];
    if (@getimagesize($file['tmp_name']) === false) return ['error' => 'File is not a valid image.'];

    $dir = rtrim(UPLOAD_DIR, '/') . '/' . trim($subfolder, '/') . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $filename = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return ['error' => 'Could not save uploaded file.'];
    }
    return ['path' => trim($subfolder, '/') . '/' . $filename];
}
function product_image_or_default($path) {
    return $path ? upload_url($path) : asset_url(DEFAULT_PRODUCT_IMAGE);
}
function category_image_or_default($path) {
    return $path ? upload_url($path) : asset_url(DEFAULT_CATEGORY_IMAGE);
}
function shop_logo_or_default($path) {
    return $path ? upload_url($path) : asset_url(DEFAULT_SHOP_LOGO);
}
function shop_cover_or_default($path) {
    return $path ? upload_url($path) : asset_url(DEFAULT_SHOP_COVER);
}

// ------------------------------------------------------------------
// COMMISSION CALCULATION
// ------------------------------------------------------------------
function calculate_commission($categoryId, $lineTotal) {
    $cat = db_fetch_one("SELECT commission_type, commission_value FROM categories WHERE id = ?", 'i', [$categoryId]);
    if (!$cat) return ['percent' => 0, 'amount' => 0];
    if ($cat['commission_type'] === 'fixed') {
        $amount = min((float)$cat['commission_value'], $lineTotal);
        $percent = $lineTotal > 0 ? round(($amount / $lineTotal) * 100, 2) : 0;
    } else {
        $percent = (float)$cat['commission_value'];
        $amount = round($lineTotal * $percent / 100, 2);
    }
    return ['percent' => $percent, 'amount' => $amount];
}

// ------------------------------------------------------------------
// NOTIFICATIONS
// ------------------------------------------------------------------
function notify($recipientType, $recipientId, $title, $message, $link = null) {
    db_insert("INSERT INTO notifications (recipient_type, recipient_id, title, message, link) VALUES (?,?,?,?,?)",
        'sisss', [$recipientType, $recipientId, $title, $message, $link]);
}
function unread_notification_count($type, $id) {
    $row = db_fetch_one("SELECT COUNT(*) c FROM notifications WHERE recipient_type=? AND recipient_id=? AND is_read=0", 'si', [$type, $id]);
    return $row ? (int)$row['c'] : 0;
}

// ------------------------------------------------------------------
// AUDIT LOG
// ------------------------------------------------------------------
function audit_log($userType, $userId, $userName, $action, $module, $recordId = null, $description = '') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    db_insert("INSERT INTO audit_logs (user_type,user_id,user_name,action,module,record_id,description,ip_address)
               VALUES (?,?,?,?,?,?,?,?)", 'sissssss', [$userType, $userId, $userName, $action, $module, $recordId, $description, $ip]);
}

// ------------------------------------------------------------------
// PAGINATION
// ------------------------------------------------------------------
function paginate($totalRows, $perPage = 20, $page = null) {
    $page = $page ?: max(1, (int)($_GET['page'] ?? 1));
    $totalPages = max(1, (int)ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;
    return ['page' => $page, 'perPage' => $perPage, 'totalPages' => $totalPages, 'offset' => $offset, 'totalRows' => $totalRows];
}
function pagination_links($pagination, $baseUrl) {
    if ($pagination['totalPages'] <= 1) return '';
    $html = '<nav class="pagination"><ul class="pagination-list">';
    for ($i = 1; $i <= $pagination['totalPages']; $i++) {
        $sep = strpos($baseUrl, '?') !== false ? '&' : '?';
        $active = $i === $pagination['page'] ? ' active' : '';
        $html .= '<li><a class="page-link' . $active . '" href="' . $baseUrl . $sep . 'page=' . $i . '">' . $i . '</a></li>';
    }
    return $html . '</ul></nav>';
}

// ------------------------------------------------------------------
// SEO SCORE (simplified, transparent algorithm — /100)
// ------------------------------------------------------------------
function calculate_seo_score($data) {
    $score = 0; $tips = [];
    $title = $data['meta_title'] ?? ''; $desc = $data['meta_description'] ?? '';
    $keyword = $data['focus_keyword'] ?? ''; $content = $data['content'] ?? '';

    if (strlen($title) >= 30 && strlen($title) <= 65) { $score += 15; $tips[] = ['ok', 'Meta title length is good']; }
    else { $tips[] = ['warn', 'Meta title should be 30-65 characters']; }

    if (strlen($desc) >= 70 && strlen($desc) <= 160) { $score += 15; $tips[] = ['ok', 'Meta description length is good']; }
    else { $tips[] = ['warn', 'Meta description should be 70-160 characters']; }

    if ($keyword && stripos($title, $keyword) !== false) { $score += 15; $tips[] = ['ok', 'Focus keyword found in title']; }
    else { $tips[] = ['bad', 'Focus keyword missing from title']; }

    if ($keyword && stripos($desc, $keyword) !== false) { $score += 10; $tips[] = ['ok', 'Focus keyword found in description']; }
    else { $tips[] = ['warn', 'Add focus keyword to meta description']; }

    if (strlen(strip_tags($content)) >= 200) { $score += 15; $tips[] = ['ok', 'Content length is sufficient']; }
    else { $tips[] = ['warn', 'Content is thin — add more descriptive text']; }

    if (!empty($data['canonical_url'])) { $score += 10; $tips[] = ['ok', 'Canonical URL exists']; }
    else { $tips[] = ['warn', 'Canonical URL is missing']; }

    if (!empty($data['og_image'])) { $score += 10; $tips[] = ['ok', 'Open Graph image is set']; }
    else { $tips[] = ['warn', 'Add an Open Graph image']; }

    if (!empty($data['schema_type'])) { $score += 10; $tips[] = ['ok', 'Schema type configured']; }
    else { $tips[] = ['bad', 'Schema type not configured']; }

    return ['score' => min(100, $score), 'tips' => $tips];
}

// ------------------------------------------------------------------
// JSON-LD STRUCTURED DATA
// ------------------------------------------------------------------
function render_schema($schemaArray) {
    return '<script type="application/ld+json">' . json_encode($schemaArray, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

// ------------------------------------------------------------------
// SHOP STATUS GATE
// ------------------------------------------------------------------
function shop_is_purchasable($status) {
    return in_array($status, ['active'], true);
}
function check_commission_overdue() {
    // Any shop with a pending commission whose due_date has passed becomes payment_overdue.
    db_exec("UPDATE commissions SET payment_status = 'overdue' WHERE payment_status = 'pending' AND due_date < CURDATE()");
    $overdueShops = db_fetch_all("SELECT DISTINCT shop_id FROM commissions WHERE payment_status = 'overdue'");
    foreach ($overdueShops as $row) {
        db_exec("UPDATE shops SET status='payment_overdue', status_reason='Commission payment overdue' WHERE id=? AND status='active'", 'i', [$row['shop_id']]);
    }
}

// ------------------------------------------------------------------
// SEO META LOOKUP
// ------------------------------------------------------------------
function get_seo_meta($entityType, $entityId = null) {
    if ($entityId === null) {
        return db_fetch_one("SELECT * FROM seo_meta WHERE entity_type=? AND entity_id IS NULL", 's', [$entityType]);
    }
    return db_fetch_one("SELECT * FROM seo_meta WHERE entity_type=? AND entity_id=?", 'si', [$entityType, $entityId]);
}
function save_seo_meta($entityType, $entityId, $fields) {
    $existing = get_seo_meta($entityType, $entityId);
    $cols = ['meta_title','meta_description','focus_keyword','keywords','canonical_url','robots',
             'og_title','og_description','og_image','twitter_title','twitter_description','twitter_image',
             'schema_type','faq_json','entity_description','key_facts','seo_score'];
    $values = [];
    foreach ($cols as $c) $values[$c] = $fields[$c] ?? ($existing[$c] ?? null);
    if ($existing) {
        $sets = implode(',', array_map(fn($c) => "$c=?", $cols));
        db_exec("UPDATE seo_meta SET $sets WHERE id=?", str_repeat('s', count($cols)) . 'i',
            [...array_values($values), $existing['id']]);
    } else {
        $placeholders = implode(',', array_fill(0, count($cols) + 2, '?'));
        db_insert("INSERT INTO seo_meta (entity_type, entity_id, " . implode(',', $cols) . ") VALUES ($placeholders)",
            'ss' . str_repeat('s', count($cols)), [$entityType, $entityId, ...array_values($values)]);
    }
}

// ------------------------------------------------------------------
// DASHBOARD SIDEBAR MENUS
// ------------------------------------------------------------------
function dashboard_sidebar($role) {
    if ($role === 'admin') {
        return [
            ['Dashboard', 'fa-gauge', admin_url('index.php')],
            ['Customers', 'fa-users', admin_url('users/customers.php')],
            ['Shop Owners', 'fa-store', admin_url('users/shop-owners.php')],
            ['Shops', 'fa-shop', admin_url('shops/index.php')],
            ['Shop Staff', 'fa-user-tie', admin_url('employees/index.php')],
            ['Categories', 'fa-tags', admin_url('categories/index.php')],
            ['Products', 'fa-box', admin_url('products/index.php')],
            ['Orders', 'fa-receipt', admin_url('orders/index.php')],
            ['Commissions', 'fa-percent', admin_url('commissions/index.php')],
            ['Payments', 'fa-money-bill', admin_url('payments/index.php')],
            ['Banners', 'fa-image', admin_url('banners/index.php')],
            ['Homepage Sections', 'fa-table-cells', admin_url('shop-sections/index.php')],
            ['SEO', 'fa-magnifying-glass-chart', admin_url('seo/index.php')],
            ['Reports', 'fa-chart-line', admin_url('reports/index.php')],
            ['Settings', 'fa-gear', admin_url('settings/index.php')],
        ];
    }
    if ($role === 'shop') {
        return [
            ['Dashboard', 'fa-gauge', shop_url('dashboard.php'), 'view_dashboard'],
            ['Products', 'fa-box', shop_url('products.php'), 'manage_products'],
            ['Orders', 'fa-receipt', shop_url('orders.php'), 'view_orders'],
            ['Staff', 'fa-user-tie', shop_url('employees.php'), null],
            ['Commissions', 'fa-percent', shop_url('commissions.php'), 'view_commission'],
            ['Payments', 'fa-money-bill', shop_url('payments.php'), 'manage_payments'],
            ['Shop Design', 'fa-palette', shop_url('shop-design.php'), 'manage_shop_design'],
            ['Shop Sections', 'fa-table-cells', shop_url('shop-sections.php'), 'manage_shop_sections'],
            ['Profile', 'fa-store', shop_url('profile.php'), 'manage_shop_profile'],
            ['Settings', 'fa-gear', shop_url('settings.php'), null],
        ];
    }
    if ($role === 'employee') {
        return [
            ['Dashboard', 'fa-gauge', employee_url('dashboard.php'), 'view_dashboard'],
            ['Products', 'fa-box', employee_url('products.php'), 'manage_products'],
            ['Orders', 'fa-receipt', employee_url('orders.php'), 'view_orders'],
        ];
    }
    if ($role === 'customer') {
        return [
            ['Dashboard', 'fa-gauge', customer_url('dashboard.php')],
            ['My Orders', 'fa-receipt', customer_url('orders.php')],
            ['Wishlist', 'fa-heart', customer_url('wishlist.php')],
            ['Addresses', 'fa-location-dot', customer_url('addresses.php')],
            ['Reviews', 'fa-star', customer_url('reviews.php')],
            ['Profile', 'fa-user', customer_url('profile.php')],
        ];
    }
    return [];
}

// ------------------------------------------------------------------
// CART
// ------------------------------------------------------------------
function get_or_create_cart($customerId) {
    $cart = db_fetch_one("SELECT * FROM carts WHERE customer_id = ?", 'i', [$customerId]);
    if ($cart) return $cart;
    $id = db_insert("INSERT INTO carts (customer_id) VALUES (?)", 'i', [$customerId]);
    return ['id' => $id, 'customer_id' => $customerId];
}
function get_cart_items_grouped($customerId) {
    $cart = db_fetch_one("SELECT id FROM carts WHERE customer_id = ?", 'i', [$customerId]);
    if (!$cart) return [];
    $rows = db_fetch_all("SELECT ci.*, p.name, p.slug, p.main_image, p.regular_price, p.sale_price, p.stock_quantity,
                                  p.stock_status, p.shop_id, s.shop_name, s.slug as shop_slug, s.status as shop_status,
                                  v.variation_label, v.price as variation_price, v.stock_quantity as variation_stock
                           FROM cart_items ci
                           JOIN products p ON p.id = ci.product_id
                           JOIN shops s ON s.id = p.shop_id
                           LEFT JOIN product_variations v ON v.id = ci.variation_id
                           WHERE ci.cart_id = ? ORDER BY s.shop_name", 'i', [$cart['id']]);
    $grouped = [];
    foreach ($rows as $row) {
        $unitPrice = $row['variation_price'] ?? ($row['sale_price'] ?? $row['regular_price']);
        $row['unit_price'] = $unitPrice;
        $row['line_total'] = $unitPrice * $row['quantity'];
        $grouped[$row['shop_id']]['shop_name'] = $row['shop_name'];
        $grouped[$row['shop_id']]['shop_slug'] = $row['shop_slug'];
        $grouped[$row['shop_id']]['shop_status'] = $row['shop_status'];
        $grouped[$row['shop_id']]['items'][] = $row;
    }
    return $grouped;
}
function cart_totals($grouped) {
    $subtotal = 0; $count = 0;
    foreach ($grouped as $shop) {
        foreach ($shop['items'] as $item) { $subtotal += $item['line_total']; $count += $item['quantity']; }
    }
    return ['subtotal' => $subtotal, 'count' => $count];
}

// ------------------------------------------------------------------
// ORDER NUMBER GENERATOR
// ------------------------------------------------------------------
function generate_order_number() {
    $row = db_fetch_one("SELECT MAX(id) as maxid FROM orders");
    $next = ($row['maxid'] ?? 0) + 100001;
    return 'BG-' . $next;
}
