<div class="modal-overlay" id="guest-contact-modal" role="dialog" aria-modal="true" aria-labelledby="guest-contact-modal-title">
    <div class="modal-box" style="grid-template-columns:1fr;max-width:440px;">
        <button class="modal-close" data-modal-close aria-label="Close"><i class="ri-close-line"></i></button>
        <div class="modal-form-side">
            <h3 id="guest-contact-modal-title" style="font-size:20px;margin-bottom:8px;">Continue without an account</h3>
            <p style="color:var(--color-text-muted);font-size:14px;margin-bottom:24px;">Enter your email or phone number and we'll set up an account for you automatically — no password to create right now.</p>
            <form id="guest-contact-form" novalidate>
                <div class="form-group" data-field="contact">
                    <label class="form-label">Email or Phone Number</label>
                    <input type="text" class="form-control" name="contact" placeholder="you@example.com or +1 555 000 0000" required autofocus>
                    <div class="form-error"></div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Continue</button>
                <p style="text-align:center;font-size:13.5px;margin-top:18px;color:var(--color-text-muted);">
                    Already have an account? <a href="#" style="color:var(--color-primary);font-weight:600;" onclick="event.preventDefault();closeGuestModal();openAuthModal('login')">Log in</a>
                </p>
            </form>
        </div>
    </div>
</div>
