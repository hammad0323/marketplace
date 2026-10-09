<?php
require __DIR__ . '/_inc/bootstrap.php';
$token = input('token', input('token', '', 'get'));
$reset = find_password_reset('admin', $token);
$error = null;
if ($reset && is_post()) {
    require_csrf();
    $pass = (string) ($_POST['password'] ?? '');
    if (strlen($pass) < 10 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/\d/', $pass)) {
        $error = 'Admin passwords need at least 10 characters including letters and numbers.';
    } elseif ($pass !== ($_POST['password_confirm'] ?? '')) {
        $error = 'Passwords do not match.';
    } else {
        db_tx(function () use ($reset, $pass) {
            db_exec('UPDATE admins SET password_hash = ?, must_change_password = 0 WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $reset['user_id']]);
            db_exec('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$reset['id']]);
        });
        audit_log('password_reset', 'admin', (int) $reset['user_id'], [], (int) $reset['user_id']);
        flash('success', 'Password updated. Please sign in.');
        redirect(admin_url('login'));
    }
}
admin_header('Choose a new password');
?>
<div class="auth-box">
  <div class="auth-box__brand">BEGLET<small>New password</small></div>
  <?php if (!$reset): ?>
    <div class="alert alert-warning">This link is invalid or has expired.</div>
  <?php else: ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
      <?= f_text('password', 'New password', '', ['required' => true, 'autocomplete' => 'new-password'], 'At least 10 characters with letters and numbers.', 'password') ?>
      <?= f_text('password_confirm', 'Confirm password', '', ['required' => true, 'autocomplete' => 'new-password'], null, 'password') ?>
      <button class="btn btn-primary w-100">Update password</button>
    </form>
  <?php endif; ?>
</div>
<?php admin_footer();
