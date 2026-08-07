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

function notify_user($userId, $type, $title, $message, $link = null)
{
    $stmt = mysqli_prepare(db(), 'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'issss', $userId, $type, $title, $message, $link);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
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
