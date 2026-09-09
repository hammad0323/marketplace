(function ($) {
    'use strict';
    var $overlay = $('#guest-contact-modal');
    if (!$overlay.length) return;

    var onSuccess = null;

    function open(callback) {
        onSuccess = callback;
        $overlay.find('.form-group').removeClass('error');
        $overlay.find('.form-error').text('');
        $overlay.find('input[name="contact"]').val('');
        $overlay.addClass('open');
        $('body').css('overflow', 'hidden');
        setTimeout(function () { $overlay.find('input[name="contact"]').trigger('focus'); }, 100);
    }
    function close() {
        $overlay.removeClass('open');
        $('body').css('overflow', '');
    }

    $overlay.find('[data-modal-close]').on('click', close);
    $overlay.on('click', function (e) { if (e.target === this) close(); });

    $('#guest-contact-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $group = $form.find('[data-field="contact"]').removeClass('error');
        $group.find('.form-error').text('');
        var contact = $form.find('input[name="contact"]').val().trim();
        if (!contact) {
            $group.addClass('error');
            $group.find('.form-error').text('Please enter your email or phone number.');
            return;
        }

        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Please wait…');
        $.post('/ajax/guest-signup.php', { csrf_token: window.APP.csrfToken, contact: contact }, null, 'json')
            .done(function (res) {
                $btn.prop('disabled', false).text('Continue');
                if (res.success) {
                    window.APP.loggedIn = true;
                    close();
                    if (res.password) {
                        showToast('info', 'Save your password', 'You are Patient — temporary password: ' + res.password);
                    } else {
                        showToast('success', 'Account created', res.message);
                    }
                    if (typeof onSuccess === 'function') onSuccess(res);
                } else {
                    $group.addClass('error');
                    $group.find('.form-error').text(res.message);
                }
            })
            .fail(function () {
                $btn.prop('disabled', false).text('Continue');
                showToast('error', 'Network error', 'Please try again.');
            });
    });

    window.openGuestModal = open;
    window.closeGuestModal = close;

    // Any link/button marked data-guest-message becomes a guest entry point
    // instead of a login wall: capture contact info, create the account,
    // then follow the link's own href now that the visitor has a session.
    $(document).on('click', '[data-guest-message]', function (e) {
        e.preventDefault();
        var href = $(this).attr('href');
        open(function () {
            window.location.href = href;
        });
    });
})(jQuery);
