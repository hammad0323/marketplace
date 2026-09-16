<?php
require_once __DIR__ . '/../includes/functions.php';
require_customer_login();
$customer = current_customer();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $stmt = mysqli_prepare($mysqli, "UPDATE customers SET name=?, phone=? WHERE id=?");
        mysqli_stmt_bind_param($stmt, 'ssi', $name, $phone, $customer['id']);
        mysqli_stmt_execute($stmt);
        flash_set('success', 'Profile updated.');
    } elseif ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        if (!$customer['password_hash'] || !password_verify($current, $customer['password_hash'])) {
            flash_set('danger', 'Current password is incorrect.');
        } elseif (strlen($new) < 6) {
            flash_set('danger', 'New password must be at least 6 characters.');
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($mysqli, "UPDATE customers SET password_hash=? WHERE id=?");
            mysqli_stmt_bind_param($stmt, 'si', $hash, $customer['id']);
            mysqli_stmt_execute($stmt);
            flash_set('success', 'Password changed successfully.');
        }
    }
    redirect(url('account/profile'));
}

$pageTitle = 'Profile | ' . get_setting('store_name');
require_once __DIR__ . '/../includes/header.php';
?>
<div class="container section-tight">
  <div class="row g-4">
    <div class="col-lg-3"><?php include __DIR__ . '/../includes/account_sidebar.php'; ?></div>
    <div class="col-lg-9">
      <h1 class="h4 font-serif mb-4">Profile</h1>
      <div class="summary-box mb-4">
        <h2 class="h6 mb-3">Account Information</h2>
        <form method="post" class="row g-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="update_profile">
          <div class="col-md-6"><label class="form-label">Name</label><input type="text" name="name" class="form-control" value="<?= e($customer['name']) ?>" required></div>
          <div class="col-md-6"><label class="form-label">Email</label><input type="email" class="form-control" value="<?= e($customer['email']) ?>" disabled></div>
          <div class="col-md-6"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control" value="<?= e($customer['phone']) ?>"></div>
          <div class="col-12"><button class="btn-brand">Save Changes</button></div>
        </form>
      </div>
      <div class="summary-box">
        <h2 class="h6 mb-3">Change Password</h2>
        <form method="post" class="row g-3">
          <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
          <div class="col-md-6"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
          <div class="col-md-6"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
          <div class="col-12"><button class="btn-brand">Change Password</button></div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
