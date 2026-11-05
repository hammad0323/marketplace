<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
$in = input();
if ($pid = v_int($in, 'playlist_id', false, 1)) {
    owned_row('playlists', 'playlist_id', $pid, $u);
    ok(shape_rows(db_all('SELECT * FROM playlist_schedules WHERE playlist_id = ? ORDER BY priority DESC, start_time', [$pid]), ['days_of_week_json']));
}
ok(shape_rows(db_all('SELECT ps.*, p.playlist_name FROM playlist_schedules ps JOIN playlists p USING (playlist_id) WHERE p.user_id = ? AND p.deleted_at IS NULL ORDER BY ps.start_time, ps.priority DESC', [$u['user_id']]), ['days_of_week_json']));
