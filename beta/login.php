<?php
require __DIR__ . '/config/config.php';
if (current_customer()) redirect(customer_url('dashboard.php'));
$pageTitle = 'Login'; $bodyClass = 'auth-page';
require __DIR__ . '/includes/header.php';
?>
<div class="container auth-container">
  <div class="auth-card">
    <h1>Login to <?= clean(site_name()) ?></h1>
    <form method="post" action="<?= base_url('actions/auth.php?do=customer_login') ?>">
      <?= csrf_field() ?>
      <input type="hidden" name="redirect" value="<?= clean($_GET['redirect'] ?? '') ?>">
      <label>Email</label>
      <input type="email" name="email" required>
      <label>Password</label>
      <input type="password" name="password" required>
      <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>
    <a class="btn btn-google btn-block" href="<?= base_url('actions/auth.php?do=google_login') ?>">
      <i class="fa-brands fa-google"></i> Continue with Google
    </a>
    <p class="auth-switch">Don't have an account? <a href="<?= base_url('register.php') ?>">Register</a></p>
    <p class="auth-switch"><a href="<?= shop_url('login.php') ?>">Vendor / Staff Login</a> &nbsp;|&nbsp; <a href="<?= admin_url('login.php') ?>">Admin Login</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
