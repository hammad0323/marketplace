<?php
require __DIR__ . '/_inc/bootstrap.php';
if (current_admin()) {
    redirect(admin_url());
}
$error = null;
if (is_post()) {
    require_csrf();
    $error = admin_login(input('email'), (string) ($_POST['password'] ?? ''));
    if (!$error) {
        $to = $_SESSION['admin_after_login'] ?? admin_url();
        unset($_SESSION['admin_after_login']);
        if (!is_string($to) || strpos($to, base_path() . '/admin') !== 0) {
            $to = admin_url();
        }
        redirect($to);
    }
}
admin_header('Sign in');
?>
<div class="auth-box">
  <div class="auth-box__brand">BEGLET<small>Store administration</small></div>
  <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
  <?php foreach (take_flashes() as $f): ?><div class="alert alert-info py-2"><?= e($f['message']) ?></div><?php endforeach; ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <?= f_text('email', 'Email', input('email'), ['required' => true, 'autocomplete' => 'username', 'autofocus' => true], null, 'email') ?>
    <?= f_text('password', 'Password', '', ['required' => true, 'autocomplete' => 'current-password'], null, 'password') ?>
    <button class="btn btn-primary w-100 py-2">Sign in</button>
  </form>
  <p class="text-center small mt-3 mb-0"><a href="<?= e(admin_url('forgot-password')) ?>">Forgot your password?</a></p>
</div>
<?php admin_footer();
