<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'playlists.manage');
$in = input();
db_begin();
$p = owned_row('playlists', 'playlist_id', v_int($in, 'playlist_id', true, 1), $u);
$ids = isset($in['screen_ids']) ? v_int_list($in, 'screen_ids', true) : [v_int($in, 'screen_id', true, 1)];
assert_owned_screens($ids, (int)$p['user_id']);
$order = (int)db_val('SELECT COALESCE(MAX(sort_order), 0) FROM playlist_items WHERE playlist_id = ?', [$p['playlist_id']]);
foreach ($ids as $sid) {
    db_run('INSERT INTO playlist_items (playlist_id, screen_id, user_id, sort_order, duration) VALUES (?, ?, ?, ?, ?)',
        [$p['playlist_id'], $sid, $p['user_id'], ++$order, v_int($in, 'duration', false, 1, 86400)]);
}
playlist_changed((int)$p['playlist_id'], (int)$u['user_id']);
db_commit();
ok(present_playlist(db_one('SELECT * FROM playlists WHERE playlist_id = ?', [$p['playlist_id']])), count($ids) === 1 ? 'Screen added' : count($ids) . ' screens added');
