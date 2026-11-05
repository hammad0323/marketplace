<?php
/**
 * Player first launch (no auth). Body: {device_uid, device_type?, platform?, os_version?, manufacturer?, model?,
 * resolution?, orientation?, app_version?, timezone?, capabilities?}
 * Returns a NEW device token every call (store it securely on the device).
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
rate_limit('register:ip:' . client_ip(), 30, 3600);
$in = input();
$uidStr = v_str($in, 'device_uid', true, 36, 8);
if (!preg_match('/^[A-Za-z0-9\-]{8,36}$/', $uidStr)) v_error('device_uid', 'Invalid device id');
$token = random_token(32);
$caps = is_array($in['capabilities'] ?? null) ? $in['capabilities'] : ['commands' => ['restart', 'pause', 'resume', 'sync', 'clear_cache', 'identify'], 'video' => true];
$caps['commands'] = array_values(array_intersect($caps['commands'] ?? [], DEVICE_COMMANDS));
$fields = [
    v_enum($in, 'device_type', ['android_tv', 'android_box', 'tablet', 'phone', 'other'], false, 'android_tv'),
    v_str($in, 'platform', false, 50), v_str($in, 'os_version', false, 50), v_str($in, 'manufacturer', false, 100), v_str($in, 'model', false, 100),
    v_str($in, 'resolution', false, 20), v_enum($in, 'orientation', ['landscape', 'portrait', 'auto'], false, 'auto'),
    v_str($in, 'app_version', false, 30), v_timezone($in, 'timezone'), jencode($caps), client_ip(),
];
db_begin();
$d = db_one('SELECT * FROM devices WHERE device_uid = ? FOR UPDATE', [$uidStr]);
if ($d) {
    db_run('UPDATE devices SET device_token_hash = ?, device_type = ?, platform = ?, os_version = ?, manufacturer = ?, model = ?, resolution = ?, orientation = ?, app_version = ?, timezone = ?, capabilities_json = ?, ip_address = ?,
                   user_id = IF(deleted_at IS NULL, user_id, NULL), status = IF(deleted_at IS NULL, status, "unpaired"), deleted_at = NULL WHERE device_id = ?',
        array_merge([sha256($token)], $fields, [$d['device_id']]));
    $id = (int)$d['device_id'];
} else {
    do { $code = 'DSP-' . substr(str_shuffle(str_repeat(PAIRING_ALPHABET, 2)), 0, 4); } while (db_val('SELECT 1 FROM devices WHERE device_code = ?', [$code]));
    $id = db_insert('INSERT INTO devices (device_uid, device_code, device_token_hash, device_type, platform, os_version, manufacturer, model, resolution, orientation, app_version, timezone, capabilities_json, ip_address)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', array_merge([$uidStr, $code, sha256($token)], $fields));
}
db_run('INSERT INTO device_logs (device_id, log_type, message) VALUES (?, "info", ?)', [$id, 'Player registered (app ' . ($fields[7] ?? '?') . ')']);
db_commit();
$d = db_one('SELECT * FROM devices WHERE device_id = ?', [$id]);
ok(['device_id' => $id, 'device_token' => $token, 'device_code' => $d['device_code'], 'is_paired' => $d['user_id'] !== null, 'device_name' => $d['device_name'],
    'heartbeat_interval_seconds' => (int)app_setting('heartbeat_interval_seconds', 60), 'sync_interval_seconds' => (int)app_setting('sync_interval_seconds', 120)],
    'Device registered', 201);
