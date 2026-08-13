<?php
require_once __DIR__ . '/../config/config.php';

if (is_logged_in()) {
    redirect('/customer/index.php');
}

$errors = [];
$old = ['name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['name'] = clean_input($_POST['name'] ?? '');
    $old['email'] = clean_input($_POST['email'] ?? '');
    $old['phone'] = clean_input($_POST['phone'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['confirm_password'] ?? '');

    if ($old['name'] === '') $errors[] = 'Full name is required.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $result = register_customer($conn, $old['name'], $old['email'], $old['phone'], $password);
        if ($result['ok']) {
            $login = attempt_login($conn, $old['email'], $password, 'customer');
            flash_set('success', 'Welcome to ' . get_setting($conn, 'site_name', APP_NAME) . ', ' . $old['name'] . '!');
            redirect($_GET['redirect'] ?? '/customer/index.php');
        }
        $errors[] = $result['error'];
    }
}

$pageTitle = 'Create your account';
require ROOT_PATH . '/includes/header.php';
?>
<div class="auth-shell">
  <div class="auth-visual">
    <div class="hero-blob hero-blob-1"></div>
    <div class="hero-blob hero-blob-2"></div>
    <div class="auth-visual-content">
      <span class="hero-eyebrow"><i class="bi bi-suitcase-lg"></i> Join <?php echo e($siteName); ?></span>
      <h2 style="margin-top:18px;">Plan smarter. Travel better.</h2>
      <p>Save favorites, build trip itineraries, message providers directly, and book with confidence.</p>
    </div>
  </div>
  <div class="auth-form-side">
    <div class="auth-card">
      <h1>Create your account</h1>
      <p class="sub">Free for travelers, always.</p>

      <?php foreach ($errors as $err): ?>
        <div class="alert-w alert-danger"><i class="bi bi-exclamation-triangle-fill"></i> <?php echo e($err); ?></div>
      <?php endforeach; ?>

      <form method="post" class="form-w" action="?redirect=<?php echo e($_GET['redirect'] ?? ''); ?>">
        <?php echo csrf_field(); ?>
        <label>Full name</label>
        <input type="text" name="name" value="<?php echo e($old['name']); ?>" required>
        <label>Email address</label>
        <input type="email" name="email" value="<?php echo e($old['email']); ?>" required>
        <label>Phone (optional)</label>
        <input type="tel" name="phone" value="<?php echo e($old['phone']); ?>">
        <label>Password</label>
        <input type="password" name="password" minlength="8" required>
        <div class="form-hint">At least 8 characters.</div>
        <label>Confirm password</label>
        <input type="password" name="confirm_password" minlength="8" required>
        <button type="submit" class="btn-w btn-primary btn-block" style="margin-top:24px;">Create account</button>
      </form>

      <p class="sub" style="margin-top:22px;">Already have an account? <a href="<?php echo url('/customer/login.php'); ?>" style="color:var(--purple-600);font-weight:700;">Log in</a></p>
      <p class="sub">Running a business? <a href="<?php echo url('/provider/register.php'); ?>" style="color:var(--purple-600);font-weight:700;">Register as a provider</a></p>
    </div>
  </div>
</div>
<?php require ROOT_PATH . '/includes/footer.php'; ?>
