<?php
// {user_id, full_name?, email?, phone?, business_name?, timezone?, account_expires_at?, role?, reset_password?: bool, new_password?}
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$admin = require_admin();
$in = input();
$u = admin_user_row(v_int($in, 'user_id', true, 1));
$email = v_email($in, 'email') ?? $u['email'];
if ($email !== $u['email'] && db_val('SELECT 1 FROM users WHERE email = ? AND user_id <> ?', [$email, $u['user_id']])) fail('Email already in use', 409, 'CONFLICT');
$roleId = $u['role_id'];
if ($r = v_enum($in, 'role', ['user', 'super_admin'])) {
    if ((int)$u['user_id'] === (int)$admin['user_id'] && $r !== 'super_admin') fail('You cannot remove your own admin role', 400, 'VALIDATION');
    $roleId = (int)db_val('SELECT role_id FROM roles WHERE role_key = ?', [$r]);
}
db_begin();
db_run('UPDATE users SET full_name = ?, email = ?, phone = ?, business_name = ?, timezone = ?, account_expires_at = ?, role_id = ? WHERE user_id = ?', [
    v_str($in, 'full_name', false, 150, 2) ?? $u['full_name'], $email,
    array_key_exists('phone', $in) ? v_str($in, 'phone', false, 30) : $u['phone'],
    array_key_exists('business_name', $in) ? v_str($in, 'business_name', false, 150) : $u['business_name'],
    v_timezone($in, 'timezone') ?? $u['timezone'],
    array_key_exists('account_expires_at', $in) ? v_datetime($in, 'account_expires_at') : $u['account_expires_at'], $roleId, $u['user_id']]);
$credentials = null;
if (v_bool($in, 'reset_password', false) || v_present($in, 'new_password')) {
    $pw = v_present($in, 'new_password') ? v_password($in, 'new_password') : generate_password();
    db_run('UPDATE users SET password_hash = ?, must_change_password = 1 WHERE user_id = ?', [password_hash($pw, PASSWORD_DEFAULT), $u['user_id']]);
    db_run('UPDATE api_tokens SET revoked_at = UTC_TIMESTAMP() WHERE user_id = ? AND revoked_at IS NULL', [$u['user_id']]);
    $credentials = ['email' => $email, 'password' => $pw, 'share_text' => "Your password has been reset.\n\nEmail: {$email}\nNew password: {$pw}\n\nPlease sign in and change it."];
    if (v_bool($in, 'send_email', false)) send_mail($email, 'Your password has been reset', $credentials['share_text']);
    log_activity((int)$admin['user_id'], 'admin.user.reset_password', 'user', (int)$u['user_id'], "Reset password for {$email}");
}
admin_apply_limits((int)$u['user_id'], $in, (int)$admin['user_id']);
log_activity((int)$admin['user_id'], 'admin.user.update', 'user', (int)$u['user_id'], "Updated {$email}");
db_commit();
ok(['user' => admin_present_user(admin_user_row((int)$u['user_id']), true), 'credentials' => $credentials], 'User updated');
