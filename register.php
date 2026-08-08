<?php
require __DIR__ . '/config/config.php';

if (is_logged_in()) {
    $role = current_role();
    redirect($role === 'doctor' ? '/doctor/dashboard' : ($role === 'admin' ? '/admin/dashboard' : '/patient/dashboard'));
}

$pageTitle = 'Create Account — ' . SITE_NAME;
$metaDescription = 'Create a free patient account on ' . SITE_NAME . ' to book appointments and chat with verified doctors.';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 40px);min-height:80vh;display:flex;align-items:center;">
    <div class="container" style="max-width:920px;">
        <div class="card" style="display:grid;grid-template-columns:1fr 1fr;overflow:hidden;padding:0;" data-reveal>
            <div class="modal-visual" style="border-radius:0;">
                <i class="ri-user-heart-fill" style="font-size:44px;margin-bottom:20px;"></i>
                <h3>Join MediConnect</h3>
                <p>Create your free patient account to book appointments with verified doctors in seconds.</p>
            </div>
            <div style="padding:44px 40px;">
                <h2 style="margin-bottom:24px;">Create Account</h2>
                <form id="register-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="form-group" data-field="full_name">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" placeholder="John Anderson" required>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="email">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" placeholder="you@example.com" required>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="phone">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone" placeholder="+1 555 000 0000">
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="password">
                        <label class="form-label">Password</label>
                        <div class="input-icon-wrap">
                            <i class="ri-lock-line"></i>
                            <input type="password" class="form-control" name="password" id="register-page-pass" placeholder="Min. 8 characters" required data-pw-strength="#pw-bar-page">
                            <button type="button" class="toggle-pass" data-toggle-pass="register-page-pass"><i class="ri-eye-line"></i></button>
                        </div>
                        <div class="pw-strength"><div class="pw-strength-bar" id="pw-bar-page"></div></div>
                        <div class="form-error"></div>
                    </div>
                    <label class="checkbox-row" style="margin-bottom:20px;">
                        <input type="checkbox" required> I agree to the <a href="/terms" target="_blank" style="color:var(--color-primary);">Terms</a> &amp; <a href="/privacy-policy" target="_blank" style="color:var(--color-primary);">Privacy Policy</a>
                    </label>
                    <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                    <p style="text-align:center;font-size:13.5px;margin-top:18px;color:var(--color-text-muted);">
                        Already have an account? <a href="/login" style="color:var(--color-primary);font-weight:600;">Log in</a>
                    </p>
                    <p style="text-align:center;font-size:12.5px;margin-top:8px;color:var(--color-text-muted);">
                        Are you a doctor? <a href="/doctor-register" style="color:var(--color-primary);font-weight:600;">Apply here</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
