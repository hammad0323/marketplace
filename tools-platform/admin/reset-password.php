<?php
require __DIR__ . '/../includes/config.php';

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$done = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $password = (string) ($_POST['password'] ?? '');
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif (tp_admin_reset_password($token, $password)) {
        $done = true;
    } else {
        $error = 'This reset link is invalid or has expired.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reset Password — Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= tp_asset('css/theme.css') ?>">
<style>body{background:var(--tp-gradient-brand);min-height:100vh;display:flex;align-items:center;}</style>
</head>
<body>
<div class="tp-container" style="max-width:420px;">
  <div class="tp-card p-4">
    <h1 class="h5 fw-bold mb-3">Set a new password</h1>
    <?php if ($done): ?>
      <div class="alert alert-success">Password updated. <a href="<?= tp_url('admin/login.php') ?>">Log in</a></div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post">
        <?= tp_csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="mb-3"><label class="form-label">New Password</label><input type="password" name="password" class="form-control" minlength="8" required></div>
        <button class="btn tp-btn-calc">Update Password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
