<?php
require __DIR__ . '/includes/init.php';
if (current_admin()) {
    redirect('admin/');
}
$error = null;
if (is_post()) {
    csrf_check();
    $email = strtolower(input('email', '', 'post'));
    $ipKey = 'alogin:' . client_ip();
    $acctKey = 'alogin:' . hash('sha256', $email);
    if (!rate_limit($ipKey, 10, 900) || !rate_limit($acctKey, 5, 900)) {
        $error = 'Too many sign-in attempts. Please wait 15 minutes.';
        audit_log('login_rate_limited', 'admin', null, ['email' => $email]);
    } else {
        $a = db_row('SELECT * FROM admins WHERE email = ?', [$email]);
        if ($a && (int) $a['is_active'] && password_verify((string) ($_POST['password'] ?? ''), $a['password_hash'])) {
            if (password_needs_rehash($a['password_hash'], PASSWORD_DEFAULT)) {
                db_exec('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($_POST['password'], PASSWORD_DEFAULT), (int) $a['id']]);
            }
            rate_limit_reset($acctKey);
            admin_login($a);
            $dest = $_SESSION['admin_redirect'] ?? '';
            unset($_SESSION['admin_redirect']);
            if ($dest && str_starts_with($dest, BASE_PATH . '/admin/') && !str_contains($dest, '//')) {
                header('Location: ' . $dest);
                exit;
            }
            redirect('admin/');
        }
        audit_log('login_failed', 'admin', $a ? (int) $a['id'] : null, ['email' => $email]);
        $error = 'Invalid email or password.';
    }
}
admin_header('Sign in');
?>
<div class="a-login">
    <img src="<?= e(img_url(setting('logo', 'assets/images/brand/logo-round.webp'))) ?>" alt="" width="88" height="88" class="mb-3">
    <h1 class="h4 mb-1"><?= e(store_name()) ?></h1>
    <p class="text-muted small mb-4">Administration</p>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="text-start">
        <?= csrf_field() ?>
        <?= a_input('email', 'Email', input('email', '', 'post'), 'email', ['required' => true, 'autocomplete' => 'username', 'autofocus' => true]) ?>
        <?= a_input('password', 'Password', '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
        <button class="btn btn-dark w-100 py-2">Sign in</button>
    </form>
    <p class="small text-muted mt-4 mb-0">Forgot your password? Ask a Super Admin to reset it, or run <code>php tools/create-admin.php</code> on the server.</p>
</div>
<?php admin_footer();
