<?php
/**
 * Core reusable procedural helpers shared by every page. mysqli + prepared
 * statements only, no ORM, no classes.
 */
if (!defined('APP_LOADED')) {
    http_response_code(403);
    exit('Direct access forbidden.');
}

// ---------------------------------------------------------------------
// Database helpers — thin wrappers around mysqli prepared statements so
// every query in the app is parameterized (SQL injection protection)
// without repeating prepare/bind/execute boilerplate everywhere.
// ---------------------------------------------------------------------

function db_run($conn, $sql, $params = [])
{
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        error_log('SQL prepare failed: ' . mysqli_error($conn) . ' | ' . $sql);
        return false;
    }
    if (!empty($params)) {
        $types = '';
        foreach ($params as $p) {
            if (is_int($p)) {
                $types .= 'i';
            } elseif (is_float($p)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

function db_select($conn, $sql, $params = [])
{
    $stmt = db_run($conn, $sql, $params);
    if (!$stmt) {
        return [];
    }
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

function db_select_one($conn, $sql, $params = [])
{
    $rows = db_select($conn, $sql, $params);
    return $rows[0] ?? null;
}

function db_insert_get_id($conn, $sql, $params = [])
{
    $stmt = db_run($conn, $sql, $params);
    if (!$stmt) {
        return false;
    }
    $id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    return $id;
}

function db_execute($conn, $sql, $params = [])
{
    $stmt = db_run($conn, $sql, $params);
    if (!$stmt) {
        return false;
    }
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $affected;
}

function db_count($conn, $sql, $params = [])
{
    $row = db_select_one($conn, $sql, $params);
    if (!$row) {
        return 0;
    }
    return (int) reset($row);
}

// ---------------------------------------------------------------------
// Output escaping / input sanitization
// ---------------------------------------------------------------------

function e($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function clean_input($value)
{
    return trim((string) ($value ?? ''));
}

function slugify($text)
{
    $text = preg_replace('~[^\pL\d]+~u', '-', (string) $text);
    $text = trim(iconv('UTF-8', 'ASCII//TRANSLIT', $text) ?: $text, '-');
    $text = strtolower(preg_replace('~[^-\w]+~', '', $text));
    return $text !== '' ? $text : 'n-a';
}

function unique_slug($conn, $table, $baseText, $excludeId = null)
{
    $slug = slugify($baseText);
    $original = $slug;
    $i = 1;
    while (true) {
        $sql = "SELECT id FROM `$table` WHERE slug = ?" . ($excludeId ? ' AND id != ?' : '');
        $params = $excludeId ? [$slug, (int) $excludeId] : [$slug];
        if (!db_select_one($conn, $sql, $params)) {
            return $slug;
        }
        $i++;
        $slug = $original . '-' . $i;
    }
}

// ---------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------

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

function verify_csrf()
{
    $token = $_POST['csrf_token'] ?? '';
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(419);
        exit('Your session expired. Please go back and try again.');
    }
    return true;
}

// ---------------------------------------------------------------------
// URL helper — makes every internal link/redirect subfolder-aware and
// extension-less. Every href/action/redirect() in the app should route
// through this (or the BASE_PATH-derived constants) rather than using a
// raw "/xxx.php" string, so a deployment move (e.g. domain root <-> a
// /beta subfolder) is a one-constant change instead of a find/replace.
// ---------------------------------------------------------------------

function url($path = '')
{
    $path = (string) $path;
    if ($path === '') {
        return BASE_PATH !== '' ? BASE_PATH . '/' : '/';
    }
    // Leave full external URLs (and protocol-relative ones) untouched.
    if (preg_match('#^([a-z][a-z0-9+.\-]*:)?//#i', $path)) {
        return $path;
    }
    // Strip a trailing .php so generated links are extension-less;
    // .htaccess maps the clean URL back to the real file server-side.
    $path = preg_replace('/\.php(\?|#|$)/', '$1', $path);
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return BASE_PATH . $path;
}

// ---------------------------------------------------------------------
// Flash messages / redirects
// ---------------------------------------------------------------------

function redirect($path)
{
    header('Location: ' . url($path));
    exit;
}

function flash_set($type, $message)
{
    $_SESSION['flash'][$type] = $message;
}

function flash_get($type)
{
    if (!empty($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

// ---------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------

function format_price($amount, $currencySymbol = '$')
{
    return $currencySymbol . number_format((float) $amount, 2);
}

function format_date($date, $format = 'M j, Y')
{
    if (!$date) {
        return '';
    }
    $ts = is_numeric($date) ? (int) $date : strtotime($date);
    return $ts ? date($format, $ts) : '';
}

function time_ago($datetime)
{
    $ts = is_numeric($datetime) ? (int) $datetime : strtotime((string) $datetime);
    if (!$ts) {
        return '';
    }
    $diff = time() - $ts;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        return floor($diff / 60) . 'm ago';
    }
    if ($diff < 86400) {
        return floor($diff / 3600) . 'h ago';
    }
    if ($diff < 2592000) {
        return floor($diff / 86400) . 'd ago';
    }
    return format_date($datetime);
}

// ---------------------------------------------------------------------
// Small shared UI helpers (used across admin/provider/customer views)
// ---------------------------------------------------------------------

function status_badge($status)
{
    return '<span class="status-chip ' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

function require_field($value, $label, array &$errors)
{
    if (clean_input($value) === '') {
        $errors[] = $label . ' is required.';
        return false;
    }
    return true;
}

/**
 * Renders a Leaflet/OpenStreetMap embed. Requires leaflet.css/js to already
 * be loaded on the page (set $extraCss/$extraJs before including header.php).
 * No API key needed — this is the default "maps_provider" setting.
 */
function render_leaflet_map($lat, $lng, $popup = '', $height = '320px')
{
    if ($lat === null || $lng === null || $lat === '' || $lng === '') {
        return '<div class="empty-state" style="padding:32px;"><div class="icon-wrap"><i class="bi bi-map"></i></div><h4>No map location set</h4></div>';
    }
    $mapId = 'map_' . bin2hex(random_bytes(4));
    $lat = (float) $lat;
    $lng = (float) $lng;
    $popupJson = json_encode($popup, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
    return <<<HTML
<div id="{$mapId}" style="height:{$height};border-radius:var(--radius-md);overflow:hidden;border:1px solid var(--border);"></div>
<script>
(function() {
  var map = L.map('{$mapId}', { scrollWheelZoom: false }).setView([{$lat}, {$lng}], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  L.marker([{$lat}, {$lng}]).addTo(map).bindPopup({$popupJson});
})();
</script>
HTML;
}

function render_fav_button($conn, $type, $id)
{
    $isFav = false;
    if (is_logged_in()) {
        $isFav = (bool) db_select_one($conn, 'SELECT id FROM favorites WHERE user_id = ? AND favoritable_type = ? AND favoritable_id = ?', [(int) current_user_id(), $type, (int) $id]);
    }
    $csrf = is_logged_in() ? csrf_token() : '';
    return '<button class="fav-btn' . ($isFav ? ' is-fav' : '') . '" type="button" data-fav-type="' . e($type) . '" data-fav-id="' . (int) $id . '" data-csrf="' . e($csrf) . '">'
        . '<i class="bi bi-heart' . ($isFav ? '-fill' : '') . '"></i></button>';
}

function paginate($conn, $countSql, $countParams, $page, $perPage = 20)
{
    $total = db_count($conn, $countSql, $countParams);
    $totalPages = max(1, (int) ceil($total / $perPage));
    $page = max(1, min($page, $totalPages));
    return [
        'total' => $total,
        'total_pages' => $totalPages,
        'page' => $page,
        'offset' => ($page - 1) * $perPage,
        'per_page' => $perPage,
    ];
}

// ---------------------------------------------------------------------
// File uploads
// ---------------------------------------------------------------------

function upload_file($fileField, $subDir, $allowedExt = ['jpg', 'jpeg', 'png', 'webp'], $maxSizeMb = 5)
{
    if (empty($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return ['ok' => false, 'error' => null];
    }
    $file = $_FILES[$fileField];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed. Please try again.'];
    }
    if ($file['size'] > $maxSizeMb * 1024 * 1024) {
        return ['ok' => false, 'error' => "File is larger than {$maxSizeMb}MB."];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return ['ok' => false, 'error' => 'Unsupported file type: .' . e($ext)];
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    if (!in_array($mime, $allowedMimes, true)) {
        return ['ok' => false, 'error' => 'File content does not match an allowed image type.'];
    }

    $targetDir = UPLOAD_PATH . '/' . trim($subDir, '/');
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    $targetPath = $targetDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['ok' => false, 'error' => 'Could not save uploaded file.'];
    }

    return ['ok' => true, 'path' => UPLOAD_URL . '/' . trim($subDir, '/') . '/' . $filename];
}

// ---------------------------------------------------------------------
// Settings (site-wide CMS key/value store)
// ---------------------------------------------------------------------

function get_setting($conn, $key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db_select($conn, 'SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

// ---------------------------------------------------------------------
// Logging
// ---------------------------------------------------------------------

function log_activity($conn, $userId, $action, $description = null)
{
    db_execute(
        $conn,
        'INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)',
        [$userId, $action, $description, $_SERVER['REMOTE_ADDR'] ?? '']
    );
}

function log_audit($conn, $adminId, $entityType, $entityId, $action, $oldValue = null, $newValue = null)
{
    db_execute(
        $conn,
        'INSERT INTO audit_logs (admin_id, entity_type, entity_id, action, old_value, new_value) VALUES (?, ?, ?, ?, ?, ?)',
        [$adminId, $entityType, $entityId, $action, $oldValue ? json_encode($oldValue) : null, $newValue ? json_encode($newValue) : null]
    );
}
