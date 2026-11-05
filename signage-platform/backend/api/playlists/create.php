<?php
// Body: {playlist_name, description?, transition_type?, transition_duration?, loop_enabled?, ..., items:[...] | screen_ids:[...], publish?: bool}
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'playlists.manage');
$in = input();
$uid = (int)$u['user_id'];
$s = playlist_settings_from_input($in);
db_begin();
assert_limit($uid, 'playlists');
$pid = db_insert('INSERT INTO playlists (playlist_uid, user_id, playlist_name, description, loop_enabled, autoplay, shuffle, transition_type, transition_duration, default_duration, priority) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [uuid4(), $uid, $s['playlist_name'], $s['description'], $s['loop_enabled'], $s['autoplay'], $s['shuffle'], $s['transition_type'], $s['transition_duration'], $s['default_duration'], $s['priority']]);
write_items($pid, $uid, $in);
$result = null;
if (v_bool($in, 'publish', false)) { require_permission($u, 'playlists.publish'); $result = publish_playlist(db_one('SELECT * FROM playlists WHERE playlist_id = ?', [$pid]), $uid); }
log_activity($uid, 'playlist.create', 'playlist', $pid, "Created playlist {$s['playlist_name']}");
db_commit();
ok(present_playlist(db_one('SELECT * FROM playlists WHERE playlist_id = ?', [$pid])), 'Playlist created', 201, ['publish' => $result]);
