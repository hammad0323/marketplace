<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $customer = find_customer_by_email($email);
    if (!$customer || !password_verify($password, $customer['password_hash'])) {
        flash('error', 'Invalid email or password.');
        redirect('/customer/login');
    }

    $_SESSION['customer_id'] = $customer['id'];
    redirect($_POST['redirect_to'] ?? '/');
}

$pageTitle = 'Login';
$theme = 'main';
require __DIR__ . '/../../partials/header.php';
?>

<div class="form-card">
    <h1>Login</h1>
    <form method="post" action="/customer/login">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="<?= e($_GET['redirect_to'] ?? '/') ?>">
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
    <p style="margin-top:1rem;">New here? <a href="/customer/register">Create an account</a></p>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
