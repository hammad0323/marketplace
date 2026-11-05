<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
$u = require_user();
$d = owned_row('devices', 'device_id', v_int(input(), 'device_id', true, 1), $u);
ok(present_device(db_one(device_select_sql() . ' WHERE d.device_id = ?', [$d['device_id']]), true));
