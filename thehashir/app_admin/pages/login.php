<?php
/** /manage/login — Super Admin sign-in (th_admin_table). */
if (current_admin()) {
    bo_redirect('admin', 'dashboard');
}
$error = '';
$user = '';
if (is_post()) {
    $user = post('username');
    if (!csrf_check(post('csrf'))) {
        $error = 'Your session expired. Please try again.';
    } elseif ($user === '' || post('password') === '') {
        $error = 'Enter your username and password.';
    } else {
        $error = admin_login($user, (string)$_POST['password']);
        if ($error === '') {
            $to = isset($_SESSION['after_login']) ? $_SESSION['after_login'] : '';
            unset($_SESSION['after_login']);
            $base = url(ADMIN_PATH);
            header('Location: ' . ($to !== '' && strpos($to, $base) === 0 ? $to : $base . '/dashboard'), true, 303);
            exit;
        }
    }
}
auth_card_start('admin', 'Super Admin', 'Sign in to manage The Hashir.');
?>
<?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" novalidate>
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label" for="u">Username or email</label><input class="form-control form-control-lg" id="u" name="username" value="<?= e($user) ?>" autocomplete="username" required autofocus></div>
  <div class="mb-3"><label class="form-label" for="p">Password</label>
    <div class="input-group"><input class="form-control form-control-lg" id="p" name="password" type="password" autocomplete="current-password" required><button class="btn btn-outline-secondary" type="button" data-toggle-pass="#p" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
  <button class="btn btn-primary btn-lg w-100">Sign in</button>
</form>
<?php auth_card_end(array(array('Forgot your password?', url(ADMIN_PATH . '/forgot')), array('Mosque or organization? Use the portal', url(PORTAL_PATH . '/login')))); ?>
