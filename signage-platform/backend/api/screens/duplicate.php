<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'screens.duplicate');
$in = input();
$src = owned_row('screens', 'screen_id', v_int($in, 'screen_id', true, 1), $u);
$uid = (int)$src['user_id'];
$name = v_str($in, 'screen_name', false, 150) ?? mb_substr($src['screen_name'] . ' (copy)', 0, 150);
db_begin();
assert_limit($uid, 'screens');
$sid = db_insert('INSERT INTO screens (screen_uid, user_id, template_id, folder_id, screen_name, description, orientation, width, height, background_type, background_value, background_media_id, background_config_json, default_duration, expires_at)
                  SELECT ?, user_id, template_id, folder_id, ?, description, orientation, width, height, background_type, background_value, background_media_id, background_config_json, default_duration, expires_at
                    FROM screens WHERE screen_id = ?', [uuid4(), $name, $src['screen_id']]);
foreach (db_all('SELECT * FROM screen_widgets WHERE screen_id = ?', [$src['screen_id']]) as $w) {
    $wid = db_insert('INSERT INTO screen_widgets (widget_uid, screen_id, widget_type, widget_name, position_x, position_y, width, height, rotation, z_index, configuration_json, is_locked, is_visible, status)
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [uuid4(), $sid, $w['widget_type'], $w['widget_name'], $w['position_x'], $w['position_y'], $w['width'], $w['height'], $w['rotation'], $w['z_index'], $w['configuration_json'], $w['is_locked'], $w['is_visible'], $w['status']]);
    sync_widget_assets($wid, jdecode($w['configuration_json'], []));
}
log_activity((int)$u['user_id'], 'screen.duplicate', 'screen', $sid, "Duplicated {$src['screen_name']}");
db_commit();
ok(present_screen(db_one('SELECT * FROM screens WHERE screen_id = ?', [$sid]), true), 'Screen duplicated', 201);
