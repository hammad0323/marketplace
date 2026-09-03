<?php
require __DIR__ . '/../config/config.php';
$pageTitle = 'Become a Vendor';
require __DIR__ . '/../includes/header.php';
?>
<div class="container auth-container">
  <div class="auth-card auth-card-wide">
    <h1>Sell on <?= clean(site_name()) ?></h1>
    <p>Fill in the form below to apply for a vendor account. Our team will review your application.</p>
    <form method="post" action="<?= base_url('actions/auth.php?do=shop_register') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <h3>Owner Details</h3>
      <div class="form-row">
        <div><label>Owner Name</label><input type="text" name="owner_name" required></div>
        <div><label>Phone</label><input type="text" name="phone" required></div>
      </div>
      <div class="form-row">
        <div><label>Email</label><input type="email" name="email" required></div>
        <div><label>Password</label><input type="password" name="password" required minlength="6"></div>
      </div>
      <h3>Shop Details</h3>
      <label>Shop Name</label>
      <input type="text" name="shop_name" required>
      <label>Shop Description</label>
      <textarea name="shop_description" rows="4"></textarea>
      <label>Business Address</label>
      <input type="text" name="business_address">
      <div class="form-row">
        <div><label>City</label><input type="text" name="city"></div>
        <div><label>Country</label><input type="text" name="country" value="Pakistan"></div>
      </div>
      <div class="form-row">
        <div><label>Shop Logo</label><input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp"></div>
        <div><label>Cover Image</label><input type="file" name="cover_image" accept=".jpg,.jpeg,.png,.webp"></div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Submit Application</button>
    </form>
    <p class="auth-switch">Already a vendor? <a href="<?= shop_url('login.php') ?>">Login</a></p>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
