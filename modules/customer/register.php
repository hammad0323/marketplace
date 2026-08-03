<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || strlen($password) < 8) {
        flash('error', 'Please fill in all fields (password must be at least 8 characters).');
        redirect('/customer/register');
    }

    if (find_customer_by_email($email)) {
        flash('error', 'An account with that email already exists.');
        redirect('/customer/register');
    }

    $customerId = insert_customer([
        'name'          => $name,
        'email'         => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    notify('customer.welcome', $email, ['name' => $name]);

    $_SESSION['customer_id'] = $customerId;
    redirect($_POST['redirect_to'] ?? '/');
}

$pageTitle = 'Create Account';
$theme = 'main';
require __DIR__ . '/../../partials/header.php';
?>

<div class="form-card">
    <h1>Create Account</h1>
    <form method="post" action="/customer/register">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="<?= e($_GET['redirect_to'] ?? '/') ?>">
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="8" required>
        </div>
        <button type="submit" class="btn">Create Account</button>
    </form>
    <p style="margin-top:1rem;">Already have an account? <a href="/customer/login">Log in</a></p>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
