<?php
// POST {screen_id | playlist_id, code}  → choose your own code (4–12 letters/numbers, unique)
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$in = input();
[$kind, $t] = display_code_target($in, $u);
$code = normalize_display_code((string)($in['code'] ?? ''));
if (strlen($code) < 4 || strlen($code) > 12) v_error('code', 'Use 4–12 letters or numbers');
$id = (int)($kind === 'screen' ? $t['screen_id'] : $t['playlist_id']);
db_begin();
$c = display_code_for($kind, $id, (int)$t['user_id']);
$taken = db_val('SELECT code_id FROM display_codes WHERE code = ? AND code_id <> ?', [$code, $c['code_id']]);
if ($taken) fail('That code is already in use. Please choose another one.', 409, 'CONFLICT', ['code' => 'Already in use']);
db_run('UPDATE display_codes SET code = ? WHERE code_id = ?', [$code, $c['code_id']]);
log_activity((int)$u['user_id'], 'display_code.set', $kind, $id, "Display code set to {$code}");
db_commit();
ok(present_display_code(db_one('SELECT * FROM display_codes WHERE code_id = ?', [$c['code_id']]), $kind, $t), 'Code saved. Displays using the old code will stop showing this content.');
