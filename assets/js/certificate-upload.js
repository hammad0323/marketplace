(function ($) {
    'use strict';

    var CERT_URL = window.CERT_UPLOAD_URL || '/ajax/doctor-certificate.php';

    $('#cert-file').on('change', function () {
        var label = $('label[for="cert-file"]');
        var files = this.files;
        if (!files.length) label.text('Choose File(s)');
        else if (files.length === 1) label.text(files[0].name);
        else label.text(files.length + ' files selected');
    });

    function uploadOneCertificate($form, file, title) {
        var formData = new FormData();
        formData.append('csrf_token', $form.find('[name="csrf_token"]').val());
        formData.append('title', title);
        formData.append('issued_by', $form.find('[name="issued_by"]').val());
        formData.append('issued_year', $form.find('[name="issued_year"]').val());
        formData.append('file', file);
        return $.ajax({ url: CERT_URL, type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json' });
    }

    $('#cert-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        var files = $form.find('#cert-file')[0].files;
        if (!files.length) return;
        var baseTitle = $.trim($form.find('[name="title"]').val());
        var $btn = $form.find('button[type="submit"]').prop('disabled', true);
        var total = files.length;
        var done = 0;
        var failed = [];

        function fileTitle(file, index) {
            if (baseTitle === '') return file.name.replace(/\.[^.]+$/, '');
            return total > 1 ? baseTitle + ' (' + (index + 1) + ')' : baseTitle;
        }

        function next(i) {
            if (i >= total) {
                $btn.prop('disabled', false).text('Upload');
                if (failed.length) {
                    showToast('error', 'Some uploads failed', failed.join(' '));
                } else {
                    showToast('success', 'Uploaded', done + ' certificate' + (done === 1 ? '' : 's') + ' uploaded.');
                }
                if (done > 0) setTimeout(function () { window.location.reload(); }, 700);
                return;
            }
            $btn.text('Uploading ' + (i + 1) + '/' + total + '…');
            uploadOneCertificate($form, files[i], fileTitle(files[i], i))
                .done(function (res) {
                    if (res.success) done++;
                    else failed.push(files[i].name + ': ' + res.message);
                })
                .fail(function () {
                    failed.push(files[i].name + ': network error');
                })
                .always(function () { next(i + 1); });
        }

        next(0);
    });

    $(document).on('click', '.btn-remove-cert', function () {
        if (!confirm('Remove this certificate?')) return;
        var $card = $(this).closest('[data-cert-id]');
        $.post(CERT_URL, { csrf_token: window.APP.csrfToken, op: 'remove', id: $card.data('cert-id') }, null, 'json')
            .done(function (res) {
                if (res.success) $card.fadeOut(200, function () { $(this).remove(); });
            });
    });
})(jQuery);
