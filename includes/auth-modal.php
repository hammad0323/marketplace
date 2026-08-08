<?php $specs = mysqli_query(db(), 'SELECT id, name FROM specializations WHERE is_active = 1 ORDER BY name'); ?>
<div class="modal-overlay" id="auth-modal">
    <div class="modal-box">
        <button class="modal-close" data-modal-close aria-label="Close"><i class="ri-close-line"></i></button>
        <div class="modal-visual">
            <div class="floating-shape" style="width:120px;height:120px;background:rgba(255,255,255,0.15);top:-30px;right:-30px;"></div>
            <div class="floating-shape" style="width:80px;height:80px;background:rgba(255,255,255,0.12);bottom:20px;left:-20px;animation-delay:1s;"></div>
            <i class="ri-shield-check-fill" style="font-size:44px;margin-bottom:20px;"></i>
            <h3>Care that comes to you</h3>
            <p>Join thousands of patients booking verified doctors online in seconds — or apply as a doctor to grow your practice.</p>
        </div>
        <div class="modal-form-side">
            <div class="auth-tabs">
                <button class="auth-tab active" data-tab="login">Log In</button>
                <button class="auth-tab" data-tab="register">Register</button>
            </div>

            <div class="auth-panel active" data-panel="login">
                <form id="login-form-modal" novalidate>
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
                            <input type="password" class="form-control" name="password" id="login-modal-pass" placeholder="••••••••" required>
                            <button type="button" class="toggle-pass" data-toggle-pass="login-modal-pass"><i class="ri-eye-line"></i></button>
                        </div>
                        <div class="form-error"></div>
                    </div>
                    <div class="checkbox-row" style="margin-bottom:20px;">
                        <input type="checkbox" name="remember"> <label>Remember me</label>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Log In</button>
                    <p style="text-align:center;font-size:13.5px;margin-top:18px;color:var(--color-text-muted);">
                        New here? <a href="#" style="color:var(--color-primary);font-weight:600;" onclick="event.preventDefault();openAuthModal('register')">Create an account</a>
                    </p>
                </form>
            </div>

            <div class="auth-panel" data-panel="register">
                <form id="register-form-modal" novalidate>
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
                            <input type="password" class="form-control" name="password" id="register-modal-pass" placeholder="Min. 8 characters" required data-pw-strength="#pw-bar-modal">
                            <button type="button" class="toggle-pass" data-toggle-pass="register-modal-pass"><i class="ri-eye-line"></i></button>
                        </div>
                        <div class="pw-strength"><div class="pw-strength-bar" id="pw-bar-modal"></div></div>
                        <div class="form-error"></div>
                    </div>
                    <label class="checkbox-row" style="margin-bottom:20px;">
                        <input type="checkbox" required> I agree to the <a href="/terms" target="_blank" style="color:var(--color-primary);">Terms</a> &amp; <a href="/privacy-policy" target="_blank" style="color:var(--color-primary);">Privacy Policy</a>
                    </label>
                    <button type="submit" class="btn btn-primary btn-block">Create Account</button>
                    <p style="text-align:center;font-size:13.5px;margin-top:18px;color:var(--color-text-muted);">
                        Already have an account? <a href="#" style="color:var(--color-primary);font-weight:600;" onclick="event.preventDefault();openAuthModal('login')">Log in</a>
                    </p>
                    <p style="text-align:center;font-size:12.5px;margin-top:10px;color:var(--color-text-muted);">
                        Are you a doctor? <a href="/doctor-register" style="color:var(--color-primary);font-weight:600;">Apply here</a>
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>
