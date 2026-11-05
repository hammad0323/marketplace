<?php
// Draft rendered as a publication document (what the player would show if published now).
require __DIR__ . '/../../includes/bootstrap.php';
require_method('GET');
$u = require_user();
$s = owned_row('screens', 'screen_id', v_int(input(), 'screen_id', true, 1), $u);
$doc = build_screen_document($s, (int)($s['published_version'] ?? 0) + 1);
$doc['media'] = array_map(fn($m) => ['url' => media_url($m['media_id'])] + $m, $doc['media']);
foreach ($doc['media'] as &$m) unset($m['file_path']);
ok($doc);
