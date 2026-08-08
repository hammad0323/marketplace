<?php
require __DIR__ . '/config/config.php';

if (is_logged_in()) {
    $role = current_role();
    redirect($role === 'doctor' ? '/doctor/dashboard' : ($role === 'admin' ? '/admin/dashboard' : '/patient/dashboard'));
}

$specs = mysqli_query(db(), 'SELECT id, name FROM specializations WHERE is_active = 1 ORDER BY name');

$pageTitle = 'Apply as a Doctor — ' . SITE_NAME;
$metaDescription = 'Join ' . SITE_NAME . ' as a verified doctor. Submit your credentials for review and start accepting patients online.';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container" style="max-width:760px;">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <span>Apply as a Doctor</span></nav>
        <div class="section-head" style="text-align:left;margin-left:0;" data-reveal>
            <span class="eyebrow">Join Our Network</span>
            <h1>Apply as a Doctor</h1>
            <p>Submit your credentials below. Our medical credentialing team reviews every application before you can accept patients.</p>
        </div>

        <div class="card" style="padding:36px;" data-reveal>
            <form id="doctor-register-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <div class="grid grid-2">
                    <div class="form-group" data-field="full_name">
                        <label class="form-label">Full Name</label>
                        <input type="text" class="form-control" name="full_name" placeholder="Dr. Jane Smith" required>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="email">
                        <label class="form-label">Email Address</label>
                        <input type="email" class="form-control" name="email" required>
                        <div class="form-error"></div>
                    </div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group" data-field="phone">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone">
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="password">
                        <label class="form-label">Password</label>
                        <div class="input-icon-wrap">
                            <i class="ri-lock-line"></i>
                            <input type="password" class="form-control" name="password" id="doc-reg-pass" placeholder="Min. 8 characters" required data-pw-strength="#pw-bar-doc">
                            <button type="button" class="toggle-pass" data-toggle-pass="doc-reg-pass"><i class="ri-eye-line"></i></button>
                        </div>
                        <div class="pw-strength"><div class="pw-strength-bar" id="pw-bar-doc"></div></div>
                        <div class="form-error"></div>
                    </div>
                </div>
                <div class="form-group" data-field="specialization_ids">
                    <label class="form-label">Specializations <span style="font-weight:400;color:var(--color-text-muted);">(select one or more)</span></label>
                    <div class="grid grid-3" style="gap:8px;">
                        <?php while ($s = mysqli_fetch_assoc($specs)): ?>
                        <label class="checkbox-row" style="border:1.5px solid var(--color-border);border-radius:var(--radius-sm);padding:10px 12px;">
                            <input type="checkbox" name="specialization_ids[]" value="<?= (int)$s['id'] ?>"> <?= e($s['name']) ?>
                        </label>
                        <?php endwhile; ?>
                    </div>
                    <div class="form-error"></div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group" data-field="experience_years">
                        <label class="form-label">Years of Experience</label>
                        <input type="number" min="0" max="70" class="form-control" name="experience_years" required>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="qualification">
                        <label class="form-label">Qualification</label>
                        <input type="text" class="form-control" name="qualification" placeholder="e.g. MD, FACC" required>
                        <div class="form-error"></div>
                    </div>
                </div>
                <div class="form-group" data-field="registration_number">
                    <label class="form-label">Medical License / Registration No.</label>
                    <input type="text" class="form-control" name="registration_number" required>
                    <div class="form-error"></div>
                </div>
                <div class="form-group" data-field="bio">
                    <label class="form-label">Short Bio</label>
                    <textarea class="form-control" name="bio" rows="4" placeholder="Tell patients about your practice and approach to care."></textarea>
                    <div class="form-error"></div>
                </div>
                <label class="checkbox-row" style="margin-bottom:20px;">
                    <input type="checkbox" required> I confirm the information provided is accurate and I agree to the <a href="/terms" target="_blank" style="color:var(--color-primary);">Terms</a>.
                </label>
                <button type="submit" class="btn btn-primary btn-block">Submit Application</button>
            </form>
        </div>
    </div>
</section>
<script>
document.getElementById('doctor-register-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = e.target;
    form.querySelectorAll('.form-group.error').forEach(function (g) { g.classList.remove('error'); });
    var btn = form.querySelector('button[type="submit"]');
    var original = btn.innerHTML;
    btn.disabled = true; btn.textContent = 'Submitting…';
    fetch('/ajax/doctor-register.php', { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false; btn.innerHTML = original;
            if (data.success) {
                showToast('success', 'Application submitted', data.message);
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
