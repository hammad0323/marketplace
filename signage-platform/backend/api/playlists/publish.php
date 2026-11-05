<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'playlists.publish');
$in = input();
db_begin();
$p = owned_row('playlists', 'playlist_id', v_int($in, 'playlist_id', true, 1), $u);
$p = db_one('SELECT * FROM playlists WHERE playlist_id = ? FOR UPDATE', [$p['playlist_id']]);
// Optionally publish draft screens first so nothing is skipped.
if (v_bool($in, 'publish_screens', false)) {
    require_permission($u, 'screens.publish');
    foreach (db_all("SELECT s.* FROM playlist_items pi JOIN screens s USING (screen_id) WHERE pi.playlist_id = ? AND s.deleted_at IS NULL AND (s.published_version IS NULL OR s.has_unpublished_changes = 1)", [$p['playlist_id']]) as $s) {
        publish_screen($s, (int)$u['user_id']);
    }
}
$r = publish_playlist($p, (int)$u['user_id']);
$devices = (int)db_val('SELECT COUNT(*) FROM device_playlists WHERE playlist_id = ?', [$p['playlist_id']]);
log_activity((int)$u['user_id'], 'playlist.publish', 'playlist', (int)$p['playlist_id'], "Published {$p['playlist_name']} v{$r['version']}");
db_commit();
$msg = "Playlist published (v{$r['version']})" . ($devices ? " to {$devices} display(s)" : '');
if ($r['skipped_screens']) $msg .= '. Not included because they are unpublished: ' . implode(', ', $r['skipped_screens']);
ok($r + ['device_count' => $devices, 'playlist' => present_playlist(db_one('SELECT * FROM playlists WHERE playlist_id = ?', [$p['playlist_id']]))], $msg);
