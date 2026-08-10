<?php
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect('/provider/index.php');
}

$redirectTo = clean_input($_GET['redirect'] ?? $_POST['redirect'] ?? '/provider/index.php');
$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = clean_input($_POST['email'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $result = attempt_login($conn, $email, $password, 'provider');
    if ($result['ok']) {
        redirect($redirectTo ?: '/provider/index.php');
    }
    $error = $result['error'];
}

$pageTitle = 'Provider login';
require ROOT_PATH . '/includes/header.php';
?>
<div class="section-tight">
  <div class="container-xl" style="max-width:440px;">
    <div class="auth-card" style="max-width:100%;">
      <h1>Provider login</h1>
      <p class="sub">Manage your business, services and bookings.</p>

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
        <button type="submit" class="btn-w btn-primary btn-block" style="margin-top:20px;">Log in</button>
      </form>
      <p class="sub" style="margin-top:22px;">New provider? <a href="/provider/register.php" style="color:var(--purple-600);font-weight:700;">List your business</a></p>
      <p class="sub"><a href="/customer/login.php" style="color:var(--ink-mute);">Customer login</a> · <a href="/admin/login.php" style="color:var(--ink-mute);">Admin login</a></p>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
