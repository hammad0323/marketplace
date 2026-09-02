(function ($) {
    'use strict';

    $('#privacy-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Saving…');
        $.post('/ajax/doctor-privacy-update.php', $form.serialize(), null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Save Privacy Settings');
            if (res.success) showToast('success', 'Saved', res.message);
            else showToast('error', 'Could not save', res.message);
        }).fail(function () {
            $btn.prop('disabled', false).text('Save Privacy Settings');
            showToast('error', 'Network error', 'Please try again.');
        });
    });

    $('#chat-settings-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Saving…');
        $.post('/ajax/doctor-chat-settings.php', $form.serialize(), null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Save Messaging Settings');
            if (res.success) showToast('success', 'Saved', res.message);
            else showToast('error', 'Could not save', res.message);
        }).fail(function () {
            $btn.prop('disabled', false).text('Save Messaging Settings');
            showToast('error', 'Network error', 'Please try again.');
        });
    });

})(jQuery);
