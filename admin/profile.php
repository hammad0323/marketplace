<?php
require __DIR__ . '/includes/init.php';
$a = current_admin();
if (is_post()) {
    csrf_check();
    $cur = (string) ($_POST['current'] ?? ''); $new = (string) ($_POST['new'] ?? '');
    $hash = db_val('SELECT password_hash FROM admins WHERE id = ?', [$a['id']]);
    if (!password_verify($cur, $hash)) flash('danger', 'Current password is incorrect.');
    elseif (strlen($new) < 8) flash('danger', 'New password must be at least 8 characters.');
    elseif ($new !== ($_POST['confirm'] ?? '')) flash('danger', 'Passwords do not match.');
    else { db_exec('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $a['id']]); session_regenerate_id(true); flash('success', 'Password updated.'); }
    redirect('admin/profile.php');
}
admin_header('My password');
?>
<form method="post" class="card card-body" style="max-width:480px">
  <?= csrf_field() ?>
  <p class="text-muted small">Signed in as <strong><?= e($a['email']) ?></strong> (<?= e($a['role_name']) ?>)</p>
  <?= af_text('current', 'Current password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'current-password']) ?>
  <?= af_text('new', 'New password (min 8 characters)', '', ['type' => 'password', 'required' => true, 'minlength' => 8, 'autocomplete' => 'new-password']) ?>
  <?= af_text('confirm', 'Confirm new password', '', ['type' => 'password', 'required' => true, 'autocomplete' => 'new-password']) ?>
  <button class="btn btn-brand">Update password</button>
</form>
<?php admin_footer();
