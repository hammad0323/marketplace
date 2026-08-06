<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $admin = mp_find_admin_by_email($email);
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        mp_flash('error', 'Invalid email or password.');
        mp_redirect('login.php');
    }

    if (!$admin['is_active']) {
        mp_flash('error', 'This admin account has been disabled.');
        mp_redirect('login.php');
    }

    mp_touch_admin_last_login($admin['id']);
    mp_login_admin($admin);
    mp_redirect('dashboard.php');
}

$pageTitle = 'Admin Login';
require __DIR__ . '/../templates/admin-header.php';
?>

<div class="form-card reveal">
    <h1>Admin Login</h1>
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
</div>

<?php require __DIR__ . '/../templates/admin-footer.php'; ?>
