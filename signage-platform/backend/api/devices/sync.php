<?php
/**
 * Player content sync. Query: ?have=<playlist_id>:<version>,<playlist_id>:<version>
 * Returns assignments; full (signed) publication documents only for playlists whose
 * version the player does not already have. Pending commands are delivered (marked delivered).
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
$d = require_device();
$have = [];
foreach (array_filter(explode(',', (string)($_GET['have'] ?? ''))) as $pair) {
    [$pid, $ver] = array_pad(explode(':', $pair, 2), 2, 0);
    $have[(int)$pid] = (int)$ver;
}
$assignments = [];
$playlists = [];
if ($d['user_id'] !== null) {
    foreach (db_all("SELECT dp.playlist_id, dp.is_default, p.playlist_name, p.priority, p.status, pp.version, pp.published_json, pp.content_hash
                       FROM device_playlists dp JOIN playlists p USING (playlist_id)
                  LEFT JOIN playlist_publications pp ON pp.playlist_id = p.playlist_id AND pp.status = 'current'
                      WHERE dp.device_id = ? AND p.deleted_at IS NULL", [$d['device_id']]) as $a) {
        if ($a['version'] === null || $a['status'] === 'paused') continue;   // unpublished or paused → not playable
        $assignments[] = ['playlist_id' => (int)$a['playlist_id'], 'playlist_name' => $a['playlist_name'], 'version' => (int)$a['version'],
            'content_hash' => $a['content_hash'], 'is_default' => (bool)$a['is_default'], 'priority' => (int)$a['priority']];
        if (($have[(int)$a['playlist_id']] ?? -1) !== (int)$a['version']) {
            $playlists[] = sign_manifest(jdecode($a['published_json'], []));
        }
    }
}
db_begin();
$commands = db_all("SELECT command_id, command, payload_json FROM device_commands WHERE device_id = ? AND status = 'pending' AND expires_at > UTC_TIMESTAMP() ORDER BY command_id FOR UPDATE", [$d['device_id']]);
if ($commands) db_run("UPDATE device_commands SET status = 'delivered', delivered_at = UTC_TIMESTAMP() WHERE command_id IN (" . placeholders($commands) . ')', array_column($commands, 'command_id'));
db_run('UPDATE devices SET last_sync_at = UTC_TIMESTAMP(), last_heartbeat = UTC_TIMESTAMP() WHERE device_id = ?', [$d['device_id']]);
db_commit();
$fresh = db_one('SELECT * FROM devices WHERE device_id = ?', [$d['device_id']]);
ok([
    'device' => ['device_id' => (int)$d['device_id'], 'device_name' => $fresh['device_name'], 'device_code' => $fresh['device_code'],
        'orientation' => $fresh['orientation'], 'timezone' => $fresh['timezone'], 'is_paired' => $fresh['user_id'] !== null],
    'assignments' => $assignments,
    'playlists' => $playlists,
    'emergency' => active_emergencies($fresh),
    'commands' => shape_rows($commands, ['payload_json']),
    'settings' => [
        'heartbeat_interval_seconds' => (int)app_setting('heartbeat_interval_seconds', 60),
        'sync_interval_seconds' => (int)app_setting('sync_interval_seconds', 120),
        'weather_provider' => app_setting('weather_provider', 'open_meteo'),
    ],
    'sync_token' => device_sync_token($fresh),
    'server_time' => gmdate(DATE_ATOM),
]);
