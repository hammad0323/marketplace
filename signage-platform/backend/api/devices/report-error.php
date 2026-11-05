<?php
// Single error: {message, log_type?, context?, logged_at?}
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$d = require_device();
$in = input();
rate_limit('devlog:' . $d['device_id'], 600, 3600);
db_run('INSERT INTO device_logs (device_id, log_type, message, context_json, logged_at) VALUES (?, ?, ?, ?, ?)', [
    $d['device_id'], v_enum($in, 'log_type', ['error', 'playback_error', 'warning', 'crash', 'info', 'sync'], false, 'error'),
    v_str($in, 'message', true, 1000), isset($in['context']) && is_array($in['context']) ? jencode($in['context']) : null, v_datetime($in, 'logged_at')]);
ok(null, 'Logged');
