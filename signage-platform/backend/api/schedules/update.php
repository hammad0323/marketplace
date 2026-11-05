<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'schedules.manage');
$in = input();
db_begin();
$cur = db_one('SELECT * FROM playlist_schedules WHERE schedule_id = ?', [v_int($in, 'schedule_id', true, 1)]);
if (!$cur) not_found('Schedule');
$p = owned_row('playlists', 'playlist_id', (int)$cur['playlist_id'], $u);
$s = schedule_from_input($in, $cur);
db_run('UPDATE playlist_schedules SET schedule_name = ?, schedule_type = ?, start_date = ?, end_date = ?, start_time = ?, end_time = ?, days_of_week_json = ?, timezone = ?, priority = ?, status = ? WHERE schedule_id = ?',
    [$s['schedule_name'], $s['schedule_type'], $s['start_date'], $s['end_date'], $s['start_time'], $s['end_time'], $s['days'] === null ? null : jencode($s['days']), $s['timezone'], $s['priority'], $s['status'], $cur['schedule_id']]);
playlist_changed((int)$p['playlist_id'], (int)$u['user_id']);
db_commit();
ok(shape_row(db_one('SELECT * FROM playlist_schedules WHERE schedule_id = ?', [$cur['schedule_id']]), ['days_of_week_json']), 'Schedule updated');
