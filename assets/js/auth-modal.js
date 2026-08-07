(function () {
    'use strict';

    var overlay = document.getElementById('auth-modal');
    if (!overlay) return;
    var pendingRedirect = null;

    function openModal(tab) {
        setTab(tab || 'login');
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
    }
    function setTab(tab) {
        overlay.querySelectorAll('.auth-tab').forEach(function (t) {
            t.classList.toggle('active', t.getAttribute('data-tab') === tab);
        });
        overlay.querySelectorAll('.auth-panel').forEach(function (p) {
            p.classList.toggle('active', p.getAttribute('data-panel') === tab);
        });
    }

    overlay.querySelectorAll('.auth-tab').forEach(function (t) {
        t.addEventListener('click', function () { setTab(t.getAttribute('data-tab')); });
    });
    overlay.querySelectorAll('[data-modal-close]').forEach(function (btn) {
        btn.addEventListener('click', closeModal);
    });
    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closeModal();
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeModal();
    });

    // Intercept any restricted action a guest clicks anywhere on the site.
    document.addEventListener('click', function (e) {
        var el = e.target.closest('[data-requires-auth]');
        if (!el || window.APP.loggedIn) return;
        e.preventDefault();
        pendingRedirect = el.getAttribute('href') || el.getAttribute('data-action-url') || window.location.href;
        openModal('login');
    });

    window.openAuthModal = openModal;

    function setFieldError(form, field, message) {
        var group = form.querySelector('[data-field="' + field + '"]');
        if (!group) return;
        group.classList.add('error');
        var err = group.querySelector('.form-error');
        if (err) err.textContent = message;
    }
    function clearErrors(form) {
        form.querySelectorAll('.form-group.error').forEach(function (g) { g.classList.remove('error'); });
    }

    function handleAuthForm(formId, url) {
        var form = document.getElementById(formId);
        if (!form) return;
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            clearErrors(form);
            var submitBtn = form.querySelector('button[type="submit"]');
            var originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="loader-ring" style="width:18px;height:18px;border-width:2px;"></span>';

            fetch(url, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    if (data.success) {
                        showToast('success', 'Success', data.message || 'Welcome!');
                        window.APP.loggedIn = true;
                        setTimeout(function () {
                            window.location.href = pendingRedirect || data.redirect || window.location.href;
                        }, 600);
                    } else {
                        if (data.errors) {
                            Object.keys(data.errors).forEach(function (field) {
                                setFieldError(form, field, data.errors[field]);
                            });
                        }
                        showToast('error', 'Error', data.message || 'Please check the form and try again.');
                    }
                })
                .catch(function () {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                    showToast('error', 'Network error', 'Please check your connection and try again.');
                });
        });
    }

    handleAuthForm('login-form', '/ajax/login.php');
    handleAuthForm('register-form', '/ajax/register.php');
    handleAuthForm('login-form-modal', '/ajax/login.php');
    handleAuthForm('register-form-modal', '/ajax/register.php');
})();
