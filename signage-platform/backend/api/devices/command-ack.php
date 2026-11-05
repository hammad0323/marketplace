<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$d = require_device();
$in = input();
$n = db_exec("UPDATE device_commands SET status = ?, result_message = ?, completed_at = UTC_TIMESTAMP() WHERE command_id = ? AND device_id = ?",
    [v_enum($in, 'status', ['completed', 'failed'], false, 'completed'), v_str($in, 'result_message', false, 500), v_int($in, 'command_id', true, 1), $d['device_id']]);
ok(['updated' => $n]);
