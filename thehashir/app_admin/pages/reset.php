<?php
/** /manage/reset?id=&token= — choose a new admin password. */
$id = int_param('id');
$token = str_param('token', 64);
$r = $id ? storage_read('resets/admin-' . $id . '.json', null) : null;
$valid = $r && $r['expires'] > time() && preg_match('/^[a-f0-9]{64}$/', $token) && hash_equals($r['hash'], hash('sha256', $token));
$error = '';
if ($valid && is_post()) {
    require_post_csrf();
    $error = password_problem(post('password'), post('password_confirm'));
    if ($error === '') {
        db_exec('UPDATE th_admin_table SET admin_password = ? WHERE admin_id = ?', 'si', array(password_make((string)$_POST['password']), $id));
        storage_delete('resets/admin-' . $id . '.json');
        log_activity('admin', $id, 'auth.password_reset', 'admin', $id);
        flash('success', 'Your password was changed. Please sign in.');
        bo_redirect('admin', 'login');
    }
}
auth_card_start('admin', 'Choose a new password');
if (!$valid): ?>
  <div class="alert alert-warning">This reset link is invalid or has expired. <a href="<?= e(url(ADMIN_PATH . '/forgot')) ?>">Request a new one</a>.</div>
<?php else: ?>
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="np">New password</label><input class="form-control form-control-lg" id="np" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
    <div class="mb-3"><label class="form-label" for="np2">Repeat new password</label><input class="form-control form-control-lg" id="np2" name="password_confirm" type="password" autocomplete="new-password" required></div>
    <p class="small text-muted">At least 8 characters, with a letter and a number.</p>
    <button class="btn btn-primary btn-lg w-100">Save password</button>
  </form>
<?php endif;
auth_card_end(array(array('Back to sign in', url(ADMIN_PATH . '/login'))));
