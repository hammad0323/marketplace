<?php
require dirname(__DIR__) . '/config.php';

if (admin()) {
    redirect('admin/');
}
$error = '';
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $attempts = $_SESSION['login_attempts'] ?? ['n' => 0, 't' => time()];
    if (time() - $attempts['t'] > 900) {
        $attempts = ['n' => 0, 't' => time()];
    }
    if (!csrf_ok()) {
        $error = 'Your session expired. Please try again.';
    } elseif ($attempts['n'] >= 5) {
        $error = 'Too many failed attempts. Please wait 15 minutes and try again.';
    } else {
        $user = row('SELECT * FROM admins WHERE email = ?', [$email]);
        if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $user['id'];
            unset($_SESSION['login_attempts']);
            q('UPDATE admins SET last_login = NOW() WHERE id = ?', [$user['id']]);
            redirect('admin/');
        }
        $attempts['n']++;
        $error = 'Incorrect email or password.';
    }
    $_SESSION['login_attempts'] = $attempts;
}
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Admin Login · <?= e(setting('site_name', 'Webanza Tech')) ?></title>
<link rel="icon" href="<?= e(media(setting('favicon', 'assets/img/favicon.png'))) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/fontawesome/css/all.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="login-page">
  <div class="login-card">
    <img class="login-logo" src="<?= e(media(setting('logo', 'assets/img/logo.png'))) ?>" alt="<?= e(setting('site_name')) ?>">
    <h1>Welcome back</h1>
    <p class="muted">Sign in to manage your website.</p>
    <?php if ($error): ?><div class="alert alert-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <label class="field"><span>Email</span><input type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
      <label class="field"><span>Password</span><input type="password" name="password" required></label>
      <button class="btn btn-primary btn-block" type="submit">Sign in <i class="fa-solid fa-arrow-right"></i></button>
    </form>
    <a class="back" href="<?= e(url()) ?>"><i class="fa-solid fa-arrow-left"></i> Back to website</a>
  </div>
</body>
</html>
