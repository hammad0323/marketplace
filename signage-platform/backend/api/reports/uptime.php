<?php
// Per-device played time and errors in the last N days (default 7).
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
require_permission($u, 'analytics.view');
$days = v_int(input(), 'days', false, 1, 90, 7);
ok(db_all('SELECT d.device_id, d.device_name, d.last_heartbeat, d.last_sync_at,
                  (SELECT ROUND(COALESCE(SUM(duration_ms),0)/3600000, 2) FROM playback_logs pl WHERE pl.device_id = d.device_id AND pl.started_at > UTC_TIMESTAMP() - INTERVAL ? DAY) AS hours_played,
                  (SELECT COUNT(*) FROM device_logs dl WHERE dl.device_id = d.device_id AND dl.log_type IN ("error","playback_error","crash") AND dl.created_at > UTC_TIMESTAMP() - INTERVAL ? DAY) AS errors
             FROM devices d WHERE d.user_id = ? AND d.deleted_at IS NULL ORDER BY d.device_name', [$days, $days, $u['user_id']]));
