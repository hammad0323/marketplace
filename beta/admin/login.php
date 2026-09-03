<?php
require __DIR__ . '/../config/config.php';
if (current_admin()) redirect(admin_url('index.php'));
$pageTitle = 'Admin Login'; $bodyClass = 'auth-page admin-auth'; $hideNavbar = true; $hideFooter = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="container auth-container">
  <div class="auth-card">
    <h1><?= clean(site_name()) ?> Admin</h1>
    <form method="post" action="<?= base_url('actions/auth.php?do=admin_login') ?>">
      <?= csrf_field() ?>
      <label>Email</label>
      <input type="email" name="email" required autofocus>
      <label>Password</label>
      <input type="password" name="password" required>
      <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>
    <p class="auth-switch"><small>Demo: admin@beglet.com / Demo@1234</small></p>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
