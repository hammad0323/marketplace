<?php
function admin_user_row(int $userId): array
{
    $u = db_one('SELECT u.*, r.role_key FROM users u JOIN roles r USING (role_id) WHERE u.user_id = ? AND u.deleted_at IS NULL', [$userId]);
    if (!$u) not_found('User');
    return $u;
}

function admin_present_user(array $u, bool $full = false): array
{
    $uid = (int)$u['user_id'];
    $out = present_user($u);
    $out['plan'] = db_one("SELECT p.plan_id, p.plan_name, s.start_date, s.end_date, s.subscription_id FROM user_subscriptions s JOIN plans p USING (plan_id)
                           WHERE s.user_id = ? AND s.status = 'active' ORDER BY s.start_date DESC, s.subscription_id DESC LIMIT 1", [$uid]);
    $out['limits'] = effective_limits($uid);
    $out['usage'] = user_usage($uid);
    $ov = db_one('SELECT max_screens, max_playlists, max_devices, max_storage_mb, max_upload_mb, allowed_widgets_json, can_upload_video, can_use_premium_templates FROM user_limits WHERE user_id = ?', [$uid]);
    $out['limit_overrides'] = $ov ? shape_row($ov, ['allowed_widgets_json']) : null;
    if ($full) {
        $out['permissions'] = user_permission_keys($uid);
        $out['permission_overrides'] = db_all('SELECT p.permission_key, up.allowed FROM user_permissions up JOIN permissions p USING (permission_id) WHERE up.user_id = ?', [$uid]);
        $out['screens'] = array_map(fn($s) => present_screen($s, false), db_all('SELECT * FROM screens WHERE user_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 50', [$uid]));
        $out['playlists'] = db_all('SELECT playlist_id, playlist_name, status, published_version, updated_at FROM playlists WHERE user_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 50', [$uid]);
        $out['devices'] = db_all('SELECT device_id, device_name, device_code, status, last_heartbeat, app_version FROM devices WHERE user_id = ? AND deleted_at IS NULL', [$uid]);
        $out['activity'] = db_all('SELECT activity_id, action, description, ip_address, created_at FROM activity_logs WHERE user_id = ? ORDER BY activity_id DESC LIMIT 30', [$uid]);
    }
    return $out;
}

/** Apply limits/permissions from admin input. */
function admin_apply_limits(int $userId, array $in, int $adminId): void
{
    $cols = ['max_screens', 'max_playlists', 'max_devices', 'max_storage_mb', 'max_upload_mb'];
    $has = array_intersect(array_keys($in), array_merge($cols, ['allowed_widgets', 'can_upload_video', 'can_use_premium_templates', 'unlimited_screens']));
    if ($has) {
        $cur = db_one('SELECT * FROM user_limits WHERE user_id = ?', [$userId]) ?? [];
        $vals = [];
        foreach ($cols as $c) {
            if (array_key_exists($c, $in)) {
                $vals[$c] = ($in[$c] === null || $in[$c] === '') ? null : v_int($in, $c, false, -1, 10000000);
            } else $vals[$c] = $cur[$c] ?? null;
        }
        if (!empty($in['unlimited_screens'])) $vals['max_screens'] = -1;
        $widgets = array_key_exists('allowed_widgets', $in) ? ($in['allowed_widgets'] === null ? null : jencode(array_values(array_map('strval', (array)$in['allowed_widgets'])))) : ($cur['allowed_widgets_json'] ?? null);
        $video = array_key_exists('can_upload_video', $in) ? ($in['can_upload_video'] === null ? null : (int)v_bool($in, 'can_upload_video')) : ($cur['can_upload_video'] ?? null);
        $prem = array_key_exists('can_use_premium_templates', $in) ? ($in['can_use_premium_templates'] === null ? null : (int)v_bool($in, 'can_use_premium_templates')) : ($cur['can_use_premium_templates'] ?? null);
        db_run('INSERT INTO user_limits (user_id, max_screens, max_playlists, max_devices, max_storage_mb, max_upload_mb, allowed_widgets_json, can_upload_video, can_use_premium_templates, updated_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE max_screens = VALUES(max_screens), max_playlists = VALUES(max_playlists), max_devices = VALUES(max_devices), max_storage_mb = VALUES(max_storage_mb),
                    max_upload_mb = VALUES(max_upload_mb), allowed_widgets_json = VALUES(allowed_widgets_json), can_upload_video = VALUES(can_upload_video),
                    can_use_premium_templates = VALUES(can_use_premium_templates), updated_by = VALUES(updated_by)',
            [$userId, $vals['max_screens'], $vals['max_playlists'], $vals['max_devices'], $vals['max_storage_mb'], $vals['max_upload_mb'], $widgets, $video, $prem, $adminId]);
    }
    if (isset($in['permissions']) && is_array($in['permissions'])) {
        // {permission_key: true|false|null}  null = follow role
        foreach ($in['permissions'] as $key => $allowed) {
            $pid = db_val('SELECT permission_id FROM permissions WHERE permission_key = ?', [(string)$key]);
            if (!$pid) continue;
            if ($allowed === null) db_run('DELETE FROM user_permissions WHERE user_id = ? AND permission_id = ?', [$userId, $pid]);
            else db_run('INSERT INTO user_permissions (user_id, permission_id, allowed) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE allowed = VALUES(allowed)',
                [$userId, $pid, filter_var($allowed, FILTER_VALIDATE_BOOLEAN)]);
        }
    }
    if ($planId = v_int($in, 'plan_id', false, 1)) {
        if (!db_val("SELECT 1 FROM plans WHERE plan_id = ?", [$planId])) fail('Plan not found', 400, 'VALIDATION');
        $cur = db_one("SELECT * FROM user_subscriptions WHERE user_id = ? AND status = 'active' ORDER BY subscription_id DESC LIMIT 1", [$userId]);
        $end = array_key_exists('plan_end_date', $in) ? v_date($in, 'plan_end_date') : ($cur['end_date'] ?? null);
        if (!$cur || (int)$cur['plan_id'] !== $planId) {
            db_run("UPDATE user_subscriptions SET status = 'cancelled' WHERE user_id = ? AND status = 'active'", [$userId]);
            db_run("INSERT INTO user_subscriptions (user_id, plan_id, start_date, end_date, status, notes) VALUES (?, ?, UTC_DATE(), ?, 'active', 'Assigned by admin')", [$userId, $planId, $end]);
        } elseif (array_key_exists('plan_end_date', $in)) {
            db_run('UPDATE user_subscriptions SET end_date = ? WHERE subscription_id = ?', [$end, $cur['subscription_id']]);
        }
    }
}

function generate_password(): string
{
    $sets = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnpqrstuvwxyz', '23456789', '@#%+=!'];
    $p = '';
    foreach ($sets as $s) $p .= $s[random_int(0, strlen($s) - 1)];
    $all = implode('', $sets);
    for ($i = 0; $i < 8; $i++) $p .= $all[random_int(0, strlen($all) - 1)];
    return str_shuffle($p);
}
