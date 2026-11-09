<?php
/**
 * {full_name, email, phone?, password? (generated if empty), role?: user|super_admin, business_name?, status?,
 *  account_expires_at?, plan_id?, max_screens?, max_playlists?, max_devices?, max_storage_mb?, allowed_widgets?,
 *  can_upload_video?, permissions?: {key: bool}, send_email?: bool}
 * Response includes the plain password ONCE so the admin can share it.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$admin = require_admin();
$in = input();
$email = v_email($in, 'email', true);
if (db_val('SELECT 1 FROM users WHERE email = ?', [$email])) fail('A user with this email already exists', 409, 'CONFLICT', ['email' => 'Already registered']);
$generated = !v_present($in, 'password');
$password = $generated ? generate_password() : v_password($in, 'password');
$roleKey = v_enum($in, 'role', ['user', 'super_admin'], false, 'user');
$roleId = (int)db_val('SELECT role_id FROM roles WHERE role_key = ?', [$roleKey]);
db_begin();
$uid = db_insert('INSERT INTO users (role_id, full_name, email, phone, password_hash, business_name, timezone, status, must_change_password, account_expires_at, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
    $roleId, v_str($in, 'full_name', true, 150, 2), $email, v_str($in, 'phone', false, 30), password_hash($password, PASSWORD_DEFAULT),
    v_str($in, 'business_name', false, 150), v_timezone($in, 'timezone') ?? 'UTC', v_enum($in, 'status', ['active', 'inactive', 'suspended'], false, 'active'),
    v_bool($in, 'must_change_password', $generated), v_datetime($in, 'account_expires_at'), $admin['user_id']]);
if (!v_present($in, 'plan_id')) $in['plan_id'] = (int)db_val('SELECT plan_id FROM plans WHERE is_default = 1 ORDER BY plan_id LIMIT 1');
admin_apply_template_access($uid, $in);
admin_apply_limits($uid, $in, (int)$admin['user_id']);
log_activity((int)$admin['user_id'], 'admin.user.create', 'user', $uid, "Created user {$email}");
notify($uid, 'Welcome!', 'Your account is ready. Create your first screen to get started.', 'success', '/screens/new');
db_commit();
$app = app_setting('app_name', 'Signage');
$credentials = "Your {$app} account is ready.\n\nEmail: {$email}\nPassword: {$password}\n\nPlease sign in and change your password.";
if (v_bool($in, 'send_email', false)) send_mail($email, "Your {$app} login details", $credentials);
ok(['user' => admin_present_user(admin_user_row($uid), true), 'credentials' => ['email' => $email, 'password' => $password, 'share_text' => $credentials]],
    'User created', 201);
