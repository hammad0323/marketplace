<?php
/**
 * Shared helper library — sanitization, CSRF, flashes, uploads, formatting,
 * notifications, activity logging, and small DB query helpers. Included
 * once via config/config.php.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

/** @return mysqli */
function db()
{
    return $GLOBALS['db'];
}

// ---------------------------------------------------------------------------
// Input / output safety
// ---------------------------------------------------------------------------

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clean($value)
{
    return trim((string) $value);
}

function slugify($text)
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
    $text = strtolower(preg_replace('~[^-\w]+~', '', $text));
    return $text ?: 'item';
}

function unique_slug(mysqli $db, string $table, string $base)
{
    $slug = slugify($base);
    $original = $slug;
    $i = 1;
    while (true) {
        $stmt = mysqli_prepare($db, "SELECT id FROM `$table` WHERE slug = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $slug);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        if (!$exists) {
            return $slug;
        }
        $slug = $original . '-' . (++$i);
    }
}

// ---------------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------------

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify($token)
{
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf_or_fail()
{
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!csrf_verify($token)) {
        http_response_code(419);
        if (is_ajax_request()) {
            json_response(false, [], 'Your session expired. Please refresh the page and try again.');
        }
        exit('Security check failed. Please go back and try again.');
    }
}

function is_ajax_request()
{
    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
        || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
}

function json_response($success, $data = [], $message = '')
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// ---------------------------------------------------------------------------
// Flash messages / redirects
// ---------------------------------------------------------------------------

function flash_set($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get()
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function current_url()
{
    return $_SERVER['REQUEST_URI'] ?? '/';
}

// ---------------------------------------------------------------------------
// Rate limiting (login attempts)
// ---------------------------------------------------------------------------

function client_ip()
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function record_login_attempt($identifier, $success)
{
    $db = db();
    $ip = client_ip();
    $stmt = mysqli_prepare($db, 'INSERT INTO login_attempts (identifier, ip_address, success) VALUES (?, ?, ?)');
    $successInt = $success ? 1 : 0;
    mysqli_stmt_bind_param($stmt, 'ssi', $identifier, $ip, $successInt);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function is_rate_limited($identifier, $maxAttempts = 5, $windowMinutes = 15)
{
    $db = db();
    $ip = client_ip();
    $stmt = mysqli_prepare($db, 'SELECT COUNT(*) AS c FROM login_attempts
        WHERE (identifier = ? OR ip_address = ?) AND success = 0
        AND attempted_at > (NOW() - INTERVAL ? MINUTE)');
    mysqli_stmt_bind_param($stmt, 'ssi', $identifier, $ip, $windowMinutes);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    return ((int) $row['c']) >= $maxAttempts;
}

// ---------------------------------------------------------------------------
// File uploads
// ---------------------------------------------------------------------------

/**
 * Validates and moves an uploaded file into UPLOAD_PATH/$subdir.
 * Returns the relative path (for UPLOAD_URL) on success, or [false, error].
 */
function handle_upload($fileKey, $subdir, array $allowedExt, $maxBytes = 5242880)
{
    if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
        return [false, 'No file uploaded.'];
    }
    $file = $_FILES[$fileKey];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'Upload failed. Please try again.'];
    }
    if ($file['size'] > $maxBytes) {
        return [false, 'File is too large (max ' . round($maxBytes / 1048576, 1) . 'MB).'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return [false, 'File type not allowed. Allowed: ' . implode(', ', $allowedExt)];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
    ];
    if (isset($allowedMimes[$ext]) && $mime !== $allowedMimes[$ext]) {
        return [false, 'File content does not match its extension.'];
    }

    $dir = UPLOAD_PATH . '/' . trim($subdir, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        return [false, 'Could not save the uploaded file.'];
    }
    return [true, trim($subdir, '/') . '/' . $filename];
}

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------

function format_currency($amount)
{
    return '$' . number_format((float) $amount, 2);
}

function format_date($date, $format = 'M j, Y')
{
    if (!$date) {
        return '';
    }
    return date($format, strtotime($date));
}

function format_time12($time)
{
    if (!$time) {
        return '';
    }
    return date('g:i A', strtotime($time));
}

function time_ago($datetime)
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'just now';
    }
    $units = [31536000 => 'year', 2592000 => 'month', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $seconds => $label) {
        $value = floor($diff / $seconds);
        if ($value >= 1) {
            return $value . ' ' . $label . ($value > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function excerpt($text, $length = 140)
{
    $text = trim(strip_tags($text));
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
}

function day_name($index)
{
    return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$index] ?? '';
}

function avatar_url($path, $fallbackSeed = 'U')
{
    if ($path) {
        return UPLOAD_URL . '/' . $path;
    }
    $initial = strtoupper(substr($fallbackSeed, 0, 1));
    return 'data:image/svg+xml;utf8,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
        . '<rect width="100" height="100" rx="50" fill="#8B5CF6"/>'
        . '<text x="50" y="58" font-size="42" font-family="sans-serif" fill="#fff" text-anchor="middle">' . $initial . '</text>'
        . '</svg>'
    );
}

// ---------------------------------------------------------------------------
// Doctor specializations (many-to-many)
// ---------------------------------------------------------------------------

/** @return array<int,array{id:int,name:string,slug:string}> */
function get_doctor_specializations($doctorId)
{
    $stmt = mysqli_prepare(db(), 'SELECT s.id, s.name, s.slug FROM doctor_specializations ds
        JOIN specializations s ON s.id = ds.specialization_id WHERE ds.doctor_id = ? ORDER BY s.name');
    mysqli_stmt_bind_param($stmt, 'i', $doctorId);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function specialization_names($specializations)
{
    return implode(', ', array_column($specializations, 'name'));
}

/** Replaces a doctor's specialization set with the given ids (validated against the specializations table). */
function set_doctor_specializations($doctorId, array $specializationIds)
{
    $db = db();
    $specializationIds = array_values(array_unique(array_map('intval', $specializationIds)));
    mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db, 'DELETE FROM doctor_specializations WHERE doctor_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $doctorId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($specializationIds) {
            $stmt = mysqli_prepare($db, 'INSERT INTO doctor_specializations (doctor_id, specialization_id)
                SELECT ?, id FROM specializations WHERE id = ?');
            foreach ($specializationIds as $specId) {
                mysqli_stmt_bind_param($stmt, 'ii', $doctorId, $specId);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_commit($db);
    } catch (Exception $e) {
        mysqli_rollback($db);
        error_log('set_doctor_specializations failed: ' . $e->getMessage());
        throw $e;
    }
}

// ---------------------------------------------------------------------------
// Settings, notifications, activity log, pagination
// ---------------------------------------------------------------------------

function get_setting($key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $res = mysqli_query(db(), 'SELECT setting_key, setting_value FROM site_settings');
        while ($row = mysqli_fetch_assoc($res)) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * Records an in-app notification for the user and — if email notifications
 * are enabled (Admin → Site Settings → Email) — emails them the same
 * message. Every appointment, verification, and message event in the app
 * routes through this single function, so email delivery for "all
 * activity" is handled in one place rather than at each call site.
 */
function notify_user($userId, $type, $title, $message, $link = null)
{
    $stmt = mysqli_prepare(db(), 'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'issss', $userId, $type, $title, $message, $link);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!email_enabled()) {
        return;
    }
    $stmt = mysqli_prepare(db(), 'SELECT full_name, email FROM users WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $recipient = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if (!$recipient) {
        return;
    }

    $ctaUrl = $link ? (APP_URL . $link) : null;
    $body = '<p>Hi ' . e(explode(' ', $recipient['full_name'])[0]) . ',</p><p>' . e($message) . '</p>';
    send_email($recipient['email'], $recipient['full_name'], $title, email_template($title, $body, $link ? 'View Details' : null, $ctaUrl));
}

/** Notifies (and emails) every admin account — used for platform-level events like a new doctor application or contact message. */
function notify_admins($type, $title, $message, $link = null)
{
    $res = mysqli_query(db(), "SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
    while ($row = mysqli_fetch_assoc($res)) {
        notify_user((int) $row['id'], $type, $title, $message, $link);
    }
}

// ---------------------------------------------------------------------------
// Doctor chat presence
// ---------------------------------------------------------------------------

/** Marks the logged-in user as recently active — call once per page load. Drives doctor online status. */
function touch_last_active($userId)
{
    $stmt = mysqli_prepare(db(), 'UPDATE users SET last_active_at = NOW() WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Whether a doctor is "online" for chat right now: enabled, within their
 * configured daily hours (if set), and active within the last 15 minutes.
 * $doctorRow needs chat_enabled, chat_start_time, chat_end_time, last_active_at.
 */
function doctor_chat_available(array $doctorRow)
{
    if (empty($doctorRow['chat_enabled'])) {
        return false;
    }
    if (!empty($doctorRow['chat_start_time']) && !empty($doctorRow['chat_end_time'])) {
        $now = date('H:i:s');
        if ($now < $doctorRow['chat_start_time'] || $now > $doctorRow['chat_end_time']) {
            return false;
        }
    }
    if (empty($doctorRow['last_active_at']) || strtotime($doctorRow['last_active_at']) < time() - 900) {
        return false;
    }
    return true;
}

/** Whether the chat entry point should appear at all for the current viewer (guest vs logged-in patient). */
function doctor_chat_visible(array $doctorRow, $isGuest)
{
    if (empty($doctorRow['chat_enabled'])) {
        return false;
    }
    return !$isGuest || !empty($doctorRow['chat_visible_to_guests']);
}

function doctor_chat_hours_label(array $doctorRow)
{
    if (empty($doctorRow['chat_start_time']) || empty($doctorRow['chat_end_time'])) {
        return 'Available anytime';
    }
    return 'Available ' . date('g:i A', strtotime($doctorRow['chat_start_time'])) . ' – ' . date('g:i A', strtotime($doctorRow['chat_end_time']));
}

function log_activity($userId, $role, $action, $description = '')
{
    $ip = client_ip();
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt = mysqli_prepare(db(), 'INSERT INTO activity_logs (user_id, role, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'isssss', $userId, $role, $action, $description, $ip, $ua);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function paginate($totalRows, $perPage = 12, $page = null)
{
    $page = max(1, (int) ($page ?? ($_GET['page'] ?? 1)));
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'total_pages' => $totalPages,
        'total_rows' => $totalRows,
    ];
}

function pagination_links($pagination, $baseUrl)
{
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $html = '<nav class="pagination" aria-label="Pagination">';
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        $active = $i === $pagination['page'] ? ' active' : '';
        $html .= '<a class="page-link' . $active . '" href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a>';
    }
    $html .= '</nav>';
    return $html;
}

// ---------------------------------------------------------------------------
// SEO
// ---------------------------------------------------------------------------

/** Builds the full sitemap XML. Used by both the always-fresh sitemap.php
 * (dynamic, generated per-request) and the admin "Generate Sitemap" action
 * (writes the same output to a static sitemap.xml file at the project root). */
function build_sitemap_xml()
{
    $db = db();
    $staticPages = ['/', '/doctors', '/specializations', '/products', '/blog', '/about', '/contact', '/faq', '/privacy-policy', '/terms', '/doctor-register', '/login', '/register'];

    $doctors = mysqli_query($db, "SELECT slug, updated_at FROM doctors WHERE verification_status = 'verified'");
    $specs = mysqli_query($db, 'SELECT slug FROM specializations WHERE is_active = 1');
    $blogPosts = mysqli_query($db, "SELECT slug, published_at FROM blog_posts WHERE status = 'published'");
    $storeProducts = mysqli_query($db, "
        SELECT dp.slug, dp.created_at FROM doctor_products dp JOIN doctors d ON d.id = dp.doctor_id
        WHERE dp.is_active = 1 AND d.is_premium = 1 AND d.verification_status = 'verified'
    ");

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($staticPages as $path) {
        $xml .= '<url><loc>' . e(APP_URL . $path) . '</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>' . "\n";
    }
    while ($d = mysqli_fetch_assoc($doctors)) {
        $xml .= '<url><loc>' . e(APP_URL . '/doctor-profile?slug=' . $d['slug']) . '</loc><lastmod>' . date('Y-m-d', strtotime($d['updated_at'])) . '</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>' . "\n";
    }
    while ($s = mysqli_fetch_assoc($specs)) {
        $xml .= '<url><loc>' . e(APP_URL . '/doctors?specialization=' . $s['slug']) . '</loc><changefreq>weekly</changefreq><priority>0.6</priority></url>' . "\n";
    }
    while ($b = mysqli_fetch_assoc($blogPosts)) {
        $xml .= '<url><loc>' . e(APP_URL . '/blog-post?slug=' . $b['slug']) . '</loc><lastmod>' . date('Y-m-d', strtotime($b['published_at'])) . '</lastmod><changefreq>monthly</changefreq><priority>0.5</priority></url>' . "\n";
    }
    while ($p = mysqli_fetch_assoc($storeProducts)) {
        $xml .= '<url><loc>' . e(APP_URL . '/product-detail?slug=' . $p['slug']) . '</loc><lastmod>' . date('Y-m-d', strtotime($p['created_at'])) . '</lastmod><changefreq>weekly</changefreq><priority>0.6</priority></url>' . "\n";
    }

    $xml .= '</urlset>';
    return $xml;
}
