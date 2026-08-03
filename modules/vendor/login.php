<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $vendor = find_vendor_by_email($email);
    if (!$vendor || !password_verify($password, $vendor['password_hash'])) {
        flash('error', 'Invalid email or password.');
        redirect('/vendor/login');
    }

    login_vendor($vendor);
    redirect('/vendor/dashboard');
}

$pageTitle = 'Vendor Login';
$theme = 'main';
require __DIR__ . '/../../partials/header.php';
?>

<div class="form-card">
    <h1>Vendor Login</h1>
    <form method="post" action="/vendor/login">
        <?= csrf_field() ?>
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
    <p style="margin-top:1rem;">Don't have a store yet? <a href="/vendor/register">Register as a vendor</a></p>
</div>

<?php require __DIR__ . '/../../partials/footer.php'; ?>
