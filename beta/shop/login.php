<?php
require __DIR__ . '/../config/config.php';
if (current_shop_owner()) redirect(shop_url('dashboard.php'));
if (current_shop_staff()) redirect(employee_url('dashboard.php'));
$pageTitle = 'Vendor Login'; $bodyClass = 'auth-page'; $hideNavbar = true; $hideFooter = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="container auth-container">
  <div class="auth-card">
    <h1>Vendor &amp; Staff Login</h1>
    <form method="post" action="<?= base_url('actions/auth.php?do=shop_login') ?>">
      <?= csrf_field() ?>
      <label>Email</label>
      <input type="email" name="email" required autofocus>
      <label>Password</label>
      <input type="password" name="password" required>
      <button type="submit" class="btn btn-primary btn-block">Login</button>
    </form>
    <p class="auth-switch">New seller? <a href="<?= shop_url('register.php') ?>">Apply to sell on <?= clean(site_name()) ?></a></p>
    <p class="auth-switch"><small>Demo owner: tech@beglet.com / Demo@1234 &nbsp;|&nbsp; Demo staff: usman.staff@beglet.com / Demo@1234</small></p>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
