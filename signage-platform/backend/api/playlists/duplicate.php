<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'playlists.manage');
$src = owned_row('playlists', 'playlist_id', v_int(input(), 'playlist_id', true, 1), $u);
$uid = (int)$src['user_id'];
db_begin();
assert_limit($uid, 'playlists');
$pid = db_insert('INSERT INTO playlists (playlist_uid, user_id, playlist_name, description, loop_enabled, autoplay, shuffle, transition_type, transition_duration, default_duration, priority)
                  SELECT ?, user_id, LEFT(CONCAT(playlist_name, " (copy)"), 150), description, loop_enabled, autoplay, shuffle, transition_type, transition_duration, default_duration, priority FROM playlists WHERE playlist_id = ?',
    [uuid4(), $src['playlist_id']]);
db_run('INSERT INTO playlist_items (playlist_id, screen_id, user_id, sort_order, duration, transition_type, is_enabled, valid_from, valid_until)
        SELECT ?, screen_id, user_id, sort_order, duration, transition_type, is_enabled, valid_from, valid_until FROM playlist_items WHERE playlist_id = ?', [$pid, $src['playlist_id']]);
db_run('INSERT INTO playlist_schedules (playlist_id, schedule_name, schedule_type, start_date, end_date, start_time, end_time, days_of_week_json, timezone, priority, status)
        SELECT ?, schedule_name, schedule_type, start_date, end_date, start_time, end_time, days_of_week_json, timezone, priority, status FROM playlist_schedules WHERE playlist_id = ?', [$pid, $src['playlist_id']]);
log_activity((int)$u['user_id'], 'playlist.duplicate', 'playlist', $pid, "Duplicated {$src['playlist_name']}");
db_commit();
ok(present_playlist(db_one('SELECT * FROM playlists WHERE playlist_id = ?', [$pid])), 'Playlist duplicated', 201);
