<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$db = db();
$allowedKeys = ['site_name', 'site_tagline', 'contact_email', 'contact_phone', 'contact_address', 'currency_symbol', 'maintenance_mode'];

$stmt = mysqli_prepare($db, 'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
foreach ($allowedKeys as $key) {
    $value = $key === 'maintenance_mode' ? (!empty($_POST[$key]) ? '1' : '0') : clean($_POST[$key] ?? '');
    mysqli_stmt_bind_param($stmt, 'ss', $key, $value);
    mysqli_stmt_execute($stmt);
}
mysqli_stmt_close($stmt);

log_activity($_SESSION['user_id'], 'admin', 'update_settings', 'Updated site settings');
json_response(true, [], 'Settings saved.');
