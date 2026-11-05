<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('GET');
$u = require_user();
$s = owned_row('screens', 'screen_id', v_int(input(), 'screen_id', true, 1), $u);
ok(screen_layout($s)['widgets']);
