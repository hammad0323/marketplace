<?php
/**
 * MY PROFILE — change your name, email and password.
 */
require __DIR__ . '/includes/bootstrap.php';
$me = require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    try {
        $name = post('name');
        $email = strtolower(post('email'));
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Name and a valid email are required.');
        if (db_value('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, (int) $me['id']])) throw new RuntimeException('That email is already used.');
        $upd = ['name' => $name, 'email' => $email];
        $new = (string) ($_POST['new_password'] ?? '');
        if ($new !== '') {
            $row = db_row('SELECT password FROM users WHERE id = ?', [(int) $me['id']]);
            if (!password_verify((string) ($_POST['current_password'] ?? ''), $row['password'])) throw new RuntimeException('Current password is incorrect.');
            if (strlen($new) < 6) throw new RuntimeException('New password must be at least 6 characters.');
            if ($new !== (string) ($_POST['confirm_password'] ?? '')) throw new RuntimeException('Passwords do not match.');
            $upd['password'] = password_hash($new, PASSWORD_DEFAULT);
        }
        db_update('users', $upd, 'id = ?', [(int) $me['id']]);
        flash('success', 'Profile updated.');
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('profile.php');
}
$pageTitle = 'My Profile';
include __DIR__ . '/layouts/header.php';
?>
<div class="page-head"><div><h1>My profile</h1><p>Update your account details and password.</p></div></div>
<form method="post" class="card-x" style="max-width:640px">
  <?= csrf_field() ?>
  <div class="card-body"><div class="row g-3">
    <div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e($me['name']) ?>" required></div>
    <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($me['email']) ?>" required></div>
    <div class="col-12"><hr class="my-1"><div class="small fw-semibold text-dark">Change password</div></div>
    <div class="col-md-4"><label class="form-label">Current</label><input class="form-control" type="password" name="current_password" autocomplete="current-password"></div>
    <div class="col-md-4"><label class="form-label">New</label><input class="form-control" type="password" name="new_password" autocomplete="new-password"></div>
    <div class="col-md-4"><label class="form-label">Confirm</label><input class="form-control" type="password" name="confirm_password" autocomplete="new-password"></div>
  </div></div>
  <div class="card-body border-top text-end"><button class="btn btn-brand"><i class="bi bi-check2"></i> Save</button></div>
</form>
<?php include __DIR__ . '/layouts/footer.php'; ?>
