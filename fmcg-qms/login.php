<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(app_url_for_role(current_role()));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $email = post('email');
    $password = post_raw('password', '');
    if (!validate_email($email) || $password === '') {
        $error = 'Please enter a valid email and password.';
    } else {
        $result = login_super_admin($email, $password);
        if (!$result['success']) {
            $result = login_company_user($email, $password);
        }
        if ($result['success']) {
            $redirect = get_param('redirect', '');
            redirect($redirect ? $redirect : app_url_for_role(current_role()));
        }
        $error = $result['message'] ?? 'Invalid credentials.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Login - <?= out(app_name()) ?></title>
<?php $favicon = app_favicon_url(); ?>
<?php if ($favicon): ?><link rel="icon" href="<?= out($favicon) ?>"><?php endif; ?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="<?= base_url('assets/css/style.css') ?>" rel="stylesheet">
<?php $__primary = app_primary_color(); if ($__primary !== PRIMARY_COLOR_DEFAULT): ?>
<style>:root{--primary:<?= out($__primary) ?>;--primary-dark:<?= out($__primary) ?>;}</style>
<?php endif; ?>
</head>
<body>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="text-center mb-4">
      <?php $logo = app_logo_url(); ?>
      <?php if ($logo): ?>
        <img src="<?= out($logo) ?>" alt="<?= out(app_name()) ?>" class="mx-auto mb-2 d-block" style="height:48px;width:auto;border-radius:10px;">
      <?php else: ?>
        <div class="brand-badge mx-auto mb-2" style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,var(--primary,#2563EB),#60A5FA);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1.3rem;">Q</div>
      <?php endif; ?>
      <h4 class="fw-bold mb-0"><?= out(app_name()) ?></h4>
      <p class="text-muted small mb-0">Sign in to your quality management workspace</p>
    </div>
    <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= out($error) ?></div><?php endif; ?>
    <form method="POST" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label small fw-semibold">Email Address</label>
        <input type="email" name="email" class="form-control" required value="<?= out(post('email','')) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-100 py-2 fw-semibold">Sign In</button>
    </form>
    <div class="mt-4 pt-3 border-top small text-muted">
      <div class="fw-semibold mb-1">Demo credentials</div>
      Super Admin: superadmin@qualitycore.app / SuperAdmin@123<br>
      Manager: manager@goldenharvest.demo / Manager@123<br>
      Employee: priya.rao@goldenharvest.demo / Employee@123
    </div>
    <div class="text-center mt-3"><a href="<?= base_url('index.php') ?>" class="small text-decoration-none"><i class="bi bi-arrow-left"></i> Back to homepage</a></div>
  </div>
</div>
</body>
</html>
