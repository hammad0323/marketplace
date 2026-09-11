<?php
require __DIR__ . '/../includes/config.php';

if (tp_is_business_logged_in()) {
    header('Location: ' . tp_url('crm/dashboard.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $email = tp_sanitize_text($_POST['email'] ?? '', 190);
    $password = (string) ($_POST['password'] ?? '');
    $result = tp_business_attempt_login($email, $password);
    if ($result['success']) {
        $redirect = $_GET['redirect'] ?? tp_url('crm/dashboard.php');
        header('Location: ' . $redirect);
        exit;
    }
    $error = $result['error'];
}

$pageTitle = 'Log In — Sales CRM — ' . tp_setting('site_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= tp_asset('css/theme.css') ?>">
<style>body{background:var(--tp-gradient-brand);min-height:100vh;display:flex;align-items:center;}</style>
</head>
<body>
<div class="tp-container" style="max-width:420px;">
  <div class="tp-card p-4 p-md-5">
    <div class="text-center mb-4">
      <a href="<?= tp_url() ?>" class="d-inline-flex align-items-center gap-2 text-decoration-none mb-3">
        <span class="brand-mark"><i class="bi bi-grid-1x2-fill"></i></span>
      </a>
      <h1 class="h4 fw-bold mb-1">Sales CRM Login</h1>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= tp_csrf_field() ?>
      <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required autofocus></div>
      <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <button class="tp-btn tp-btn-primary w-100" type="submit">Log In</button>
    </form>
    <p class="text-center small text-muted mt-3 mb-0">New here? <a href="<?= tp_url('crm/register.php') ?>">Create a free account</a></p>
  </div>
</div>
</body>
</html>
