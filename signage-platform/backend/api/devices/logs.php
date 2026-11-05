<?php
/**
 * POST (device): batch upload of queued logs {logs: [{log_type, message, context?, logged_at?}, ...]} (max 500)
 * GET  (user):   ?device_id=&log_type=&page=  → paginated logs for the owner
 */
require __DIR__ . '/../../includes/bootstrap.php';
if (request_method() === 'POST') {
    $d = require_device();
    $logs = v_array(input(), 'logs', true, 500);
    rate_limit('devlogbatch:' . $d['device_id'], 120, 3600);
    $types = ['info', 'warning', 'error', 'playback_error', 'sync', 'command', 'heartbeat', 'crash'];
    $n = 0;
    foreach ($logs as $l) {
        if (!is_array($l) || empty($l['message'])) continue;
        $ts = !empty($l['logged_at']) && strtotime((string)$l['logged_at']) ? gmdate('Y-m-d H:i:s', strtotime((string)$l['logged_at'])) : null;
        db_run('INSERT INTO device_logs (device_id, log_type, message, context_json, logged_at) VALUES (?, ?, ?, ?, ?)', [
            $d['device_id'], in_array($l['log_type'] ?? '', $types, true) ? $l['log_type'] : 'info', mb_substr((string)$l['message'], 0, 1000),
            isset($l['context']) && is_array($l['context']) ? jencode($l['context']) : null, $ts]);
        $n++;
    }
    ok(['stored' => $n]);
}
$u = require_user();
$in = input();
$d = owned_row('devices', 'device_id', v_int($in, 'device_id', true, 1), $u);
[$page, $per, $off] = pagination();
$where = 'device_id = ?'; $params = [$d['device_id']];
if ($t = v_str($in, 'log_type', false, 30)) { $where .= ' AND log_type = ?'; $params[] = $t; }
$total = (int)db_val("SELECT COUNT(*) FROM device_logs WHERE {$where}", $params);
paged(shape_rows(db_all("SELECT * FROM device_logs WHERE {$where} ORDER BY log_id DESC LIMIT ? OFFSET ?", array_merge($params, [$per, $off])), ['context_json']), $total, $page, $per);
