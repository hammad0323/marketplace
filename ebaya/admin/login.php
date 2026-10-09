<?php
require __DIR__ . '/partials/bootstrap.php';
if (current_admin()) redirect(admin_url());
$error = null;
if (is_post()) {
    csrf_check();
    rate_limit_or_fail('admin-login', 6, 900);
    rate_limit_or_fail('admin-login-email', 6, 900, strtolower(post('email')));
    $a = admin_attempt_login(post('email'), (string)($_POST['password'] ?? ''));
    if ($a) {
        admin_login($a);
        $ret = (string)post('return');
        redirect(str_starts_with($ret, BASE_PATH . '/admin') && !str_contains($ret, '//') ? $ret : admin_url());
    }
    $error = 'Incorrect email or password.';
    log_message('security', 'Failed admin login for ' . post('email') . ' from ' . client_ip());
}
$admin_title = 'Sign in';
require __DIR__ . '/partials/header.php';
?>
<div class="login-wrap">
  <form method="post" class="login-card" novalidate>
    <div class="brand"><?= e(setting('site_name', 'Ebaya')) ?></div>
    <p class="text-center text-muted small mb-4">Store administration</p>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?><input type="hidden" name="return" value="<?= e(get('return')) ?>">
    <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" required autocomplete="username" value="<?= e(post('email')) ?>"></div>
    <div class="mb-4"><label class="form-label">Password</label><input class="form-control" type="password" name="password" required autocomplete="current-password"></div>
    <button class="btn btn-primary w-100">Sign in</button>
    <p class="text-center small text-muted mt-3 mb-0">Forgotten your password? Ask a Super Admin to reset it.</p>
  </form>
</div>
<?php require __DIR__ . '/partials/footer.php';
