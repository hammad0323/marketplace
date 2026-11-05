<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'schedules.manage');
$in = input();
db_begin();
$p = owned_row('playlists', 'playlist_id', v_int($in, 'playlist_id', true, 1), $u);
$s = schedule_from_input($in);
$id = db_insert('INSERT INTO playlist_schedules (playlist_id, schedule_name, schedule_type, start_date, end_date, start_time, end_time, days_of_week_json, timezone, priority, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$p['playlist_id'], $s['schedule_name'], $s['schedule_type'], $s['start_date'], $s['end_date'], $s['start_time'], $s['end_time'], $s['days'] === null ? null : jencode($s['days']), $s['timezone'], $s['priority'], $s['status']]);
playlist_changed((int)$p['playlist_id'], (int)$u['user_id']);
log_activity((int)$u['user_id'], 'schedule.create', 'playlist', (int)$p['playlist_id'], 'Added schedule ' . ($s['schedule_name'] ?? ''));
db_commit();
ok(shape_row(db_one('SELECT * FROM playlist_schedules WHERE schedule_id = ?', [$id]), ['days_of_week_json']), 'Schedule added', 201);
