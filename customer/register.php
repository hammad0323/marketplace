<?php
require __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mp_verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $email === '' || $phone === '' || strlen($password) < 8) {
        mp_flash('error', 'Please fill in all fields (password must be at least 8 characters).');
        mp_redirect('register.php');
    }

    if (mp_find_customer_by_email($email)) {
        mp_flash('error', 'An account with that email already exists.');
        mp_redirect('register.php');
    }

    $customerId = mp_insert_customer([
        'name'          => $name,
        'email'         => $email,
        'phone'         => $phone,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);

    $verifyToken = mp_set_customer_verification_token($customerId);
    $verifyUrl = ROUTE_CUSTOMER . 'verify.php?token=' . $verifyToken;
    mp_notify('customer.welcome', $email, ['name' => $name, 'verify_url' => $verifyUrl]);
    mp_log_activity('customer', $customerId, 'customer.registered', 'customer', $customerId);

    $_SESSION['customer_id'] = $customerId;
    $_SESSION['_just_registered_verify_url'] = $verifyUrl;
    $redirectAfter = $_POST['redirect_to'] ?? ROUTE_HOME;
    mp_redirect(ROUTE_CUSTOMER . 'registered.php?redirect_to=' . urlencode($redirectAfter));
}

$pageTitle = 'Create Account';
$theme = 'main';
require __DIR__ . '/../templates/header.php';
?>

<div class="form-card reveal">
    <h1>Create Account</h1>
    <form method="post" action="register.php">
        <?= mp_csrf_field() ?>
        <input type="hidden" name="redirect_to" value="<?= mp_e($_GET['redirect_to'] ?? ROUTE_HOME) ?>">
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" id="name" name="name" required>
        </div>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="text" id="phone" name="phone" required placeholder="+92 300 1234567">
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" minlength="8" required>
        </div>
        <button type="submit" class="btn">Create Account</button>
    </form>
    <p style="margin-top:1rem;">Already have an account? <a href="login.php">Log in</a></p>
</div>

<?php require __DIR__ . '/../templates/footer.php'; ?>
