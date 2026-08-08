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

    $('#email-settings-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Saving…');
        $.post('/ajax/admin-email-settings-save.php', $form.serialize(), null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Save Email Settings');
            if (res.success) showToast('success', 'Saved', res.message);
            else showToast('error', 'Could not save', res.message);
        }).fail(function () { $btn.prop('disabled', false).text('Save Email Settings'); showToast('error', 'Network error', 'Please try again.'); });
    });

    $('#send-test-email-btn').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Sending…');
        $.post('/ajax/admin-send-test-email.php', { csrf_token: window.APP.csrfToken }, null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Send Test Email');
            if (res.success) showToast('success', 'Sent', res.message);
            else showToast('error', 'Could not send', res.message);
        }).fail(function () { $btn.prop('disabled', false).text('Send Test Email'); showToast('error', 'Network error', 'Please try again.'); });
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

    $('#generate-sitemap-btn').on('click', function () {
        var $btn = $(this).prop('disabled', true).text('Generating…');
        var csrf = $('#sitemap-csrf').val();
        $.post('/ajax/admin-generate-sitemap.php', { csrf_token: csrf }, null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Generate Sitemap');
            if (res.success) {
                showToast('success', 'Sitemap generated', res.message);
                $('#sitemap-status').html(
                    'Static file last generated <strong>' + res.generated_at + '</strong> — ' + res.url_count + ' URLs. ' +
                    '<a href="' + res.sitemap_url + '" target="_blank" style="color:var(--color-primary);font-weight:600;">View sitemap.xml</a>'
                );
            } else {
                showToast('error', 'Could not generate sitemap', res.message);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Generate Sitemap');
            showToast('error', 'Network error', 'Please try again.');
        });
    });
})(jQuery);
