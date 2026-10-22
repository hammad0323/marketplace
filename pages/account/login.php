<?php
seo_set(['title' => 'Sign In', 'noindex' => true]);
if (current_customer()) {
    redirect('account');
}
$error = null;
$next = input('next', '', 'get');
if (is_post()) {
    csrf_check();
    $email = strtolower(input('email', '', 'post'));
    $ipKey = 'clogin:' . client_ip();
    $acctKey = 'clogin:' . hash('sha256', $email);
    if (!rate_limit($ipKey, 20, 900) || !rate_limit($acctKey, 8, 900)) {
        $error = 'Too many sign-in attempts. Please wait 15 minutes and try again.';
    } else {
        $c = db_row('SELECT * FROM customers WHERE email = ?', [$email]);
        if ($c && (int) $c['is_active'] && password_verify((string) ($_POST['password'] ?? ''), $c['password_hash'])) {
            if (password_needs_rehash($c['password_hash'], PASSWORD_DEFAULT)) {
                db_exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($_POST['password'], PASSWORD_DEFAULT), (int) $c['id']]);
            }
            rate_limit_reset($acctKey);
            customer_login($c);
            flash('success', 'Welcome back, ' . $c['first_name'] . '.');
            $dest = $_SESSION['login_redirect'] ?? '';
            unset($_SESSION['login_redirect']);
            if (input('next', '', 'post') === 'checkout') {
                redirect('checkout');
            }
            if ($dest && str_starts_with($dest, BASE_PATH . '/') && !str_contains($dest, '//')) {
                header('Location: ' . $dest);
                exit;
            }
            redirect('account');
        }
        $error = 'The email or password is incorrect.';
    }
}
require UC_ROOT . '/includes/header.php';
?>
<section class="uc-container uc-page-pad uc-auth">
    <div class="uc-auth-card">
        <h1 class="uc-page-title">Sign In</h1>
        <?php if ($error): ?><div class="uc-alert uc-alert--error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <label class="uc-field"><span>Email</span><input type="email" name="email" required autocomplete="email" value="<?= e(input('email', '', 'post')) ?>"></label>
            <label class="uc-field"><span>Password</span><input type="password" name="password" required autocomplete="current-password"></label>
            <p class="text-end small"><a href="<?= e(url('account/forgot-password')) ?>">Forgot password?</a></p>
            <button class="uc-btn uc-btn--block" type="submit">Sign In</button>
        </form>
        <p class="text-center mt-4 mb-0">New to <?= e(store_name()) ?>? <a href="<?= e(url('account/register')) ?>">Create an account</a></p>
    </div>
</section>
<?php require UC_ROOT . '/includes/footer.php';
