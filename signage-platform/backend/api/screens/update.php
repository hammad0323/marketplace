<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'screens.edit');
$in = input();
$s = owned_row('screens', 'screen_id', v_int($in, 'screen_id', true, 1), $u);
$sets = []; $params = [];
if (array_key_exists('screen_name', $in)) { $sets[] = 'screen_name = ?'; $params[] = v_str($in, 'screen_name', true, 150); }
if (array_key_exists('description', $in)) { $sets[] = 'description = ?'; $params[] = v_str($in, 'description', false, 255); }
if (array_key_exists('default_duration', $in)) { $sets[] = 'default_duration = ?'; $params[] = v_int($in, 'default_duration', true, 1, 86400); }
if (array_key_exists('is_favorite', $in)) { $sets[] = 'is_favorite = ?'; $params[] = v_bool($in, 'is_favorite', false); }
if (array_key_exists('expires_at', $in)) { $sets[] = 'expires_at = ?'; $params[] = v_datetime($in, 'expires_at'); }
if (array_key_exists('folder_id', $in)) {
    $fid = v_int($in, 'folder_id', false, 0);
    if ($fid) owned_row('screen_folders', 'folder_id', $fid, $u);
    $sets[] = 'folder_id = ?'; $params[] = $fid ?: null;
}
if (array_key_exists('status', $in) && v_enum($in, 'status', ['archived', 'draft']) !== null) { $sets[] = 'status = ?'; $params[] = $in['status']; }
if (array_key_exists('background', $in) && is_array($in['background'])) {
    $bg = clean_background($in['background'], (int)$s['user_id']);
    $sets[] = 'background_type = ?, background_value = ?, background_media_id = ?, background_config_json = ?, has_unpublished_changes = 1, layout_version = layout_version + 1';
    array_push($params, $bg['type'], $bg['value'], $bg['media_id'], jencode($bg['config']));
}
if (!$sets) fail('Nothing to update', 400, 'VALIDATION');
db_run('UPDATE screens SET ' . implode(', ', $sets) . ' WHERE screen_id = ?', array_merge($params, [$s['screen_id']]));
log_activity((int)$u['user_id'], 'screen.update', 'screen', (int)$s['screen_id'], 'Updated screen settings');
ok(present_screen(db_one('SELECT * FROM screens WHERE screen_id = ?', [$s['screen_id']]), true), 'Screen updated');
