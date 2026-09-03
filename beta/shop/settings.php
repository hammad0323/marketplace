<?php
require __DIR__ . '/../config/config.php';
require_shop_owner_or_staff();
$isOwner = (bool)current_shop_owner();
$table = $isOwner ? 'shop_owners' : 'shop_staff';
$id = $isOwner ? current_shop_owner()['id'] : current_shop_staff()['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $current = $_POST['current_password']; $new = $_POST['new_password']; $confirm = $_POST['confirm_password'];
    $row = db_fetch_one("SELECT password_hash FROM $table WHERE id=?", 'i', [$id]);
    if (!password_verify($current, $row['password_hash'])) { flash('error', 'Current password is incorrect.'); redirect(shop_url('settings.php')); }
    if ($new !== $confirm || strlen($new) < 6) { flash('error', 'New passwords must match and be at least 6 characters.'); redirect(shop_url('settings.php')); }
    db_exec("UPDATE $table SET password_hash=? WHERE id=?", 'si', [password_hash($new, PASSWORD_DEFAULT), $id]);
    flash('success', 'Password updated successfully.');
    redirect(shop_url('settings.php'));
}

$dashRole = $isOwner ? 'shop' : 'employee'; $pageTitle = 'Account Settings';
$dashUserName = $isOwner ? current_shop_owner()['name'] : current_shop_staff()['name'];
$dashLogoutUrl = shop_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>Account Settings</h1></div>
<form method="post" class="dash-form-card">
  <?= csrf_field() ?>
  <h3>Change Password</h3>
  <label>Current Password</label><input type="password" name="current_password" required>
  <label>New Password</label><input type="password" name="new_password" required minlength="6">
  <label>Confirm New Password</label><input type="password" name="confirm_password" required minlength="6">
  <button type="submit" class="btn btn-primary">Update Password</button>
</form>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
