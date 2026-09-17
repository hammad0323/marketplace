<?php
require __DIR__ . '/../config.php';

$sent = false;
$resetLink = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $email = wh_input_post('email');
    $user = wh_fetch_one("SELECT * FROM admin_users WHERE email = ? AND status = 'active'", 's', [$email]);
    if ($user) {
        $token = bin2hex(random_bytes(32));
        wh_execute(
            'INSERT INTO password_resets (admin_user_id, token, expires_at) VALUES (?,?, DATE_ADD(NOW(), INTERVAL 1 HOUR))',
            'is',
            [$user['id'], $token]
        );
        $resetLink = BASE_URL . '/admin/reset-password.php?token=' . $token;
        error_log('[password-reset] ' . $email . ' -> ' . $resetLink); // stand-in for a real mailer
    }
    $sent = true; // always show the same message, regardless of whether the email existed
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Forgot Password</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="auth-page">
  <div class="auth-card">
    <h1>Forgot Password</h1>
    <p class="sub">We'll generate a secure reset link for your account.</p>
    <?php if ($sent): ?>
      <div class="alert alert-success">If that email exists, a password reset link has been generated.</div>
      <?php if ($resetLink): ?>
        <div class="alert alert-info">No email service configured on this install yet, so here is your one-time reset link (valid 1 hour):<br><a href="<?= e($resetLink) ?>"><?= e($resetLink) ?></a></div>
      <?php endif; ?>
    <?php else: ?>
    <form method="post">
      <?= wh_csrf_field() ?>
      <div class="form-group"><label>Admin Email</label><input type="email" name="email" required autofocus></div>
      <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Send Reset Link</button>
    </form>
    <?php endif; ?>
    <p class="hint" style="margin-top:18px;"><a href="<?= e(BASE_URL) ?>/admin/login.php">Back to login</a></p>
  </div>
</div>
</body>
</html>
