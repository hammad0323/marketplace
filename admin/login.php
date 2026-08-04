<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $admin = mp_find_admin_by_email($email);
    if (!$admin || !password_verify($password, $admin['password_hash'])) {
        mp_flash('error', 'Invalid email or password.');
        mp_redirect('/admin/login');
    }

    mp_login_admin($admin);
    mp_redirect('/admin');
}

$pageTitle = 'Admin Login';
require __DIR__ . '/_header.php';
?>

<div class="form-card">
    <h1>Admin Login</h1>
    <form method="post" action="/admin/login">
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

<?php require __DIR__ . '/_footer.php'; ?>
