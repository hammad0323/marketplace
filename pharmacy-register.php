<?php
require __DIR__ . '/config/config.php';

if (is_logged_in()) {
    redirect(role_home_url(current_role()));
}

$pageTitle = 'Register Your Pharmacy — ' . SITE_NAME;
$metaDescription = 'Register your pharmacy or medicine store on ' . SITE_NAME . '. Submit your registration number and certificates for review and start selling online.';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container" style="max-width:760px;">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <span>Register Your Pharmacy</span></nav>
        <div class="section-head" style="text-align:left;margin-left:0;" data-reveal>
            <span class="eyebrow">Join Our Network</span>
            <h1>Register Your Pharmacy</h1>
            <p>Submit your store details, registration number, and supporting certificates below. Our team reviews every application before your store can sell on the platform.</p>
        </div>

        <div class="card" style="padding:36px;" data-reveal>
            <form id="pharmacy-register-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="grid grid-2">
                    <div class="form-group" data-field="store_name">
                        <label class="form-label">Store / Pharmacy Name</label>
                        <input type="text" class="form-control" name="store_name" placeholder="City Care Pharmacy" required>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="full_name">
                        <label class="form-label">Owner / Contact Full Name</label>
                        <input type="text" class="form-control" name="full_name" required>
                        <div class="form-error"></div>
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group" data-field="email">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" required>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="phone">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone">
                        <div class="form-error"></div>
                    </div>
                </div>
                <div class="form-group" data-field="password">
                    <label class="form-label">Password</label>
                    <div class="input-icon-wrap">
                        <i class="ri-lock-line"></i>
                        <input type="password" class="form-control" name="password" id="pharm-reg-pass" placeholder="Min. 8 characters" required data-pw-strength="#pw-bar-pharm">
                        <button type="button" class="toggle-pass" data-toggle-pass="pharm-reg-pass"><i class="ri-eye-line"></i></button>
                    </div>
                    <div class="pw-strength"><div class="pw-strength-bar" id="pw-bar-pharm"></div></div>
                    <div class="form-error"></div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group" data-field="registration_number">
                        <label class="form-label">Pharmacy / Drug License No.</label>
                        <input type="text" class="form-control" name="registration_number" required>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="license_authority">
                        <label class="form-label">Issuing Authority</label>
                        <input type="text" class="form-control" name="license_authority" placeholder="e.g. Punjab Pharmacy Council">
                        <div class="form-error"></div>
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group" data-field="address">
                        <label class="form-label">Store Address</label>
                        <input type="text" class="form-control" name="address">
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="city">
                        <label class="form-label">City</label>
                        <input type="text" class="form-control" name="city" required>
                        <div class="form-error"></div>
                    </div>
                </div>
                <div class="form-group" data-field="bio">
                    <label class="form-label">About Your Store (optional)</label>
                    <textarea class="form-control" name="bio" rows="3" placeholder="Tell patients what you offer and how long you've been operating."></textarea>
                    <div class="form-error"></div>
                </div>
                <div class="form-group" data-field="certificates">
                    <label class="form-label">Registration Certificate(s)</label>
                    <input type="file" name="certificates[]" id="pharm-cert-files" accept=".jpg,.jpeg,.png,.pdf" multiple required style="display:none;">
                    <label for="pharm-cert-files" class="btn btn-outline btn-block" style="text-align:center;">Choose File(s)</label>
                    <p class="form-hint">Upload your drug license and any other certificates that support your registration number (JPG, PNG, or PDF).</p>
                    <div class="form-error"></div>
                </div>
                <label class="checkbox-row" style="margin-bottom:20px;">
                    <input type="checkbox" required> I confirm the information provided is accurate and I agree to the <a href="/terms" target="_blank" style="color:var(--color-primary);">Terms</a>.
                </label>
                <button type="submit" class="btn btn-primary btn-block">Submit Registration</button>
            </form>
        </div>
    </div>
</section>
<script>
document.getElementById('pharm-cert-files').addEventListener('change', function () {
    var label = document.querySelector('label[for="pharm-cert-files"]');
    if (!this.files.length) label.textContent = 'Choose File(s)';
    else if (this.files.length === 1) label.textContent = this.files[0].name;
    else label.textContent = this.files.length + ' files selected';
});
document.getElementById('pharmacy-register-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = e.target;
    form.querySelectorAll('.form-group.error').forEach(function (g) { g.classList.remove('error'); });
    var btn = form.querySelector('button[type="submit"]');
    var original = btn.innerHTML;
    btn.disabled = true; btn.textContent = 'Submitting…';
    fetch('/ajax/pharmacy-register.php', { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false; btn.innerHTML = original;
            if (data.success) {
                showToast('success', 'Registration submitted', data.message);
                form.reset();
                setTimeout(function () { window.location.href = '/'; }, 1800);
            } else {
                if (data.errors) {
                    Object.keys(data.errors).forEach(function (field) {
                        var group = form.querySelector('[data-field="' + field + '"]');
                        if (group) { group.classList.add('error'); group.querySelector('.form-error').textContent = data.errors[field]; }
                    });
                }
                showToast('error', 'Could not submit', data.message);
            }
        })
        .catch(function () {
            btn.disabled = false; btn.innerHTML = original;
            showToast('error', 'Network error', 'Please try again.');
        });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
