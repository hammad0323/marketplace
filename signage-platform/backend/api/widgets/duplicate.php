<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'screens.edit');
[$w, $s] = widget_with_screen(v_int(input(), 'widget_id', true, 1), $u);
db_begin();
$maxZ = (int)db_val('SELECT COALESCE(MAX(z_index), 0) FROM screen_widgets WHERE screen_id = ?', [$s['screen_id']]);
$wid = db_insert('INSERT INTO screen_widgets (widget_uid, screen_id, widget_type, widget_name, position_x, position_y, width, height, rotation, z_index, configuration_json, is_locked, is_visible)
                  VALUES (?, ?, ?, ?, LEAST(?, 0.95), LEAST(?, 0.95), ?, ?, ?, ?, ?, 0, ?)',
    [uuid4(), $s['screen_id'], $w['widget_type'], mb_substr($w['widget_name'] . ' copy', 0, 150), (float)$w['position_x'] + 0.02, (float)$w['position_y'] + 0.02,
        $w['width'], $w['height'], $w['rotation'], $maxZ + 1, $w['configuration_json'], $w['is_visible']]);
sync_widget_assets($wid, jdecode($w['configuration_json'], []));
touch_screen((int)$s['screen_id']);
db_commit();
ok(present_widget(db_one('SELECT * FROM screen_widgets WHERE widget_id = ?', [$wid])), 'Widget duplicated', 201);
