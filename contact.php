<?php
require __DIR__ . '/config/config.php';

$pageTitle = 'Contact Us — ' . SITE_NAME;
$metaDescription = 'Get in touch with the ' . SITE_NAME . ' team for support, partnerships, or general questions.';
require __DIR__ . '/includes/header.php';
?>
<section class="section" style="padding-top:calc(var(--header-height) + 48px);">
    <div class="container">
        <nav class="breadcrumb"><a href="/">Home</a> <i class="ri-arrow-right-s-line"></i> <span>Contact</span></nav>
        <div class="section-head" style="text-align:left;margin-left:0;max-width:600px;" data-reveal>
            <span class="eyebrow">Get in Touch</span>
            <h2>We'd love to hear from you</h2>
            <p>Questions about booking, billing, or partnering with us? Send a message and our team will respond within one business day.</p>
        </div>

        <div class="split-contact">
            <div class="stagger">
                <div class="card" style="padding:24px;display:flex;gap:14px;align-items:center;margin-bottom:16px;" data-reveal>
                    <div class="icon-badge" style="width:46px;height:46px;border-radius:12px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;"><i class="ri-mail-line"></i></div>
                    <div><strong>Email</strong><br><span style="color:var(--color-text-muted);"><?= e(get_setting('contact_email')) ?></span></div>
                </div>
                <div class="card" style="padding:24px;display:flex;gap:14px;align-items:center;margin-bottom:16px;" data-reveal>
                    <div class="icon-badge" style="width:46px;height:46px;border-radius:12px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;"><i class="ri-phone-line"></i></div>
                    <div><strong>Phone</strong><br><span style="color:var(--color-text-muted);"><?= e(get_setting('contact_phone')) ?></span></div>
                </div>
                <div class="card" style="padding:24px;display:flex;gap:14px;align-items:center;" data-reveal>
                    <div class="icon-badge" style="width:46px;height:46px;border-radius:12px;background:var(--gradient-primary);display:flex;align-items:center;justify-content:center;color:#fff;font-size:20px;"><i class="ri-map-pin-line"></i></div>
                    <div><strong>Address</strong><br><span style="color:var(--color-text-muted);"><?= e(get_setting('contact_address')) ?></span></div>
                </div>
            </div>

            <div class="card" style="padding:32px;" data-reveal="right">
                <form id="contact-form" novalidate>
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <div class="grid grid-2">
                        <div class="form-group" data-field="name">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="name" required>
                            <div class="form-error"></div>
                        </div>
                        <div class="form-group" data-field="email">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" required>
                            <div class="form-error"></div>
                        </div>
                    </div>
                    <div class="form-group" data-field="phone">
                        <label class="form-label">Phone (optional)</label>
                        <input type="tel" class="form-control" name="phone">
                    </div>
                    <div class="form-group" data-field="subject">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" name="subject" required>
                        <div class="form-error"></div>
                    </div>
                    <div class="form-group" data-field="message">
                        <label class="form-label">Message</label>
                        <textarea class="form-control" name="message" rows="5" required></textarea>
                        <div class="form-error"></div>
                    </div>
                    <button type="submit" class="btn btn-primary">Send Message <i class="ri-send-plane-line"></i></button>
                </form>
            </div>
        </div>
    </div>
</section>
<script>
document.getElementById('contact-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var form = e.target;
    form.querySelectorAll('.form-group.error').forEach(function (g) { g.classList.remove('error'); });
    var btn = form.querySelector('button[type="submit"]');
    var original = btn.innerHTML;
    btn.disabled = true; btn.textContent = 'Sending…';
    fetch('/ajax/contact-submit.php', { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            btn.disabled = false; btn.innerHTML = original;
            if (data.success) {
                showToast('success', 'Message sent', data.message);
                form.reset();
            } else {
                if (data.errors) {
                    Object.keys(data.errors).forEach(function (field) {
                        var group = form.querySelector('[data-field="' + field + '"]');
                        if (group) { group.classList.add('error'); group.querySelector('.form-error').textContent = data.errors[field]; }
                    });
                }
                showToast('error', 'Could not send', data.message);
            }
        })
        .catch(function () {
            btn.disabled = false; btn.innerHTML = original;
            showToast('error', 'Network error', 'Please try again.');
        });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
