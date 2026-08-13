<?php
require_once __DIR__ . '/../config/config.php';

$token = clean_input($_GET['token'] ?? $_POST['token'] ?? '');
$reset = $token ? verify_password_reset_token($conn, $token) : null;
$error = null;
$done = false;

if (!$reset) {
    $error = 'This reset link is invalid or has expired.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        consume_password_reset($conn, $token, $password);
        $done = true;
    }
}

$pageTitle = 'Set a new password';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl" style="max-width:460px;">
    <div class="auth-card" style="max-width:100%;">
      <h1>Set a new password</h1>

      <?php if ($error): ?>
        <div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($error); ?></div>
        <a href="<?php echo url('/customer/forgot-password.php'); ?>" class="btn-w btn-outline btn-block" style="margin-top:12px;">Request a new link</a>
      <?php elseif ($done): ?>
        <div class="alert-w alert-success"><i class="bi bi-check-circle-fill"></i> Password updated. You can log in now.</div>
        <a href="<?php echo url('/customer/login.php'); ?>" class="btn-w btn-primary btn-block" style="margin-top:12px;">Go to login</a>
      <?php else: ?>
        <form method="post" class="form-w">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="token" value="<?php echo e($token); ?>">
          <label>New password</label>
          <input type="password" name="password" minlength="8" required autofocus>
          <label>Confirm new password</label>
          <input type="password" name="confirm_password" minlength="8" required>
          <button type="submit" class="btn-w btn-primary btn-block" style="margin-top:20px;">Update password</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
