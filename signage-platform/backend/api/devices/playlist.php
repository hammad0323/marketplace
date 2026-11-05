<?php
// Device: fetch one playlist publication (signed) — used when re-downloading a specific version.
require __DIR__ . '/../../includes/bootstrap.php';
$d = require_device();
$pid = v_int(input(), 'playlist_id', true, 1);
if (!db_val('SELECT 1 FROM device_playlists WHERE device_id = ? AND playlist_id = ?', [$d['device_id'], $pid])) not_found('Playlist');
$pub = db_val("SELECT published_json FROM playlist_publications WHERE playlist_id = ? AND status = 'current'", [$pid]);
if (!$pub) not_found('Published playlist');
ok(sign_manifest(jdecode($pub, [])));
