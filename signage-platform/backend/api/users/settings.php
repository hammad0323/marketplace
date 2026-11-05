<?php
// GET → {key: value}; POST {settings: {theme: "dark", ...}} (allow-listed keys)
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
$allowed = ['theme', 'language', 'brand_primary_color', 'brand_logo_media_id', 'default_orientation', 'notify_device_offline'];
if (request_method() === 'POST') {
    $s = v_array(input(), 'settings', true, 50);
    foreach ($s as $k => $v) {
        if (!in_array($k, $allowed, true)) continue;
        db_run('INSERT INTO user_settings (user_id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
            [$u['user_id'], $k, is_scalar($v) ? mb_substr((string)$v, 0, 1000) : jencode($v)]);
    }
}
$out = [];
foreach (db_all('SELECT setting_key, setting_value FROM user_settings WHERE user_id = ?', [$u['user_id']]) as $r) $out[$r['setting_key']] = $r['setting_value'];
ok((object)$out, request_method() === 'POST' ? 'Settings saved' : 'OK');
