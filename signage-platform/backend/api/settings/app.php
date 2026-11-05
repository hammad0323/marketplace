<?php
// Admin: GET all settings; POST {settings: {key: value}} updates existing keys only.
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_admin();
if (request_method() === 'POST') {
    $s = v_array(input(), 'settings', true, 100);
    db_begin();
    foreach ($s as $k => $v) {
        $row = db_one('SELECT value_type FROM app_settings WHERE setting_key = ?', [(string)$k]);
        if (!$row) continue;
        $val = match ($row['value_type']) {
            'int' => (string)(int)$v,
            'bool' => filter_var($v, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false',
            'json' => is_string($v) ? (json_decode($v) !== null ? $v : fail("{$k} must be valid JSON", 400, 'VALIDATION')) : jencode($v),
            default => mb_substr((string)$v, 0, 5000),
        };
        db_run('UPDATE app_settings SET setting_value = ? WHERE setting_key = ?', [$val, (string)$k]);
    }
    log_activity((int)$u['user_id'], 'admin.settings', 'settings', null, 'Updated settings: ' . implode(', ', array_keys($s)));
    db_commit();
}
$rows = db_all('SELECT setting_key, setting_value, value_type, is_public, description, updated_at FROM app_settings ORDER BY setting_key');
foreach ($rows as &$r) if ($r['setting_key'] === 'weather_api_key' && $r['setting_value'] !== '') $r['setting_value'] = '••••••••';
ok($rows, request_method() === 'POST' ? 'Settings saved' : 'OK');
