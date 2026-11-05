<?php
// Management app: {pairing_code, device_name?, location_id?, playlist_id?}
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'devices.pair');
$uid = (int)$u['user_id'];
$in = input();
rate_limit('pair:user:' . $uid, 10, 900);
rate_limit('pair:ip:' . client_ip(), 30, 900);
$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($in['pairing_code'] ?? '')));
if (strlen($code) !== PAIRING_LENGTH) v_error('pairing_code', 'Enter the 6-character code shown on your TV');
$name = v_str($in, 'device_name', false, 150);
$loc = v_int($in, 'location_id', false, 1);
if ($loc) owned_row('locations', 'location_id', $loc, $u);
$playlistId = v_int($in, 'playlist_id', false, 1);
if ($playlistId) owned_row('playlists', 'playlist_id', $playlistId, $u);

db_begin();
$pc = db_one('SELECT * FROM device_pairing_codes WHERE pairing_code_hash = ? FOR UPDATE', [sha256($code)]);
if (!$pc || $pc['used_at'] !== null || strtotime($pc['expires_at'] . ' UTC') < time()) {
    if ($pc) db_run('UPDATE device_pairing_codes SET attempts = attempts + 1 WHERE id = ?', [$pc['id']]);
    db_commit();
    fail('That code is not valid or has expired. Check the code on your TV and try again.', 400, 'PAIRING_INVALID');
}
$d = db_one('SELECT * FROM devices WHERE device_id = ? AND deleted_at IS NULL FOR UPDATE', [$pc['device_id']]);
if (!$d) fail('That display is no longer available', 400, 'PAIRING_INVALID');
if ($d['user_id'] !== null) fail('That display is already connected to an account', 409, 'ALREADY_PAIRED');
assert_limit($uid, 'devices');
db_run("UPDATE devices SET user_id = ?, device_name = ?, location_id = ?, status = 'active', paired_at = UTC_TIMESTAMP() WHERE device_id = ?",
    [$uid, $name ?? ($d['model'] ? "{$d['model']} display" : 'My display'), $loc, $d['device_id']]);
db_run('UPDATE device_pairing_codes SET used_at = UTC_TIMESTAMP(), used_by = ? WHERE id = ?', [$uid, $pc['id']]);
if ($playlistId) db_run('INSERT INTO device_playlists (device_id, playlist_id, user_id, is_default, assigned_by) VALUES (?, ?, ?, 1, ?)', [$d['device_id'], $playlistId, $uid, $uid]);
db_run('INSERT INTO device_logs (device_id, log_type, message) VALUES (?, "info", ?)', [$d['device_id'], "Paired to {$u['email']}"]);
log_activity($uid, 'device.pair', 'device', (int)$d['device_id'], 'Connected display ' . ($name ?? $d['device_code']));
db_commit();
$fresh = db_one(device_select_sql() . ' WHERE d.device_id = ?', [$d['device_id']]);
ok(present_device($fresh), 'Display connected', 201);
