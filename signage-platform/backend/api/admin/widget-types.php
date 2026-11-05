<?php
// GET all widget types. POST {widget_key, availability?, status?, is_premium?, default_config_json?}
require __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();
if (request_method() === 'POST') {
    $in = input();
    $key = v_str($in, 'widget_key', true, 60);
    $w = db_one('SELECT * FROM widget_types WHERE widget_key = ?', [$key]);
    if (!$w) not_found('Widget type');
    $cfg = $in['default_config_json'] ?? null;
    if (is_string($cfg)) $cfg = json_decode($cfg, true);
    db_run('UPDATE widget_types SET availability = ?, status = ?, is_premium = ?, default_config_json = ? WHERE widget_key = ?', [
        v_enum($in, 'availability', ['stable', 'beta', 'planned'], false, $w['availability']), v_enum($in, 'status', ['active', 'inactive'], false, $w['status']),
        v_bool($in, 'is_premium', (bool)$w['is_premium']), is_array($cfg) ? jencode($cfg) : $w['default_config_json'], $key]);
    log_activity((int)$admin['user_id'], 'admin.widget.update', 'widget_type', (int)$w['widget_type_id'], "Updated widget {$key}");
}
ok(shape_rows(db_all('SELECT wt.*, (SELECT COUNT(*) FROM screen_widgets sw WHERE sw.widget_type = wt.widget_key) AS usage_count FROM widget_types wt ORDER BY sort_order'), ['default_config_json'], ['default_width', 'default_height']),
    request_method() === 'POST' ? 'Widget type updated' : 'OK');
