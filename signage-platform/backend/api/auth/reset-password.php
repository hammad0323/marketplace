<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$in = input();
rate_limit('reset-confirm:ip:' . client_ip(), 10, 900);
$code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)($in['token'] ?? $in['code'] ?? '')));
if ($code === '') v_error('token', 'Reset code is required');
$new = v_password($in, 'new_password');
$r = db_one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > UTC_TIMESTAMP()', [sha256($code)]);
if (!$r) fail('This reset code is invalid or has expired', 400, 'RESET_INVALID');
db_begin();
db_run('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE user_id = ?', [password_hash($new, PASSWORD_DEFAULT), $r['user_id']]);
db_run('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE reset_id = ?', [$r['reset_id']]);
db_run('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL', [$r['user_id']]);
log_activity((int)$r['user_id'], 'auth.reset_completed', 'user', (int)$r['user_id'], 'Password reset completed');
db_commit();
ok(null, 'Password updated. Please sign in with your new password.');
