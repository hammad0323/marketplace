<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$c = require_customer();
if (is_post()) {
    csrf_check();
    $name = input('name'); $email = mb_strtolower(input('email'));
    if (mb_strlen($name) < 2) flash('danger', 'Please enter your name.');
    elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) flash('danger', 'Please enter a valid email.');
    elseif ($email !== '' && db_val('SELECT id FROM customers WHERE email = ? AND id <> ?', [$email, $c['id']])) flash('danger', 'This email is used by another account.');
    else { db_exec('UPDATE customers SET name = ?, email = ? WHERE id = ?', [mb_substr($name, 0, 120), $email ?: null, $c['id']]); flash('success', 'Profile updated.'); }
    redirect('my-account/profile/');
}
$active = 'profile'; $title = 'Profile';
require FS_ROOT . '/partials/account-start.php';
?>
<form method="post" class="box" style="max-width:560px">
  <?= csrf_field() ?>
  <div class="mb-3"><label class="form-label" for="pName">Full name</label><input id="pName" name="name" class="form-control" required value="<?= e($c['name']) ?>"></div>
  <div class="mb-3"><label class="form-label" for="pPhone">Mobile number</label><input id="pPhone" class="form-control" value="<?= e($c['phone']) ?>" disabled><small class="text-muted">Contact us to change your mobile number.</small></div>
  <div class="mb-3"><label class="form-label" for="pEmail">Email</label><input id="pEmail" type="email" name="email" class="form-control" value="<?= e($c['email']) ?>"></div>
  <button class="btn btn-brand">Save changes</button>
</form>
<?php require FS_ROOT . '/partials/account-end.php';
