<?php
require __DIR__ . '/../config/config.php';
require_customer();
$customer = current_customer();
$data = db_fetch_one("SELECT * FROM customers WHERE id=?", 'i', [$customer['id']]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? 'profile';
    if ($action === 'profile') {
        $first = trim($_POST['first_name']); $last = trim($_POST['last_name']); $phone = trim($_POST['phone']);
        db_exec("UPDATE customers SET first_name=?,last_name=?,phone=? WHERE id=?", 'sssi', [$first, $last, $phone, $customer['id']]);
        $_SESSION['customer']['first_name'] = $first; $_SESSION['customer']['last_name'] = $last;
        flash('success', 'Profile updated.');
    } elseif ($action === 'password') {
        if (!$data['password_hash'] || !password_verify($_POST['current_password'], $data['password_hash'])) {
            flash('error', 'Current password is incorrect.'); redirect(customer_url('profile.php'));
        }
        if ($_POST['new_password'] !== $_POST['confirm_password'] || strlen($_POST['new_password']) < 6) {
            flash('error', 'New passwords must match and be at least 6 characters.'); redirect(customer_url('profile.php'));
        }
        db_exec("UPDATE customers SET password_hash=? WHERE id=?", 'si', [password_hash($_POST['new_password'], PASSWORD_DEFAULT), $customer['id']]);
        flash('success', 'Password changed.');
    }
    redirect(customer_url('profile.php'));
}

$dashRole = 'customer'; $pageTitle = 'My Profile'; $dashUserName = $customer['first_name']; $dashLogoutUrl = base_url('logout.php');
require __DIR__ . '/../includes/dashboard-header.php';
?>
<div class="dash-header-row"><h1>My Profile</h1></div>
<div class="dash-two-col">
  <form method="post" class="dash-form-card">
    <?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <h3>Personal Information</h3>
    <div class="form-row">
      <div><label>First Name</label><input type="text" name="first_name" value="<?= clean($data['first_name']) ?>" required></div>
      <div><label>Last Name</label><input type="text" name="last_name" value="<?= clean($data['last_name']) ?>" required></div>
    </div>
    <label>Email</label><input type="email" value="<?= clean($data['email']) ?>" disabled>
    <label>Phone</label><input type="text" name="phone" value="<?= clean($data['phone']) ?>">
    <button type="submit" class="btn btn-primary">Save Changes</button>
  </form>
  <form method="post" class="dash-form-card">
    <?= csrf_field() ?><input type="hidden" name="action" value="password">
    <h3>Change Password</h3>
    <label>Current Password</label><input type="password" name="current_password" required>
    <label>New Password</label><input type="password" name="new_password" required minlength="6">
    <label>Confirm New Password</label><input type="password" name="confirm_password" required minlength="6">
    <button type="submit" class="btn btn-primary">Update Password</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/dashboard-footer.php'; ?>
