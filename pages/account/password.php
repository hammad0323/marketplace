<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$c = require_customer();
if (is_post()) {
    csrf_check();
    $cur = (string) ($_POST['current'] ?? ''); $new = (string) ($_POST['new'] ?? '');
    if (!password_verify($cur, (string) $c['password_hash'])) flash('danger', 'Current password is incorrect.');
    elseif (strlen($new) < 6) flash('danger', 'New password must be at least 6 characters.');
    elseif ($new !== ($_POST['confirm'] ?? '')) flash('danger', 'Passwords do not match.');
    else { db_exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $c['id']]); session_regenerate_id(true); flash('success', 'Password changed.'); }
    redirect('my-account/password/');
}
$active = 'password'; $title = 'Change Password';
require FS_ROOT . '/partials/account-start.php';
?>
<form method="post" class="box" style="max-width:480px">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label" for="pw1">Current password</label><input id="pw1" type="password" name="current" class="form-control" required autocomplete="current-password"></div>
  <div class="mb-3"><label class="form-label" for="pw2">New password</label><input id="pw2" type="password" name="new" class="form-control" required minlength="6" autocomplete="new-password"></div>
  <div class="mb-3"><label class="form-label" for="pw3">Confirm new password</label><input id="pw3" type="password" name="confirm" class="form-control" required minlength="6" autocomplete="new-password"></div>
  <button class="btn btn-brand">Update password</button>
</form>
<?php require FS_ROOT . '/partials/account-end.php';
