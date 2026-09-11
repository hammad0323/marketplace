<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

/**
 * Uploads a branding image (logo/favicon) and rejects SVGs carrying embedded
 * scripts — handle_upload() only checks the MIME type for extensions it
 * knows about, and SVG (an XML format that can legally contain <script>) is
 * outside that list, so this closes the gap for these two admin-only fields
 * without a general-purpose SVG sanitizer.
 */
function upload_branding_asset($fileKey, array $allowedExt, $maxBytes)
{
    [$ok, $result] = handle_upload($fileKey, 'branding', $allowedExt, $maxBytes);
    if (!$ok) {
        return [false, $result];
    }
    if (strtolower(pathinfo($result, PATHINFO_EXTENSION)) === 'svg') {
        $contents = file_get_contents(UPLOAD_PATH . '/' . $result);
        if ($contents === false || preg_match('/<script|on[a-z]+\s*=/i', $contents)) {
            @unlink(UPLOAD_PATH . '/' . $result);
            return [false, 'That SVG file was rejected because it contains embedded scripts.'];
        }
    }
    return [true, $result];
}

$db = db();
$allowedKeys = ['site_name', 'site_tagline', 'contact_email', 'contact_phone', 'contact_address', 'currency_symbol', 'maintenance_mode'];

$stmt = mysqli_prepare($db, 'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
foreach ($allowedKeys as $key) {
    $value = $key === 'maintenance_mode' ? (!empty($_POST[$key]) ? '1' : '0') : clean($_POST[$key] ?? '');
    mysqli_stmt_bind_param($stmt, 'ss', $key, $value);
    mysqli_stmt_execute($stmt);
}

if (!empty($_FILES['logo']['name'])) {
    [$ok, $result] = upload_branding_asset('logo', ['png', 'jpg', 'jpeg', 'webp', 'svg'], 2 * 1024 * 1024);
    if (!$ok) {
        json_response(false, [], $result);
    }
    $key = 'site_logo';
    mysqli_stmt_bind_param($stmt, 'ss', $key, $result);
    mysqli_stmt_execute($stmt);
} elseif (!empty($_POST['remove_logo'])) {
    $key = 'site_logo';
    $empty = '';
    mysqli_stmt_bind_param($stmt, 'ss', $key, $empty);
    mysqli_stmt_execute($stmt);
}

if (!empty($_FILES['favicon']['name'])) {
    [$ok, $result] = upload_branding_asset('favicon', ['ico', 'png', 'svg'], 1024 * 1024);
    if (!$ok) {
        json_response(false, [], $result);
    }
    $key = 'site_favicon';
    mysqli_stmt_bind_param($stmt, 'ss', $key, $result);
    mysqli_stmt_execute($stmt);
} elseif (!empty($_POST['remove_favicon'])) {
    $key = 'site_favicon';
    $empty = '';
    mysqli_stmt_bind_param($stmt, 'ss', $key, $empty);
    mysqli_stmt_execute($stmt);
}

mysqli_stmt_close($stmt);

log_activity($_SESSION['user_id'], 'admin', 'update_settings', 'Updated site settings');
json_response(true, [], 'Settings saved.');
