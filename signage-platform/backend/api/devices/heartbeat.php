<?php
/**
 * Player → every heartbeat_interval_seconds.
 * Body: {sync_token?, playback_state?, current_playlist_id?, current_screen_id?, current_content_version?,
 *        storage_total_mb?, storage_free_mb?, app_version?, resolution?, orientation?, timezone?}
 * Response: {changed, sync_token, pending_commands, is_paired}
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$d = require_device();
$in = input();
$pl = v_int($in, 'current_playlist_id', false, 1);
$sc = v_int($in, 'current_screen_id', false, 1);
// Only accept ids that belong to the device owner.
if ($pl && !db_val('SELECT 1 FROM playlists WHERE playlist_id = ? AND user_id <=> ?', [$pl, $d['user_id']])) $pl = null;
if ($sc && !db_val('SELECT 1 FROM screens WHERE screen_id = ? AND user_id <=> ?', [$sc, $d['user_id']])) $sc = null;
db_run('UPDATE devices SET last_heartbeat = UTC_TIMESTAMP(), ip_address = ?, playback_state = ?, current_playlist_id = ?, current_screen_id = ?, current_content_version = ?,
               storage_total_mb = COALESCE(?, storage_total_mb), storage_free_mb = COALESCE(?, storage_free_mb), app_version = COALESCE(?, app_version),
               resolution = COALESCE(?, resolution), timezone = COALESCE(?, timezone) WHERE device_id = ?', [
    client_ip(), v_enum($in, 'playback_state', ['playing', 'paused', 'idle', 'error', 'downloading'], false, 'playing'), $pl, $sc,
    v_str($in, 'current_content_version', false, 60), v_int($in, 'storage_total_mb', false, 0), v_int($in, 'storage_free_mb', false, 0),
    v_str($in, 'app_version', false, 30), v_str($in, 'resolution', false, 20), v_timezone($in, 'timezone'), $d['device_id']]);
$fresh = db_one('SELECT * FROM devices WHERE device_id = ?', [$d['device_id']]);
$token = device_sync_token($fresh);
ok([
    'changed' => ($in['sync_token'] ?? '') !== $token,
    'sync_token' => $token,
    'is_paired' => $fresh['user_id'] !== null,
    'pending_commands' => (int)db_val("SELECT COUNT(*) FROM device_commands WHERE device_id = ? AND status = 'pending' AND expires_at > UTC_TIMESTAMP()", [$d['device_id']]),
    'server_time' => gmdate(DATE_ATOM),
]);
