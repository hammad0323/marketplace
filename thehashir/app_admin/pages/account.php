<?php
/** /manage/account — change your own password. */
$me = require_admin('account');
if (is_post()) {
    require_post_csrf();
    $row = db_one('SELECT admin_password FROM th_admin_table WHERE admin_id = ?', 'i', array($me['id']));
    $chk = password_check((string)post('current'), $row ? $row['admin_password'] : '');
    $problem = password_problem(post('password'), post('password_confirm'));
    if (!$chk[0]) {
        flash('danger', 'Your current password is not correct.');
    } elseif ($problem !== '') {
        flash('danger', $problem);
    } else {
        db_exec('UPDATE th_admin_table SET admin_password = ? WHERE admin_id = ?', 'si', array(password_make((string)$_POST['password']), $me['id']));
        session_regenerate_id(true);
        log_me('auth.password_changed', 'admin', $me['id']);
        flash('success', 'Your password was changed.');
    }
    bo_redirect('admin', 'account');
}
bo_start('admin', 'Change password');
?>
<form method="post" class="card bo-card" style="max-width:520px"><?= csrf_field() ?>
  <div class="card-body">
    <div class="mb-3"><label class="form-label" for="cp">Current password</label><input class="form-control" id="cp" name="current" type="password" autocomplete="current-password" required></div>
    <div class="mb-3"><label class="form-label" for="np">New password</label><input class="form-control" id="np" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
    <div class="mb-3"><label class="form-label" for="np2">Repeat new password</label><input class="form-control" id="np2" name="password_confirm" type="password" autocomplete="new-password" required></div>
    <p class="small text-muted mb-0">At least 8 characters with a letter and a number. Stored as a secure hash.</p>
  </div>
  <div class="card-footer"><button class="btn btn-primary">Change password</button></div>
</form>
<?php bo_end();
