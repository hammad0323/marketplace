<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, [], 'Invalid request method.');
}
require_csrf_or_fail();
require_role_page_or_json('admin');

$db = db();
$textKeys = ['smtp_host', 'smtp_username', 'smtp_password', 'smtp_from_email', 'smtp_from_name'];
$port = max(1, min(65535, (int) ($_POST['smtp_port'] ?? 587)));
$encryption = in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_encryption'] : 'tls';
$enabled = !empty($_POST['email_notifications_enabled']) ? '1' : '0';

$stmt = mysqli_prepare($db, 'INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
foreach ($textKeys as $key) {
    $value = clean($_POST[$key] ?? '');
    mysqli_stmt_bind_param($stmt, 'ss', $key, $value);
    mysqli_stmt_execute($stmt);
}
$portStr = (string) $port;
foreach (['smtp_port' => $portStr, 'smtp_encryption' => $encryption, 'email_notifications_enabled' => $enabled] as $key => $value) {
    mysqli_stmt_bind_param($stmt, 'ss', $key, $value);
    mysqli_stmt_execute($stmt);
}
mysqli_stmt_close($stmt);

log_activity($_SESSION['user_id'], 'admin', 'update_email_settings', 'Updated SMTP/email settings');
json_response(true, [], 'Email settings saved.');
