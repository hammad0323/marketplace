<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'devices.manage');
$in = input();
$d = owned_row('devices', 'device_id', v_int($in, 'device_id', true, 1), $u);
$loc = array_key_exists('location_id', $in) ? v_int($in, 'location_id', false, 0) : $d['location_id'];
if ($loc) owned_row('locations', 'location_id', $loc, $u);
db_run('UPDATE devices SET device_name = ?, location_id = ?, orientation = ?, timezone = ? WHERE device_id = ?', [
    v_str($in, 'device_name', false, 150) ?? $d['device_name'], $loc ?: null,
    v_enum($in, 'orientation', ['landscape', 'portrait', 'auto'], false, $d['orientation']),
    array_key_exists('timezone', $in) ? v_timezone($in, 'timezone') : $d['timezone'], $d['device_id']]);
log_activity((int)$u['user_id'], 'device.update', 'device', (int)$d['device_id'], 'Updated display settings');
ok(present_device(db_one(device_select_sql() . ' WHERE d.device_id = ?', [$d['device_id']])), 'Display updated');
