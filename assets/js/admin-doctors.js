(function ($) {
    'use strict';

    $(document).on('click', '.btn-doc-action', function () {
        var $btn = $(this);
        var action = $btn.data('action');
        var doctorId = $btn.closest('[data-doctor-id]').data('doctor-id');
        var note = '';
        if (action === 'reject') {
            note = prompt('Reason for rejection (sent to the doctor internally):') || '';
        }
        if (action === 'suspend' && !confirm('Suspend this doctor account? They will not be able to log in.')) return;

        $btn.prop('disabled', true);
        $.post('/ajax/admin-doctor-action.php', { csrf_token: window.APP.csrfToken, doctor_id: doctorId, action: action, note: note }, null, 'json')
            .done(function (res) {
                if (res.success) {
                    showToast('success', 'Updated', res.message);
                    setTimeout(function () { window.location.reload(); }, 700);
                } else {
                    $btn.prop('disabled', false);
                    showToast('error', 'Could not update', res.message);
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                showToast('error', 'Network error', 'Please try again.');
            });
    });

    $(document).on('click', '.btn-doc-seo', function () {
        var $row = $(this).closest('[data-doctor-id]');
        var doctorId = $row.data('doctor-id');
        var title = prompt('Meta title (leave blank to auto-generate):', $row.data('meta-title') || '');
        if (title === null) return;
        var description = prompt('Meta description (leave blank to auto-generate):', $row.data('meta-description') || '');
        if (description === null) return;

        $.post('/ajax/admin-doctor-seo-save.php', {
            csrf_token: window.APP.csrfToken, doctor_id: doctorId, meta_title: title, meta_description: description
        }, null, 'json').done(function (res) {
            if (res.success) {
                $row.attr('data-meta-title', title).attr('data-meta-description', description);
                showToast('success', 'Saved', res.message);
            } else {
                showToast('error', 'Could not save', res.message);
            }
        }).fail(function () { showToast('error', 'Network error', 'Please try again.'); });
    });
})(jQuery);
