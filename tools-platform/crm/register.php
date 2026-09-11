<?php
require __DIR__ . '/../includes/config.php';

if (tp_is_business_logged_in()) {
    header('Location: ' . tp_url('crm/dashboard.php'));
    exit;
}

$error = null;
$old = ['business_name' => '', 'owner_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    tp_require_csrf();
    $old['business_name'] = tp_sanitize_text($_POST['business_name'] ?? '', 160);
    $old['owner_name'] = tp_sanitize_text($_POST['owner_name'] ?? '', 120);
    $old['email'] = tp_sanitize_text($_POST['email'] ?? '', 190);
    $old['phone'] = tp_sanitize_text($_POST['phone'] ?? '', 30);
    $password = (string) ($_POST['password'] ?? '');

    $result = tp_business_register($old['business_name'], $old['owner_name'], $old['email'], $old['phone'], $password);
    if ($result['success']) {
        header('Location: ' . tp_url('crm/dashboard.php'));
        exit;
    }
    $error = $result['error'];
}

$pageTitle = 'Sign Up Free — Sales CRM — ' . tp_setting('site_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="<?= tp_asset('css/theme.css') ?>">
<style>body{background:var(--tp-gradient-brand);min-height:100vh;display:flex;align-items:center;padding:2rem 0;}</style>
</head>
<body>
<div class="tp-container" style="max-width:460px;">
  <div class="tp-card p-4 p-md-5">
    <div class="text-center mb-4">
      <a href="<?= tp_url() ?>" class="d-inline-flex align-items-center gap-2 text-decoration-none mb-3">
        <span class="brand-mark"><i class="bi bi-grid-1x2-fill"></i></span>
      </a>
      <h1 class="h4 fw-bold mb-1">Start Your Free Sales CRM</h1>
      <p class="text-muted small mb-0">Leads, follow-ups, quotations and khata — all in one place.</p>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <form method="post" novalidate>
      <?= tp_csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Business Name</label>
        <input type="text" name="business_name" class="form-control" value="<?= e($old['business_name']) ?>" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Your Name</label>
        <input type="text" name="owner_name" class="form-control" value="<?= e($old['owner_name']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Email</label>
        <input type="email" name="email" class="form-control" value="<?= e($old['email']) ?>" required>
      </div>
      <div class="mb-3">
        <label class="form-label">Phone (optional)</label>
        <input type="text" name="phone" class="form-control" value="<?= e($old['phone']) ?>" placeholder="03001234567">
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" minlength="8" required>
        <small class="text-muted">At least 8 characters.</small>
      </div>
      <button class="tp-btn tp-btn-primary w-100" type="submit">Create Free Account</button>
    </form>
    <p class="text-center small text-muted mt-3 mb-0">Already have an account? <a href="<?= tp_url('crm/login.php') ?>">Log in</a></p>
  </div>
</div>
</body>
</html>
