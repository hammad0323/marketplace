<?php
require __DIR__ . '/_init.php';
$a = current_admin();
if (is_post()) {
    $hash = (string) db_val('SELECT password_hash FROM admins WHERE id = ?', [(int) $a['id']]);
    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $hash)) {
        flash('error', 'Your current password is incorrect.');
    } elseif ($new !== (string) ($_POST['confirm_password'] ?? '')) {
        flash('error', 'The new passwords do not match.');
    } elseif ($err = password_ok($new)) {
        flash('error', $err);
    } elseif (password_verify($new, $hash)) {
        flash('error', 'Choose a password different from the current one.');
    } else {
        db_exec('UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int) $a['id']]);
        session_regenerate_id(true);
        activity('admin', (int) $a['id'], 'password_changed');
        flash('success', 'Password updated.');
        redirect('admin/');
    }
    redirect('admin/password.php');
}
admin_header('Change password');
?>
<div class="row"><div class="col-lg-5"><div class="p-card"><div class="p-card-body">
<form method="post"><?= csrf_field() ?>
    <?= f_input('current_password', 'Current password', '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
    <?= f_input('new_password', 'New password', '', 'password', ['required' => true, 'minlength' => 8, 'autocomplete' => 'new-password'], 'At least 8 characters with letters and numbers.') ?>
    <?= f_input('confirm_password', 'Confirm new password', '', 'password', ['required' => true, 'autocomplete' => 'new-password']) ?>
    <button class="btn btn-primary">Update password</button>
</form></div></div></div></div>
<?php admin_footer();
