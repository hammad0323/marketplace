<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('GET');
$u = require_user();
$s = owned_row('screens', 'screen_id', v_int(input(), 'screen_id', true, 1), $u);
$out = present_screen($s, true);
$out['playlists'] = db_all('SELECT DISTINCT p.playlist_id, p.playlist_name, p.status FROM playlist_items pi JOIN playlists p USING (playlist_id) WHERE pi.screen_id = ? AND p.deleted_at IS NULL', [$s['screen_id']]);
ok($out);
