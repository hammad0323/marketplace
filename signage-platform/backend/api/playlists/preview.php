<?php
// The current publication (or, with draft=1, a live build of the draft) with signed media URLs.
require __DIR__ . '/../../includes/bootstrap.php';
require_method('GET');
$u = require_user();
$in = input();
$p = owned_row('playlists', 'playlist_id', v_int($in, 'playlist_id', true, 1), $u);
if (!v_bool($in, 'draft', true)) {
    $pub = db_val("SELECT published_json FROM playlist_publications WHERE playlist_id = ? AND status = 'current'", [$p['playlist_id']]);
    if (!$pub) fail('This playlist has not been published yet', 404, 'NOT_FOUND');
    ok(sign_manifest(jdecode($pub, [])));
}
// Draft build: screens rendered from their drafts (no DB writes).
$items = db_all('SELECT pi.*, s.* FROM playlist_items pi JOIN screens s ON s.screen_id = pi.screen_id WHERE pi.playlist_id = ? AND pi.is_enabled = 1 AND s.deleted_at IS NULL ORDER BY pi.sort_order', [$p['playlist_id']]);
$out = []; $media = [];
foreach ($items as $it) {
    $doc = build_screen_document($it, 0);
    foreach ($doc['media'] as $m) $media[$m['media_id']] = $m;
    $out[] = ['item_id' => (int)$it['item_id'], 'screen_id' => (int)$it['screen_id'], 'duration' => (int)($it['duration'] ?? $it['default_duration']),
        'transition_type' => $it['transition_type'], 'screen' => $doc];
}
ok(sign_manifest(['playlist_id' => (int)$p['playlist_id'], 'playlist_name' => $p['playlist_name'], 'loop_enabled' => (bool)$p['loop_enabled'],
    'transition' => ['type' => $p['transition_type'], 'duration_ms' => (int)$p['transition_duration']], 'items' => $out, 'media' => array_values($media)]));
