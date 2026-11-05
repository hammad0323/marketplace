<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'playlists.manage');
$p = owned_row('playlists', 'playlist_id', v_int(input(), 'playlist_id', true, 1), $u);
db_begin();
db_run('DELETE FROM device_playlists WHERE playlist_id = ?', [$p['playlist_id']]);
db_run('UPDATE devices SET current_playlist_id = NULL WHERE current_playlist_id = ?', [$p['playlist_id']]);
db_run("UPDATE playlists SET deleted_at = UTC_TIMESTAMP(), status = 'archived' WHERE playlist_id = ?", [$p['playlist_id']]);
db_run("UPDATE playlist_publications SET status = 'revoked' WHERE playlist_id = ?", [$p['playlist_id']]);
log_activity((int)$u['user_id'], 'playlist.delete', 'playlist', (int)$p['playlist_id'], "Deleted {$p['playlist_name']}");
db_commit();
ok(null, 'Playlist deleted');
