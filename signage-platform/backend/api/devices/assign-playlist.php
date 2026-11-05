<?php
/**
 * Replace a device's playlists. Body: {device_id, playlist_ids: [..], default_playlist_id?}
 * or simple form {device_id, playlist_id} (single playlist, becomes default).
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'devices.manage');
$in = input();
db_begin();
$d = owned_row('devices', 'device_id', v_int($in, 'device_id', true, 1), $u);
if ($d['user_id'] === null) fail('Connect this display before assigning content', 400, 'VALIDATION');
$ids = isset($in['playlist_ids']) ? v_int_list($in, 'playlist_ids') : (v_present($in, 'playlist_id') ? [v_int($in, 'playlist_id', true, 1)] : []);
$default = v_int($in, 'default_playlist_id', false, 1) ?? ($ids[0] ?? null);
if ($default && !in_array($default, $ids, true)) $ids[] = $default;
foreach ($ids as $pid) {
    $p = db_one('SELECT playlist_id FROM playlists WHERE playlist_id = ? AND user_id = ? AND deleted_at IS NULL', [$pid, $d['user_id']]);
    if (!$p) fail("Playlist #{$pid} was not found in this account", 400, 'VALIDATION');
}
db_run('DELETE FROM device_playlists WHERE device_id = ?', [$d['device_id']]);
foreach ($ids as $pid) {
    db_run('INSERT INTO device_playlists (device_id, playlist_id, user_id, is_default, assigned_by) VALUES (?, ?, ?, ?, ?)',
        [$d['device_id'], $pid, $d['user_id'], $pid === $default, $u['user_id']]);
}
$unpublished = db_all("SELECT playlist_name FROM playlists WHERE playlist_id IN (" . placeholders($ids) . ") AND (published_version IS NULL)", $ids ?: [0]);
db_run('INSERT INTO device_logs (device_id, log_type, message) VALUES (?, "info", ?)', [$d['device_id'], 'Content assignment changed']);
log_activity((int)$u['user_id'], 'device.assign', 'device', (int)$d['device_id'], 'Assigned ' . count($ids) . ' playlist(s)');
db_commit();
$msg = $ids ? 'Content assigned. The display will update within a minute.' : 'All content removed from this display';
if ($unpublished) $msg .= ' Note: publish ' . implode(', ', array_column($unpublished, 'playlist_name')) . ' so it can play.';
ok(present_device(db_one(device_select_sql() . ' WHERE d.device_id = ?', [$d['device_id']])), $msg);
