<?php
require __DIR__ . '/../config.php';

if (wh_is_logged_in()) {
    wh_redirect(BASE_URL . '/admin/');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $email = wh_input_post('email');
    $password = wh_input_post('password');
    $result = wh_admin_login($email, $password);
    if ($result['ok']) {
        if (!empty($result['must_change_password'])) {
            wh_redirect(BASE_URL . '/admin/change-password.php?forced=1');
        }
        wh_redirect(BASE_URL . '/admin/');
    }
    $error = $result['error'] === 'locked'
        ? 'Too many failed attempts. Please wait 15 minutes and try again.'
        : 'Invalid email or password.';
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Login</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer">
<link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="auth-page">
  <div class="auth-card">
    <h1>Admin Login</h1>
    <p class="sub">Sign in to manage your wedding hall bookings.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= wh_csrf_field() ?>
      <div class="form-group"><label>Email</label><input type="email" name="email" required autofocus value="<?= e($_POST['email'] ?? '') ?>"></div>
      <div class="form-group"><label>Password</label><input type="password" name="password" required></div>
      <button type="submit" class="btn btn-primary btn-block" style="width:100%;justify-content:center;">Login</button>
    </form>
    <p class="hint" style="margin-top:18px;"><a href="<?= e(BASE_URL) ?>/admin/forgot-password.php">Forgot password?</a></p>
  </div>
</div>
</body>
</html>
