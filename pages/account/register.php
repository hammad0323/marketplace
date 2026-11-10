<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
if (customer_id()) redirect('my-account/');
if (is_post()) {
    csrf_check();
    keep_old(['name' => input('name'), 'phone' => input('phone'), 'email' => input('email')]);
    if (($_POST['password'] ?? '') !== ($_POST['password2'] ?? '')) {
        flash('danger', 'Passwords do not match.');
        redirect('register/');
    }
    if (!form_throttle('register', 10)) { flash('warning', 'Please wait a moment and try again.'); redirect('register/'); }
    $r = customer_register(input('name'), input('phone'), input('email'), (string) ($_POST['password'] ?? ''));
    if (is_string($r)) { flash('danger', $r); redirect('register/'); }
    clear_old();
    customer_login($r);
    flash('success', 'Your account has been created. Welcome to Fahim Store!');
    $to = $_SESSION['after_login'] ?? url('my-account/');
    unset($_SESSION['after_login']);
    redirect($to);
}
page_set(['title' => 'Create Account', 'robots' => 'noindex,follow']);
require FS_ROOT . '/partials/header.php';
?>
<div class="container">
  <div class="box auth-card">
    <h1 class="h4 fw-bold mb-3">Create your account</h1>
    <?= render_flashes() ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-2"><label class="form-label" for="rName">Full name *</label><input id="rName" name="name" class="form-control" required maxlength="120" autocomplete="name" value="<?= e(old('name')) ?>"></div>
      <div class="mb-2"><label class="form-label" for="rPhone">Mobile number *</label><input id="rPhone" name="phone" class="form-control" required maxlength="20" inputmode="tel" placeholder="03XXXXXXXXX" value="<?= e(old('phone')) ?>"></div>
      <div class="mb-2"><label class="form-label" for="rEmail">Email (optional)</label><input id="rEmail" type="email" name="email" class="form-control" maxlength="190" value="<?= e(old('email')) ?>"></div>
      <div class="row g-2 mb-3">
        <div class="col"><label class="form-label" for="rP1">Password *</label><input id="rP1" type="password" name="password" class="form-control" required minlength="6" autocomplete="new-password"></div>
        <div class="col"><label class="form-label" for="rP2">Confirm *</label><input id="rP2" type="password" name="password2" class="form-control" required minlength="6" autocomplete="new-password"></div>
      </div>
      <button class="btn btn-brand btn-lg w-100">Create account</button>
    </form>
    <p class="text-center mt-3 mb-0 small">Already registered? <a href="<?= url('login/') ?>" class="fw-semibold">Login</a></p>
  </div>
</div>
<?php clear_old(); require FS_ROOT . '/partials/footer.php';
