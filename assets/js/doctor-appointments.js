(function ($) {
    'use strict';

    function doAction(appointmentId, action, note, $row) {
        $.post('/ajax/doctor-appointment-action.php', {
            csrf_token: window.APP.csrfToken, appointment_id: appointmentId, action: action, note: note || ''
        }, null, 'json').done(function (res) {
            if (res.success) {
                showToast('success', 'Updated', res.message);
                setTimeout(function () { window.location.reload(); }, 700);
            } else {
                showToast('error', 'Could not update', res.message);
            }
        }).fail(function () { showToast('error', 'Network error', 'Please try again.'); });
    }

    $(document).on('click', '.btn-approve', function () {
        var id = $(this).closest('[data-appt-id]').data('appt-id');
        doAction(id, 'approve');
    });
    $(document).on('click', '.btn-reject', function () {
        var id = $(this).closest('[data-appt-id]').data('appt-id');
        var reason = prompt('Reason for declining (optional):') || '';
        doAction(id, 'reject', reason);
    });
    $(document).on('click', '.btn-complete', function () {
        var id = $(this).closest('[data-appt-id]').data('appt-id');
        doAction(id, 'complete');
    });
    $(document).on('click', '.btn-noshow', function () {
        var id = $(this).closest('[data-appt-id]').data('appt-id');
        if (!confirm('Mark this patient as a no-show?')) return;
        doAction(id, 'no_show');
    });
})(jQuery);
