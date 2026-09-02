(function ($) {
    'use strict';

    $(document).on('click', '.btn-pharm-action', function () {
        var $btn = $(this);
        var action = $btn.data('action');
        var pharmacyId = $btn.closest('[data-pharmacy-id]').data('pharmacy-id');
        var note = '';
        if (action === 'reject') {
            note = prompt('Reason for rejection (sent to the pharmacy internally):') || '';
        }
        if (action === 'suspend' && !confirm('Suspend this pharmacy account? They will not be able to log in.')) return;

        $btn.prop('disabled', true);
        $.post('/ajax/admin-pharmacy-action.php', { csrf_token: window.APP.csrfToken, pharmacy_id: pharmacyId, action: action, note: note }, null, 'json')
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
