<?php
require __DIR__ . '/../config.php';
wh_require_admin();

$admin = wh_current_admin();
$forced = wh_input_get('forced') === '1' && !empty($_SESSION['must_change_password']);
$error = '';
$done = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    wh_csrf_verify();
    $current = $_POST['current_password'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (!password_verify($current, $admin['password_hash'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        wh_execute('UPDATE admin_users SET password_hash = ?, must_change_password = 0 WHERE id = ?', 'si', [password_hash($password, PASSWORD_DEFAULT), $admin['id']]);
        $_SESSION['must_change_password'] = false;
        $done = true;
        wh_flash_set('success', 'Password changed successfully.');
        wh_redirect(BASE_URL . '/admin/');
    }
}

$pageTitle = 'Change Password';
$activePage = '';
require __DIR__ . '/header.php';
?>
<div class="admin-card" style="max-width:480px;">
  <?php if ($forced): ?><div class="alert alert-info">For security, please set a new password before continuing.</div><?php endif; ?>
  <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <?= wh_csrf_field() ?>
    <div class="form-group"><label>Current Password</label><input type="password" name="current_password" required></div>
    <div class="form-group"><label>New Password</label><input type="password" name="password" minlength="8" required></div>
    <div class="form-group"><label>Confirm New Password</label><input type="password" name="password_confirm" minlength="8" required></div>
    <button type="submit" class="btn btn-primary">Update Password</button>
  </form>
</div>
<?php require __DIR__ . '/footer.php'; ?>
