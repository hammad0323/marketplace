<?php
require __DIR__ . '/_init.php';
if (is_post()) {
    if (rate_limit('forgot:' . client_ip(), 5, 3600)) {
        owner_reset_request(trim((string) ($_POST['login'] ?? '')));
    }
    flash('success', 'If your account has an email address, we sent a reset link. Otherwise ask ' . platform_name() . ' support to reset your password.');
    redirect('owner/forgot.php');
}
panel_auth_header('Forgot password', 'owner');
?>
<h2>Reset your password</h2><p class="text-muted small mb-4">Enter your username or email. We’ll email a reset link if an email address is saved on your account.</p>
<form method="post"><?= csrf_field() ?><?= f_input('login', 'Username or email', '', 'text', ['required' => true]) ?><button class="btn btn-primary w-100">Send reset link</button></form>
<p class="text-center small mt-3 mb-0"><a href="<?= e(path('owner/login.php')) ?>">Back to sign in</a></p>
<?php panel_auth_footer();
