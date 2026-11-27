<?php
/**
 * /manage/forgot — emails a one-hour reset link to the admin's address.
 * th_admin_table has no token columns, so the token hash is kept in storage/resets/.
 */
$sent = false;
if (is_post()) {
    require_post_csrf();
    $email = post('email');
    $key = 'adminreset|ip|' . client_ip();
    if (throttle_wait($key) === 0) {
        throttle_fail($key);
        $a = filter_var($email, FILTER_VALIDATE_EMAIL) ? db_one('SELECT admin_id, admin_name, admin_email FROM th_admin_table WHERE admin_email = ? AND admin_status = 1', 's', array($email)) : null;
        if ($a) {
            $token = bin2hex(random_bytes(32));
            storage_write('resets/admin-' . (int)$a['admin_id'] . '.json', array('hash' => hash('sha256', $token), 'expires' => time() + 3600));
            $link = SITE_URL . url(ADMIN_PATH . '/reset?id=' . (int)$a['admin_id'] . '&token=' . $token);
            send_mail($a['admin_email'], 'Reset your The Hashir admin password', "Assalamu alaikum " . $a['admin_name'] . ",\n\nUse this link within one hour to choose a new password:\n" . $link . "\n\nIf you didn't ask for this, you can ignore this email.\n\nThe Hashir");
            log_activity('admin', (int)$a['admin_id'], 'auth.reset_requested', 'admin', (int)$a['admin_id']);
        }
    }
    $sent = true;
}
auth_card_start('admin', 'Reset your password', 'We\'ll email you a link to choose a new password.');
if ($sent): ?>
  <div class="alert alert-success">If that address belongs to an admin account, a reset link is on its way. Check your inbox and spam folder.</div>
<?php else: ?>
<form method="post"><?= csrf_field() ?>
  <div class="mb-3"><label class="form-label" for="em">Admin email address</label><input class="form-control form-control-lg" id="em" name="email" type="email" required autofocus></div>
  <button class="btn btn-primary btn-lg w-100">Send reset link</button>
</form>
<?php endif;
auth_card_end(array(array('Back to sign in', url(ADMIN_PATH . '/login'))));
