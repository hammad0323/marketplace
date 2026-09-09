(function ($) {
    'use strict';
    var $form = $('#history-form');
    if (!$form.length) return;

    function setFieldError(field, message) {
        var $group = $form.find('[data-field="' + field + '"]');
        $group.addClass('error');
        $group.find('.form-error').text(message);
    }
    function clearErrors() {
        $form.find('.form-group.error').removeClass('error');
    }

    $form.on('submit', function (e) {
        e.preventDefault();
        clearErrors();
        var $btn = $form.find('button[type="submit"]').prop('disabled', true);

        $.post('/ajax/doctor-patient-history-save.php', $form.serialize(), null, 'json')
            .done(function (res) {
                $btn.prop('disabled', false);
                if (res.success) {
                    showToast('success', 'Added', res.message);
                    setTimeout(function () { window.location.reload(); }, 500);
                } else {
                    if (res.errors) {
                        Object.keys(res.errors).forEach(function (f) { setFieldError(f, res.errors[f]); });
                    } else {
                        setFieldError('title', res.message);
                    }
                    showToast('error', 'Could not add entry', res.message);
                }
            })
            .fail(function () {
                $btn.prop('disabled', false);
                showToast('error', 'Network error', 'Please try again.');
            });
    });

    $(document).on('click', '.btn-delete-history', function () {
        var $card = $(this).closest('[data-history-id]');
        var id = $card.data('history-id');
        if (!confirm('Delete this history entry? This cannot be undone.')) return;
        $.post('/ajax/doctor-patient-history-delete.php', { csrf_token: window.APP.csrfToken, id: id }, null, 'json')
            .done(function (res) {
                if (res.success) {
                    $card.fadeOut(200, function () { $(this).remove(); });
                } else {
                    showToast('error', 'Could not delete', res.message);
                }
            })
            .fail(function () { showToast('error', 'Network error', 'Please try again.'); });
    });
})(jQuery);
