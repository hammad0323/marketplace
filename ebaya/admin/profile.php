<?php
require __DIR__ . '/partials/bootstrap.php';
$me = require_admin();
$errors = [];
if (is_post()) {
    csrf_check();
    $row = db_one('SELECT * FROM admins WHERE id = ?', [(int)$me['id']]);
    $name = mb_substr(post('name'), 0, 120);
    $new = (string)($_POST['new_password'] ?? '');
    if (!v_len($name, 2, 120)) $errors[] = 'Please enter your name.';
    if ($new !== '' || $me['must_change_password']) {
        if (!password_verify((string)($_POST['current_password'] ?? ''), $row['password_hash'])) $errors[] = 'Current password is incorrect.';
        elseif (strlen($new) < 10) $errors[] = 'Admin passwords must be at least 10 characters.';
        elseif ($e = v_password($new)) $errors[] = $e;
        elseif ($new !== ($_POST['new_password_confirm'] ?? '')) $errors[] = 'New passwords do not match.';
    }
    if (!$errors) {
        db_exec('UPDATE admins SET name = ? WHERE id = ?', [$name, (int)$me['id']]);
        if ($new !== '') {
            db_exec('UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int)$me['id']]);
            session_regenerate_id(true);
            audit('password_change', 'admin', (int)$me['id']);
        }
        flash('success', 'Profile updated.');
        redirect(admin_url('profile'));
    }
}
$admin_title = 'My profile';
require __DIR__ . '/partials/header.php';
?>
<?php if ($me['must_change_password']): ?><div class="alert alert-warning">For security, please choose a new password before using the admin panel.</div><?php endif; ?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger"><?= e($er) ?></div><?php endforeach; ?>
<div class="card" style="max-width:640px"><div class="card-body">
<form method="post" novalidate>
  <?= csrf_field() ?>
  <?= f_text('name', 'Name', $me['name'], ['required' => true]) ?>
  <?= f_text('email', 'Email', $me['email'], ['readonly' => true, 'help' => 'Ask a Super Admin to change your sign-in email.']) ?>
  <hr><h2 class="h6">Change password</h2>
  <?= f_text('current_password', 'Current password', '', ['type' => 'password']) ?>
  <?= f_text('new_password', 'New password', '', ['type' => 'password', 'help' => 'At least 10 characters, with letters and numbers.']) ?>
  <?= f_text('new_password_confirm', 'Confirm new password', '', ['type' => 'password']) ?>
  <button class="btn btn-primary">Save</button>
</form>
</div></div>
<?php require __DIR__ . '/partials/footer.php';
