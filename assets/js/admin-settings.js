(function ($) {
    'use strict';

    $('#settings-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Saving…');
        $.post('/ajax/admin-settings-save.php', $form.serialize(), null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Save Settings');
            if (res.success) showToast('success', 'Saved', res.message);
            else showToast('error', 'Could not save', res.message);
        }).fail(function () { $btn.prop('disabled', false).text('Save Settings'); showToast('error', 'Network error', 'Please try again.'); });
    });

    $('.cms-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Saving…');
        $.post('/ajax/admin-cms-save.php', $form.serialize(), null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Save Page');
            if (res.success) showToast('success', 'Saved', res.message);
            else showToast('error', 'Could not save', res.message);
        }).fail(function () { $btn.prop('disabled', false).text('Save Page'); showToast('error', 'Network error', 'Please try again.'); });
    });

    $('#faq-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        $form.find('.form-group.error').removeClass('error');
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Adding…');
        $.post('/ajax/admin-faq.php', $form.serialize() + '&op=add', null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Add FAQ');
            if (res.success) {
                showToast('success', 'Added', res.message);
                setTimeout(function () { window.location.reload(); }, 600);
            } else {
                showToast('error', 'Could not add', res.message);
            }
        }).fail(function () { $btn.prop('disabled', false).text('Add FAQ'); showToast('error', 'Network error', 'Please try again.'); });
    });

    $(document).on('click', '.btn-delete-faq', function () {
        if (!confirm('Delete this FAQ?')) return;
        var $card = $(this).closest('[data-faq-id]');
        $.post('/ajax/admin-faq.php', { csrf_token: window.APP.csrfToken, op: 'delete', id: $card.data('faq-id') }, null, 'json').done(function (res) {
            if (res.success) $card.fadeOut(200, function () { $(this).remove(); });
        });
    });
})(jQuery);
