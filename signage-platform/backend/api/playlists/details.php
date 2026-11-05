<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
$u = require_user();
ok(present_playlist(owned_row('playlists', 'playlist_id', v_int(input(), 'playlist_id', true, 1), $u)));
