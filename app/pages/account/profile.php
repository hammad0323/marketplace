<?php
$customer = require_customer();
meta_set(['title' => 'Profile', 'noindex' => true]);
$errors = [];
if (is_post()) {
    require_csrf();
    if (input('action') === 'password') {
        if (!password_verify((string) ($_POST['current_password'] ?? ''), $customer['password_hash'])) {
            $errors['current_password'] = 'Your current password is incorrect.';
        } elseif ($e = password_policy_error((string) ($_POST['new_password'] ?? ''))) {
            $errors['new_password'] = $e;
        } elseif (($_POST['new_password'] ?? '') !== ($_POST['new_password_confirm'] ?? '')) {
            $errors['new_password_confirm'] = 'Passwords do not match.';
        } else {
            db_exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($_POST['new_password'], PASSWORD_DEFAULT), $customer['id']]);
            session_regenerate_id(true);
            flash('success', 'Password updated.');
            redirect(path_url('account/profile'));
        }
    } else {
        $first = input('first_name');
        $last = input('last_name');
        $phone = input('phone');
        if ($first === '' || mb_strlen($first) > 80) { $errors['first_name'] = 'Required.'; }
        if ($phone !== '' && !valid_phone($phone)) { $errors['phone'] = 'Enter a valid phone number.'; }
        if (!$errors) {
            db_exec('UPDATE customers SET first_name = ?, last_name = ?, phone = ? WHERE id = ?', [$first, mb_substr($last, 0, 80), $phone ?: null, $customer['id']]);
            flash('success', 'Profile updated.');
            redirect(path_url('account/profile'));
        }
    }
}
$err = fn($k) => isset($errors[$k]) ? '<div class="invalid-feedback d-block">' . e($errors[$k]) . '</div>' : '';
partial('header');
?>
<div class="container container--wide page-pad account">
  <h1 class="page-title">Profile</h1>
  <div class="account__layout">
    <?php $active = 'profile'; require __DIR__ . '/_nav.php'; ?>
    <div class="account__main">
      <form method="post" class="row g-3 mb-5" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="profile">
        <div class="col-md-6"><label class="form-label">First name</label><input class="form-control" name="first_name" maxlength="80" value="<?= e($customer['first_name']) ?>"><?= $err('first_name') ?></div>
        <div class="col-md-6"><label class="form-label">Last name</label><input class="form-control" name="last_name" maxlength="80" value="<?= e($customer['last_name']) ?>"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" value="<?= e($customer['email']) ?>" disabled><small class="text-muted">Contact us to change your email.</small></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" maxlength="30" value="<?= e($customer['phone']) ?>"><?= $err('phone') ?></div>
        <div class="col-12"><button class="btn-lux">Save profile</button></div>
      </form>
      <h2 class="h5">Change password</h2>
      <form method="post" class="row g-3" novalidate>
        <?= csrf_field() ?><input type="hidden" name="action" value="password">
        <div class="col-md-4"><label class="form-label">Current password</label><input class="form-control" type="password" name="current_password" autocomplete="current-password"><?= $err('current_password') ?></div>
        <div class="col-md-4"><label class="form-label">New password</label><input class="form-control" type="password" name="new_password" autocomplete="new-password"><?= $err('new_password') ?></div>
        <div class="col-md-4"><label class="form-label">Confirm new password</label><input class="form-control" type="password" name="new_password_confirm" autocomplete="new-password"><?= $err('new_password_confirm') ?></div>
        <div class="col-12"><button class="btn-lux">Update password</button></div>
      </form>
    </div>
  </div>
</div>
<?php partial('footer');
