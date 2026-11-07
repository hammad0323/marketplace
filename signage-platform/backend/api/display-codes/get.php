<?php
// GET ?screen_id= | ?playlist_id=  → the item's display code (created on first request)
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
[$kind, $t] = display_code_target(input(), $u);
$id = (int)($kind === 'screen' ? $t['screen_id'] : $t['playlist_id']);
$c = display_code_for($kind, $id, (int)$t['user_id']);
ok(present_display_code($c, $kind, $t));
