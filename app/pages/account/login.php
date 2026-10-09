<?php
if (current_customer()) {
    redirect(path_url('account'));
}
meta_set(['title' => 'Sign in', 'noindex' => true]);
$next = input('next', '', 'get');
if ($next === 'checkout') {
    $_SESSION['after_login'] = path_url('checkout');
}
$error = null;
if (is_post()) {
    require_csrf();
    $error = customer_login(input('email'), (string) ($_POST['password'] ?? ''));
    if (!$error) {
        $to = $_SESSION['after_login'] ?? path_url('account');
        unset($_SESSION['after_login']);
        if (!is_string($to) || strpos($to, base_path() . '/') !== 0 || strpos($to, '//') !== false) {
            $to = path_url('account');
        }
        flash('success', 'Welcome back.');
        redirect($to);
    }
}
partial('header');
?>
<div class="container page-pad">
  <div class="auth-split">
    <div class="auth-card" data-reveal="fade-up">
      <p class="eyebrow">Welcome back</p>
      <h1 class="page-title">Sign in</h1>
      <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label" for="email">Email</label><input class="form-control" type="email" id="email" name="email" required autocomplete="email" maxlength="190" value="<?= e(input('email')) ?>"></div>
        <div class="mb-2"><label class="form-label" for="password">Password</label><input class="form-control" type="password" id="password" name="password" required autocomplete="current-password" maxlength="128"></div>
        <p class="text-end small"><a href="<?= e(path_url('account/forgot-password')) ?>">Forgot password?</a></p>
        <button class="btn-lux btn-lux--block" type="submit">Sign in</button>
      </form>
    </div>
    <div class="auth-aside" data-reveal="fade-up" style="--reveal-delay:120ms">
      <h2 class="h4">New to <?= e(setting('site_name', 'Beglet')) ?>?</h2>
      <p>Create an account to track orders, save addresses and keep a wishlist. You can also check out as a guest — no account required.</p>
      <a class="btn-outline-lux" href="<?= e(path_url('account/register')) ?>">Create an account</a>
      <a class="link-arrow mt-3" href="<?= e(path_url('track-order')) ?>">Track a guest order <i class="bi bi-arrow-right"></i></a>
    </div>
  </div>
</div>
<?php partial('footer');
