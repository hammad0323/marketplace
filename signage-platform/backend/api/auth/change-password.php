<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$in = input();
rate_limit('chpw:user:' . $u['user_id'], 10, 900);
$current = (string)($in['current_password'] ?? '');
$new = v_password($in, 'new_password');
$hash = db_val('SELECT password_hash FROM users WHERE user_id = ?', [$u['user_id']]);
if (!password_verify($current, $hash)) fail('Your current password is incorrect', 400, 'VALIDATION', ['current_password' => 'Incorrect password']);
if (password_verify($new, $hash)) fail('Choose a password different from the current one', 400, 'VALIDATION', ['new_password' => 'Same as current']);
db_begin();
db_run('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE user_id = ?', [password_hash($new, PASSWORD_DEFAULT), $u['user_id']]);
// Sign out other sessions
db_run('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND token_id <> ? AND revoked_at IS NULL', [$u['user_id'], $u['token_id']]);
log_activity((int)$u['user_id'], 'auth.password_change', 'user', (int)$u['user_id'], 'Changed password');
db_commit();
ok(null, 'Password changed. Other devices have been signed out.');
