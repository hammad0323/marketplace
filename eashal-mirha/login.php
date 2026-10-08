<?php
require __DIR__ . '/includes/bootstrap.php';

if (customer()) redirect('account');
if (get('next') === 'checkout') $_SESSION['after_login'] = url('checkout');

$error = '';
if (is_post()) {
    require_csrf();
    $key = 'login_fail_' . md5(client_ip());
    if (($_SESSION[$key] ?? 0) >= 8) {
        $error = 'Too many attempts. Please wait a few minutes and try again.';
    } else {
        $u = row('SELECT * FROM customers WHERE email = ?', [post('email')]);
        if ($u && (int)$u['status'] === 1 && password_verify((string)post('password'), $u['password'])) {
            session_regenerate_id(true);
            $_SESSION['customer_id'] = (int)$u['id'];
            unset($_SESSION[$key]);
            // Move guest wishlist to the account
            foreach ($_SESSION['wishlist'] ?? [] as $pid) q('INSERT IGNORE INTO wishlist (customer_id, product_id) VALUES (?, ?)', [$u['id'], (int)$pid]);
            unset($_SESSION['wishlist']);
            $next = $_SESSION['after_login'] ?? url('account');
            unset($_SESSION['after_login']);
            header('Location: ' . (strpos($next, '/') === 0 && strpos($next, '//') !== 0 ? $next : url('account')));
            exit;
        }
        $_SESSION[$key] = ($_SESSION[$key] ?? 0) + 1;
        $error = $u && (int)$u['status'] !== 1 ? 'Your account has been disabled. Please contact us.' : 'Incorrect email or password.';
    }
}
$seo = ['title' => 'Login | ' . setting('site_name'), 'noindex' => true];
require ROOT . '/includes/header.php';
?>
<section class="section auth">
  <div class="auth__box" data-reveal>
    <span class="ornament">✦</span>
    <h1 class="section-title">Welcome Back</h1>
    <p class="muted">Sign in to track orders and checkout faster.</p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form-stack">
      <?= csrf_field() ?>
      <label>Email<input type="email" name="email" value="<?= e(post('email')) ?>" required autocomplete="email"></label>
      <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
      <button class="btn btn-dark btn-block" type="submit">Sign In</button>
    </form>
    <p class="auth__alt">New to <?= e(setting('site_name')) ?>? <a class="link-underline" href="<?= url('register') ?>">Create an account</a></p>
    <?php if (setting('guest_checkout', '1') === '1' && cart_count()): ?><p class="auth__alt"><a class="link-underline" href="<?= url('checkout') ?>">Continue as guest →</a></p><?php endif; ?>
  </div>
</section>
<?php require ROOT . '/includes/footer.php';
