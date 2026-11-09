<?php
// {user_id, plan_id?, plan_end_date?, max_screens? (-1 unlimited, null inherit), max_playlists?, max_devices?, max_storage_mb?, max_upload_mb?,
//  allowed_widgets? (["*"] | [...] | null), can_upload_video?, can_use_premium_templates?, permissions?: {key: true|false|null}}
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$admin = require_admin();
$in = input();
$u = admin_user_row(v_int($in, 'user_id', true, 1));
db_begin();
admin_apply_template_access((int)$u['user_id'], $in);
admin_apply_limits((int)$u['user_id'], $in, (int)$admin['user_id']);
$l = effective_limits((int)$u['user_id']);
log_activity((int)$admin['user_id'], 'admin.user.limits', 'user', (int)$u['user_id'], 'Updated limits', $l);
db_commit();
notify((int)$u['user_id'], 'Your plan was updated', 'Screens: ' . ($l['max_screens'] === -1 ? 'unlimited' : $l['max_screens']) . ', displays: ' . ($l['max_devices'] === -1 ? 'unlimited' : $l['max_devices']) . '.', 'info', '/profile');
ok(admin_present_user(admin_user_row((int)$u['user_id']), true), 'Limits updated');
