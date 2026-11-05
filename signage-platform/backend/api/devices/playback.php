<?php
// Device: proof-of-play batch {entries: [{playlist_id, screen_id, playlist_version, screen_version, started_at, duration_ms, completed}]}
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$d = require_device();
if ($d['user_id'] === null) ok(['stored' => 0]);
$entries = v_array(input(), 'entries', true, 1000);
$n = 0;
foreach ($entries as $e) {
    if (!is_array($e) || empty($e['started_at']) || !strtotime((string)$e['started_at'])) continue;
    $pl = isset($e['playlist_id']) && db_val('SELECT 1 FROM playlists WHERE playlist_id = ? AND user_id = ?', [(int)$e['playlist_id'], $d['user_id']]) ? (int)$e['playlist_id'] : null;
    $sc = isset($e['screen_id']) && db_val('SELECT 1 FROM screens WHERE screen_id = ? AND user_id = ?', [(int)$e['screen_id'], $d['user_id']]) ? (int)$e['screen_id'] : null;
    db_run('INSERT INTO playback_logs (device_id, playlist_id, screen_id, playlist_version, screen_version, started_at, duration_ms, completed) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', [
        $d['device_id'], $pl, $sc, isset($e['playlist_version']) ? (int)$e['playlist_version'] : null, isset($e['screen_version']) ? (int)$e['screen_version'] : null,
        gmdate('Y-m-d H:i:s', strtotime((string)$e['started_at'])), max(0, min(86400000, (int)($e['duration_ms'] ?? 0))), !isset($e['completed']) || $e['completed']]);
    $n++;
}
ok(['stored' => $n]);
