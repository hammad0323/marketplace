<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
$next = input('next');
if ($next !== '' && (strpos($next, '/') !== 0 || strpos($next, '//') === 0)) $next = ''; // only local paths
if ($next !== '') $_SESSION['after_login'] = $next;
if (customer_id()) redirect('my-account/');
if (is_post()) {
    csrf_check();
    $r = customer_attempt(input('login'), (string) ($_POST['password'] ?? ''));
    if (is_string($r)) {
        flash('danger', $r);
        keep_old(['login' => input('login')]);
        redirect('login/');
    }
    customer_login($r);
    $to = $_SESSION['after_login'] ?? url('my-account/');
    unset($_SESSION['after_login']);
    flash('success', 'Welcome back, ' . explode(' ', $r['name'])[0] . '!');
    redirect($to);
}
page_set(['title' => 'Login', 'robots' => 'noindex,follow']);
require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="box auth-card">
    <h1 class="h4 fw-bold mb-1">Login to Fahim Store</h1>
    <p class="text-muted small">Track orders, save rashan lists and checkout faster.</p>
    <?= render_flashes() ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label" for="lLogin">Mobile number or email</label><input id="lLogin" name="login" class="form-control form-control-lg" required autocomplete="username" value="<?= e(old('login')) ?>"></div>
      <div class="mb-3"><label class="form-label" for="lPass">Password</label><input id="lPass" type="password" name="password" class="form-control form-control-lg" required autocomplete="current-password"></div>
      <button class="btn btn-brand btn-lg w-100">Login</button>
    </form>
    <p class="text-center mt-3 mb-0 small">New customer? <a href="<?= url('register/') ?>" class="fw-semibold">Create an account</a></p>
  </div>
</div>
<?php clear_old(); require FS_ROOT . '/partials/footer.php';
