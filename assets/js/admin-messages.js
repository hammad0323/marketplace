(function ($) {
    'use strict';
    $(document).on('change', '.btn-msg-status', function () {
        var id = $(this).closest('[data-msg-id]').data('msg-id');
        var status = $(this).val();
        $.post('/ajax/admin-message-action.php', { csrf_token: window.APP.csrfToken, id: id, status: status }, null, 'json')
            .done(function (res) {
                if (res.success) showToast('success', 'Updated', res.message);
                else showToast('error', 'Could not update', res.message);
            });
    });
})(jQuery);
