<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'playlists.manage');
$in = input();
db_begin();
$item = db_one('SELECT * FROM playlist_items WHERE item_id = ?', [v_int($in, 'item_id', true, 1)]);
if (!$item) not_found('Playlist item');
$p = owned_row('playlists', 'playlist_id', (int)$item['playlist_id'], $u);
db_run('UPDATE playlist_items SET duration = ?, transition_type = ?, is_enabled = ?, valid_from = ?, valid_until = ? WHERE item_id = ?', [
    array_key_exists('duration', $in) ? v_int($in, 'duration', false, 1, 86400) : $item['duration'],
    v_enum($in, 'transition_type', array_merge(['inherit'], TRANSITIONS), false, $item['transition_type']),
    v_bool($in, 'is_enabled', (bool)$item['is_enabled']),
    array_key_exists('valid_from', $in) ? v_datetime($in, 'valid_from') : $item['valid_from'],
    array_key_exists('valid_until', $in) ? v_datetime($in, 'valid_until') : $item['valid_until'],
    $item['item_id']]);
playlist_changed((int)$p['playlist_id'], (int)$u['user_id']);
db_commit();
ok(present_playlist(db_one('SELECT * FROM playlists WHERE playlist_id = ?', [$p['playlist_id']])), 'Item updated');
