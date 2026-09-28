<?php
define('BASE', '../');
require __DIR__ . '/../config.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Simple brute-force brake: 5 failed attempts → wait 5 minutes.
    $_SESSION['login_fail'] = $_SESSION['login_fail'] ?? ['n' => 0, 't' => 0];
    if ($_SESSION['login_fail']['n'] >= 5 && time() - $_SESSION['login_fail']['t'] < 300) {
        $error = 'Too many failed attempts. Please wait a few minutes.';
    } elseif (!csrf_ok()) {
        $error = 'Session expired, please try again.';
    } else {
        $admin = q('SELECT * FROM admins WHERE username = ?', [post('username')])->fetch();
        if ($admin && password_verify(post('password'), $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id']   = (int) $admin['id'];
            $_SESSION['admin_name'] = $admin['username'];
            unset($_SESSION['login_fail']);
            redirect('dashboard.php');
        }
        $_SESSION['login_fail'] = ['n' => $_SESSION['login_fail']['n'] + 1, 't' => time()];
        $error = 'Wrong username or password.';
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Admin Login · <?= e(setting('site_name')) ?></title>
<link rel="icon" href="../assets/img/favicon.svg" type="image/svg+xml">
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600&family=Jost:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<link rel="stylesheet" href="../assets/css/admin.css?v=1">
</head>
<body class="login-page">
<form method="post" class="login-card">
    <div class="login-mark">RK</div>
    <h1>Welcome back</h1>
    <p><?= e(setting('site_name')) ?> — Admin</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?= csrf_field() ?>
    <label>Username<input name="username" required autofocus autocomplete="username"></label>
    <label>Password<input name="password" type="password" required autocomplete="current-password"></label>
    <button class="btn btn-primary btn-block">Sign in</button>
    <a href="../index.php" class="back">← Back to website</a>
</form>
</body>
</html>
