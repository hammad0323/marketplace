<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'screens.edit');
$in = input();
$s = owned_row('screens', 'screen_id', v_int($in, 'screen_id', true, 1), $u);
$limits = effective_limits((int)$s['user_id']);
$reg = widget_registry();
$type = v_str($in, 'widget_type', true, 60);
if (!isset($reg[$type])) fail('Unknown widget type', 400, 'VALIDATION');
$in['width'] = $in['width'] ?? null;
$def = db_one('SELECT default_width, default_height FROM widget_types WHERE widget_key = ?', [$type]);
$maxZ = (int)db_val('SELECT COALESCE(MAX(z_index), 0) FROM screen_widgets WHERE screen_id = ?', [$s['screen_id']]);
$cw = clean_widget([
    'widget_type' => $type, 'widget_name' => $in['widget_name'] ?? null, 'widget_uid' => $in['widget_uid'] ?? null,
    'position_x' => $in['position_x'] ?? (1 - (float)$def['default_width']) / 2, 'position_y' => $in['position_y'] ?? (1 - (float)$def['default_height']) / 2,
    'width' => $in['width'] ?? $def['default_width'], 'height' => $in['height'] ?? $def['default_height'],
    'z_index' => $in['z_index'] ?? $maxZ + 1, 'configuration' => $in['configuration'] ?? [],
], $limits, false, 0);
$ids = config_media_ids($cw['configuration']);
$ok = accessible_media((int)$s['user_id'], $ids);
foreach ($ids as $m) if (!isset($ok[$m])) fail("Media #{$m} is not in your library", 400, 'VALIDATION');
db_begin();
$wid = db_insert('INSERT INTO screen_widgets (widget_uid, screen_id, widget_type, widget_name, position_x, position_y, width, height, rotation, z_index, configuration_json, is_locked, is_visible) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
    [$cw['widget_uid'], $s['screen_id'], $cw['widget_type'], $cw['widget_name'], $cw['position_x'], $cw['position_y'], $cw['width'], $cw['height'], $cw['rotation'], $cw['z_index'], jencode($cw['configuration']), $cw['is_locked'], $cw['is_visible']]);
sync_widget_assets($wid, $cw['configuration']);
touch_screen((int)$s['screen_id']);
record_recent_widget((int)$u['user_id'], $type);
db_commit();
ok(present_widget(db_one('SELECT * FROM screen_widgets WHERE widget_id = ?', [$wid])), 'Widget added', 201);
