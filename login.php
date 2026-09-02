<?php
require __DIR__ . '/config/config.php';

if (is_logged_in()) {
    $role = current_role();
    redirect(role_home_url($role));
}

$pageTitle = 'Log In — ' . SITE_NAME;
$metaDescription = 'Log in to your ' . SITE_NAME . ' account to manage appointments, chat with doctors, and more.';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 40px);min-height:80vh;display:flex;align-items:center;">
    <div class="container" style="max-width:920px;">
        <div class="card split-even" data-reveal>
            <div class="modal-visual" style="border-radius:0;">
                <i class="ri-shield-check-fill" style="font-size:44px;margin-bottom:20px;"></i>
                <h3>Welcome back</h3>
                <p>Log in to manage your appointments, chat with your doctor, and access your medical records.</p>
            </div>
            <div style="padding:44px 40px;">
                <h1 style="margin-bottom:24px;">Log In</h1>
                <form id="login-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="form-group" data-field="email">
                        <label class="form-label">Email Address</label>
                        <div class="input-icon-wrap">
                            <i class="ri-mail-line"></i>
                            <input type="email" class="form-control" name="email" placeholder="you@example.com" required>
                        </div>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="password">
                        <label class="form-label">Password</label>
                        <div class="input-icon-wrap">
                            <i class="ri-lock-line"></i>
                            <input type="password" class="form-control" name="password" id="login-page-pass" placeholder="••••••••" required>
                            <button type="button" class="toggle-pass" data-toggle-pass="login-page-pass"><i class="ri-eye-line"></i></button>
                        </div>
                        <div class="form-error"></div>
                    </div>
                    <div class="checkbox-row" style="margin-bottom:20px;">
                        <input type="checkbox" name="remember"> <label>Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Log In</button>
                    <p style="text-align:center;font-size:13.5px;margin-top:18px;color:var(--color-text-muted);">
                        New here? <a href="/register" style="color:var(--color-primary);font-weight:600;">Create an account</a>
                    </p>
                    <p style="text-align:center;font-size:12.5px;margin-top:8px;color:var(--color-text-muted);">
                        <a href="/admin/login" style="color:var(--color-text-muted);">Admin Login</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
