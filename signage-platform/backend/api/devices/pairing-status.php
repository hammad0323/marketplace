<?php
require __DIR__ . '/../../includes/bootstrap.php';
$d = require_device();
db_run('UPDATE devices SET last_heartbeat = UTC_TIMESTAMP() WHERE device_id = ?', [$d['device_id']]);
ok(['is_paired' => $d['user_id'] !== null, 'device_name' => $d['device_name'], 'device_code' => $d['device_code']]);
