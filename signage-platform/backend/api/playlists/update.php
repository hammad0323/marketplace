<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'playlists.manage');
$in = input();
db_begin();
$p = owned_row('playlists', 'playlist_id', v_int($in, 'playlist_id', true, 1), $u);
$s = playlist_settings_from_input($in, $p);
db_run('UPDATE playlists SET playlist_name = ?, description = ?, loop_enabled = ?, autoplay = ?, shuffle = ?, transition_type = ?, transition_duration = ?, default_duration = ?, priority = ?, has_unpublished_changes = 1 WHERE playlist_id = ?',
    [$s['playlist_name'], $s['description'], $s['loop_enabled'], $s['autoplay'], $s['shuffle'], $s['transition_type'], $s['transition_duration'], $s['default_duration'], $s['priority'], $p['playlist_id']]);
write_items((int)$p['playlist_id'], (int)$p['user_id'], $in);
if ($st = v_enum($in, 'status', ['paused'])) db_run("UPDATE playlists SET status = 'paused' WHERE playlist_id = ?", [$p['playlist_id']]);
$result = null;
if (v_bool($in, 'publish', false)) { require_permission($u, 'playlists.publish'); $result = publish_playlist(db_one('SELECT * FROM playlists WHERE playlist_id = ?', [$p['playlist_id']]), (int)$u['user_id']); }
log_activity((int)$u['user_id'], 'playlist.update', 'playlist', (int)$p['playlist_id'], 'Updated playlist');
db_commit();
ok(present_playlist(db_one('SELECT * FROM playlists WHERE playlist_id = ?', [$p['playlist_id']])), $result ? 'Saved and published' : 'Playlist saved', 200, ['publish' => $result]);
