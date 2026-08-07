(function ($) {
    'use strict';

    function bindForm(formId, endpointFallback, onSuccess) {
        var $form = $('#' + formId);
        if (!$form.length) return;
        $form.on('submit', function (e) {
            e.preventDefault();
            $form.find('.form-group.error').removeClass('error');
            var endpoint = $form.data('endpoint') || endpointFallback;
            var $btn = $form.find('button[type="submit"]');
            var original = $btn.html();
            $btn.prop('disabled', true).text('Saving…');
            $.post(endpoint, $form.serialize(), null, 'json')
                .done(function (res) {
                    $btn.prop('disabled', false).html(original);
                    if (res.success) {
                        showToast('success', 'Saved', res.message);
                        $form[0].reset ? null : null;
                        if (formId === 'password-form') $form[0].reset();
                        if (onSuccess) onSuccess(res);
                    } else {
                        if (res.errors) {
                            Object.keys(res.errors).forEach(function (field) {
                                var $group = $form.find('[data-field="' + field + '"]');
                                $group.addClass('error');
                                $group.find('.form-error').text(res.errors[field]);
                            });
                        }
                        showToast('error', 'Could not save', res.message);
                    }
                })
                .fail(function () {
                    $btn.prop('disabled', false).html(original);
                    showToast('error', 'Network error', 'Please try again.');
                });
        });
    }

    bindForm('profile-form', '/ajax/patient-profile-update.php');
    bindForm('password-form', '/ajax/change-password.php');

    $('#avatar-input').on('change', function () {
        var file = this.files[0];
        if (!file) return;
        var formData = new FormData();
        formData.append('avatar', file);
        formData.append('csrf_token', window.APP.csrfToken);
        $.ajax({
            url: '/ajax/update-avatar.php', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json'
        }).done(function (res) {
            if (res.success) {
                $('#avatar-preview').attr('src', res.avatar_url);
                showToast('success', 'Updated', res.message);
            } else {
                showToast('error', 'Upload failed', res.message);
            }
        }).fail(function () {
            showToast('error', 'Network error', 'Please try again.');
        });
    });
})(jQuery);
