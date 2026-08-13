<?php
require_once __DIR__ . '/../config/config.php';

if (is_logged_in() && current_user_role() === 'admin') {
    redirect('/admin/index.php');
}

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = clean_input($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $result = attempt_login($conn, $email, $password, 'admin');
    if ($result['ok']) {
        redirect('/admin/index.php');
    }
    $error = $result['error'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login — <?php echo e(APP_NAME); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body style="background:var(--gradient-hero);min-height:100vh;display:flex;align-items:center;justify-content:center;font-family:var(--font-body);">
  <div class="hero-blob hero-blob-1" style="position:fixed;"></div>
  <div class="hero-blob hero-blob-2" style="position:fixed;"></div>
  <div class="auth-card" style="position:relative;z-index:2;background:var(--white);padding:40px;border-radius:var(--radius-lg);box-shadow:var(--shadow-lg);max-width:400px;width:92%;">
    <div style="text-align:center;margin-bottom:8px;">
      <span class="brand-mark" style="display:inline-flex;"><i class="bi bi-shield-lock"></i></span>
    </div>
    <h1 style="text-align:center;">Admin Console</h1>
    <p class="sub" style="text-align:center;">Restricted access — authorized staff only.</p>

    <?php if ($error): ?>
      <div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="post" class="form-w">
      <?php echo csrf_field(); ?>
      <label>Email address</label>
      <input type="email" name="email" value="<?php echo e($email); ?>" required autofocus>
      <label>Password</label>
      <input type="password" name="password" required>
      <button type="submit" class="btn-w btn-primary btn-block" style="margin-top:20px;">Log in</button>
    </form>
    <p class="sub" style="text-align:center;margin-top:20px;"><a href="<?php echo url('/index.php'); ?>" style="color:var(--ink-mute);">&larr; Back to site</a></p>
  </div>
</body>
</html>
