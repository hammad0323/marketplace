<?php
define('ADMIN_PASSWORD_PAGE', true);
require __DIR__ . '/_inc/bootstrap.php';
$admin = require_admin();
$errors = [];
if (is_post()) {
    require_csrf();
    $name = input('name');
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');
    if ($name === '' || mb_strlen($name) > 120) {
        $errors[] = 'Please enter your name.';
    }
    if ($new !== '' || $admin['must_change_password']) {
        if (!password_verify($current, $admin['password_hash'])) {
            $errors[] = 'Your current password is incorrect.';
        } elseif (strlen($new) < 10 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
            $errors[] = 'New password needs at least 10 characters with letters and numbers.';
        } elseif ($new !== ($_POST['new_password_confirm'] ?? '')) {
            $errors[] = 'New passwords do not match.';
        } elseif (password_verify($new, $admin['password_hash'])) {
            $errors[] = 'Choose a password different from the current one.';
        }
    }
    if (!$errors) {
        db_exec('UPDATE admins SET name = ? WHERE id = ?', [$name, $admin['id']]);
        if ($new !== '') {
            db_exec('UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
            session_regenerate_id(true);
            audit_log('password_changed', 'admin', (int) $admin['id']);
        }
        flash('success', 'Profile updated.');
        redirect(admin_url('profile'));
    }
}
admin_header('My profile');
?>
<div class="row"><div class="col-lg-6">
<?php if ($admin['must_change_password']): ?><div class="alert alert-warning"><strong>Security:</strong> you are using a temporary password. Set a new one to continue.</div><?php endif; ?>
<?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
<div class="card"><div class="card-body">
<form method="post">
  <?= csrf_field() ?>
  <?= f_text('name', 'Name', $admin['name'], ['required' => true]) ?>
  <?= f_text('email_ro', 'Email', $admin['email'], ['disabled' => true]) ?>
  <hr>
  <h2 class="h6">Change password</h2>
  <?= f_text('current_password', 'Current password', '', ['autocomplete' => 'current-password'], null, 'password') ?>
  <?= f_text('new_password', 'New password', '', ['autocomplete' => 'new-password'], 'At least 10 characters with letters and numbers.', 'password') ?>
  <?= f_text('new_password_confirm', 'Confirm new password', '', ['autocomplete' => 'new-password'], null, 'password') ?>
  <?= f_submit() ?>
</form>
</div></div>
<p class="small text-muted mt-3">Role: <?= e($admin['role_name']) ?> · Last sign-in: <?= e(format_date($admin['last_login_at'], true)) ?> from <?= e($admin['last_login_ip']) ?></p>
</div></div>
<?php admin_footer();
