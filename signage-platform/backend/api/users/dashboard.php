<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('GET');
$u = require_user();
$uid = (int)$u['user_id'];
$offS = (int)app_setting('offline_after_seconds', 180);
$counts = db_one("SELECT
    (SELECT COUNT(*) FROM screens WHERE user_id = ? AND deleted_at IS NULL) AS total_screens,
    (SELECT COUNT(*) FROM screens WHERE user_id = ? AND deleted_at IS NULL AND status = 'published') AS published_screens,
    (SELECT COUNT(*) FROM screens WHERE user_id = ? AND deleted_at IS NULL AND status = 'draft') AS draft_screens,
    (SELECT COUNT(*) FROM playlists WHERE user_id = ? AND deleted_at IS NULL) AS total_playlists,
    (SELECT COUNT(*) FROM playlists WHERE user_id = ? AND deleted_at IS NULL AND status = 'published') AS active_playlists,
    (SELECT COUNT(*) FROM devices WHERE user_id = ? AND deleted_at IS NULL) AS total_devices,
    (SELECT COUNT(*) FROM devices WHERE user_id = ? AND deleted_at IS NULL AND last_heartbeat >= UTC_TIMESTAMP() - INTERVAL ? SECOND) AS online_devices",
    [$uid, $uid, $uid, $uid, $uid, $uid, $uid, $offS]);
$recentScreens = db_all('SELECT * FROM screens WHERE user_id = ? AND deleted_at IS NULL ORDER BY updated_at DESC LIMIT 6', [$uid]);
ok([
    'user' => present_user($u),
    'stats' => array_map('intval', $counts),
    'limits' => effective_limits($uid),
    'usage' => user_usage($uid),
    'recent_screens' => array_map(fn($s) => present_screen($s, true), $recentScreens),
    'recent_activity' => db_all('SELECT activity_id, action, entity_type, entity_id, description, created_at FROM activity_logs WHERE user_id = ? ORDER BY activity_id DESC LIMIT 10', [$uid]),
    'unread_notifications' => (int)db_val('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$uid]),
]);
