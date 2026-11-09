<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'screens.create');
$in = input();
$uid = (int)$u['user_id'];
$name = v_str($in, 'screen_name', true, 150);
$templateId = v_int($in, 'template_id', false, 1);
$folderId = v_int($in, 'folder_id', false, 1);
if ($folderId) owned_row('screen_folders', 'folder_id', $folderId, $u);
$limits = effective_limits($uid);

$layout = null;
if ($templateId) {
    $t = db_one("SELECT * FROM screen_templates WHERE template_id = ? AND status = 'active' AND deleted_at IS NULL AND (is_public = 1 OR created_by = ?)", [$templateId, $uid]);
    if (!$t || !template_allowed($u, $templateId)) not_found('Template');
    if ($t['is_premium'] && !$limits['can_use_premium_templates'] && !has_permission($u, 'templates.premium')) {
        fail('This is a premium template. Upgrade your plan to use it.', 403, 'PREMIUM_REQUIRED');
    }
    $layout = jdecode($t['template_json'], []);
    [$orientation, $w, $h] = canvas_for($t['orientation'], (int)$t['width'], (int)$t['height']);
} else {
    $orientation = v_enum($in, 'orientation', ORIENTATIONS, false, 'landscape');
    [$orientation, $w, $h] = canvas_for($orientation, v_int($in, 'width', false, 16, 16384), v_int($in, 'height', false, 16, 16384));
    $layout = ['background' => ['type' => 'color', 'value' => v_color($in, 'background_color') ?? '#17191C'], 'widgets' => []];
}

db_begin();
assert_limit($uid, 'screens');
$sid = db_insert('INSERT INTO screens (screen_uid, user_id, template_id, folder_id, screen_name, description, orientation, width, height, background_type, background_value, background_config_json, default_duration)
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "color", "#17191C", "{}", ?)',
    [uuid4(), $uid, $templateId, $folderId, $name, v_str($in, 'description', false, 255), $orientation, $w, $h, v_int($in, 'default_duration', false, 1, 86400, 10)]);
$screen = db_one('SELECT * FROM screens WHERE screen_id = ?', [$sid]);
foreach ($layout['widgets'] as &$wd) unset($wd['widget_uid']);   // fresh ids per screen
unset($wd);
$skipped = write_layout($screen, ['background' => $layout['background'] ?? null, 'widgets' => $layout['widgets'] ?? []], $limits, true);
if ($templateId) db_run('UPDATE screen_templates SET usage_count = usage_count + 1 WHERE template_id = ?', [$templateId]);
log_activity($uid, 'screen.create', 'screen', $sid, "Created screen {$name}");
db_commit();

$screen = db_one('SELECT * FROM screens WHERE screen_id = ?', [$sid]);
$msg = 'Screen created successfully';
if ($skipped) $msg .= '. Some widgets are not included in your plan and were left out: ' . implode(', ', $skipped);
ok(present_screen($screen, true), $msg, 201, ['skipped_widgets' => $skipped]);
