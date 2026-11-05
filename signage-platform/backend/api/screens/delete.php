<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'screens.delete');
$s = owned_row('screens', 'screen_id', v_int(input(), 'screen_id', true, 1), $u);
db_begin();
// Remove from playlists, soft-delete the screen, republish affected live playlists.
$pls = array_column(db_all('SELECT DISTINCT playlist_id FROM playlist_items WHERE screen_id = ?', [$s['screen_id']]), 'playlist_id');
db_run('DELETE FROM playlist_items WHERE screen_id = ?', [$s['screen_id']]);
db_run("UPDATE screens SET deleted_at = UTC_TIMESTAMP(), status = 'archived' WHERE screen_id = ?", [$s['screen_id']]);
db_run("UPDATE screen_publications SET status = 'revoked' WHERE screen_id = ?", [$s['screen_id']]);
foreach ($pls as $pid) playlist_changed((int)$pid, (int)$u['user_id']);
log_activity((int)$u['user_id'], 'screen.delete', 'screen', (int)$s['screen_id'], "Deleted {$s['screen_name']}");
db_commit();
ok(['screen_id' => (int)$s['screen_id'], 'updated_playlists' => array_map('intval', $pls)], 'Screen deleted');
