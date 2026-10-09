<?php
require __DIR__ . '/_inc/bootstrap.php';
$sent = false;
if (is_post()) {
    require_csrf();
    $email = mb_strtolower(input('email'));
    if (valid_email($email) && rate_limit('admin_pw_reset', $email . client_ip(), 3, 3600)) {
        $a = db_one("SELECT * FROM admins WHERE email = ? AND status = 'active'", [$email]);
        if ($a) {
            $token = create_password_reset('admin', (int) $a['id']);
            send_template_email($email, 'Admin password reset', 'password_reset', ['link' => url('admin/reset-password', ['token' => $token])]);
            audit_log('password_reset_requested', 'admin', (int) $a['id'], [], (int) $a['id']);
        }
    }
    $sent = true;
}
admin_header('Reset password');
?>
<div class="auth-box">
  <div class="auth-box__brand">BEGLET<small>Reset password</small></div>
  <?php if ($sent): ?>
    <div class="alert alert-success">If that email belongs to an administrator, a reset link has been sent. It expires in 60 minutes.</div>
  <?php else: ?>
    <form method="post"><?= csrf_field() ?><?= f_text('email', 'Admin email', '', ['required' => true], null, 'email') ?><button class="btn btn-primary w-100">Send reset link</button></form>
  <?php endif; ?>
  <p class="text-center small mt-3 mb-0"><a href="<?= e(admin_url('login')) ?>">Back to sign in</a></p>
</div>
<?php admin_footer();
