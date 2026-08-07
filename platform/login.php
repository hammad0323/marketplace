<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $platformAdmin = mp_find_platform_admin_by_email($email);
    if (!$platformAdmin || !password_verify($password, $platformAdmin['password_hash'])) {
        mp_flash('error', 'Invalid email or password.');
        mp_redirect('login.php');
    }

    if (!$platformAdmin['is_active']) {
        mp_flash('error', 'This platform operator account has been disabled.');
        mp_redirect('login.php');
    }

    mp_touch_platform_admin_last_login($platformAdmin['id']);
    mp_login_platform_admin($platformAdmin);
    mp_redirect('dashboard.php');
}

$pageTitle = 'Platform Login';
require __DIR__ . '/../templates/platform-header.php';
?>

<div class="form-card reveal">
    <h1>Platform Operator Login</h1>
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

<?php require __DIR__ . '/../templates/platform-footer.php'; ?>
