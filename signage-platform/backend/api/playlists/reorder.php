<?php
// Body: {playlist_id, item_ids: [in new order]}
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'playlists.manage');
$in = input();
db_begin();
$p = owned_row('playlists', 'playlist_id', v_int($in, 'playlist_id', true, 1), $u);
$ids = v_int_list($in, 'item_ids', true);
$own = array_map('intval', array_column(db_all('SELECT item_id FROM playlist_items WHERE playlist_id = ?', [$p['playlist_id']]), 'item_id'));
if (array_diff($ids, $own)) fail('Items do not belong to this playlist', 400, 'VALIDATION');
foreach ($ids as $i => $id) db_run('UPDATE playlist_items SET sort_order = ? WHERE item_id = ?', [$i + 1, $id]);
playlist_changed((int)$p['playlist_id'], (int)$u['user_id']);
db_commit();
ok(present_playlist(db_one('SELECT * FROM playlists WHERE playlist_id = ?', [$p['playlist_id']])), 'Order updated');
