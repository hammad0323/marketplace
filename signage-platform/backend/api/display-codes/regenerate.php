<?php
// POST {screen_id | playlist_id} → new random code (the old one stops working)
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
[$kind, $t] = display_code_target(input(), $u);
$id = (int)($kind === 'screen' ? $t['screen_id'] : $t['playlist_id']);
db_begin();
$c = display_code_for($kind, $id, (int)$t['user_id']);
db_run('UPDATE display_codes SET code = ? WHERE code_id = ?', [generate_display_code(), $c['code_id']]);
db_commit();
ok(present_display_code(db_one('SELECT * FROM display_codes WHERE code_id = ?', [$c['code_id']]), $kind, $t), 'New code created');
