<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_admin();
$off = (int)app_setting('offline_after_seconds', 180);
$s = db_one("SELECT
  (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND role_id = (SELECT role_id FROM roles WHERE role_key='user')) AS total_users,
  (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND status = 'active' AND role_id = (SELECT role_id FROM roles WHERE role_key='user')) AS active_users,
  (SELECT COUNT(*) FROM users WHERE deleted_at IS NULL AND status <> 'active') AS inactive_users,
  (SELECT COUNT(*) FROM screens WHERE deleted_at IS NULL) AS total_screens,
  (SELECT COUNT(*) FROM screens WHERE deleted_at IS NULL AND status = 'published') AS published_screens,
  (SELECT COUNT(*) FROM screens WHERE deleted_at IS NULL AND status = 'draft') AS draft_screens,
  (SELECT COUNT(*) FROM playlists WHERE deleted_at IS NULL) AS total_playlists,
  (SELECT COUNT(*) FROM devices WHERE deleted_at IS NULL AND user_id IS NOT NULL) AS total_devices,
  (SELECT COUNT(*) FROM devices WHERE deleted_at IS NULL AND user_id IS NOT NULL AND last_heartbeat >= UTC_TIMESTAMP() - INTERVAL ? SECOND) AS connected_devices,
  (SELECT COALESCE(SUM(file_size),0) FROM media_library WHERE deleted_at IS NULL) AS storage_used_bytes,
  (SELECT COUNT(*) FROM media_library WHERE deleted_at IS NULL) AS media_files", [$off]);
$s = array_map('intval', $s);
$s['offline_devices'] = $s['total_devices'] - $s['connected_devices'];
ok([
    'stats' => $s,
    'plans' => db_all("SELECT p.plan_id, p.plan_name, COUNT(s.subscription_id) AS users FROM plans p LEFT JOIN user_subscriptions s ON s.plan_id = p.plan_id AND s.status = 'active' GROUP BY p.plan_id, p.plan_name ORDER BY p.sort_order"),
    'recent_users' => db_all('SELECT user_id, full_name, email, status, created_at FROM users WHERE deleted_at IS NULL ORDER BY user_id DESC LIMIT 5'),
    'recent_activity' => db_all('SELECT a.activity_id, a.action, a.description, a.created_at, u.full_name FROM activity_logs a LEFT JOIN users u USING (user_id) ORDER BY a.activity_id DESC LIMIT 15'),
    'offline_device_list' => db_all('SELECT d.device_id, d.device_name, d.last_heartbeat, u.full_name AS owner_name FROM devices d JOIN users u ON u.user_id = d.user_id
                                     WHERE d.deleted_at IS NULL AND (d.last_heartbeat IS NULL OR d.last_heartbeat < UTC_TIMESTAMP() - INTERVAL ? SECOND) ORDER BY d.last_heartbeat DESC LIMIT 10', [$off]),
]);
