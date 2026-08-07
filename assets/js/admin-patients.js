(function ($) {
    'use strict';
    $(document).on('click', '.btn-user-action', function () {
        var $btn = $(this);
        var action = $btn.data('action');
        var userId = $btn.closest('[data-user-id]').data('user-id');
        if (action === 'suspend' && !confirm('Suspend this patient account?')) return;
        $btn.prop('disabled', true);
        $.post('/ajax/admin-user-action.php', { csrf_token: window.APP.csrfToken, user_id: userId, action: action }, null, 'json')
            .done(function (res) {
                if (res.success) { showToast('success', 'Updated', res.message); setTimeout(function () { window.location.reload(); }, 700); }
                else { $btn.prop('disabled', false); showToast('error', 'Could not update', res.message); }
            }).fail(function () { $btn.prop('disabled', false); showToast('error', 'Network error', 'Please try again.'); });
    });
})(jQuery);
