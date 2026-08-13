<?php
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect('/customer/index.php');
}

$redirectTo = clean_input($_GET['redirect'] ?? $_POST['redirect'] ?? '/customer/index.php');
$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = clean_input($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $result = attempt_login($conn, $email, $password, 'customer');
    if ($result['ok']) {
        redirect($redirectTo ?: '/customer/index.php');
    }
    $error = $result['error'];
}

$pageTitle = 'Log in';
require ROOT_PATH . '/includes/header.php';
?>
<div class="auth-shell">
  <div class="auth-visual">
    <div class="hero-blob hero-blob-1"></div>
    <div class="hero-blob hero-blob-2"></div>
    <div class="auth-visual-content">
      <span class="hero-eyebrow"><i class="bi bi-compass"></i> Welcome back</span>
      <h2 style="margin-top:18px;">Your next trip is one login away.</h2>
      <p>Pick up your saved trips, favorites and bookings right where you left off.</p>
    </div>
  </div>
  <div class="auth-form-side">
    <div class="auth-card">
      <h1>Log in to Wanderly</h1>
      <p class="sub">Welcome back — we missed you.</p>

      <?php if ($error): ?>
        <div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($error); ?></div>
      <?php endif; ?>

      <form method="post" class="form-w">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="redirect" value="<?php echo e($redirectTo); ?>">
        <label>Email address</label>
        <input type="email" name="email" value="<?php echo e($email); ?>" required autofocus>
        <label>Password</label>
        <input type="password" name="password" required>
        <div style="text-align:right;margin-top:8px;">
          <a href="<?php echo url('/customer/forgot-password.php'); ?>" style="font-size:13px;color:var(--purple-600);font-weight:600;">Forgot password?</a>
        </div>
        <button type="submit" class="btn-w btn-primary btn-block" style="margin-top:20px;">Log in</button>
      </form>

      <p class="sub" style="margin-top:22px;">New to Wanderly? <a href="<?php echo url('/customer/register.php'); ?>" style="color:var(--purple-600);font-weight:700;">Create an account</a></p>
      <p class="sub">
        <a href="<?php echo url('/provider/login.php'); ?>" style="color:var(--ink-mute);">Provider login</a> ·
        <a href="<?php echo url('/admin/login.php'); ?>" style="color:var(--ink-mute);">Admin login</a>
      </p>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
