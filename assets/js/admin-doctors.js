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
})(jQuery);
