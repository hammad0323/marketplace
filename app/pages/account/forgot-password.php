<?php
meta_set(['title' => 'Reset your password', 'noindex' => true]);
$sent = false;
if (is_post()) {
    require_csrf();
    $email = mb_strtolower(input('email'));
    // Same response whether or not the account exists (no enumeration).
    if (valid_email($email) && rate_limit('pw_reset', $email, 3, 3600) && rate_limit('pw_reset_ip', client_ip(), 10, 3600)) {
        $c = db_one("SELECT * FROM customers WHERE email = ? AND status = 'active'", [$email]);
        if ($c) {
            $token = create_password_reset('customer', (int) $c['id']);
            send_template_email($email, 'Reset your ' . setting('site_name', 'Beglet') . ' password', 'password_reset', ['link' => url('account/reset-password', ['token' => $token])]);
        }
    }
    $sent = true;
}
partial('header');
?>
<div class="container container--narrow page-pad">
  <div class="auth-card" data-reveal="fade-up">
    <h1 class="page-title">Forgot your password?</h1>
    <?php if ($sent): ?>
      <div class="alert alert-success">If an account exists for that email, a reset link is on its way. It expires in 60 minutes.</div>
    <?php else: ?>
      <p class="text-muted">Enter your email and we will send you a secure link to choose a new password.</p>
      <form method="post" novalidate><?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" required maxlength="190"></div>
        <button class="btn-lux btn-lux--block">Send reset link</button>
      </form>
    <?php endif; ?>
    <p class="small text-center mt-3"><a href="<?= e(path_url('account/login')) ?>">Back to sign in</a></p>
  </div>
</div>
<?php partial('footer');
