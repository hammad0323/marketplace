<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$in = input();
$email = v_email($in, 'email', true);
rate_limit('reset:ip:' . client_ip(), 10, 3600);
rate_limit('reset:email:' . $email, 3, 3600);
$u = db_one("SELECT user_id, full_name, email FROM users WHERE email = ? AND deleted_at IS NULL AND status = 'active'", [$email]);
if ($u) {
    // 8-character code (easy to type on a phone) — stored hashed, valid 30 minutes, single use.
    $code = '';
    for ($i = 0; $i < 8; $i++) $code .= PAIRING_ALPHABET[random_int(0, strlen(PAIRING_ALPHABET) - 1)];
    db_run('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE user_id = ? AND used_at IS NULL', [$u['user_id']]);
    db_run('INSERT INTO password_resets (user_id, token_hash, expires_at, ip_address) VALUES (?, ?, UTC_TIMESTAMP() + INTERVAL 30 MINUTE, ?)',
        [$u['user_id'], sha256($code), client_ip()]);
    $app = app_setting('app_name', 'Signage');
    send_mail($u['email'], "{$app} password reset code",
        "Hi {$u['full_name']},\n\nYour password reset code is: {$code}\n\nIt expires in 30 minutes. If you did not request this, you can ignore this email.\n");
    log_activity((int)$u['user_id'], 'auth.reset_requested', 'user', (int)$u['user_id'], 'Password reset requested');
}
ok(null, 'If an account exists for that email, a reset code has been sent.');
