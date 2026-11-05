<?php
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$admin = require_admin();
$in = input();
$u = admin_user_row(v_int($in, 'user_id', true, 1));
$status = v_enum($in, 'status', ['active', 'inactive', 'suspended'], true);
if ((int)$u['user_id'] === (int)$admin['user_id'] && $status !== 'active') fail('You cannot deactivate your own account', 400, 'VALIDATION');
db_begin();
db_run('UPDATE users SET status = ? WHERE user_id = ?', [$status, $u['user_id']]);
if ($status !== 'active') db_run('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL', [$u['user_id']]);
log_activity((int)$admin['user_id'], 'admin.user.' . ($status === 'active' ? 'activate' : 'suspend'), 'user', (int)$u['user_id'], ucfirst($status) . " {$u['email']}");
db_commit();
ok(admin_present_user(admin_user_row((int)$u['user_id'])), $status === 'active' ? 'User activated' : 'User ' . $status . '. They have been signed out.');
