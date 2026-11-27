<?php
/** /portal/reset?id=&token= */
$id = int_param('id');
$token = str_param('token', 64);
$v = $id ? db_one('SELECT venue_id, reset_token, token_expiry FROM th_venue WHERE venue_id = ?', 'i', array($id)) : null;
$valid = $v && preg_match('/^[a-f0-9]{64}$/', $token) && !empty($v['reset_token']) && ctype_digit((string)$v['token_expiry']) && (int)$v['token_expiry'] > time()
    && hash_equals((string)$v['reset_token'], hash('sha256', $token));
$error = '';
if ($valid && is_post()) {
    require_post_csrf();
    $error = password_problem(post('password'), post('password_confirm'));
    if ($error === '') {
        db_exec('UPDATE th_venue SET password = ?, reset_token = NULL, token_expiry = NULL WHERE venue_id = ?', 'si', array(password_make((string)$_POST['password']), $id));
        log_activity('venue', $id, 'auth.password_reset', 'venue', $id);
        flash('success', 'Your password was changed. Please sign in.');
        bo_redirect('venue', 'login');
    }
}
auth_card_start('venue', 'Choose a new password');
if (!$valid): ?>
  <div class="alert alert-warning">This reset link is invalid or has expired. <a href="<?= e(url(PORTAL_PATH . '/forgot')) ?>">Request a new one</a>.</div>
<?php else: ?>
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <form method="post"><?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="np">New password</label><input class="form-control form-control-lg" id="np" name="password" type="password" autocomplete="new-password" minlength="8" required></div>
    <div class="mb-3"><label class="form-label" for="np2">Repeat new password</label><input class="form-control form-control-lg" id="np2" name="password_confirm" type="password" autocomplete="new-password" required></div>
    <p class="small text-muted">At least 8 characters, with a letter and a number.</p>
    <button class="btn btn-primary btn-lg w-100">Save password</button>
  </form>
<?php endif;
auth_card_end(array(array('Back to sign in', url(PORTAL_PATH . '/login'))));
