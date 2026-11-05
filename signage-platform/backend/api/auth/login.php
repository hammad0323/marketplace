<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$in = input();
$email = v_email($in, 'email', true);
$password = (string)($in['password'] ?? '');
if ($password === '') v_error('password', 'Password is required');

$max = (int)app_setting('login_rate_limit_per_15min', 10);
rate_limit('login:ip:' . client_ip(), $max * 3, 900);
rate_limit('login:email:' . $email, $max, 900);

$u = db_one('SELECT u.*, r.role_key FROM users u JOIN roles r USING (role_id) WHERE u.email = ? AND u.deleted_at IS NULL', [$email]);
// Constant-time-ish: always run password_verify
$hash = $u['password_hash'] ?? '$2y$10$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';
if (!password_verify($password, $hash) || !$u) {
    log_activity($u['user_id'] ?? null, 'auth.login_failed', 'user', $u['user_id'] ?? null, "Failed login for {$email}");
    fail('Incorrect email or password', 401, 'INVALID_CREDENTIALS');
}
if ($p = user_account_problem($u)) fail($p[0], 403, $p[1]);
rate_limit_reset('login:email:' . $email);

if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
    db_run('UPDATE users SET password_hash = ? WHERE user_id = ?', [password_hash($password, PASSWORD_DEFAULT), $u['user_id']]);
}
[$token, $exp] = issue_user_token((int)$u['user_id'], v_str($in, 'device_label', false, 150));
db_run('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE user_id = ?', [$u['user_id']]);
log_activity((int)$u['user_id'], 'auth.login', 'user', (int)$u['user_id'], 'Signed in');

ok([
    'token' => $token,
    'expires_at' => $exp,
    'user' => present_user($u),
    'permissions' => user_permission_keys((int)$u['user_id']),
    'limits' => effective_limits((int)$u['user_id']),
    'usage' => user_usage((int)$u['user_id']),
], 'Signed in successfully');
