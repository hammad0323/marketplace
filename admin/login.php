<?php
define('ADMIN_LOGIN_PAGE', true);
require __DIR__ . '/includes/init.php';
if (admin_session_valid()) redirect('admin/index.php');
if (is_post()) {
    csrf_check();
    $r = admin_attempt(input('email'), (string) ($_POST['password'] ?? ''));
    if (is_string($r)) {
        flash('danger', $r);
        redirect('admin/login.php');
    }
    admin_login($r);
    $to = $_SESSION['admin_after_login'] ?? '';
    unset($_SESSION['admin_after_login']);
    redirect($to && strpos($to, base_path() . '/admin/') === 0 && strpos($to, 'login') === false ? $to : 'admin/index.php');
}
admin_header('Login');
?>
<div class="login-card">
  <div class="text-center mb-4"><img src="<?= url('assets/images/logo.svg') ?>" alt="Fahim Store" height="52"><div class="text-muted small mt-2">Admin Panel</div></div>
  <?= render_flashes() ?>
  <form method="post" autocomplete="on">
    <?= csrf_field() ?>
    <div class="mb-3"><label class="form-label" for="em">Email</label><input type="email" id="em" name="email" class="form-control form-control-lg" required autofocus autocomplete="username"></div>
    <div class="mb-3"><label class="form-label" for="pw">Password</label><input type="password" id="pw" name="password" class="form-control form-control-lg" required autocomplete="current-password"></div>
    <button class="btn btn-brand btn-lg w-100">Sign in</button>
  </form>
  <p class="text-center small text-muted mt-3 mb-0"><a href="<?= url('') ?>">&larr; Back to store</a></p>
</div>
<?php admin_footer();
