(function ($) {
    'use strict';
    var $modal = $('#manager-modal');

    function openModal() {
        $modal.addClass('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        $modal.removeClass('open');
        document.body.style.overflow = '';
    }
    $('#add-manager-btn').on('click', openModal);
    $modal.on('click', '[data-modal-close]', closeModal);
    $modal.on('click', function (e) { if (e.target === this) closeModal(); });

    $('#manager-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        $form.find('.form-group.error').removeClass('error');
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Creating…');
        $.post('/ajax/doctor-manager-save.php', $form.serialize(), null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Create Login');
            if (res.success) {
                showToast('success', 'Created', res.message);
                closeModal();
                setTimeout(function () { window.location.reload(); }, 900);
            } else {
                if (res.errors) {
                    Object.keys(res.errors).forEach(function (f) {
                        $form.find('[data-field="' + f + '"]').addClass('error').find('.form-error').text(res.errors[f]);
                    });
                }
                showToast('error', 'Could not create', res.message);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Create Login');
            showToast('error', 'Network error', 'Please try again.');
        });
    });

    $(document).on('click', '.btn-remove-manager', function () {
        if (!confirm('Remove this staff login? They will no longer be able to log in.')) return;
        var managerId = $(this).closest('[data-manager-id]').data('manager-id');
        $.post('/ajax/doctor-manager-delete.php', { csrf_token: window.APP.csrfToken, manager_id: managerId }, null, 'json').done(function (res) {
            if (res.success) {
                showToast('success', 'Removed', res.message);
                setTimeout(function () { window.location.reload(); }, 600);
            } else {
                showToast('error', 'Could not remove', res.message);
            }
        });
    });

    $(document).on('click', '.btn-reset-manager-password', function () {
        var managerId = $(this).closest('[data-manager-id]').data('manager-id');
        var $btn = $(this).prop('disabled', true);
        $.post('/ajax/doctor-manager-reset-password.php', { csrf_token: window.APP.csrfToken, manager_id: managerId }, null, 'json').done(function (res) {
            $btn.prop('disabled', false);
            if (res.success) showToast('success', 'Password reset', res.message);
            else showToast('error', 'Could not reset', res.message);
        });
    });
})(jQuery);
