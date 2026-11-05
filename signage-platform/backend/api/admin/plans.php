<?php
// GET list (admin). POST {action: save|delete, plan_id?, plan_key, plan_name, ... , features: {...}}
require __DIR__ . '/../../includes/bootstrap.php';
$admin = require_admin();
if (request_method() === 'POST') {
    $in = input();
    $action = v_enum($in, 'action', ['save', 'delete'], false, 'save');
    if ($action === 'delete') {
        $id = v_int($in, 'plan_id', true, 1);
        if (db_val("SELECT COUNT(*) FROM user_subscriptions WHERE plan_id = ? AND status = 'active'", [$id])) fail('This plan has active users. Move them to another plan first.', 409, 'IN_USE');
        db_run('DELETE FROM plans WHERE plan_id = ? AND is_default = 0', [$id]);
        log_activity((int)$admin['user_id'], 'admin.plan.delete', 'plan', $id, 'Deleted plan');
        ok(null, 'Plan deleted');
    }
    $features = is_array($in['features'] ?? null) ? $in['features'] : (is_array($in['features_json'] ?? null) ? $in['features_json'] : []);
    $vals = [
        v_str($in, 'plan_name', true, 100), v_str($in, 'description', false, 255), v_num($in, 'price_monthly', false, 0, 1000000, 0), v_num($in, 'price_yearly', false, 0, 1000000, 0),
        strtoupper(v_str($in, 'currency', false, 3) ?? 'USD'), v_int($in, 'max_screens', true, -1), v_int($in, 'max_playlists', true, -1), v_int($in, 'max_devices', true, -1),
        v_int($in, 'max_storage_mb', true, -1), v_int($in, 'max_upload_mb', false, -1, 100000, 50), jencode([
            'video_upload' => (bool)($features['video_upload'] ?? false), 'premium_templates' => (bool)($features['premium_templates'] ?? false),
            'allowed_widgets' => array_values((array)($features['allowed_widgets'] ?? ['*'])), 'remote_commands' => (bool)($features['remote_commands'] ?? true),
            'analytics' => (bool)($features['analytics'] ?? false), 'emergency' => (bool)($features['emergency'] ?? true), 'locations' => (bool)($features['locations'] ?? false),
            'custom_branding' => (bool)($features['custom_branding'] ?? false)]),
        v_int($in, 'sort_order', false, 0, 1000, 0), v_enum($in, 'status', ['active', 'inactive'], false, 'active'),
    ];
    db_begin();
    if (v_bool($in, 'is_default', false)) db_run('UPDATE plans SET is_default = 0');
    if ($id = v_int($in, 'plan_id', false, 1)) {
        db_run('UPDATE plans SET plan_name = ?, description = ?, price_monthly = ?, price_yearly = ?, currency = ?, max_screens = ?, max_playlists = ?, max_devices = ?, max_storage_mb = ?, max_upload_mb = ?, features_json = ?, sort_order = ?, status = ?, is_default = IF(?, 1, is_default) WHERE plan_id = ?',
            array_merge($vals, [v_bool($in, 'is_default', false), $id]));
    } else {
        $key = preg_replace('/[^a-z0-9_]/', '_', strtolower(v_str($in, 'plan_key', false, 50) ?? $vals[0]));
        $id = db_insert('INSERT INTO plans (plan_name, description, price_monthly, price_yearly, currency, max_screens, max_playlists, max_devices, max_storage_mb, max_upload_mb, features_json, sort_order, status, is_default, plan_key) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            array_merge($vals, [v_bool($in, 'is_default', false), $key]));
    }
    log_activity((int)$admin['user_id'], 'admin.plan.save', 'plan', $id, "Saved plan {$vals[0]}");
    db_commit();
}
$rows = shape_rows(db_all("SELECT p.*, (SELECT COUNT(*) FROM user_subscriptions s WHERE s.plan_id = p.plan_id AND s.status = 'active') AS user_count FROM plans p ORDER BY sort_order, plan_id"), ['features_json']);
ok($rows, request_method() === 'POST' ? 'Plan saved' : 'OK');
