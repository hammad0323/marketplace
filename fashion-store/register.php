<?php
require_once __DIR__ . '/includes/functions.php';

if (customer_logged_in()) redirect(BASE_URL . '/account/dashboard.php');

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 6) {
        $error = 'Please fill in all fields with a valid email and a password of at least 6 characters.';
    } else {
        $existing = mysqli_fetch_assoc(mysqli_query($mysqli, "SELECT id FROM customers WHERE email = '" . mysqli_real_escape_string($mysqli, $email) . "'"));
        if ($existing) {
            $error = 'An account with this email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($mysqli, "INSERT INTO customers (name, email, phone, password_hash) VALUES (?,?,?,?)");
            mysqli_stmt_bind_param($stmt, 'ssss', $name, $email, $phone, $hash);
            mysqli_stmt_execute($stmt);
            $customerId = mysqli_insert_id($mysqli);
            session_regenerate_id(true);
            $_SESSION['customer_id'] = $customerId;
            merge_guest_cart_into_customer($mysqli, $customerId);
            redirect(BASE_URL . '/account/dashboard.php');
        }
    }
}

$pageTitle = 'Create Account | ' . get_setting('store_name');
require_once __DIR__ . '/includes/header.php';
?>
<div class="container section-tight" style="max-width:480px">
  <div class="summary-box">
    <h1 class="h4 font-serif mb-4 text-center">Create an Account</h1>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Full Name</label><input type="text" name="name" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
      <div class="mb-3"><label class="form-label">Phone</label><input type="text" name="phone" class="form-control"></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required minlength="6"></div>
      <button class="btn-brand w-100 mb-3">Create Account</button>
    </form>
    <p class="text-center small text-muted mb-0">Already have an account? <a href="<?= BASE_URL ?>/login.php">Login</a></p>
  </div>
</div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
