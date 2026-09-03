<?php
require __DIR__ . '/config/config.php';
if (current_customer()) redirect(customer_url('dashboard.php'));
$pageTitle = 'Register'; $bodyClass = 'auth-page';
require __DIR__ . '/includes/header.php';
?>
<div class="container auth-container">
  <div class="auth-card">
    <h1>Create your account</h1>
    <form method="post" action="<?= base_url('actions/auth.php?do=customer_register') ?>">
      <?= csrf_field() ?>
      <div class="form-row">
        <div><label>First Name</label><input type="text" name="first_name" required></div>
        <div><label>Last Name</label><input type="text" name="last_name" required></div>
      </div>
      <label>Email</label>
      <input type="email" name="email" required>
      <label>Phone</label>
      <input type="text" name="phone">
      <div class="form-row">
        <div><label>Password</label><input type="password" name="password" required minlength="6"></div>
        <div><label>Confirm Password</label><input type="password" name="confirm_password" required minlength="6"></div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Create Account</button>
    </form>
    <a class="btn btn-google btn-block" href="<?= base_url('actions/auth.php?do=google_login') ?>">
      <i class="fa-brands fa-google"></i> Sign up with Google
    </a>
    <p class="auth-switch">Already have an account? <a href="<?= base_url('login.php') ?>">Login</a></p>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
