<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'screens.edit');
[$w, $s] = widget_with_screen(v_int(input(), 'widget_id', true, 1), $u);
db_begin();
db_run('DELETE FROM screen_widgets WHERE widget_id = ?', [$w['widget_id']]);
touch_screen((int)$s['screen_id']);
db_commit();
ok(['widget_id' => (int)$w['widget_id']], 'Widget deleted');
