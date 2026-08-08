<?php
require __DIR__ . '/../config/config.php';

if (is_logged_in() && current_role() === 'admin') {
    redirect('/admin/dashboard');
}

$pageTitle = 'Admin Login';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — <?= e(SITE_NAME) ?></title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"></noscript>
<link rel="stylesheet" href="/assets/fonts/remixicon/remixicon.css">
<link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;">
    <div class="floating-shape" style="width:300px;height:300px;background:var(--color-accent);opacity:0.25;top:-80px;left:-80px;"></div>
    <div class="floating-shape" style="width:220px;height:220px;background:var(--color-primary);opacity:0.15;bottom:-60px;right:-60px;animation-delay:1s;"></div>
    <div class="card" style="width:100%;max-width:400px;padding:40px;position:relative;z-index:2;">
        <div style="text-align:center;margin-bottom:28px;">
            <span class="brand-mark" style="margin:0 auto 16px;"><i class="ri-shield-star-fill"></i></span>
            <h2>Admin Login</h2>
            <p style="color:var(--color-text-muted);font-size:13.5px;">Restricted access — platform administrators only.</p>
        </div>
        <form id="admin-login-form" novalidate>
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group" data-field="email">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control" name="email" required autofocus>
                <div class="form-error"></div>
            </div>
            <div class="form-group" data-field="password">
                <label class="form-label">Password</label>
                <div class="input-icon-wrap">
                    <i class="ri-lock-line"></i>
                    <input type="password" class="form-control" name="password" id="admin-pass" required>
                    <button type="button" class="toggle-pass" data-toggle-pass="admin-pass"><i class="ri-eye-line"></i></button>
                </div>
                <div class="form-error"></div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Log In</button>
        </form>
        <p style="text-align:center;font-size:13px;margin-top:20px;"><a href="/" style="color:var(--color-text-muted);"><i class="ri-arrow-left-line"></i> Back to site</a></p>
    </div>
</div>
<script>window.APP = { csrfToken: <?= json_encode(csrf_token()) ?> };</script>
<script src="/assets/js/toast.js"></script>
<script src="/assets/js/main.js"></script>
<script>
document.getElementById('admin-login-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = e.target;
    form.querySelectorAll('.form-group.error').forEach(function (g) { g.classList.remove('error'); });
    var btn = form.querySelector('button[type="submit"]');
    var original = btn.innerHTML;
    btn.disabled = true; btn.textContent = 'Logging in…';
    fetch('/ajax/admin-login.php', { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false; btn.innerHTML = original;
            if (data.success) {
                showToast('success', 'Welcome', data.message);
                setTimeout(function () { window.location.href = data.redirect || '/admin/dashboard'; }, 500);
            } else {
                showToast('error', 'Login failed', data.message);
            }
        })
        .catch(function () {
            btn.disabled = false; btn.innerHTML = original;
            showToast('error', 'Network error', 'Please try again.');
        });
});
</script>
</body>
</html>
