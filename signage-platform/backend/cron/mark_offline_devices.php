<?php
// Every 5 minutes: notify owners once when a display goes offline.
//   */5 * * * * php /path/backend/cron/mark_offline_devices.php
require __DIR__ . '/_cli.php';
$off = (int)app_setting('offline_after_seconds', 180);
$rows = db_all("SELECT d.device_id, d.user_id, d.device_name, d.last_heartbeat FROM devices d
                 WHERE d.deleted_at IS NULL AND d.user_id IS NOT NULL AND d.status = 'active' AND d.last_heartbeat IS NOT NULL
                   AND d.last_heartbeat < UTC_TIMESTAMP() - INTERVAL ? SECOND AND d.playback_state <> 'error'", [$off]);
foreach ($rows as $d) {
    db_run("UPDATE devices SET playback_state = 'error' WHERE device_id = ?", [$d['device_id']]);   // 'error' marks "offline notified"
    notify((int)$d['user_id'], 'Display offline', "{$d['device_name']} has not checked in since {$d['last_heartbeat']} UTC.", 'device_offline', '/devices/' . $d['device_id']);
    db_run('INSERT INTO device_logs (device_id, log_type, message) VALUES (?, "warning", "Marked offline by server")', [$d['device_id']]);
}
out(count($rows) . ' device(s) marked offline');
