<?php
require_once __DIR__ . '/../config/config.php';

$resetLink = null;
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = clean_input($_POST['email'] ?? '');
    $submitted = true;
    $token = create_password_reset($conn, $email);
    if ($token) {
        $resetLink = APP_URL . '/customer/reset-password.php?token=' . $token;
    }
}

$pageTitle = 'Reset your password';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl" style="max-width:460px;">
    <div class="auth-card" style="max-width:100%;">
      <h1>Reset your password</h1>
      <p class="sub">Enter the email on your account and we'll send you a reset link.</p>

      <?php if (get_setting($conn, 'smtp_host', '') === ''): ?>
        <div class="alert-w alert-info"><i class="bi bi-info-circle-fill"></i> Outbound email isn't configured yet (arrives in Phase 7), so your reset link is shown directly below instead of emailed.</div>
      <?php endif; ?>

      <?php if ($submitted): ?>
        <?php if ($resetLink): ?>
          <div class="alert-w alert-success"><i class="bi bi-check-circle-fill"></i> Reset link generated. It expires in 1 hour.</div>
          <div class="form-hint" style="word-break:break-all;background:var(--purple-50);padding:12px;border-radius:10px;"><a href="<?php echo e($resetLink); ?>"><?php echo e($resetLink); ?></a></div>
        <?php else: ?>
          <div class="alert-w alert-info"><i class="bi bi-info-circle-fill"></i> If that email exists, a reset link has been generated.</div>
        <?php endif; ?>
      <?php endif; ?>

      <form method="post" class="form-w" style="margin-top:20px;">
        <?php echo csrf_field(); ?>
        <label>Email address</label>
        <input type="email" name="email" required>
        <button type="submit" class="btn-w btn-primary btn-block" style="margin-top:20px;">Send reset link</button>
      </form>
      <p class="sub" style="margin-top:20px;"><a href="/customer/login.php" style="color:var(--purple-600);font-weight:700;">Back to login</a></p>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
