<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
$u = require_user();
[$w] = widget_with_screen(v_int(input(), 'widget_id', true, 1), $u);
ok(present_widget($w));
