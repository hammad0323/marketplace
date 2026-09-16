<?php
require_once __DIR__ . '/includes/functions.php';

if (customer_logged_in()) redirect(url('account/dashboard'));

$redirect = $_GET['redirect'] ?? 'account/dashboard';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = mysqli_prepare($mysqli, "SELECT * FROM customers WHERE email = ?");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $customer = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($customer && $customer['status'] === 'active' && $customer['password_hash'] && password_verify($password, $customer['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['customer_id'] = $customer['id'];
        merge_guest_cart_into_customer($mysqli, $customer['id']);
        redirect(url(ltrim($redirect, '/')));
    } else {
        $error = 'Invalid email or password.';
    }
}

$pageTitle = 'Login | ' . get_setting('store_name');
require_once __DIR__ . '/includes/header.php';
$googleEnabled = get_setting('google_login_enabled') === '1';
?>
<div class="container section-tight" style="max-width:480px">
  <div class="summary-box">
    <h1 class="h4 font-serif mb-4 text-center">Login to Your Account</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <button class="btn-brand w-100 mb-3">Login</button>
    </form>
    <?php if ($googleEnabled): ?>
      <a href="<?= e(url('google-login', ['redirect' => $redirect])) ?>" class="btn btn-outline-dark w-100 mb-3"><i class="bi bi-google"></i> Continue with Google</a>
    <?php endif; ?>
    <p class="text-center small text-muted mb-0">Don't have an account? <a href="<?= url('register') ?>">Create one</a></p>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
