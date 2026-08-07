(function ($) {
    'use strict';
    var $modal = $('#spec-modal');

    function openModal() {
        $modal.addClass('open');
        document.body.style.overflow = 'hidden';
    }
    function closeModal() {
        $modal.removeClass('open');
        document.body.style.overflow = '';
    }
    $modal.on('click', '[data-modal-close]', closeModal);
    $modal.on('click', function (e) { if (e.target === this) closeModal(); });

    $('#add-spec-btn').on('click', function () {
        $('#spec-modal-title').text('Add Specialization');
        $('#spec-id').val('0');
        $('#spec-name, #spec-icon, #spec-description').val('');
        openModal();
    });

    $(document).on('click', '.btn-edit-spec', function () {
        var $row = $(this).closest('[data-spec-id]');
        $('#spec-modal-title').text('Edit Specialization');
        $('#spec-id').val($row.data('spec-id'));
        $('#spec-name').val($row.data('name'));
        $('#spec-icon').val($row.data('icon'));
        $('#spec-description').val($row.data('description'));
        openModal();
    });

    $('#spec-form').on('submit', function (e) {
        e.preventDefault();
        var $form = $(this);
        $form.find('.form-group.error').removeClass('error');
        var $btn = $form.find('button[type="submit"]').prop('disabled', true).text('Saving…');
        $.post('/ajax/admin-specialization.php', $form.serialize() + '&op=save', null, 'json').done(function (res) {
            $btn.prop('disabled', false).text('Save');
            if (res.success) {
                showToast('success', 'Saved', res.message);
                setTimeout(function () { window.location.reload(); }, 600);
            } else {
                if (res.errors) {
                    Object.keys(res.errors).forEach(function (f) {
                        var $g = $form.find('[data-field="' + f + '"]');
                        $g.addClass('error');
                        $g.find('.form-error').text(res.errors[f]);
                    });
                }
                showToast('error', 'Could not save', res.message);
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Save');
            showToast('error', 'Network error', 'Please try again.');
        });
    });

    $(document).on('click', '.btn-toggle-spec', function () {
        var id = $(this).closest('[data-spec-id]').data('spec-id');
        $.post('/ajax/admin-specialization.php', { csrf_token: window.APP.csrfToken, op: 'toggle', id: id }, null, 'json').done(function (res) {
            if (res.success) window.location.reload();
        });
    });

    $(document).on('click', '.btn-delete-spec', function () {
        if (!confirm('Delete this specialization? This cannot be undone.')) return;
        var id = $(this).closest('[data-spec-id]').data('spec-id');
        $.post('/ajax/admin-specialization.php', { csrf_token: window.APP.csrfToken, op: 'delete', id: id }, null, 'json').done(function (res) {
            if (res.success) window.location.reload();
            else showToast('error', 'Could not delete', res.message);
        });
    });
})(jQuery);
