<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/seo_head.php';

/* ---------------------------------------------------------------------
 * Generic helpers
 * ------------------------------------------------------------------- */

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

/* ---------------------------------------------------------------------
 * Clean URL helpers — every internal link should be built with these so
 * the site never exposes a .php extension in the address bar.
 * ------------------------------------------------------------------- */

function url($path = '', $params = []) {
    $path = ltrim((string)$path, '/');
    $url = $path === '' ? BASE_URL . '/' : BASE_URL . '/' . $path;
    if ($params) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }
    return $url;
}

function resolve_link($link, $fallback = 'shop') {
    $link = trim((string)$link);
    if ($link === '') return url($fallback);
    if (preg_match('~^(https?:)?//~i', $link) || str_starts_with($link, '#')) return $link;
    return url($link);
}

function product_url($slug) {
    return url('product/' . rawurlencode($slug));
}

function category_url($slug) {
    return url('category/' . rawurlencode($slug));
}

function blog_url($slug) {
    return url('blog/' . rawurlencode($slug));
}

function page_url($slug) {
    return url('page/' . rawurlencode($slug));
}

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
    $text = strtolower(preg_replace('~[^-\w]+~', '', $text));
    return $text !== '' ? $text : 'n-a';
}

function unique_slug($mysqli, $table, $slug, $ignoreId = 0) {
    $base = $slug;
    $i = 1;
    while (true) {
        $stmt = mysqli_prepare($mysqli, "SELECT id FROM `$table` WHERE slug = ? AND id != ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'si', $slug, $ignoreId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if (!mysqli_fetch_assoc($res)) {
            return $slug;
        }
        $i++;
        $slug = $base . '-' . $i;
    }
}

function format_price($amount) {
    return STORE_CURRENCY_SYMBOL . ' ' . number_format((float)$amount, 0);
}

function flash_set($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get_all() {
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/* ---------------------------------------------------------------------
 * CSRF protection
 * ------------------------------------------------------------------- */

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify() {
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid or expired form submission. Please go back and try again.');
    }
}

/* ---------------------------------------------------------------------
 * Settings (generic key/value store)
 * ------------------------------------------------------------------- */

function get_setting($key, $default = '') {
    global $mysqli;
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $res = mysqli_query($mysqli, "SELECT setting_key, setting_value FROM settings");
        while ($row = mysqli_fetch_assoc($res)) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting($key, $value) {
    global $mysqli;
    $stmt = mysqli_prepare($mysqli, "INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    mysqli_stmt_bind_param($stmt, 'ss', $key, $value);
    mysqli_stmt_execute($stmt);
}

/* ---------------------------------------------------------------------
 * File uploads
 * ------------------------------------------------------------------- */

function handle_upload($fieldName, $subfolder) {
    if (empty($_FILES[$fieldName]) || $_FILES[$fieldName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$fieldName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!array_key_exists($ext, $allowed)) {
        return false;
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return false;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, $allowed, true)) {
        return false;
    }
    $destDir = UPLOAD_DIR . '/' . trim($subfolder, '/');
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    $filename = bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = $destDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return false;
    }
    return 'uploads/' . trim($subfolder, '/') . '/' . $filename;
}

/* ---------------------------------------------------------------------
 * Admin auth
 * ------------------------------------------------------------------- */

function admin_logged_in() {
    return !empty($_SESSION['admin_id']);
}

function require_admin_login() {
    if (!admin_logged_in()) {
        redirect(url('admin/login'));
    }
}

function current_admin() {
    global $mysqli;
    if (empty($_SESSION['admin_id'])) return null;
    $stmt = mysqli_prepare($mysqli, "SELECT a.*, r.name AS role_name FROM admins a JOIN admin_roles r ON r.id = a.role_id WHERE a.id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['admin_id']);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

/* ---------------------------------------------------------------------
 * Customer auth
 * ------------------------------------------------------------------- */

function customer_logged_in() {
    return !empty($_SESSION['customer_id']);
}

function require_customer_login() {
    if (!customer_logged_in()) {
        redirect(url('login'));
    }
}

function current_customer() {
    global $mysqli;
    if (empty($_SESSION['customer_id'])) return null;
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM customers WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $_SESSION['customer_id']);
    mysqli_stmt_execute($stmt);
    return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
}

/* ---------------------------------------------------------------------
 * Cart helpers (DB backed, keyed by session id or logged in customer)
 * ------------------------------------------------------------------- */

function cart_session_id() {
    if (empty($_SESSION['cart_session_id'])) {
        $_SESSION['cart_session_id'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['cart_session_id'];
}

function cart_owner_clause(&$types, &$params) {
    if (customer_logged_in()) {
        $types .= 'i';
        $params[] = $_SESSION['customer_id'];
        return 'customer_id = ?';
    }
    $types .= 's';
    $params[] = cart_session_id();
    return 'session_id = ?';
}

function get_cart_items() {
    global $mysqli;
    $types = '';
    $params = [];
    $clause = cart_owner_clause($types, $params);
    $sql = "SELECT ci.*, p.name, p.slug, p.sku, p.regular_price, p.sale_price, p.stock_qty AS product_stock,
                   pv.price AS variation_price, pv.stock_qty AS variation_stock,
                   (SELECT image_path FROM product_images WHERE product_id = p.id ORDER BY is_primary DESC, sort_order ASC LIMIT 1) AS image
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            LEFT JOIN product_variations pv ON pv.id = ci.variation_id
            WHERE ci.$clause
            ORDER BY ci.id DESC";
    $stmt = mysqli_prepare($mysqli, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $price = $row['variation_price'] !== null ? $row['variation_price'] : ($row['sale_price'] ?: $row['regular_price']);
        $row['unit_price'] = (float)$price;
        $row['line_total'] = $row['unit_price'] * (int)$row['qty'];
        $items[] = $row;
    }
    return $items;
}

function cart_count() {
    $items = get_cart_items();
    $count = 0;
    foreach ($items as $i) $count += (int)$i['qty'];
    return $count;
}

function cart_totals($items) {
    $subtotal = 0;
    foreach ($items as $i) $subtotal += $i['line_total'];
    return $subtotal;
}

/* ---------------------------------------------------------------------
 * Order number
 * ------------------------------------------------------------------- */

function merge_guest_cart_into_customer($mysqli, $customerId) {
    if (empty($_SESSION['cart_session_id'])) return;
    $sid = $_SESSION['cart_session_id'];
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM cart_items WHERE session_id = ?");
    mysqli_stmt_bind_param($stmt, 's', $sid);
    mysqli_stmt_execute($stmt);
    $guestItems = mysqli_stmt_get_result($stmt);
    while ($item = mysqli_fetch_assoc($guestItems)) {
        $variationClause = $item['variation_id'] ? "variation_id = {$item['variation_id']}" : "variation_id IS NULL";
        $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id, qty FROM cart_items WHERE customer_id = $customerId AND product_id = {$item['product_id']} AND $variationClause"));
        if ($existing) {
            $newQty = $existing['qty'] + $item['qty'];
            mysqli_query($mysqli, "UPDATE cart_items SET qty = $newQty WHERE id = {$existing['id']}");
            mysqli_query($mysqli, "DELETE FROM cart_items WHERE id = {$item['id']}");
        } else {
            mysqli_query($mysqli, "UPDATE cart_items SET customer_id = $customerId, session_id = NULL WHERE id = {$item['id']}");
        }
    }
}

function generate_order_number() {
    return 'NC' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
}

/* ---------------------------------------------------------------------
 * Product helpers
 * ------------------------------------------------------------------- */

function product_primary_image($mysqli, $productId) {
    $stmt = mysqli_prepare($mysqli, "SELECT image_path FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, sort_order ASC LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $productId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    return $row['image_path'] ?? 'assets/img/placeholder.svg';
}

function fetch_products_by_filter($mysqli, $filter, $categoryId = null, $limit = 8) {
    $where = "status = 'active'";
    switch ($filter) {
        case 'featured': $where .= " AND is_featured = 1"; break;
        case 'new_arrival': $where .= " AND is_new_arrival = 1"; break;
        case 'best_seller': $where .= " AND is_best_seller = 1"; break;
        case 'trending': $where .= " AND is_trending = 1"; break;
        case 'sale': $where .= " AND sale_price IS NOT NULL AND sale_price < regular_price"; break;
        case 'category': if ($categoryId) $where .= " AND category_id = " . (int)$categoryId; break;
    }
    $limit = (int)$limit;
    $res = mysqli_query($mysqli, "SELECT * FROM products WHERE $where ORDER BY created_at DESC LIMIT $limit");
    $products = [];
    while ($row = mysqli_fetch_assoc($res)) $products[] = $row;
    return $products;
}

function product_card_images($mysqli, $productId) {
    $res = mysqli_query($mysqli, "SELECT image_path FROM product_images WHERE product_id = $productId ORDER BY is_primary DESC, sort_order ASC LIMIT 2");
    $imgs = [];
    while ($r = mysqli_fetch_assoc($res)) $imgs[] = $r['image_path'];
    if (!$imgs) $imgs[] = 'assets/img/placeholder.svg';
    if (count($imgs) === 1) $imgs[] = $imgs[0];
    return $imgs;
}

function render_product_card($mysqli, $product) {
    $imgs = product_card_images($mysqli, $product['id']);
    $onSale = !empty($product['sale_price']) && $product['sale_price'] < $product['regular_price'];
    ?>
    <div class="product-card" data-aos="fade-up">
      <div class="product-thumb">
        <a href="<?= e(product_url($product['slug'])) ?>">
          <img src="<?= e(BASE_URL . '/' . $imgs[0]) ?>" class="img-main" alt="<?= e($product['name']) ?>">
          <img src="<?= e(BASE_URL . '/' . $imgs[1]) ?>" class="img-hover" alt="">
        </a>
        <div class="product-badges">
          <?php if ($onSale): ?><span class="badge-pill badge-sale">Sale</span><?php endif; ?>
          <?php if (!empty($product['is_new_arrival'])): ?><span class="badge-pill badge-new">New</span><?php endif; ?>
        </div>
        <div class="product-quick-actions">
          <button class="qa-btn js-wishlist-toggle" data-product-id="<?= (int)$product['id'] ?>" title="Add to wishlist"><i class="bi bi-heart"></i></button>
          <a href="<?= e(product_url($product['slug'])) ?>" class="qa-btn" title="Quick view"><i class="bi bi-eye"></i></a>
        </div>
      </div>
      <div class="product-info">
        <a href="<?= e(product_url($product['slug'])) ?>" class="p-name"><?= e($product['name']) ?></a>
        <div class="product-price">
          <?php if ($onSale): ?>
            <span class="old"><?= format_price($product['regular_price']) ?></span><span class="new"><?= format_price($product['sale_price']) ?></span>
          <?php else: ?>
            <span class="new"><?= format_price($product['regular_price']) ?></span>
          <?php endif; ?>
        </div>
        <div class="add-to-cart-mini">
          <button class="btn btn-outline-brand btn-sm js-add-to-cart" data-product-id="<?= (int)$product['id'] ?>" style="padding:.4rem 1.2rem;font-size:.72rem;">Add to Cart</button>
        </div>
      </div>
    </div>
    <?php
}

function category_tree($mysqli) {
    $res = mysqli_query($mysqli, "SELECT * FROM categories WHERE status = 'active' ORDER BY sort_order ASC, name ASC");
    $all = [];
    while ($row = mysqli_fetch_assoc($res)) $all[] = $row;
    $byParent = [];
    foreach ($all as $c) $byParent[$c['parent_id'] ?? 0][] = $c;
    $build = function ($parentId) use (&$build, &$byParent) {
        $branch = [];
        foreach ($byParent[$parentId] ?? [] as $cat) {
            $cat['children'] = $build($cat['id']);
            $branch[] = $cat;
        }
        return $branch;
    };
    return $build(0);
}
