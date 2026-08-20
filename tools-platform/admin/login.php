<?php
require __DIR__ . '/../includes/config.php';

if (tp_is_admin_logged_in()) {
    header('Location: ' . tp_url('admin/index.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $email = tp_sanitize_text($_POST['email'] ?? '', 190);
    $password = (string) ($_POST['password'] ?? '');
    $result = tp_admin_attempt_login($email, $password);
    if ($result['success']) {
        $redirect = $_GET['redirect'] ?? tp_url('admin/index.php');
        header('Location: ' . $redirect);
        exit;
    }
    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login — <?= e(tp_setting('site_name')) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= tp_asset('css/theme.css') ?>">
<style>body{background:var(--tp-gradient-brand);min-height:100vh;display:flex;align-items:center;}</style>
</head>
<body>
<div class="tp-container" style="max-width:420px;">
  <div class="tp-card p-4">
    <h1 class="h4 fw-bold mb-3 text-center"><?= e(tp_setting('site_name')) ?> Admin</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= tp_csrf_field() ?>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <button class="btn tp-btn-calc">Log In</button>
    </form>
    <div class="text-center mt-3"><a href="<?= tp_url('admin/forgot-password.php') ?>" class="small">Forgot password?</a></div>
  </div>
</div>
</body>
</html>
