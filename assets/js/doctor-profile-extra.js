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

    $('#cert-file').on('change', function () {
        var label = $('label[for="cert-file"]');
        label.text(this.files[0] ? this.files[0].name : 'Choose File');
    });

    $('#cert-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var formData = new FormData($form[0]);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Uploading…');
        $.ajax({ url: '/ajax/doctor-certificate.php', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json' })
            .done(function (res) {
                $btn.prop('disabled', false).text('Upload');
                if (res.success) {
                    showToast('success', 'Uploaded', res.message);
                    setTimeout(function () { window.location.reload(); }, 700);
                } else {
                    showToast('error', 'Could not upload', res.message);
                }
            }).fail(function () {
                $btn.prop('disabled', false).text('Upload');
                showToast('error', 'Network error', 'Please try again.');
            });
    });

    $(document).on('click', '.btn-remove-cert', function () {
        if (!confirm('Remove this certificate?')) return;
        var $card = $(this).closest('[data-cert-id]');
        $.post('/ajax/doctor-certificate.php', { csrf_token: window.APP.csrfToken, op: 'remove', id: $card.data('cert-id') }, null, 'json')
            .done(function (res) {
                if (res.success) $card.fadeOut(200, function () { $(this).remove(); });
            });
    });
})(jQuery);
