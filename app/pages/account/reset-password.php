<?php
meta_set(['title' => 'Choose a new password', 'noindex' => true]);
$token = input('token', input('token', '', 'get'));
$reset = find_password_reset('customer', $token);
$error = null;
if ($reset && is_post()) {
    require_csrf();
    $pass = (string) ($_POST['password'] ?? '');
    if ($e = password_policy_error($pass)) {
        $error = $e;
    } elseif ($pass !== ($_POST['password_confirm'] ?? '')) {
        $error = 'Passwords do not match.';
    } else {
        db_tx(function () use ($reset, $pass) {
            db_exec('UPDATE customers SET password_hash = ? WHERE id = ?', [password_hash($pass, PASSWORD_DEFAULT), $reset['user_id']]);
            db_exec('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$reset['id']]);
        });
        customer_start_session((int) $reset['user_id']);
        flash('success', 'Your password has been changed.');
        redirect(path_url('account'));
    }
}
partial('header');
?>
<div class="container container--narrow page-pad">
  <div class="auth-card">
    <h1 class="page-title">Choose a new password</h1>
    <?php if (!$reset): ?>
      <div class="alert alert-warning">This reset link is invalid or has expired. <a href="<?= e(path_url('account/forgot-password')) ?>">Request a new one</a>.</div>
    <?php else: ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post" novalidate><?= csrf_field() ?><input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="mb-3"><label class="form-label">New password</label><input class="form-control" type="password" name="password" required minlength="8" autocomplete="new-password"></div>
        <div class="mb-3"><label class="form-label">Confirm password</label><input class="form-control" type="password" name="password_confirm" required autocomplete="new-password"></div>
        <button class="btn-lux btn-lux--block">Update password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php partial('footer');
