<?php
require __DIR__ . '/../includes/config.php';

$sent = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    tp_admin_request_password_reset(tp_sanitize_text($_POST['email'] ?? '', 190));
    $sent = true; // never reveal whether the email exists
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot Password — Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="<?= tp_asset('css/theme.css') ?>">
<style>body{background:var(--tp-gradient-brand);min-height:100vh;display:flex;align-items:center;}</style>
</head>
<body>
<div class="tp-container" style="max-width:420px;">
  <div class="tp-card p-4">
    <h1 class="h5 fw-bold mb-3">Reset your password</h1>
    <?php if ($sent): ?>
      <div class="alert alert-success">If that email exists, a reset link has been generated. Check the server log (or your configured mail sender) for the reset token/link.</div>
    <?php else: ?>
      <form method="post">
        <?= tp_csrf_field() ?>
        <div class="mb-3"><label class="form-label">Admin Email</label><input type="email" name="email" class="form-control" required></div>
        <button class="btn tp-btn-calc">Send Reset Link</button>
      </form>
    <?php endif; ?>
    <div class="text-center mt-3"><a href="<?= tp_url('admin/login.php') ?>" class="small">Back to login</a></div>
  </div>
</div>
</body>
</html>
