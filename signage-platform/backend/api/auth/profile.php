<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('GET');
$u = require_user();
$uid = (int)$u['user_id'];
$settings = [];
foreach (db_all('SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?', [$uid]) as $s) $settings[$s['setting_key']] = $s['setting_value'];
ok([
    'user' => present_user($u),
    'permissions' => user_permission_keys($uid),
    'limits' => effective_limits($uid),
    'usage' => user_usage($uid),
    'settings' => (object)$settings,
    'unread_notifications' => (int)db_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$uid]),
]);
