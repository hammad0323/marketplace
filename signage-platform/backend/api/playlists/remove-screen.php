<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'playlists.manage');
$in = input();
db_begin();
$p = owned_row('playlists', 'playlist_id', v_int($in, 'playlist_id', true, 1), $u);
$itemId = v_int($in, 'item_id', false, 1);
$n = $itemId ? db_exec('DELETE FROM playlist_items WHERE item_id = ? AND playlist_id = ?', [$itemId, $p['playlist_id']])
             : db_exec('DELETE FROM playlist_items WHERE screen_id = ? AND playlist_id = ?', [v_int($in, 'screen_id', true, 1), $p['playlist_id']]);
if (!$n) not_found('Playlist item');
playlist_changed((int)$p['playlist_id'], (int)$u['user_id']);
db_commit();
ok(present_playlist(db_one('SELECT * FROM playlists WHERE playlist_id = ?', [$p['playlist_id']])), 'Screen removed');
