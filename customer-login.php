<?php
require __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $customer = mp_find_customer_by_email($email);
    if (!$customer || !password_verify($password, $customer['password_hash'])) {
        mp_flash('error', 'Invalid email or password.');
        mp_redirect('/customer-login.php');
    }

    $_SESSION['customer_id'] = $customer['id'];
    mp_redirect($_POST['redirect_to'] ?? '/');
}

$pageTitle = 'Login';
$theme = 'main';
require __DIR__ . '/header.php';
?>

<div class="form-card">
    <h1>Login</h1>
    <form method="post" action="/customer-login.php">
        <?= mp_csrf_field() ?>
        <input type="hidden" name="redirect_to" value="<?= mp_e($_GET['redirect_to'] ?? '/') ?>">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn">Log In</button>
    </form>
    <p style="margin-top:1rem;">New here? <a href="/customer-register.php">Create an account</a></p>
</div>

<?php require __DIR__ . '/footer.php'; ?>
