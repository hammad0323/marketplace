<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'screens.edit');
$in = input();
[$w, $s] = widget_with_screen(v_int($in, 'widget_id', true, 1), $u);
$limits = effective_limits((int)$s['user_id']);
$merged = [
    'widget_uid' => $w['widget_uid'], 'widget_type' => $w['widget_type'],
    'widget_name' => $in['widget_name'] ?? $w['widget_name'],
    'position_x' => $in['position_x'] ?? $w['position_x'], 'position_y' => $in['position_y'] ?? $w['position_y'],
    'width' => $in['width'] ?? $w['width'], 'height' => $in['height'] ?? $w['height'], 'rotation' => $in['rotation'] ?? $w['rotation'],
    'z_index' => $in['z_index'] ?? $w['z_index'], 'is_locked' => $in['is_locked'] ?? $w['is_locked'], 'is_visible' => $in['is_visible'] ?? $w['is_visible'],
    'configuration' => isset($in['configuration']) && is_array($in['configuration'])
        ? array_replace(jdecode($w['configuration_json'], []), $in['configuration']) : jdecode($w['configuration_json'], []),
];
$cw = clean_widget($merged, $limits, false, 0);
$ids = config_media_ids($cw['configuration']);
$ok = accessible_media((int)$s['user_id'], $ids);
foreach ($ids as $m) if (!isset($ok[$m])) fail("Media #{$m} is not in your library", 400, 'VALIDATION');
db_begin();
db_run('UPDATE screen_widgets SET widget_name = ?, position_x = ?, position_y = ?, width = ?, height = ?, rotation = ?, z_index = ?, configuration_json = ?, is_locked = ?, is_visible = ? WHERE widget_id = ?',
    [$cw['widget_name'], $cw['position_x'], $cw['position_y'], $cw['width'], $cw['height'], $cw['rotation'], $cw['z_index'], jencode($cw['configuration']), $cw['is_locked'], $cw['is_visible'], $w['widget_id']]);
sync_widget_assets((int)$w['widget_id'], $cw['configuration']);
touch_screen((int)$s['screen_id']);
db_commit();
ok(present_widget(db_one('SELECT * FROM screen_widgets WHERE widget_id = ?', [$w['widget_id']])), 'Widget updated');
