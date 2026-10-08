<?php
define('ADMIN_PUBLIC', true);
require __DIR__ . '/includes/admin.php';

if (admin()) redirect('admin');

$error = '';
if (is_post()) {
    require_csrf();
    $key = 'admin_fail_' . md5(client_ip());
    $fails = $_SESSION[$key] ?? ['n' => 0, 't' => 0];
    if ($fails['n'] >= 5 && $fails['t'] > time() - 900) {
        $error = 'Too many failed attempts. Try again in 15 minutes.';
    } else {
        $a = row('SELECT * FROM admins WHERE email = ? AND status = 1', [post('email')]);
        if ($a && password_verify((string)post('password'), $a['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int)$a['id'];
            unset($_SESSION[$key]);
            q('UPDATE admins SET last_login = NOW() WHERE id = ?', [$a['id']]);
            redirect('admin');
        }
        $_SESSION[$key] = ['n' => $fails['n'] + 1, 't' => time()];
        $error = 'Invalid email or password.';
    }
}
?><!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Admin Login · <?= e(setting('site_name')) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= asset('css/admin.css') ?>">
</head>
<body class="login-page">
  <div class="login-art" style="background-image:url('<?= e(url('assets/images/demo/hero-2.svg')) ?>')"><div><span class="brand__mark lg">EM</span><h2><?= e(setting('site_name')) ?></h2><p>Manage your store, orders and collections.</p></div></div>
  <div class="login-box">
    <form method="post">
      <?= csrf_field() ?>
      <h1>Welcome back</h1>
      <p class="muted">Sign in to the admin panel</p>
      <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
      <?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
      <?= f_text('email', 'Email', post('email'), ['type' => 'email', 'attrs' => 'required autofocus autocomplete="username"']) ?>
      <?= f_text('password', 'Password', '', ['type' => 'password', 'attrs' => 'required autocomplete="current-password"']) ?>
      <button class="btn btn-primary btn-block" type="submit">Sign In</button>
      <p class="muted sm center"><a href="<?= url('') ?>">← Back to store</a></p>
    </form>
  </div>
</body></html>
