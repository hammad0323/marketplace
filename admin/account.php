<?php
require __DIR__ . '/includes/init.php';
$admin = admin_require();
$errors = [];
if (is_post()) {
    $row = db_row('SELECT * FROM admins WHERE id = ?', [(int) $admin['id']]);
    $new = (string) ($_POST['new_password'] ?? '');
    if (!password_verify((string) ($_POST['current_password'] ?? ''), $row['password_hash'])) {
        $errors[] = 'Current password is incorrect.';
    } elseif (strlen($new) < 10 || !preg_match('/[A-Za-z]/', $new) || !preg_match('/\d/', $new)) {
        $errors[] = 'Admin passwords need at least 10 characters including letters and numbers.';
    } elseif ($new !== ($_POST['new_password_confirm'] ?? '')) {
        $errors[] = 'New passwords do not match.';
    } elseif (password_verify($new, $row['password_hash'])) {
        $errors[] = 'Choose a password different from the current one.';
    }
    if (!$errors) {
        db_exec('UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), (int) $admin['id']]);
        uc_regenerate_session();
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_seen'] = time();
        audit_log('password_changed', 'admin', (int) $admin['id']);
        flash('success', 'Password updated.');
        redirect('admin/');
    }
}
admin_header('My account');
?>
<div class="row"><div class="col-lg-6">
<div class="card a-card"><div class="card-body">
    <p><strong><?= e($admin['name']) ?></strong> · <?= e($admin['email']) ?> · <?= e($admin['role_name']) ?></p>
    <?php if ((int) $admin['must_change_password']): ?><div class="alert alert-warning">For security, please replace the initial password before using the admin panel.</div><?php endif; ?>
    <?php foreach ($errors as $er): ?><div class="alert alert-danger py-2"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post"><?= csrf_field() ?>
        <?= a_input('current_password', 'Current password', '', 'password', ['required' => true, 'autocomplete' => 'current-password']) ?>
        <?= a_input('new_password', 'New password', '', 'password', ['required' => true, 'minlength' => 10, 'autocomplete' => 'new-password'], 'At least 10 characters with letters and numbers.') ?>
        <?= a_input('new_password_confirm', 'Confirm new password', '', 'password', ['required' => true, 'autocomplete' => 'new-password']) ?>
        <button class="btn btn-dark">Update password</button>
    </form>
</div></div>
</div></div>
<?php admin_footer();
