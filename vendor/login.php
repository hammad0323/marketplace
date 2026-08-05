<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $vendor = mp_find_vendor_by_email($email);
    if (!$vendor || !password_verify($password, $vendor['password_hash'])) {
        mp_flash('error', 'Invalid email or password.');
        mp_redirect('login.php');
    }

    mp_login_vendor($vendor);
    mp_redirect('dashboard.php');
}

$pageTitle = 'Vendor Login';
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>

<div class="form-card reveal">
    <h1>Vendor Login</h1>
    <form method="post" action="login.php">
        <?= mp_csrf_field() ?>
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
    <p style="margin-top:1rem;">Don't have a store yet? <a href="register.php">Register as a vendor</a></p>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
