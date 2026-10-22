<?php
seo_set(['title' => 'Reset Password', 'noindex' => true]);
$sent = false;
$error = null;
if (is_post()) {
    csrf_check();
    $email = strtolower(input('email', '', 'post'));
    if (!rate_limit('forgot:' . client_ip(), 5, 3600)) {
        $error = 'Too many requests. Please try again later.';
    } else {
        $c = valid_email($email) ? db_row('SELECT * FROM customers WHERE email = ? AND is_active = 1', [$email]) : null;
        if ($c) {
            $token = password_reset_create('customer', (int) $c['id']);
            $link = url('account/reset-password?token=' . $token);
            send_mail($c['email'], 'Reset your password', mail_layout('Reset your password', '<p>Hi ' . e($c['first_name']) . ',</p><p>Use the button below to choose a new password. This link expires in 1 hour and can be used once.</p>'
                . '<p><a href="' . e($link) . '" style="background:#111;color:#fff;padding:12px 22px;text-decoration:none;display:inline-block">Reset password</a></p><p style="font-size:12px;color:#777">If you did not request this, you can ignore this email.</p>'));
        }
        $sent = true; // same response whether or not the account exists
    }
}
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad uc-auth">
    <div class="uc-auth-card">
        <h1 class="uc-page-title">Reset Password</h1>
        <?php if ($sent): ?>
            <div class="uc-alert uc-alert--success">If an account exists for that email, we have sent a password reset link. Please check your inbox.</div>
            <?php if (!mail_enabled()): ?><p class="small text-muted">Email delivery is not configured on this store yet — please contact us for help.</p><?php endif; ?>
        <?php else: ?>
            <?php if ($error): ?><div class="uc-alert uc-alert--error"><?= e($error) ?></div><?php endif; ?>
            <p>Enter your email and we will send you a link to reset your password.</p>
            <form method="post"><?= csrf_field() ?>
                <label class="uc-field"><span>Email</span><input type="email" name="email" required autocomplete="email"></label>
                <button class="uc-btn uc-btn--block" type="submit">Send Reset Link</button>
            </form>
        <?php endif; ?>
        <p class="text-center mt-4 mb-0"><a href="<?= e(url('account/login')) ?>">Back to sign in</a></p>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
