<?php
/**
 * Role permissions + per-user overrides, and plan-based quantitative limits.
 * Every check here is enforced server-side; the Flutter UI only mirrors it.
 */

function user_permission_keys(int $userId): array
{
    static $cache = [];
    if (isset($cache[$userId])) return $cache[$userId];
    $rows = db_all(
        'SELECT p.permission_key FROM users u
           JOIN role_permissions rp ON rp.role_id = u.role_id
           JOIN permissions p ON p.permission_id = rp.permission_id
          WHERE u.user_id = ?', [$userId]);
    $keys = array_fill_keys(array_column($rows, 'permission_key'), true);
    foreach (db_all('SELECT p.permission_key, up.allowed FROM user_permissions up JOIN permissions p USING (permission_id) WHERE up.user_id = ?', [$userId]) as $o) {
        if ($o['allowed']) $keys[$o['permission_key']] = true; else unset($keys[$o['permission_key']]);
    }
    return $cache[$userId] = array_keys($keys);
}

function has_permission(array $user, string $key): bool
{
    return in_array($key, user_permission_keys((int)$user['user_id']), true);
}

function require_permission(array $user, string $key): void
{
    if (!has_permission($user, $key)) fail('You do not have permission to perform this action', 403, 'FORBIDDEN');
}

function effective_limits(int $userId): array
{
    $l = db_one('SELECT * FROM v_user_effective_limits WHERE user_id = ?', [$userId]) ?? [];
    $allowed = jdecode($l['allowed_widgets_json'] ?? null, ['*']);
    if (is_string($allowed)) $allowed = jdecode($allowed, ['*']);
    return [
        'plan_id'        => $l['plan_id'] ?? null,
        'plan_name'      => $l['plan_name'] ?? null,
        'max_screens'    => (int)($l['max_screens'] ?? 0),
        'max_playlists'  => (int)($l['max_playlists'] ?? 0),
        'max_devices'    => (int)($l['max_devices'] ?? 0),
        'max_storage_mb' => (int)($l['max_storage_mb'] ?? 0),
        'max_upload_mb'  => (int)($l['max_upload_mb'] ?? 10),
        'allowed_widgets'           => is_array($allowed) ? array_values($allowed) : ['*'],
        'can_upload_video'          => (bool)($l['can_upload_video'] ?? false),
        'can_use_premium_templates' => (bool)($l['can_use_premium_templates'] ?? false),
    ];
}

function user_usage(int $userId): array
{
    $u = db_one('SELECT * FROM v_user_usage WHERE user_id = ?', [$userId]) ?? [];
    return [
        'screens_used'       => (int)($u['screens_used'] ?? 0),
        'playlists_used'     => (int)($u['playlists_used'] ?? 0),
        'devices_used'       => (int)($u['devices_used'] ?? 0),
        'storage_used_bytes' => (int)($u['storage_used_bytes'] ?? 0),
    ];
}

/**
 * Enforce a count limit. MUST be called inside a transaction: locks the user
 * row so two concurrent requests cannot both pass the check.
 * $resource: screens | playlists | devices
 */
function assert_limit(int $userId, string $resource, int $adding = 1): void
{
    if (!db_in_transaction()) throw new LogicException('assert_limit requires a transaction');
    db_run('SELECT user_id FROM users WHERE user_id = ? FOR UPDATE', [$userId]);
    $limits = effective_limits($userId);
    $max = $limits["max_{$resource}"];
    if ($max === -1) return;
    $used = user_usage($userId)["{$resource}_used"];
    if ($used + $adding > $max) {
        $label = ['screens' => 'screens', 'playlists' => 'playlists', 'devices' => 'connected displays'][$resource];
        $code  = 'LIMIT_' . strtoupper($resource);
        $msg   = "You have used {$used} of {$max} {$label} allowed on your plan. Please ask your administrator to increase your limit.";
        notify($userId, 'Limit reached', $msg, 'limit_reached', '/' . $resource);
        db_commit();   // keep the notification
        fail($msg, 403, $code);
    }
}

function assert_storage(int $userId, int $addingBytes): void
{
    $limits = effective_limits($userId);
    if ($limits['max_upload_mb'] !== -1 && $addingBytes > $limits['max_upload_mb'] * 1048576) {
        fail("This file is larger than the {$limits['max_upload_mb']} MB per-file limit of your plan.", 413, 'FILE_TOO_LARGE');
    }
    if ($limits['max_storage_mb'] === -1) return;
    $used = user_usage($userId)['storage_used_bytes'];
    if ($used + $addingBytes > $limits['max_storage_mb'] * 1048576) {
        $usedMb = round($used / 1048576, 1);
        fail("Not enough storage: {$usedMb} MB of {$limits['max_storage_mb']} MB used. Delete unused media or ask your administrator for more space.", 403, 'LIMIT_STORAGE');
    }
}

function widget_allowed(array $limits, string $widgetKey): bool
{
    return in_array('*', $limits['allowed_widgets'], true) || in_array($widgetKey, $limits['allowed_widgets'], true);
}

/** Admins bypass ownership; regular users must own the row. Returns row or 404. */
function owned_row(string $table, string $pk, int $id, array $user, bool $includeDeleted = false): array
{
    static $allowed = ['screens' => 1, 'playlists' => 1, 'devices' => 1, 'media_library' => 1, 'media_folders' => 1,
        'screen_folders' => 1, 'locations' => 1, 'emergency_messages' => 1];
    if (!isset($allowed[$table])) throw new InvalidArgumentException('table');
    $softDelete = in_array($table, ['screens', 'playlists', 'devices', 'media_library'], true);
    $sql = "SELECT * FROM {$table} WHERE {$pk} = ?";
    $params = [$id];
    if (!is_admin($user)) { $sql .= ' AND user_id = ?'; $params[] = (int)$user['user_id']; }
    if ($softDelete && !$includeDeleted) $sql .= ' AND deleted_at IS NULL';
    $row = db_one($sql, $params);
    if (!$row) {
        $names = ['screens' => 'Screen', 'playlists' => 'Playlist', 'devices' => 'Device', 'media_library' => 'Media', 'media_folders' => 'Folder',
            'screen_folders' => 'Folder', 'locations' => 'Location', 'emergency_messages' => 'Message'];
        not_found($names[$table]);
    }
    return $row;
}
