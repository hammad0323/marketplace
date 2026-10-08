<?php
require __DIR__ . '/includes/bootstrap.php';

if (customer()) redirect('account');

$errors = [];
if (is_post()) {
    require_csrf();
    $name = mb_substr(post('name'), 0, 120);
    $email = mb_substr(post('email'), 0, 190);
    $phone = mb_substr(post('phone'), 0, 40);
    $pass = (string)post('password');
    if ($name === '') $errors[] = 'Please enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
    elseif (val('SELECT COUNT(*) FROM customers WHERE email = ?', [$email])) $errors[] = 'An account with this email already exists.';
    if (strlen($pass) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($pass !== (string)post('password2')) $errors[] = 'Passwords do not match.';
    if (!$errors) {
        q('INSERT INTO customers (name, email, phone, password) VALUES (?, ?, ?, ?)', [$name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['customer_id'] = (int)db()->lastInsertId();
        send_mail($email, 'Welcome to ' . setting('site_name'), '<h2 style="font-family:Georgia,serif">Welcome, ' . e($name) . '</h2><p>Your account has been created. Discover our latest collections at <a href="' . e(abs_url('')) . '">' . e(setting('site_name')) . '</a>.</p>');
        flash('success', 'Welcome to ' . setting('site_name') . ', ' . $name . '!');
        $next = $_SESSION['after_login'] ?? url('account');
        unset($_SESSION['after_login']);
        header('Location: ' . (strpos($next, '/') === 0 && strpos($next, '//') !== 0 ? $next : url('account')));
        exit;
    }
}
$seo = ['title' => 'Create Account | ' . setting('site_name'), 'noindex' => true];
require ROOT . '/includes/header.php';
?>
<section class="section auth">
  <div class="auth__box" data-reveal>
    <span class="ornament">✦</span>
    <h1 class="section-title">Create Account</h1>
    <p class="muted">Join us for faster checkout, order tracking and a saved wishlist.</p>
    <?php foreach ($errors as $er): ?><div class="alert alert-error"><?= e($er) ?></div><?php endforeach; ?>
    <form method="post" class="form-stack">
      <?= csrf_field() ?>
      <label>Full Name<input type="text" name="name" value="<?= e(post('name')) ?>" required autocomplete="name"></label>
      <label>Email<input type="email" name="email" value="<?= e(post('email')) ?>" required autocomplete="email"></label>
      <label>Phone<input type="tel" name="phone" value="<?= e(post('phone')) ?>" autocomplete="tel"></label>
      <label>Password<input type="password" name="password" required minlength="6" autocomplete="new-password"></label>
      <label>Confirm Password<input type="password" name="password2" required minlength="6" autocomplete="new-password"></label>
      <button class="btn btn-dark btn-block" type="submit">Create Account</button>
    </form>
    <p class="auth__alt">Already have an account? <a class="link-underline" href="<?= url('login') ?>">Sign in</a></p>
  </div>
</section>
<?php require ROOT . '/includes/footer.php';
