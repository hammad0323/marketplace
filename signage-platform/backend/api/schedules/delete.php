<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'schedules.manage');
db_begin();
$cur = db_one('SELECT * FROM playlist_schedules WHERE schedule_id = ?', [v_int(input(), 'schedule_id', true, 1)]);
if (!$cur) not_found('Schedule');
$p = owned_row('playlists', 'playlist_id', (int)$cur['playlist_id'], $u);
db_run('DELETE FROM playlist_schedules WHERE schedule_id = ?', [$cur['schedule_id']]);
playlist_changed((int)$p['playlist_id'], (int)$u['user_id']);
db_commit();
ok(null, 'Schedule deleted');
