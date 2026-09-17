<?php
require __DIR__ . '/../config.php';

$token = wh_input_get('token');
$reset = wh_fetch_one(
    'SELECT pr.*, au.email FROM password_resets pr JOIN admin_users au ON au.id = pr.admin_user_id
     WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()',
    's',
    [$token]
);

$error = '';
$done = false;

if (!$reset) {
    $error = 'This reset link is invalid or has expired. Please request a new one.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        wh_execute('UPDATE admin_users SET password_hash = ?, must_change_password = 0 WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), $reset['admin_user_id']]);
        wh_execute('UPDATE password_resets SET used = 1 WHERE id = ?', 'i', [$reset['id']]);
        $done = true;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="auth-page">
  <div class="auth-card">
    <h1>Reset Password</h1>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($done): ?>
      <div class="alert alert-success">Your password has been reset. You can now log in.</div>
      <a href="<?= e(BASE_URL) ?>/admin/login.php" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Go to Login</a>
    <?php elseif ($reset): ?>
      <p class="sub">Resetting password for <?= e($reset['email']) ?></p>
      <form method="post">
        <?= wh_csrf_field() ?>
        <div class="form-group"><label>New Password</label><input type="password" name="password" minlength="8" required></div>
        <div class="form-group"><label>Confirm Password</label><input type="password" name="password_confirm" minlength="8" required></div>
        <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Reset Password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
