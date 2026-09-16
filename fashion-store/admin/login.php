<?php
require_once __DIR__ . '/../includes/functions.php';

if (admin_logged_in()) {
    redirect(url('admin'));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = mysqli_prepare($mysqli, "SELECT * FROM admins WHERE email = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 's', $email);
    mysqli_stmt_execute($stmt);
    $admin = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

    if ($admin && $admin['status'] === 'active' && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $upd = mysqli_prepare($mysqli, "UPDATE admins SET last_login = NOW() WHERE id = ?");
        mysqli_stmt_bind_param($upd, 'i', $admin['id']);
        mysqli_stmt_execute($upd);
        redirect(url('admin'));
    } else {
        $error = 'Invalid email or password.';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Login | <?= e(get_setting('store_name', 'Fashion Store')) ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body class="admin-login-body">
<div class="admin-login-wrap">
  <div class="admin-login-card">
    <h1 class="brand-mark"><?= e(get_setting('store_name', 'Fashion Store')) ?></h1>
    <p class="text-muted mb-4">Admin Panel</p>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" required autofocus value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-dark w-100">Login</button>
    </form>
  </div>
</div>
</body>
</html>
