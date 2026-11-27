<?php
/** /portal/login — mosque & organization sign-in (th_venue.username / password). */
if (current_venue()) {
    bo_redirect('venue', 'dashboard');
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
        $error = venue_login($user, (string)$_POST['password']);
        if ($error === '') {
            $to = isset($_SESSION['after_login_portal']) ? $_SESSION['after_login_portal'] : '';
            unset($_SESSION['after_login_portal']);
            $base = url(PORTAL_PATH);
            header('Location: ' . ($to !== '' && strpos($to, $base) === 0 ? $to : $base . '/dashboard'), true, 303);
            exit;
        }
    }
}
auth_card_start('venue', 'Mosque & organization portal', 'Sign in to update your profile, prayer times, programs and events.');
?>
<?php if ($error): ?><div class="alert alert-danger py-2" role="alert"><?= e($error) ?></div><?php endif; ?>
<form method="post" novalidate>
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label" for="u">Username</label><input class="form-control form-control-lg" id="u" name="username" value="<?= e($user) ?>" autocomplete="username" required autofocus></div>
  <div class="mb-3"><label class="form-label" for="p">Password</label>
    <div class="input-group"><input class="form-control form-control-lg" id="p" name="password" type="password" autocomplete="current-password" required><button class="btn btn-outline-secondary" type="button" data-toggle-pass="#p" aria-label="Show password"><i class="bi bi-eye"></i></button></div></div>
  <button class="btn btn-primary btn-lg w-100">Sign in</button>
</form>
<?php auth_card_end(array(array('Forgot your password?', url(PORTAL_PATH . '/forgot')), array('Not listed yet? Add your mosque', url('/solution-mosque#get-started')))); ?>
