<?php
// Unauthenticated: branding + client timing settings.
require __DIR__ . '/../../includes/bootstrap.php';
$out = [];
foreach (db_all('SELECT setting_key, setting_value, value_type FROM app_settings WHERE is_public = 1') as $r) $out[$r['setting_key']] = app_setting($r['setting_key']);
$out['api_version'] = API_VERSION;
$out['server_time'] = gmdate(DATE_ATOM);
ok($out);
