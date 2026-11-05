<?php
// Disconnect (keep record) or remove (remove=true → also hidden from list). Transaction per DATABASE.md §4.
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'devices.manage');
$in = input();
db_begin();
$d = owned_row('devices', 'device_id', v_int($in, 'device_id', true, 1), $u);
$remove = v_bool($in, 'remove', false);
db_run('DELETE FROM device_playlists WHERE device_id = ?', [$d['device_id']]);
db_run("UPDATE device_commands SET status = 'expired' WHERE device_id = ? AND status = 'pending'", [$d['device_id']]);
db_run("UPDATE devices SET user_id = NULL, location_id = NULL, status = 'unpaired', current_playlist_id = NULL, current_screen_id = NULL, playback_state = 'idle', paired_at = NULL,
               device_name = 'New display', deleted_at = IF(?, UTC_TIMESTAMP(), NULL) WHERE device_id = ?", [$remove, $d['device_id']]);
db_run('INSERT INTO device_logs (device_id, log_type, message) VALUES (?, "info", ?)', [$d['device_id'], 'Disconnected from account']);
log_activity((int)$u['user_id'], $remove ? 'device.remove' : 'device.unpair', 'device', (int)$d['device_id'], ($remove ? 'Removed ' : 'Disconnected ') . $d['device_name']);
db_commit();
ok(null, $remove ? 'Display removed' : 'Display disconnected. It will show a new pairing code.');
